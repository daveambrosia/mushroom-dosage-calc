<?php
/**
 * QR Code URL Handler - Version 2.0
 *
 * Handles short URL redirects and legacy URL parsing
 *
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_QR_Handler class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_QR_Handler {

	/**
	 * Handle redirect for short URLs
	 */
	public static function handle_redirect() {
		// Handle legacy ?data= URLs on the short URL path (e.g. /c/?data=name:X,psilocybin:Y)
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only QR redirect.
		if ( get_query_var( 'adc_legacy_data' ) && isset( $_GET['data'] ) ) {
			$redirect_url = self::get_calculator_url(
				array(
					'data' => sanitize_text_field( wp_unslash( $_GET['data'] ) ),
				)
			);
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( $redirect_url, 302 );
			exit;
		}

		$code = get_query_var( 'adc_code' );

		if ( empty( $code ) ) {
			return;
		}

		// Look up the code in strains first
		$strain = ADC_Strains::get_by_code( $code );
		if ( $strain ) {
			$redirect_url = self::get_calculator_url(
				array(
					't'    => 'm',
					'code' => $code,
				)
			);
			wp_safe_redirect( $redirect_url, 302 );
			exit;
		}

		// Then try edibles
		$edible = ADC_Edibles::get_by_code( $code );
		if ( $edible ) {
			$redirect_url = self::get_calculator_url(
				array(
					't'    => 'e',
					'code' => $code,
				)
			);
			wp_safe_redirect( $redirect_url, 302 );
			exit;
		}

		// Unknown code - check if auto-submit is enabled
		if ( ADC_DB::get_setting( 'auto_submit_unknown_qr', true ) ) {
			// Create a submission for unknown code
			ADC_Submissions::create(
				array(
					'type'       => 'strain', // BUG-007 fix: Use 'type' not 't'
					'source'     => 'qr_scan',
					'data'       => array(
						'short_code' => $code,
						'name'       => 'Unknown - ' . $code,
						'note'       => 'Auto-submitted from QR scan',
					),
					'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- user agent sanitized via sanitize_text_field() after substr().
					'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( substr( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ), 0, 500 ) ) : '',
				)
			);
		}

		// Redirect to calculator with error
		$redirect_url = self::get_calculator_url(
			array(
				'error' => 'not_found',
				'code'  => $code,
			)
		);
		wp_safe_redirect( $redirect_url, 302 );
		exit;
	}

	/**
	 * Parse legacy URL format.
	 *
	 * Supports both:
	 * - ?data=name:X,psilocybin:Y,psilocin:Z,...
	 * - ?strain=X&psilocybin=Y&psilocin=Z&...
	 *
	 * @param string|array $url_or_params URL string or array of query parameters.
	 * @return array Parsed compound data.
	 */
	public static function parse_legacy_url( $url_or_params ) {
		$params = array();

		if ( is_string( $url_or_params ) ) {
			parse_str( wp_parse_url( $url_or_params, PHP_URL_QUERY ), $params );
		} else {
			$params = $url_or_params;
		}

		$result = array(
			't'             => 'm',
			'name'          => '',
			'psilocybin'    => 0,
			'psilocin'      => 0,
			'norpsilocin'   => 0,
			'baeocystin'    => 0,
			'norbaeocystin' => 0,
			'aeruginascin'  => 0,
			'batch_number'  => '',
		);

		// Check for legacy "data" format (comma-separated key:value)
		if ( ! empty( $params['data'] ) ) {
			$pairs = explode( ',', sanitize_text_field( $params['data'] ) );
			foreach ( $pairs as $pair ) {
				$parts = explode( ':', $pair, 2 );
				if ( count( $parts ) === 2 ) {
					$key   = strtolower( trim( $parts[0] ) );
					$value = trim( $parts[1] );

					if ( 'name' === $key ) {
						$result['name'] = sanitize_text_field( $value );
					} elseif ( isset( $result[ $key ] ) ) {
						$result[ $key ] = absint( $value );
					}
				}
			}
			return $result;
		}

		// Check for edible type
		if ( ! empty( $params['type'] ) && 'edible' === $params['type'] ) {
			$result['type']               = 'edible';
			$result['name']               = sanitize_text_field( $params['name'] ?? '' );
			$result['brand']              = sanitize_text_field( $params['brand'] ?? '' );
			$result['product_type']       = sanitize_key( $params['product_type'] ?? 'other' );
			$result['pieces_per_package'] = absint( $params['pieces'] ?? $params['pieces_per_package'] ?? 1 );
			$result['total_mg']           = absint( $params['total_mg'] ?? 0 );
			$result['batch_number']       = sanitize_text_field( $params['batch'] ?? '' );
			return $result;
		}

		// Standard query params for strain
		$result['name']          = sanitize_text_field( $params['strain'] ?? $params['name'] ?? '' );
		$result['psilocybin']    = absint( $params['psilocybin'] ?? 0 );
		$result['psilocin']      = absint( $params['psilocin'] ?? 0 );
		$result['norpsilocin']   = absint( $params['norpsilocin'] ?? 0 );
		$result['baeocystin']    = absint( $params['baeocystin'] ?? 0 );
		$result['norbaeocystin'] = absint( $params['norbaeocystin'] ?? 0 );
		$result['aeruginascin']  = absint( $params['aeruginascin'] ?? 0 );
		$result['batch_number']  = sanitize_text_field( $params['batch'] ?? '' );

		return $result;
	}

	/**
	 * Check if current request has legacy URL params
	 */
	public static function has_legacy_params() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check for legacy QR params.
		return isset( $_GET['data'] ) || isset( $_GET['strain'] ) || ( isset( $_GET['type'] ) && 'edible' === $_GET['type'] );
	}

	/**
	 * Process legacy URL and return data for calculator
	 */
	public static function process_legacy_request() {
		if ( ! self::has_legacy_params() ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- legacy QR redirect; sanitization in parse_legacy_url().
		$parsed = self::parse_legacy_url( wp_unslash( $_GET ) );

		// Auto-submit to review queue if enabled
		if ( ADC_DB::get_setting( 'auto_submit_unknown_qr', true ) ) {
			// Check if this exact data already exists
			$existing = null;

			if ( ! empty( $parsed['name'] ) ) {
				if ( 'edible' === $parsed['type'] ) {
					// Check edibles by name
					global $wpdb;
					$table    = ADC_DB::table( 'edibles' );
					$existing = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM $table WHERE name = %s",
							$parsed['name']
						)
					);
				} else {
					// Check strains by name
					global $wpdb;
					$table    = ADC_DB::table( 'strains' );
					$existing = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM $table WHERE name = %s",
							$parsed['name']
						)
					);
				}
			}

			// Only submit if not already in database
			if ( ! $existing && ! empty( $parsed['name'] ) && ( $parsed['psilocybin'] > 0 || $parsed['psilocin'] > 0 || ( 'edible' === $parsed['type'] && $parsed['total_mg'] > 0 ) ) ) {
				ADC_Submissions::create(
					array(
						'type'       => $parsed['type'],
						'source'     => 'qr_scan',
						'data'       => $parsed,
						'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- user agent sanitized via sanitize_text_field() after substr().
						'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( substr( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ), 0, 500 ) ) : '',
					)
				);
			}
		}

		return $parsed;
	}

	/**
	 * Generate short URL for an item.
	 *
	 * @param string $short_code Item short code.
	 * @return string Full short URL.
	 */
	public static function get_short_url( $short_code ) {
		$path = ADC_DB::get_setting( 'short_url_path', 'c' );
		return home_url( '/' . $path . '/' . $short_code );
	}

	/**
	 * Generate legacy URL for an item (for external producers).
	 *
	 * @param array  $data Compound/product data array.
	 * @param string $type Item type ('strain' or 'edible').
	 * @return string Full legacy URL with query parameters.
	 */
	public static function get_legacy_url( $data, $type = 'strain' ) {
		$calculator_url = self::get_calculator_page_url();

		if ( 'edible' === $type ) {
			$params = array(
				't'        => 'e',
				'name'     => $data['name'] ?? '',
				'total_mg' => $data['total_mg'] ?? 0,
				'pieces'   => $data['pieces_per_package'] ?? 1,
			);
			if ( ! empty( $data['brand'] ) ) {
				$params['brand'] = $data['brand'];
			}
			if ( ! empty( $data['batch_number'] ) ) {
				$params['batch'] = $data['batch_number'];
			}
		} else {
			// Use compact data format for strains
			$parts   = array();
			$parts[] = 'name:' . ( $data['name'] ?? 'Unknown' );
			if ( ! empty( $data['psilocybin'] ) ) {
				$parts[] = 'psilocybin:' . $data['psilocybin'];
			}
			if ( ! empty( $data['psilocin'] ) ) {
				$parts[] = 'psilocin:' . $data['psilocin'];
			}
			if ( ! empty( $data['norpsilocin'] ) ) {
				$parts[] = 'norpsilocin:' . $data['norpsilocin'];
			}
			if ( ! empty( $data['baeocystin'] ) ) {
				$parts[] = 'baeocystin:' . $data['baeocystin'];
			}
			if ( ! empty( $data['norbaeocystin'] ) ) {
				$parts[] = 'norbaeocystin:' . $data['norbaeocystin'];
			}
			if ( ! empty( $data['aeruginascin'] ) ) {
				$parts[] = 'aeruginascin:' . $data['aeruginascin'];
			}

			$params = array( 'data' => implode( ',', $parts ) );
		}

		return add_query_arg( $params, $calculator_url );
	}

	/**
	 * Get the URL of the calculator page
	 */
	public static function get_calculator_page_url() {
		// Try to find page with calculator shortcode
		global $wpdb;
		$like_dosage = '%' . $wpdb->esc_like( '[dosage_calculator' ) . '%';
		$like_adc    = '%' . $wpdb->esc_like( '[adc_calculator' ) . '%';
		$page_id     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'page' 
             AND post_status = 'publish' 
             AND (post_content LIKE %s OR post_content LIKE %s)
             LIMIT 1",
				$like_dosage,
				$like_adc
			)
		);

		if ( $page_id ) {
			return get_permalink( $page_id );
		}

		// Fall back to home URL
		return home_url( '/calculator/' );
	}

	/**
	 * Get calculator URL with params.
	 *
	 * @param array $params Query parameters to append.
	 * @return string Calculator URL with query string.
	 */
	private static function get_calculator_url( $params = array() ) {
		$base = self::get_calculator_page_url();
		return add_query_arg( $params, $base );
	}
}
