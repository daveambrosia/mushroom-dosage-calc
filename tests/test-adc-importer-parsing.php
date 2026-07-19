<?php
/**
 * Test importer numeric parsing: thousands separators, percent columns,
 * and the per-package -> per-piece division for edibles.
 *
 * Regression guards for two silent data-corruption bugs: intval("6,200")
 * returning 6 (a 1000x dose error), and "psilocybin %" columns parsing to
 * zero potency because the percent conversion was missing in the Sheets
 * importer.
 *
 * @package Ambrosia_Dosage_Calculator
 */

/**
 * Test_ADC_Importer_Parsing class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class Test_ADC_Importer_Parsing extends WP_UnitTestCase {

	/**
	 * Disable transaction rollback so plugin table inserts persist across calls.
	 *
	 * @var bool
	 */
	public static $use_transactions = false;

	/**
	 * Create plugin tables and load admin importer classes.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		ADC_DB::init();
		if ( class_exists( 'ADC_Activator' ) ) {
			ADC_Activator::activate();
		}
		if ( ! class_exists( 'ADC_CSV_Importer' ) ) {
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-csv-importer.php';
		}
		if ( ! class_exists( 'ADC_Sheets_Importer' ) ) {
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-sheets-importer.php';
		}
		if ( ! class_exists( 'ADC_Google_Sheets' ) ) {
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-google-sheets.php';
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
	 * Set up: clean strain/edible tables.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		ADC_DB::init();
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}adc_strains" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}adc_edibles" );
		ADC_Strains::clear_cache();
		ADC_Edibles::clear_cache();
	}

	/**
	 * Invoke ADC_CSV_Importer::map_row via reflection.
	 *
	 * @param array $row        Row values.
	 * @param array $column_map Field => column index.
	 * @param array $headers    Header names by index.
	 * @return array Mapped data.
	 */
	private function map_csv_row( $row, $column_map, $headers ) {
		$method = new ReflectionMethod( 'ADC_CSV_Importer', 'map_row' );
		return $method->invoke( null, $row, $column_map, $headers );
	}

	/**
	 * Thousands separators must not truncate values: intval("6,200") === 6.
	 *
	 * @return void
	 */
	public function test_csv_thousands_separator_not_truncated() {
		$headers    = array( 'name', 'psilocybin' );
		$column_map = array(
			'name'       => 0,
			'psilocybin' => 1,
		);
		$data       = $this->map_csv_row( array( 'Golden Teacher', '6,200' ), $column_map, $headers );
		$this->assertSame( 6200, $data['psilocybin'] );
	}

	/**
	 * Percent cells convert to mcg/g: 0.62% by weight = 6200 mcg/g.
	 *
	 * @return void
	 */
	public function test_csv_percent_cell_converts() {
		$headers    = array( 'name', 'psilocybin' );
		$column_map = array(
			'name'       => 0,
			'psilocybin' => 1,
		);
		$data       = $this->map_csv_row( array( 'GT', '0.62%' ), $column_map, $headers );
		$this->assertSame( 6200, $data['psilocybin'] );
	}

	/**
	 * A column HEADED "psilocybin %" is percent even when cells omit the sign.
	 *
	 * @return void
	 */
	public function test_csv_percent_header_converts_bare_decimals() {
		$headers    = array( 'name', 'psilocybin %' );
		$column_map = array(
			'name'       => 0,
			'psilocybin' => 1,
		);
		$data       = $this->map_csv_row( array( 'GT', '0.62' ), $column_map, $headers );
		$this->assertSame( 6200, $data['psilocybin'] );
	}

	/**
	 * Plain integer mcg values pass through unchanged.
	 *
	 * @return void
	 */
	public function test_csv_plain_integer_unchanged() {
		$headers    = array( 'name', 'psilocybin' );
		$column_map = array(
			'name'       => 0,
			'psilocybin' => 1,
		);
		$data       = $this->map_csv_row( array( 'GT', '6200' ), $column_map, $headers );
		$this->assertSame( 6200, $data['psilocybin'] );
	}

	/**
	 * Sheets importer: percent header column with bare decimal imports at
	 * full potency, not zero (the old truncate-to-int bug).
	 *
	 * @return void
	 */
	public function test_sheets_percent_header_row_imports_potency() {
		$method = new ReflectionMethod( 'ADC_Sheets_Importer', 'process_row' );

		$row        = array(
			'name'         => 'Percent Header Strain',
			'psilocybin %' => '0.62',
		);
		$column_map = array(
			'name'       => 'name',
			'psilocybin' => 'psilocybin %',
		);

		$result = $method->invoke( null, $row, $column_map, 'strain', 'import' );
		$this->assertSame( 'imported', $result );

		$strains = ADC_Strains::get_all( array( 'active_only' => false ) );
		$created = null;
		foreach ( $strains as $s ) {
			if ( 'Percent Header Strain' === $s['name'] ) {
				$created = $s;
			}
		}
		$this->assertNotNull( $created );
		$this->assertSame( 6200, intval( $created['psilocybin'] ) );
	}

	/**
	 * Sheets importer: thousands separator survives, and edible package
	 * totals still divide down to per-piece.
	 *
	 * @return void
	 */
	public function test_sheets_edible_division_with_separator() {
		$method = new ReflectionMethod( 'ADC_Sheets_Importer', 'process_row' );

		$row        = array(
			'name'       => 'Sep Gummies',
			'psilocybin' => '50,000',
			'pieces'     => '10',
		);
		$column_map = array(
			'name'               => 'name',
			'psilocybin'         => 'psilocybin',
			'pieces_per_package' => 'pieces',
		);

		$result = $method->invoke( null, $row, $column_map, 'edible', 'import' );
		$this->assertSame( 'imported', $result );

		$edibles = ADC_Edibles::get_all( array( 'active_only' => false ) );
		$created = null;
		foreach ( $edibles as $e ) {
			if ( 'Sep Gummies' === $e['name'] ) {
				$created = $e;
			}
		}
		$this->assertNotNull( $created );
		$this->assertSame( 5000, intval( $created['psilocybin'] ), '50,000 mcg package / 10 pieces must store 5000 mcg per piece' );
		$this->assertSame( 10, intval( $created['pieces_per_package'] ) );
	}
}
