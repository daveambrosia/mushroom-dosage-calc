<?php
/**
 * Plugin activation handler - Version 2.1
 * With improved error handling and diagnostics
 *
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_Activator class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_Activator {

	private static $activation_errors   = array();
	private static $activation_warnings = array();

	public static function activate() {
		self::$activation_errors   = array();
		self::$activation_warnings = array();

		// Create tables
		$tables_ok = self::create_tables();

		// Insert default data (only if tables exist)
		if ( $tables_ok ) {
			self::insert_default_data();
		}

		self::set_default_options();
		self::maybe_migrate_data();

		// Clear cached health check so admin sees fresh state
		delete_transient( 'adc_db_health' );

		// Store any errors for admin notice
		if ( ! empty( self::$activation_errors ) ) {
			update_option( 'adc_activation_errors', self::$activation_errors );
		} else {
			delete_option( 'adc_activation_errors' );
		}

		if ( ! empty( self::$activation_warnings ) ) {
			update_option( 'adc_activation_warnings', self::$activation_warnings );
		} else {
			delete_option( 'adc_activation_warnings' );
		}

		// Store activation time for diagnostics
		update_option( 'adc_activated_at', current_time( 'mysql' ) );

		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
		if ( class_exists( 'ADC_Sheets_Importer' ) ) {
			ADC_Sheets_Importer::deactivate();
		}

		// Clear all plugin transients
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_adc\_%' OR option_name LIKE '_transient_timeout_adc\_%'" );
	}

	/**
	 * Get activation errors (for admin display)
	 */
	public static function get_activation_errors() {
		return get_option( 'adc_activation_errors', array() );
	}

	/**
	 * Get activation warnings
	 */
	public static function get_activation_warnings() {
		return get_option( 'adc_activation_warnings', array() );
	}

	/**
	 * Check database health
	 */
	public static function check_database_health() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'adc_';
		$status = array(
			'tables' => array(),
			'ok'     => true,
			'errors' => array(),
		);

		$required_tables = array(
			'strains'       => array( 'id', 'short_code', 'name', 'category', 'psilocybin' ),
			'edibles'       => array( 'id', 'short_code', 'name', 'product_type', 'psilocybin' ),
			'categories'    => array( 'id', 'name', 'slug', 'type' ),
			'product_types' => array( 'id', 'name', 'unit_name', 'slug' ),
			'compounds'     => array( 'id', 'compound_key', 'display_name' ),
			'submissions'   => array( 'id', 'type', 'data', 'status' ),
		);

		foreach ( $required_tables as $table => $required_cols ) {
			$full_table = $prefix . $table;
			$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table ) );

			if ( $exists ) {
				$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$full_table}`" );
				$missing = array_diff( $required_cols, $columns );
				$count   = $wpdb->get_var( "SELECT COUNT(*) FROM `{$full_table}`" );

				$status['tables'][ $table ] = array(
					'exists'          => true,
					'columns'         => $columns,
					'missing_columns' => $missing,
					'row_count'       => intval( $count ),
				);

				if ( ! empty( $missing ) ) {
					$status['ok']       = false;
					$status['errors'][] = "Table {$table} is missing columns: " . implode( ', ', $missing );
				}
			} else {
				$status['tables'][ $table ] = array(
					'exists'    => false,
					'row_count' => 0,
				);
				$status['ok']               = false;
				$status['errors'][]         = "Table {$table} does not exist";
			}
		}

		return $status;
	}

	/**
	 * Repair database tables
	 */
	public static function repair_database() {
		self::$activation_errors   = array();
		self::$activation_warnings = array();

		$result = self::create_tables();

		if ( $result ) {
			self::insert_default_data();
		}

		return array(
			'success'  => $result,
			'errors'   => self::$activation_errors,
			'warnings' => self::$activation_warnings,
		);
	}

	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . 'adc_';
		$all_ok          = true;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Strains table
		$sql_strains = "CREATE TABLE {$prefix}strains (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            short_code VARCHAR(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            batch_number VARCHAR(100),
            category VARCHAR(100) NOT NULL DEFAULT 'uncategorized',
            psilocybin INT UNSIGNED DEFAULT 0,
            psilocin INT UNSIGNED DEFAULT 0,
            norpsilocin INT UNSIGNED DEFAULT 0,
            baeocystin INT UNSIGNED DEFAULT 0,
            norbaeocystin INT UNSIGNED DEFAULT 0,
            aeruginascin INT UNSIGNED DEFAULT 0,
            extra_compounds LONGTEXT,
            is_active TINYINT(1) DEFAULT 1,
            notes TEXT,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_short_code (short_code),
            KEY idx_category (category),
            KEY idx_active (is_active)
        ) $charset_collate;";

		$result = dbDelta( $sql_strains );
		if ( $wpdb->last_error ) {
			error_log( '[ADC] Strains table creation failed: ' . $wpdb->last_error );
			self::$activation_errors[] = 'Strains table: ' . $wpdb->last_error;
			$all_ok                    = false;
		}

		// Edibles table - Without generated column for broader MySQL compatibility
		$sql_edibles = "CREATE TABLE {$prefix}edibles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            short_code VARCHAR(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            brand VARCHAR(255),
            product_type VARCHAR(50) NOT NULL DEFAULT 'other',
            batch_number VARCHAR(100),
            pieces_per_package INT UNSIGNED NOT NULL DEFAULT 1,
            total_mg INT UNSIGNED NOT NULL DEFAULT 0,
            psilocybin INT UNSIGNED DEFAULT 0,
            psilocin INT UNSIGNED DEFAULT 0,
            norpsilocin INT UNSIGNED DEFAULT 0,
            baeocystin INT UNSIGNED DEFAULT 0,
            norbaeocystin INT UNSIGNED DEFAULT 0,
            aeruginascin INT UNSIGNED DEFAULT 0,
            extra_compounds LONGTEXT,
            image_id BIGINT UNSIGNED,
            is_active TINYINT(1) DEFAULT 1,
            notes TEXT,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_short_code (short_code),
            KEY idx_product_type (product_type),
            KEY idx_brand (brand),
            KEY idx_active (is_active)
        ) $charset_collate;";

		$result = dbDelta( $sql_edibles );
		if ( $wpdb->last_error ) {
			error_log( '[ADC] Edibles table creation failed: ' . $wpdb->last_error );
			self::$activation_errors[] = 'Edibles table: ' . $wpdb->last_error;
			$all_ok                    = false;
		}

		// Categories table
		$sql_categories = "CREATE TABLE {$prefix}categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            type VARCHAR(20) DEFAULT 'both',
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            UNIQUE KEY uk_slug (slug)
        ) $charset_collate;";

		$result = dbDelta( $sql_categories );
		if ( $wpdb->last_error ) {
			error_log( '[ADC] Categories table creation failed: ' . $wpdb->last_error );
			self::$activation_errors[] = 'Categories table: ' . $wpdb->last_error;
			$all_ok                    = false;
		}

		// Product types table
		$sql_product_types = "CREATE TABLE {$prefix}product_types (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            unit_name VARCHAR(50) NOT NULL DEFAULT 'pieces',
            slug VARCHAR(100) NOT NULL,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            UNIQUE KEY uk_slug (slug)
        ) $charset_collate;";

		$result = dbDelta( $sql_product_types );
		if ( $wpdb->last_error ) {
			error_log( '[ADC] Product types table creation failed: ' . $wpdb->last_error );
			self::$activation_errors[] = 'Product types table: ' . $wpdb->last_error;
			$all_ok                    = false;
		}

		// Compounds table
		$sql_compounds = "CREATE TABLE {$prefix}compounds (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            compound_key VARCHAR(50) NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            unit VARCHAR(20) DEFAULT 'mcg/g',
            affects_dosing TINYINT(1) DEFAULT 0,
            calculation_weight DECIMAL(5,2) DEFAULT 1.00,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_compound_key (compound_key)
        ) $charset_collate;";

		$result = dbDelta( $sql_compounds );
		if ( $wpdb->last_error ) {
			error_log( '[ADC] Compounds table creation failed: ' . $wpdb->last_error );
			self::$activation_errors[] = 'Compounds table: ' . $wpdb->last_error;
			$all_ok                    = false;
		}

		// Submissions table
		$sql_submissions = "CREATE TABLE {$prefix}submissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(20) NOT NULL,
            source VARCHAR(50) DEFAULT 'user_submit',
            data LONGTEXT NOT NULL,
            submitter_name VARCHAR(255),
            submitter_email VARCHAR(255),
            submitter_notes TEXT,
            submitter_source VARCHAR(255),
            testing_lab VARCHAR(255),
            status VARCHAR(20) DEFAULT 'pending',
            admin_notes TEXT,
            reviewed_by BIGINT UNSIGNED,
            reviewed_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT,
            KEY idx_status (status),
            KEY idx_type (type),
            KEY idx_source (source),
            KEY idx_created (created_at)
        ) $charset_collate;";

		$result = dbDelta( $sql_submissions );
		if ( $wpdb->last_error ) {
			error_log( '[ADC] Submissions table creation failed: ' . $wpdb->last_error );
			self::$activation_errors[] = 'Submissions table: ' . $wpdb->last_error;
			$all_ok                    = false;
		}

		// Blacklist table
		$sql_blacklist = "CREATE TABLE {$prefix}blacklist (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(20) NOT NULL,
            value VARCHAR(255) NOT NULL,
            reason TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED,
            UNIQUE KEY uk_type_value (type, value),
            KEY idx_type (type)
        ) $charset_collate;";

		$result = dbDelta( $sql_blacklist );
		if ( $wpdb->last_error ) {
			error_log( '[ADC] Blacklist table creation failed: ' . $wpdb->last_error );
			self::$activation_errors[] = 'Blacklist table: ' . $wpdb->last_error;
			$all_ok                    = false;
		}

		// Store DB version
		update_option( 'adc_db_version', defined( 'ADC_DB_VERSION' ) ? ADC_DB_VERSION : '2.1.0' );

		return $all_ok;
	}

	private static function insert_default_data() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'adc_';

		// Check if tables exist first
		$cat_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . 'categories' ) );
		if ( ! $cat_table ) {
			self::$activation_errors[] = 'Cannot insert default data: categories table does not exist';
			return;
		}

		// Default categories
		$cat_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}categories" );
		if ( 0 === (int) $cat_count ) {
			$categories = array(
				array(
					'name'       => 'Lab Tested',
					'slug'       => 'lab-tested',
					'type'       => 'both',
					'sort_order' => 1,
				),
				array(
					'name'       => 'House Strain',
					'slug'       => 'house-strain',
					'type'       => 'strain',
					'sort_order' => 2,
				),
				array(
					'name'       => 'Community',
					'slug'       => 'community',
					'type'       => 'both',
					'sort_order' => 3,
				),
				array(
					'name'       => 'Psilocybe cubensis',
					'slug'       => 'psilocybe-cubensis',
					'type'       => 'strain',
					'sort_order' => 10,
				),
				array(
					'name'       => 'Psilocybe cyanescens',
					'slug'       => 'psilocybe-cyanescens',
					'type'       => 'strain',
					'sort_order' => 11,
				),
				array(
					'name'       => 'Panaeolus cyanescens',
					'slug'       => 'panaeolus-cyanescens',
					'type'       => 'strain',
					'sort_order' => 12,
				),
				array(
					'name'       => 'Standard Potency',
					'slug'       => 'standard-potency',
					'type'       => 'strain',
					'sort_order' => 20,
				),
				array(
					'name'       => 'Moderate Potency',
					'slug'       => 'moderate-potency',
					'type'       => 'strain',
					'sort_order' => 21,
				),
				array(
					'name'       => 'High Potency',
					'slug'       => 'high-potency',
					'type'       => 'strain',
					'sort_order' => 22,
				),
				array(
					'name'       => 'Very High Potency',
					'slug'       => 'very-high-potency',
					'type'       => 'strain',
					'sort_order' => 23,
				),
			);

			foreach ( $categories as $cat ) {
				$inserted = $wpdb->insert( $prefix . 'categories', $cat );
				if ( false === $inserted ) {
					self::$activation_warnings[] = "Failed to insert category: {$cat['name']} - " . $wpdb->last_error;
				}
			}
		}

		// Default product types
		$type_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . 'product_types' ) );
		if ( $type_table ) {
			$type_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}product_types" );
			if ( 0 === (int) $type_count ) {
				$product_types = array(
					array(
						'name'       => 'Chocolate',
						'slug'       => 'chocolate',
						'sort_order' => 1,
					),
					array(
						'name'       => 'Gummy',
						'slug'       => 'gummy',
						'sort_order' => 2,
					),
					array(
						'name'       => 'Capsule',
						'slug'       => 'capsule',
						'sort_order' => 3,
					),
					array(
						'name'       => 'Tea',
						'slug'       => 'tea',
						'sort_order' => 4,
					),
					array(
						'name'       => 'Tincture',
						'slug'       => 'tincture',
						'sort_order' => 5,
					),
					array(
						'name'       => 'Other',
						'slug'       => 'other',
						'sort_order' => 99,
					),
				);

				foreach ( $product_types as $type ) {
					$wpdb->insert( $prefix . 'product_types', $type );
				}
			}
		}

		// Default compounds
		$comp_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . 'compounds' ) );
		if ( $comp_table ) {
			$comp_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}compounds" );
			if ( 0 === (int) $comp_count ) {
				$compounds = array(
					array(
						'compound_key'       => 'psilocybin',
						'display_name'       => 'Psilocybin',
						'unit'               => 'mcg/g',
						'affects_dosing'     => 1,
						'calculation_weight' => 1.00,
						'sort_order'         => 1,
					),
					array(
						'compound_key'       => 'psilocin',
						'display_name'       => 'Psilocin',
						'unit'               => 'mcg/g',
						'affects_dosing'     => 1,
						'calculation_weight' => 1.00,
						'sort_order'         => 2,
					),
					array(
						'compound_key'       => 'norpsilocin',
						'display_name'       => 'Norpsilocin',
						'unit'               => 'mcg/g',
						'affects_dosing'     => 0,
						'calculation_weight' => 0.00,
						'sort_order'         => 3,
					),
					array(
						'compound_key'       => 'baeocystin',
						'display_name'       => 'Baeocystin',
						'unit'               => 'mcg/g',
						'affects_dosing'     => 0,
						'calculation_weight' => 0.00,
						'sort_order'         => 4,
					),
					array(
						'compound_key'       => 'norbaeocystin',
						'display_name'       => 'Norbaeocystin',
						'unit'               => 'mcg/g',
						'affects_dosing'     => 0,
						'calculation_weight' => 0.00,
						'sort_order'         => 5,
					),
					array(
						'compound_key'       => 'aeruginascin',
						'display_name'       => 'Aeruginascin',
						'unit'               => 'mcg/g',
						'affects_dosing'     => 0,
						'calculation_weight' => 0.00,
						'sort_order'         => 6,
					),
				);

				foreach ( $compounds as $comp ) {
					$wpdb->insert( $prefix . 'compounds', $comp );
				}
			}
		}

		// Default strains
		$strain_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . 'strains' ) );
		if ( $strain_table ) {
			$strain_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}strains" );
			if ( 0 === (int) $strain_count ) {
				$strains = array(
					array(
						'short_code' => 'ZD-GT-001',
						'name'       => 'Golden Teacher',
						'category'   => 'standard-potency',
						'psilocybin' => 6200,
						'psilocin'   => 800,
					),
					array(
						'short_code' => 'ZD-BP-001',
						'name'       => 'B+',
						'category'   => 'standard-potency',
						'psilocybin' => 7000,
						'psilocin'   => 0,
					),
					array(
						'short_code' => 'ZD-PE-001',
						'name'       => 'Penis Envy',
						'category'   => 'high-potency',
						'psilocybin' => 12000,
						'psilocin'   => 3000,
					),
					array(
						'short_code' => 'ZD-APE-001',
						'name'       => 'Albino Penis Envy',
						'category'   => 'high-potency',
						'psilocybin' => 18000,
						'psilocin'   => 0,
					),
					array(
						'short_code' => 'ZD-EN-001',
						'name'       => 'Enigma',
						'category'   => 'very-high-potency',
						'psilocybin' => 18000,
						'psilocin'   => 4000,
					),
				);

				foreach ( $strains as $strain ) {
					$wpdb->insert( $prefix . 'strains', $strain );
				}
			}
		}
	}

	private static function set_default_options() {
		$defaults = array(
			'template'                      => 'default',
			'default_tab'                   => 'mushrooms',
			'show_edibles'                  => true,
			'show_mushrooms'                => true,
			'show_quick_converter'          => true,
			'show_compound_breakdown'       => true,
			'show_safety_warning'           => true,
			'allow_custom'                  => true,
			'allow_submit'                  => true,
			'short_url_path'                => 'c',
			'short_code_prefix'             => 'ZD',
			'disclaimer_text'               => 'For educational and spiritual purposes only.',
			'custom_css'                    => '',
			'auto_submit_unknown_qr'        => true,
			'submission_notification_email' => '',
		);

		$existing = get_option( 'adc_settings', array() );
		$merged   = array_merge( $defaults, $existing );
		update_option( 'adc_settings', $merged );
	}

	private static function maybe_migrate_data() {
		global $wpdb;

		// Migration: Add is_active column to blacklist table if missing
		$blacklist_table = $wpdb->prefix . 'adc_blacklist';
		$columns         = $wpdb->get_col( "SHOW COLUMNS FROM `{$blacklist_table}`" );
		if ( $columns && ! in_array( 'is_active', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$blacklist_table}` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1" );
		}

		// Migration: Add unit_name column to product_types table if missing
		$product_types_table = $wpdb->prefix . 'adc_product_types';
		$pt_columns          = $wpdb->get_col( "SHOW COLUMNS FROM `{$product_types_table}`" );
		if ( $pt_columns && ! in_array( 'unit_name', $pt_columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$product_types_table}` ADD COLUMN `unit_name` VARCHAR(50) NOT NULL DEFAULT 'pieces' AFTER `name`" );
		}

		update_option( 'adc_v2_migrated', true );
	}
}
