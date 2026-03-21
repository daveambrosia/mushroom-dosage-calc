<?php
/**
 * Ambrosia Dosage Calculator Uninstall Handler
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Drops all adc_* tables, deletes all adc_* options, and clears all adc_* transients.
 *
 * @package Ambrosia_Dosage_Calculator
 * @since   2.12.17
 */

// Abort if not called by WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/*
 * 1. Drop all adc_* custom tables.
 */
$tables = array(
	$wpdb->prefix . 'adc_strains',
	$wpdb->prefix . 'adc_edibles',
	$wpdb->prefix . 'adc_categories',
	$wpdb->prefix . 'adc_product_types',
	$wpdb->prefix . 'adc_compounds',
	$wpdb->prefix . 'adc_submissions',
	$wpdb->prefix . 'adc_blacklist',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

/*
 * 2. Delete all adc_* options.
 */
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'adc\_%'" );

/*
 * 3. Clear all adc_* transients (including timeout entries).
 */
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_adc\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_adc\_%'" );

/*
 * 4. Clear any site transients (multisite).
 */
if ( is_multisite() ) {
	$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_adc\_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_adc\_%'" );
}
