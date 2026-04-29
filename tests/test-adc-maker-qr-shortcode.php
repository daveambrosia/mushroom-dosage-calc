<?php
/**
 * Tests for ADC_Maker_QR_Shortcode.
 *
 * @package Ambrosia_Dosage_Calculator
 */

require_once dirname( __DIR__ ) . '/includes/class-adc-maker-qr-shortcode.php';

class Test_ADC_Maker_QR_Shortcode extends WP_UnitTestCase {

	public function test_shortcode_is_registered_after_register() {
		ADC_Maker_QR_Shortcode::register();
		$this->assertTrue( shortcode_exists( 'adc_maker_qr' ) );
	}

	public function test_render_outputs_form_preview_and_saved_list_regions() {
		$output = do_shortcode( '[adc_maker_qr]' );
		$this->assertStringContainsString( 'adc-maker-qr', $output );
		$this->assertStringContainsString( 'data-adc-maker-form', $output );
		$this->assertStringContainsString( 'data-adc-maker-preview', $output );
		$this->assertStringContainsString( 'data-adc-maker-saved-list', $output );
	}

	public function test_render_includes_honeypot_field() {
		$output = do_shortcode( '[adc_maker_qr]' );
		$this->assertMatchesRegularExpression(
			'/name=[\'\"]website[\'\"]/',
			$output,
			'Honeypot website field must be present.'
		);
	}

	public function test_render_has_strain_and_edible_type_toggle() {
		$output = do_shortcode( '[adc_maker_qr]' );
		$this->assertStringContainsString( 'value="strain"', $output );
		$this->assertStringContainsString( 'value="edible"', $output );
	}
}
