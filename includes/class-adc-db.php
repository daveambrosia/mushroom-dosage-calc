<?php
/**
 * Database helper class.
 *
 * Provides table name helpers, settings CRUD, and short code generation
 * for the Ambrosia Dosage Calculator plugin.
 *
 * @package Ambrosia_Dosage_Calculator
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_DB class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_DB {

	/** @var string|null Cached table prefix (e.g. wp_adc_). */
	private static $prefix;

	/** @var array|null In-memory settings cache for current request. */
	private static $settings_cache = null;

	/**
	 * Initialize the table prefix from wpdb.
	 *
	 * @return void
	 */
	public static function init() {
		global $wpdb;
		self::$prefix = $wpdb->prefix . 'adc_';
	}

	/**
	 * Get the ADC table prefix (e.g. wp_adc_).
	 *
	 * @return string
	 */
	public static function get_prefix() {
		if ( ! self::$prefix ) {
			self::init();
		}
		return self::$prefix;
	}

	/**
	 * Get a fully-qualified table name.
	 *
	 * @param string $name Table suffix (e.g. 'strains', 'edibles').
	 * @return string Full table name (e.g. wp_adc_strains).
	 */
	public static function table( $name ) {
		return self::get_prefix() . $name;
	}

	/**
	 * Generate a unique short code for a strain or edible.
	 *
	 * Format: ZD-{PREFIX}-{NNN} for strains, ZD-E-{PREFIX}-{NNN} for edibles.
	 *
	 * @param string $type 'strain' or 'edible'.
	 * @param string $name Item name (used to derive prefix).
	 * @return string Generated short code.
	 */
	public static function generate_short_code( $type, $name ) {
		global $wpdb;

		// Create prefix from name (first 2-3 letters)
		$clean_name = preg_replace( '/[^a-zA-Z0-9]/', '', $name );
		$prefix     = strtoupper( substr( $clean_name, 0, 3 ) );
		if ( strlen( $prefix ) < 2 ) {
			$prefix = 'XX';
		}

		// Type prefix
		$type_prefix = ( 'edible' === $type ) ? 'ZD-E-' : 'ZD-';

		// Find next number
		$table   = ( 'edible' === $type ) ? self::table( 'edibles' ) : self::table( 'strains' );
		$pattern = $type_prefix . $prefix . '-%';

		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(CAST(SUBSTRING_INDEX(short_code, '-', -1) AS UNSIGNED)) 
             FROM $table 
             WHERE short_code LIKE %s",
				$pattern
			)
		);

		$next   = ( $max ? (int) $max : 0 ) + 1;
		$number = str_pad( (string) $next, 3, '0', STR_PAD_LEFT );

		return $type_prefix . $prefix . '-' . $number;
	}

	/**
	 * Get all plugin settings as an associative array.
	 *
	 * @return array Settings array (empty array if none saved).
	 */
	public static function get_settings() {
		if ( null !== self::$settings_cache ) {
			return self::$settings_cache;
		}
		self::$settings_cache = get_option( 'adc_settings', array() );
		return self::$settings_cache;
	}

	/**
	 * Update plugin settings (merges with existing).
	 *
	 * @param array $settings Key-value pairs to merge into current settings.
	 * @return bool True if updated, false on failure.
	 */
	public static function update_settings( $settings ) {
		$current              = self::get_settings();
		$merged               = array_merge( $current, $settings );
		self::$settings_cache = $merged;
		// autoload=true: adc_settings is read on every calculator page load, saving one DB query (BUG-013).
		return update_option( 'adc_settings', $merged, true );
	}

	/**
	 * Invalidate the in-memory settings cache.
	 * Called automatically when adc_settings option is updated externally.
	 *
	 * @since 2.17.0
	 * @return void
	 */
	public static function invalidate_cache() {
		self::$settings_cache = null;
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed Setting value or default.
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}
