<?php
/**
 * Tests for ADC_Maker_QR_Shortcode.
 *
 * @package Ambrosia_Dosage_Calculator
 */

/**
 * Test_ADC_Maker_QR_Shortcode class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class Test_ADC_Maker_QR_Shortcode extends WP_UnitTestCase {

	/**
	 * Load the shortcode class before any tests run.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once dirname( __DIR__ ) . '/includes/class-adc-maker-qr-shortcode.php';
	}

	/**
	 * The canonical [dosage_calculator_qr_maker] shortcode registers.
	 *
	 * @return void
	 */
	public function test_shortcode_is_registered_after_register() {
		ADC_Maker_QR_Shortcode::register();
		$this->assertTrue( shortcode_exists( 'dosage_calculator_qr_maker' ) );
	}

	/**
	 * The deprecated [adc_maker_qr] alias still resolves for back-compat.
	 *
	 * @return void
	 */
	public function test_legacy_alias_still_registered() {
		ADC_Maker_QR_Shortcode::register();
		$this->assertTrue( shortcode_exists( 'adc_maker_qr' ) );
		$this->assertSame(
			do_shortcode( '[dosage_calculator_qr_maker]' ),
			do_shortcode( '[adc_maker_qr]' ),
			'The legacy alias must render identically to the canonical tag.'
		);
	}

	/**
	 * Rendered output contains the form, preview, and saved-list regions.
	 *
	 * @return void
	 */
	public function test_render_outputs_form_preview_and_saved_list_regions() {
		$output = do_shortcode( '[dosage_calculator_qr_maker]' );
		$this->assertStringContainsString( 'adc-maker-qr', $output );
		$this->assertStringContainsString( 'data-adc-maker-form', $output );
		$this->assertStringContainsString( 'data-adc-maker-preview', $output );
		$this->assertStringContainsString( 'data-adc-maker-saved-list', $output );
	}

	/**
	 * Rendered output includes the spam honeypot field.
	 *
	 * @return void
	 */
	public function test_render_includes_honeypot_field() {
		$output = do_shortcode( '[dosage_calculator_qr_maker]' );
		$this->assertMatchesRegularExpression(
			'/name=[\'\"]website[\'\"]/',
			$output,
			'Honeypot website field must be present.'
		);
	}

	/**
	 * Rendered output offers both the strain and edible product types.
	 *
	 * @return void
	 */
	public function test_render_has_strain_and_edible_type_toggle() {
		$output = do_shortcode( '[dosage_calculator_qr_maker]' );
		$this->assertStringContainsString( 'value="strain"', $output );
		$this->assertStringContainsString( 'value="edible"', $output );
	}
}
