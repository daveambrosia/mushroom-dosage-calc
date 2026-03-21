<?php
/**
 * Google Sheets Fetcher & URL Parser
 *
 * Fetches Google Sheets data via CSV export (no API key needed).
 * Sheets must be shared as "Anyone with the link can view".
 *
 * @since 2.12.0
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_Google_Sheets class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_Google_Sheets {

	/**
	 * Extract spreadsheet ID from various Google Sheets URL formats.
	 *
	 * Supports:
	 *   https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/edit
	 *   https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/edit#gid=123
	 *   https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/
	 *   https://docs.google.com/spreadsheets/d/SPREADSHEET_ID
	 *   Just the raw spreadsheet ID
	 *
	 * @param string $url_or_id Google Sheets URL or spreadsheet ID.
	 * @return array|WP_Error { spreadsheet_id, gid }
	 */
	public static function parse_url( $url_or_id ) {
		$url_or_id = trim( $url_or_id );

		if ( empty( $url_or_id ) ) {
			return new WP_Error( 'empty_url', 'Google Sheet URL is required.' );
		}

		// If it looks like a raw ID (no slashes, alphanumeric + dashes/underscores)
		if ( preg_match( '/^[a-zA-Z0-9_-]{20,}$/', $url_or_id ) ) {
			return array(
				'spreadsheet_id' => $url_or_id,
				'gid'            => '0',
			);
		}

		// Extract spreadsheet ID from URL
		if ( preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url_or_id, $matches ) ) {
			$spreadsheet_id = $matches[1];

			// Try to extract gid from URL fragment or query param
			$gid = '0';
			if ( preg_match( '/[?&#]gid=(\d+)/', $url_or_id, $gid_matches ) ) {
				$gid = $gid_matches[1];
			}

			return array(
				'spreadsheet_id' => $spreadsheet_id,
				'gid'            => $gid,
			);
		}

		return new WP_Error( 'invalid_url', 'Could not parse Google Sheets URL. Please use a full sharing URL or spreadsheet ID.' );
	}

	/**
	 * Build the CSV export URL for a Google Sheet.
	 *
	 * @param string $spreadsheet_id Spreadsheet ID.
	 * @param string $gid            Sheet tab GID (default '0').
	 * @return string CSV export URL.
	 */
	public static function build_export_url( $spreadsheet_id, $gid = '0' ) {
		return sprintf(
			'https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%s',
			urlencode( $spreadsheet_id ),
			urlencode( $gid )
		);
	}

	/**
	 * Fetch CSV data from a Google Sheet.
	 *
	 * @param string $url_or_id Google Sheets URL or spreadsheet ID.
	 * @param string $gid       Optional GID override.
	 * @return array|WP_Error Array of associative arrays (rows keyed by header), or WP_Error.
	 */
	public static function fetch( $url_or_id, $gid = null ) {
		$parsed = self::parse_url( $url_or_id );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		if ( null !== $gid ) {
			$parsed['gid'] = $gid;
		}

		$export_url = self::build_export_url( $parsed['spreadsheet_id'], $parsed['gid'] );

		$response = wp_remote_get(
			$export_url,
			array(
				'timeout'     => 30,
				'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . esc_url_raw( home_url() ),
				'sslverify'   => true,
				'redirection' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fetch_failed', 'Failed to fetch Google Sheet: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			if ( 404 === $code ) {
				return new WP_Error( 'not_found', 'Google Sheet not found. Check the URL and make sure the sheet is shared publicly.' );
			}
			return new WP_Error( 'http_error', sprintf( 'Google Sheets returned HTTP %d. Make sure the sheet is shared as "Anyone with the link can view".', $code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error( 'empty_response', 'Google Sheet returned empty data.' );
		}

		// Check if we got HTML instead of CSV (common for private sheets)
		if ( stripos( $body, '<!DOCTYPE html' ) !== false || stripos( $body, '<html' ) !== false ) {
			return new WP_Error( 'not_csv', 'Got HTML instead of CSV. The sheet is likely not shared publicly. Set sharing to "Anyone with the link can view".' );
		}

		return self::parse_csv( $body );
	}

	/**
	 * Parse CSV string into array of associative arrays.
	 *
	 * @param string $csv_string Raw CSV data.
	 * @return array|WP_Error Array of rows (each keyed by header), or WP_Error.
	 */
	public static function parse_csv( $csv_string ) {
		$rows = array();

		// Use a memory stream + fgetcsv for proper RFC 4180 CSV parsing
		// (handles commas, quotes, and newlines inside quoted fields)
		$stream = fopen( 'php://temp', 'r+' );
		fwrite( $stream, $csv_string );
		rewind( $stream );

		// Parse header row
		$headers = fgetcsv( $stream );
		if ( false === $headers || count( $headers ) < 1 ) {
			fclose( $stream );
			return new WP_Error( 'too_few_rows', 'Sheet must have at least a header row and one data row.' );
		}
		$headers = array_map( 'trim', $headers );
		$headers = array_map( 'strtolower', $headers );

		// Parse data rows
		while ( ( $values = fgetcsv( $stream ) ) !== false ) {
			// Skip empty lines
			if ( count( $values ) === 1 && trim( $values[0] ) === '' ) {
				continue;
			}

			$row = array();
			foreach ( $headers as $idx => $header ) {
				$row[ $header ] = isset( $values[ $idx ] ) ? trim( $values[ $idx ] ) : '';
			}

			// Skip completely empty rows
			$non_empty = array_filter(
				$row,
				function ( $v ) {
					return '' !== $v;
				}
			);
			if ( ! empty( $non_empty ) ) {
				$rows[] = $row;
			}
		}

		fclose( $stream );

		if ( empty( $rows ) ) {
			return new WP_Error( 'no_data', 'Sheet has headers but no data rows.' );
		}

		return array(
			'headers' => $headers,
			'rows'    => $rows,
			'count'   => count( $rows ),
		);
	}

	/**
	 * Fetch and return a preview (first N rows).
	 *
	 * @param string $url_or_id Google Sheets URL or spreadsheet ID.
	 * @param int    $limit     Number of rows to preview.
	 * @param string $gid       Optional GID override.
	 * @return array|WP_Error { headers, rows, total_count }
	 */
	public static function preview( $url_or_id, $limit = 5, $gid = null ) {
		$result = self::fetch( $url_or_id, $gid );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'headers'     => $result['headers'],
			'rows'        => array_slice( $result['rows'], 0, $limit ),
			'total_count' => $result['count'],
		);
	}

	/**
	 * Check if a rate limit should block this sync.
	 * Prevents syncing more than once per 5 minutes.
	 *
	 * @return bool|int False if not limited, or seconds remaining if limited.
	 */
	public static function is_rate_limited() {
		$last_sync = get_option( 'adc_gsheets_last_sync_time', 0 );
		$elapsed   = time() - $last_sync;
		$cooldown  = 300;
		if ( $elapsed < $cooldown ) {
			return $cooldown - $elapsed;
		}
		return false;
	}

	/**
	 * Record that a sync just happened.
	 */
	public static function record_sync_time() {
		update_option( 'adc_gsheets_last_sync_time', time() );
	}
}
