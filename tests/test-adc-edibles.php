<?php
/**
 * Tests for the ADC_Edibles model — the class at the center of the
 * 2.25.1..2.25.3 unit regressions. Pins the per-piece storage contract,
 * total_mg derivation, pieces clamping, and short_code format validation.
 *
 * @package Ambrosia_Dosage_Calculator
 */

/**
 * Test_ADC_Edibles class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class Test_ADC_Edibles extends WP_UnitTestCase {

	/**
	 * Disable transaction rollback so plugin table inserts persist across calls.
	 *
	 * @var bool
	 */
	public static $use_transactions = false;

	/**
	 * Create plugin tables once.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		ADC_DB::init();
		if ( class_exists( 'ADC_Activator' ) ) {
			ADC_Activator::activate();
		}
	}

	/**
	 * Disable WP test suite DB transaction wrapping.
	 *
	 * @return void
	 */
	public function start_transaction(): void {
		// No-op: skip transaction wrapping for plugin tables.
	}

	/**
	 * Clean table between tests.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		ADC_DB::init();
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}adc_edibles" );
		ADC_Edibles::clear_cache();
	}

	/**
	 * Compound values are stored per piece, exactly as given.
	 *
	 * @return void
	 */
	public function test_create_stores_per_piece_values() {
		$id = ADC_Edibles::create(
			array(
				'name'               => 'Contract Gummies',
				'pieces_per_package' => 10,
				'psilocybin'         => 4000,
				'psilocin'           => 250,
			)
		);
		$this->assertNotWPError( $id );
		$edible = ADC_Edibles::get( intval( $id ) );
		$this->assertSame( 4000, intval( $edible['psilocybin'] ) );
		$this->assertSame( 250, intval( $edible['psilocin'] ) );
	}

	/**
	 * The total_mg is derived from the per-piece compound sum, not caller input.
	 *
	 * @return void
	 */
	public function test_total_mg_recomputed_from_compounds() {
		$id = ADC_Edibles::create(
			array(
				'name'               => 'TotalMg Gummies',
				'pieces_per_package' => 4,
				'psilocybin'         => 5000,
				'psilocin'           => 1000,
				'total_mg'           => 999999,
			)
		);
		$this->assertNotWPError( $id );
		$edible = ADC_Edibles::get( intval( $id ) );
		$this->assertLessThan( 999999, intval( $edible['total_mg'] ), 'caller-supplied total_mg must not be stored verbatim' );
	}

	/**
	 * The pieces_per_package can never reach the DB as zero (divide-by-zero guard).
	 *
	 * @return void
	 */
	public function test_pieces_clamped_to_minimum_one() {
		$id = ADC_Edibles::create(
			array(
				'name'               => 'Zero Piece Gummies',
				'pieces_per_package' => 0,
				'psilocybin'         => 4000,
			)
		);
		$this->assertNotWPError( $id );
		$edible = ADC_Edibles::get( intval( $id ) );
		$this->assertGreaterThanOrEqual( 1, intval( $edible['pieces_per_package'] ) );
	}

	/**
	 * The format_for_api output returns per-piece values unmodified — the exact site of
	 * the 2.25.1 regression (an erroneous division was added, then reverted).
	 *
	 * @return void
	 */
	public function test_format_for_api_returns_per_piece_unmodified() {
		$id = ADC_Edibles::create(
			array(
				'name'               => 'API Gummies',
				'pieces_per_package' => 10,
				'psilocybin'         => 4000,
			)
		);
		$this->assertNotWPError( $id );
		$formatted = ADC_Edibles::format_for_api( ADC_Edibles::get( intval( $id ) ) );
		$this->assertSame( 4000, intval( $formatted['psilocybin'] ), 'format_for_api must pass per-piece values through unchanged' );
		$this->assertSame( 10, intval( $formatted['piecesPerPackage'] ) );
	}

	/**
	 * The short_code format rule: only letters, numbers, dashes.
	 *
	 * @return void
	 */
	public function test_short_code_format_enforced() {
		$bad = ADC_Edibles::create(
			array(
				'name'       => 'XSS Gummies',
				'short_code' => 'a" onmouseover="alert(1)',
				'psilocybin' => 4000,
			)
		);
		$this->assertWPError( $bad );

		$good = ADC_Edibles::create(
			array(
				'name'       => 'Good Gummies',
				'short_code' => 'ZD-GUM-001',
				'psilocybin' => 4000,
			)
		);
		$this->assertNotWPError( $good );
	}

	/**
	 * Auto-generated short codes satisfy the new format rule.
	 *
	 * @return void
	 */
	public function test_generated_short_code_passes_format() {
		$id = ADC_Edibles::create(
			array(
				'name'       => 'Autogen Gummies',
				'psilocybin' => 4000,
			)
		);
		$this->assertNotWPError( $id );
		$edible = ADC_Edibles::get( intval( $id ) );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9-]{1,50}$/', $edible['short_code'] );
	}
}
