<?php
/**
 * Maker QR Shortcode — renders the dedicated maker QR generator page.
 *
 * @package Ambrosia_Dosage_Calculator
 * @since   2.26.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_Maker_QR_Shortcode class.
 */
class ADC_Maker_QR_Shortcode {

	/**
	 * Canonical shortcode tag for the maker QR page.
	 *
	 * @var string
	 */
	const TAG = 'dosage_calculator_qr_maker';

	/**
	 * Deprecated tag the feature shipped under in 2.26.0. Kept as a silent
	 * alias so pages created with it do not break; not documented anywhere.
	 *
	 * @var string
	 */
	const LEGACY_TAG = 'adc_maker_qr';

	/**
	 * Register the [dosage_calculator_qr_maker] shortcode (and its legacy alias).
	 */
	public static function register() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_shortcode( self::LEGACY_TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the maker QR page.
	 *
	 * @param array $atts Shortcode attributes (none used in v1).
	 * @return string HTML.
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts( array(), $atts, self::TAG );
		// Render-time enqueue: covers shortcode placements that
		// has_shortcode(post_content) cannot see (widgets, blocks, FSE
		// templates). Idempotent when the wp_enqueue_scripts pass already ran.
		adc()->enqueue_calculator_assets( true );
		ob_start();
		?>
		<div class="adc-maker-qr" data-adc-maker-qr>
			<header class="adc-maker-qr__header">
				<h2><?php esc_html_e( 'Generate a QR code for your product', 'ambrosia-dosage-calc' ); ?></h2>
				<p class="adc-maker-qr__intro">
					<?php esc_html_e( 'Enter your product details to create a QR code anyone can scan to open the dosage calculator with your data pre-filled. Your codes save in your browser and remain useful even if you do not submit them to the church.', 'ambrosia-dosage-calc' ); ?>
				</p>
			</header>

			<form class="adc-maker-qr__form" data-adc-maker-form novalidate>
				<fieldset class="adc-maker-qr__type">
					<legend><?php esc_html_e( 'Product type', 'ambrosia-dosage-calc' ); ?></legend>
					<label>
						<input type="radio" name="type" value="strain" checked>
						<?php esc_html_e( 'Strain (loose powder, mcg per gram)', 'ambrosia-dosage-calc' ); ?>
					</label>
					<label>
						<input type="radio" name="type" value="edible">
						<?php esc_html_e( 'Edible (capsules / unit-dose, mcg per piece)', 'ambrosia-dosage-calc' ); ?>
					</label>
				</fieldset>

				<div class="adc-maker-qr__fields" data-adc-maker-fields>
					<!-- Populated by JS based on type. -->
				</div>

				<div class="adc-maker-qr__honeypot" aria-hidden="true" style="position:absolute;left:-9999px;">
					<label>
						<?php esc_html_e( 'Website', 'ambrosia-dosage-calc' ); ?>
						<input type="text" name="website" tabindex="-1" autocomplete="off">
					</label>
				</div>

				<div class="adc-maker-qr__actions">
					<button type="submit" class="button button-primary" data-adc-maker-generate>
						<?php esc_html_e( 'Generate QR', 'ambrosia-dosage-calc' ); ?>
					</button>
					<button type="button" class="button" data-adc-maker-download style="display:none;">
						<?php esc_html_e( 'Download PNG', 'ambrosia-dosage-calc' ); ?>
					</button>
					<button type="button" class="button" data-adc-maker-submit style="display:none;">
						<?php esc_html_e( 'Submit to Church', 'ambrosia-dosage-calc' ); ?>
					</button>
				</div>

				<div class="adc-maker-qr__feedback" data-adc-maker-feedback role="status" aria-live="polite"></div>
			</form>

			<section class="adc-maker-qr__preview" data-adc-maker-preview>
				<h3><?php esc_html_e( 'Preview', 'ambrosia-dosage-calc' ); ?></h3>
				<p class="adc-maker-qr__placeholder" data-adc-maker-placeholder>
					<?php esc_html_e( 'Fill in the form to generate a QR code.', 'ambrosia-dosage-calc' ); ?>
				</p>
				<canvas data-adc-maker-canvas style="display:none;"></canvas>
				<div class="adc-maker-qr__url" data-adc-maker-url-row style="display:none;">
					<label>
						<?php esc_html_e( 'URL', 'ambrosia-dosage-calc' ); ?>
						<input type="text" data-adc-maker-url readonly>
					</label>
					<button type="button" class="button" data-adc-maker-copy>
						<?php esc_html_e( 'Copy', 'ambrosia-dosage-calc' ); ?>
					</button>
				</div>
			</section>

			<section class="adc-maker-qr__saved">
				<h3><?php esc_html_e( 'My saved QR codes', 'ambrosia-dosage-calc' ); ?></h3>
				<ul class="adc-maker-qr__list" data-adc-maker-saved-list></ul>
				<p class="adc-maker-qr__empty" data-adc-maker-saved-empty>
					<?php esc_html_e( 'Save a QR code to keep it here for next time.', 'ambrosia-dosage-calc' ); ?>
				</p>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
