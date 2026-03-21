<?php
/**
 * Plugin-wide constants and shared data definitions.
 *
 * Single source of truth for compound lists, option keys, and other
 * values that were previously duplicated across multiple files.
 *
 * @since 2.17.0
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_Constants class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_Constants {

	/**
	 * Psilocybin compound keys (canonical order).
	 * Used for DB schema, REST API, shortcode, and admin forms.
	 */
	const COMPOUNDS = array(
		'psilocybin',
		'psilocin',
		'norpsilocin',
		'baeocystin',
		'norbaeocystin',
		'aeruginascin',
	);

	/**
	 * Human-readable compound labels, keyed by compound key.
	 */
	const COMPOUND_LABELS = array(
		'psilocybin'    => 'Psilocybin',
		'psilocin'      => 'Psilocin',
		'norpsilocin'   => 'Norpsilocin',
		'baeocystin'    => 'Baeocystin',
		'norbaeocystin' => 'Norbaeocystin',
		'aeruginascin'  => 'Aeruginascin',
	);

	/**
	 * WordPress option name for plugin settings.
	 */
	const OPTION_SETTINGS = 'adc_settings';

	/**
	 * WordPress option name for custom templates JSON.
	 */
	const OPTION_CUSTOM_TEMPLATES = 'adc_custom_templates';
}
