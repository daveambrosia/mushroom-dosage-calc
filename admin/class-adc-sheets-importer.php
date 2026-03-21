<?php
/**
 * Google Sheets Importer — Import strains & edibles from Google Sheets
 * with WP-Cron auto-sync support.
 *
 * @since 2.12.0
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_Sheets_Importer class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_Sheets_Importer {

	/** WP-Cron hook name */
	const CRON_HOOK = 'adc_google_sheets_sync';

	/** Option keys */
	const OPT_SETTINGS = 'adc_gsheets_settings';
	const OPT_LAST_LOG = 'adc_gsheets_last_log';

	/**
	 * Header mappings for fuzzy matching (reused from CSV importer pattern).
	 *
	 * @var array<string, array<string>>
	 */
	private static $header_mappings = array(
		'short_code'         => array( 'short_code', 'shortcode', 'short code', 'code', 'sku', 'id' ),
		'name'               => array( 'name', 'strain', 'strain name', 'product', 'product name', 'title' ),
		'batch_number'       => array( 'batch', 'batch number', 'batch_number', 'batch #', 'lot', 'lot number' ),
		'category'           => array( 'category', 'cat', 'strain type', 'species' ),
		'psilocybin'         => array( 'psilocybin', 'psilocybin %', 'psilocybin_mcg', 'psilocybin mcg/g', 'psilo' ),
		'psilocin'           => array( 'psilocin', 'psilocin %', 'psilocin_mcg', 'psilocin mcg/g' ),
		'norpsilocin'        => array( 'norpsilocin', 'norpsilocin %', 'norpsilocin_mcg', 'norpsilo' ),
		'baeocystin'         => array( 'baeocystin', 'baeocystin %', 'baeocystin_mcg', 'baeo' ),
		'norbaeocystin'      => array( 'norbaeocystin', 'norbaeocystin %', 'norbaeocystin_mcg', 'norbaeo' ),
		'aeruginascin'       => array( 'aeruginascin', 'aeruginascin %', 'aeruginascin_mcg', 'aerug' ),
		'brand'              => array( 'brand', 'manufacturer', 'maker', 'company' ),
		'product_type'       => array( 'product type', 'product_type', 'type', 'edible type' ),
		'pieces_per_package' => array( 'pieces', 'pieces per package', 'pieces_per_package', 'count', 'units' ),
		'total_mg'           => array( 'total mg', 'total_mg', 'mg total', 'total psilocybin mg', 'mg' ),
		'notes'              => array( 'notes', 'note', 'comments', 'description' ),
		'is_active'          => array( 'active', 'is_active', 'enabled', 'status' ),
	);

	/**
	 * Initialize cron hooks.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_sync' ) );
	}

	/**
	 * Get default settings.
	 */
	public static function get_defaults() {
		return array(
			'strains_url'    => 'https://docs.google.com/spreadsheets/d/1x50He5HTMD2ISYo61xVjOKh9Fcv18C1i1EcpXM18rFU/edit?gid=0#gid=0',
			'strains_gid'    => '0',
			'edibles_url'    => 'https://docs.google.com/spreadsheets/d/1x50He5HTMD2ISYo61xVjOKh9Fcv18C1i1EcpXM18rFU/edit?gid=1037373262#gid=1037373262',
			'edibles_gid'    => '1037373262',
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- documenting valid options.
			'import_mode'    => 'update', // 'add_new', 'update', 'replace'
			'auto_sync'      => false,
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- documenting valid options.
			'sync_frequency' => 'daily', // 'hourly', 'twicedaily', 'daily', 'weekly'
			'notify_admin'   => false,
		);
	}

	/**
	 * Get current settings.
	 */
	public static function get_settings() {
		$saved    = get_option( self::OPT_SETTINGS, array() );
		$defaults = self::get_defaults();

		// For URL/GID fields only: if the saved value is an empty string, fall back to
		// the default so that new installs and reset sites get the pre-configured URLs.
		// Other fields (booleans, mode strings) are intentionally left as saved.
		foreach ( array( 'strains_url', 'strains_gid', 'edibles_url', 'edibles_gid' ) as $key ) {
			if ( isset( $saved[ $key ] ) && '' === $saved[ $key ] && '' !== $defaults[ $key ] ) {
				unset( $saved[ $key ] );
			}
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Save settings and manage cron schedule.
	 *
	 * @param array $settings Settings to save.
	 */
	public static function save_settings( $settings ) {
		$old = self::get_settings();

		// Sanitize
		$clean = array(
			'strains_url'    => sanitize_text_field( $settings['strains_url'] ?? '' ),
			'strains_gid'    => sanitize_text_field( $settings['strains_gid'] ?? '' ),
			'edibles_url'    => sanitize_text_field( $settings['edibles_url'] ?? '' ),
			'edibles_gid'    => sanitize_text_field( $settings['edibles_gid'] ?? '' ),
			'import_mode'    => in_array( $settings['import_mode'] ?? '', array( 'add_new', 'update', 'replace' ), true )
								? $settings['import_mode'] : 'update',
			'auto_sync'      => ! empty( $settings['auto_sync'] ),
			'sync_frequency' => in_array( $settings['sync_frequency'] ?? '', array( 'hourly', 'twicedaily', 'daily', 'weekly' ), true )
								? $settings['sync_frequency'] : 'daily',
			'notify_admin'   => ! empty( $settings['notify_admin'] ),
		);

		update_option( self::OPT_SETTINGS, $clean );

		// Manage cron
		self::manage_cron_schedule( $clean );

		return $clean;
	}

	/**
	 * Schedule or unschedule the cron event.
	 *
	 * @param array $settings Sync settings.
	 */
	private static function manage_cron_schedule( $settings ) {
		$next = wp_next_scheduled( self::CRON_HOOK );

		if ( $settings['auto_sync'] && ( $settings['strains_url'] || $settings['edibles_url'] ) ) {
			// Need cron running
			$recurrence = $settings['sync_frequency'];

			// If already scheduled with different frequency, reschedule
			if ( $next ) {
				$current_schedule = wp_get_schedule( self::CRON_HOOK );
				if ( $current_schedule !== $recurrence ) {
					wp_unschedule_event( $next, self::CRON_HOOK );
					wp_schedule_event( time(), $recurrence, self::CRON_HOOK );
				}
			} else {
				wp_schedule_event( time(), $recurrence, self::CRON_HOOK );
			}
		} elseif ( $next ) {
			// Disable cron
			wp_unschedule_event( $next, self::CRON_HOOK );
		}
	}

	/**
	 * Run the scheduled sync (called by WP-Cron).
	 */
	public static function run_scheduled_sync() {
		$settings = self::get_settings();
		$log      = array(
			'time'    => current_time( 'mysql' ),
			'type'    => 'auto',
			'results' => array(),
		);

		if ( ! empty( $settings['strains_url'] ) ) {
			$log['results']['strains'] = self::import_type(
				'strain',
				$settings['strains_url'],
				$settings['strains_gid'] ?: null,
				$settings['import_mode']
			);
		}

		if ( ! empty( $settings['edibles_url'] ) ) {
			$log['results']['edibles'] = self::import_type(
				'edible',
				$settings['edibles_url'],
				$settings['edibles_gid'] ?: null,
				$settings['import_mode']
			);
		}

		ADC_Google_Sheets::record_sync_time();
		update_option( self::OPT_LAST_LOG, $log );

		// Notify admin if enabled
		if ( $settings['notify_admin'] ) {
			self::send_notification( $log );
		}

		return $log;
	}

	/**
	 * Run a manual import for a specific type.
	 *
	 * @param string $type    'strain' or 'edible'.
	 * @param string $url     Google Sheets URL.
	 * @param string $gid     Optional GID.
	 * @param string $mode    Import mode.
	 * @return array Result summary.
	 */
	public static function import_type( $type, $url, $gid = null, $mode = 'update' ) {
		$result = array(
			'type'        => $type,
			'imported'    => 0,
			'updated'     => 0,
			'skipped'     => 0,
			'deactivated' => 0,
			'errors'      => array(),
			'success'     => false,
		);

		// Rate limit check
		$rate_remaining = ADC_Google_Sheets::is_rate_limited();
		if ( false !== $rate_remaining ) {
			$result['errors'][] = 'Rate limited. Please wait ' . $rate_remaining . ' seconds.';
			return $result;
		}

		// Fetch data
		$data = ADC_Google_Sheets::fetch( $url, $gid );
		if ( is_wp_error( $data ) ) {
			$result['errors'][] = $data->get_error_message();
			return $result;
		}

		// Map headers
		$column_map = self::map_headers( $data['headers'], $type );
		if ( ! isset( $column_map['name'] ) ) {
			$result['errors'][] = 'Could not find a "name" column in the sheet. At minimum, a name column is required.';
			return $result;
		}

		// If replace mode, deactivate all existing
		if ( 'replace' === $mode ) {
			$result['deactivated'] = self::deactivate_all( $type );
		}

		// Process rows
		foreach ( $data['rows'] as $idx => $row ) {
			$row_result = self::process_row( $row, $column_map, $type, $mode );

			if ( is_wp_error( $row_result ) ) {
				$result['errors'][] = sprintf( 'Row %d: %s', $idx + 2, $row_result->get_error_message() );
				continue;
			}

			switch ( $row_result ) {
				case 'imported':
					++$result['imported'];
					break;
				case 'updated':
					++$result['updated'];
					break;
				case 'skipped':
					++$result['skipped'];
					break;
			}
		}

		// Clear caches
		if ( 'strain' === $type ) {
			ADC_Strains::clear_cache();
		} else {
			ADC_Edibles::clear_cache();
		}

		$result['success'] = true;
		ADC_Google_Sheets::record_sync_time();

		return $result;
	}

	/**
	 * Map sheet headers to database columns using fuzzy matching.
	 *
	 * @param array  $headers Sheet headers (lowercase).
	 * @param string $type    'strain' or 'edible'.
	 * @return array Map of db_column => sheet_header.
	 */
	public static function map_headers( $headers, $type = 'strain' ) {
		$map = array();

		foreach ( self::$header_mappings as $db_col => $aliases ) {
			// Skip edible-only fields for strains
			if ( 'strain' === $type && in_array( $db_col, array( 'brand', 'product_type', 'pieces_per_package', 'total_mg' ), true ) ) {
				continue;
			}

			foreach ( $aliases as $alias ) {
				$alias_lower = strtolower( $alias );
				foreach ( $headers as $header ) {
					if ( $header === $alias_lower ) {
						$map[ $db_col ] = $header;
						break 2;
					}
				}
			}
		}

		return $map;
	}

	/**
	 * Process a single row.
	 *
	 * @param array  $row        Row data keyed by sheet header.
	 * @param array  $column_map DB column => sheet header mapping.
	 * @param string $type       'strain' or 'edible'.
	 * @param string $mode       Import mode.
	 * @return string|WP_Error 'imported', 'updated', 'skipped', or WP_Error.
	 */
	private static function process_row( $row, $column_map, $type, $mode ) {
		// Build data array from mapped columns
		$data = array();
		foreach ( $column_map as $db_col => $header ) {
			$value           = $row[ $header ] ?? '';
			$data[ $db_col ] = $value;
		}

		// Name is required
		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'missing_name', 'Name is empty' );
		}

		// Clean up numeric fields
		$numeric_fields = array( 'psilocybin', 'psilocin', 'norpsilocin', 'baeocystin', 'norbaeocystin', 'aeruginascin' );
		if ( 'edible' === $type ) {
			$numeric_fields = array_merge( $numeric_fields, array( 'pieces_per_package', 'total_mg' ) );
		}
		foreach ( $numeric_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = max( 0, intval( preg_replace( '/[^0-9.]/', '', $data[ $field ] ) ) );
			}
		}

		// Slugify category/product_type for DB storage
		if ( ! empty( $data['category'] ) ) {
			$data['category'] = sanitize_title( $data['category'] );
		}
		if ( ! empty( $data['product_type'] ) ) {
			$data['product_type'] = sanitize_title( $data['product_type'] );
			// Normalize common plurals to match product_types table slugs
			$plural_map = array(
				'gummies'    => 'gummy',
				'capsules'   => 'capsule',
				'chocolates' => 'chocolate',
				'tinctures'  => 'tincture',
				'teas'       => 'tea',
			);
			if ( isset( $plural_map[ $data['product_type'] ] ) ) {
				$data['product_type'] = $plural_map[ $data['product_type'] ];
			}
		}

		// Handle is_active
		if ( isset( $data['is_active'] ) ) {
			$val               = strtolower( trim( $data['is_active'] ) );
			$data['is_active'] = in_array( $val, array( '1', 'yes', 'true', 'active', 'y' ), true ) ? 1 : 0;
		} else {
			$data['is_active'] = 1;
		}

		// Look up existing by short_code if provided
		$existing   = null;
		$short_code = $data['short_code'] ?? '';

		if ( 'strain' === $type ) {
			if ( $short_code ) {
				$existing = self::find_by_short_code( $type, $short_code );
			}
			if ( ! $existing ) {
				// Also try matching by name
				$existing = self::find_by_name( $type, $data['name'] );
			}
		} else {
			if ( $short_code ) {
				$existing = self::find_by_short_code( $type, $short_code );
			}
			if ( ! $existing ) {
				$existing = self::find_by_name( $type, $data['name'] );
			}
		}

		if ( $existing ) {
			if ( 'add_new' === $mode ) {
				return 'skipped';
			}

			// Update existing
			unset( $data['short_code'] ); // Don't overwrite short_code
			if ( 'strain' === $type ) {
				ADC_Strains::update( $existing['id'], $data );
			} else {
				ADC_Edibles::update( $existing['id'], $data );
			}
			return 'updated';
		}

		// Create new
		// Generate short_code if not provided
		if ( empty( $data['short_code'] ) ) {
			$data['short_code'] = ADC_DB::generate_short_code( 'edible' === $type ? 'edible' : 'strain', $data['name'] ?? 'Unknown' );
		}

		// Set defaults
		if ( 'strain' === $type ) {
			$data = wp_parse_args(
				$data,
				array(
					'category'   => 'uncategorized',
					'sort_order' => 0,
				)
			);
			ADC_Strains::create( $data );
		} else {
			$data = wp_parse_args(
				$data,
				array(
					'product_type'       => 'other',
					'pieces_per_package' => 1,
					'total_mg'           => 0,
					'sort_order'         => 0,
				)
			);
			ADC_Edibles::create( $data );
		}

		return 'imported';
	}

	/**
	 * Find record by short_code.
	 *
	 * @param string $type       'strain' or 'edible'.
	 * @param string $short_code Short code to search for.
	 * @return array|null Row data or null.
	 */
	private static function find_by_short_code( $type, $short_code ) {
		global $wpdb;
		$table = ADC_DB::table( 'edible' === $type ? 'edibles' : 'strains' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE short_code = %s",
				$short_code
			),
			ARRAY_A
		);
	}

	/**
	 * Find record by name (exact match).
	 *
	 * @param string $type 'strain' or 'edible'.
	 * @param string $name Name to search for.
	 * @return array|null Row data or null.
	 */
	private static function find_by_name( $type, $name ) {
		global $wpdb;
		$table = ADC_DB::table( 'edible' === $type ? 'edibles' : 'strains' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE name = %s",
				$name
			),
			ARRAY_A
		);
	}

	/**
	 * Deactivate all records of a type (for replace mode).
	 *
	 * @param string $type 'strain' or 'edible'.
	 * @return int Number of rows deactivated.
	 */
	private static function deactivate_all( $type ) {
		global $wpdb;
		$table = ADC_DB::table( 'edible' === $type ? 'edibles' : 'strains' );
		return $wpdb->query( "UPDATE $table SET is_active = 0" );
	}

	/**
	 * Send email notification about sync results.
	 *
	 * @param array $log Sync log data.
	 */
	private static function send_notification( $log ) {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );
		$subject     = sprintf( '[%s] Dosage Calculator — Google Sheets Sync Report', $site_name );

		$body = 'Google Sheets auto-sync completed at ' . $log['time'] . "\n\n";

		foreach ( $log['results'] as $type => $result ) {
			$body .= strtoupper( $type ) . ":\n";
			$body .= "  Imported: {$result['imported']}\n";
			$body .= "  Updated: {$result['updated']}\n";
			$body .= "  Skipped: {$result['skipped']}\n";
			if ( $result['deactivated'] ) {
				$body .= "  Deactivated: {$result['deactivated']}\n";
			}
			if ( ! empty( $result['errors'] ) ) {
				$body .= "  Errors:\n";
				foreach ( $result['errors'] as $err ) {
					$body .= "    - $err\n";
				}
			}
			$body .= "\n";
		}

		wp_mail( $admin_email, $subject, $body );
	}

	/**
	 * Get the last sync log.
	 */
	public static function get_last_log() {
		return get_option( self::OPT_LAST_LOG, null );
	}

	/**
	 * Get next scheduled sync time.
	 */
	public static function get_next_sync() {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Add custom cron schedule for weekly.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public static function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => 604800,
				'display'  => __( 'Once Weekly', 'ambrosia-dosage-calc' ),
			);
		}
		return $schedules;
	}

	/**
	 * Clean up on plugin deactivation.
	 */
	public static function deactivate() {
		$next = wp_next_scheduled( self::CRON_HOOK );
		if ( $next ) {
			wp_unschedule_event( $next, self::CRON_HOOK );
		}
	}
}
