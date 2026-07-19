<?php
/**
 * Test edible export unit conversion (mcg per piece vs mcg per package).
 *
 * The DB stores compound values as mcg per PIECE. The CSV interchange format
 * (matching the Google Sheet) carries mcg per PACKAGE, and the CSV importer
 * divides by pieces_per_package on import. These tests pin the export side to
 * package totals so an export -> re-import round trip leaves stored values
 * unchanged. Regression guard for the bug where both CSV export paths wrote
 * per-piece values, shrinking potency by the piece count on every round trip.
 *
 * @package Ambrosia_Dosage_Calculator
 */

/**
 * Test_ADC_Export_Units class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class Test_ADC_Export_Units extends WP_UnitTestCase {

	/**
	 * Disable transaction rollback so plugin table inserts persist across calls.
	 *
	 * @var bool
	 */
	public static $use_transactions = false;

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Create plugin tables once before any tests in this class run.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		ADC_DB::init();
		if ( class_exists( 'ADC_Activator' ) ) {
			ADC_Activator::activate();
		}
		// Admin classes only load under is_admin(); pull in the tools class
		// directly so the formatter can be tested.
		if ( ! class_exists( 'ADC_Admin_Tools' ) ) {
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-admin-tools.php';
		}
	}

	/**
	 * Disable WP test suite DB transaction wrapping (see Test_ADC_REST_API).
	 *
	 * @return void
	 */
	public function start_transaction(): void {
		// No-op: skip transaction wrapping for plugin tables.
	}

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		ADC_DB::init();
		ADC_DB::invalidate_cache();
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}adc_edibles" );
		ADC_Edibles::clear_cache();

		global $wp_rest_server;
		// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found -- standard WP REST test bootstrap pattern.
		$this->server = $wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Create the canonical test edible: 10 pieces, 5000 mcg psilocybin per piece.
	 *
	 * @return array Created edible row.
	 */
	private function create_ten_piece_edible() {
		$id = ADC_Edibles::create(
			array(
				'name'               => 'Unit Contract Gummies',
				'pieces_per_package' => 10,
				'psilocybin'         => 5000,
				'psilocin'           => 300,
				'is_active'          => 1,
			)
		);
		$this->assertNotWPError( $id );
		$edible = ADC_Edibles::get( intval( $id ) );
		$this->assertIsArray( $edible );
		return $edible;
	}

	/**
	 * Parse CSV text into rows with PHP 8.5-safe explicit arguments.
	 *
	 * @param string $csv Raw CSV body.
	 * @return array Parsed rows.
	 */
	private function parse_csv( $csv ) {
		$rows = array();
		foreach ( array_filter( explode( "\n", trim( $csv ) ) ) as $line ) {
			$rows[] = str_getcsv( $line, ',', '"', '\\' );
		}
		$rows[0][0] = preg_replace( '/^\xEF\xBB\xBF/', '', $rows[0][0] );
		return $rows;
	}

	/**
	 * The DB must store per-piece values exactly as given.
	 *
	 * @return void
	 */
	public function test_db_stores_per_piece() {
		$edible = $this->create_ten_piece_edible();
		$this->assertSame( 5000, intval( $edible['psilocybin'] ) );
		$this->assertSame( 10, intval( $edible['pieces_per_package'] ) );
	}

	/**
	 * REST CSV export must emit package totals (per-piece x pieces).
	 *
	 * @return void
	 */
	public function test_rest_csv_export_emits_package_totals() {
		$this->create_ten_piece_edible();

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$request = new WP_REST_Request( 'GET', '/adc/v1/admin/edibles/export' );
		$request->set_param( 'format', 'csv' );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$rows   = $this->parse_csv( $response->get_data() );
		$header = array_flip( $rows[0] );
		$this->assertArrayHasKey( 'psilocybin', $header );
		$this->assertArrayHasKey( 'pieces_per_package', $header );

		$found = false;
		foreach ( array_slice( $rows, 1 ) as $row ) {
			if ( 'Unit Contract Gummies' === $row[ $header['name'] ] ) {
				$found = true;
				$this->assertSame( '10', $row[ $header['pieces_per_package'] ] );
				$this->assertSame( '50000', $row[ $header['psilocybin'] ], 'CSV psilocybin must be the package total (5000/piece x 10 pieces)' );
				$this->assertSame( '3000', $row[ $header['psilocin'] ], 'CSV psilocin must be the package total' );
			}
		}
		$this->assertTrue( $found, 'Exported CSV must contain the test edible' );
	}

	/**
	 * CSV export -> importer division must round-trip to the stored value.
	 *
	 * Replicates the exact conversion the CSV importer applies on import
	 * (divide by pieces_per_package) against real exported output, so the
	 * two sides can never drift apart silently again.
	 *
	 * @return void
	 */
	public function test_csv_round_trip_preserves_per_piece_value() {
		$this->create_ten_piece_edible();

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$request = new WP_REST_Request( 'GET', '/adc/v1/admin/edibles/export' );
		$request->set_param( 'format', 'csv' );
		$response = $this->server->dispatch( $request );
		$rows     = $this->parse_csv( $response->get_data() );
		$header   = array_flip( $rows[0] );

		foreach ( array_slice( $rows, 1 ) as $row ) {
			if ( 'Unit Contract Gummies' === $row[ $header['name'] ] ) {
				$pieces     = max( 1, intval( $row[ $header['pieces_per_package'] ] ) );
				$reimported = intval( round( $row[ $header['psilocybin'] ] / $pieces ) );
				$this->assertSame( 5000, $reimported, 'Import division must recover the stored per-piece value' );
			}
		}
	}

	/**
	 * Admin tools formatter: CSV mode converts to package totals, JSON mode does not.
	 *
	 * @return void
	 */
	public function test_admin_tools_formatter_units_per_format() {
		$edible = $this->create_ten_piece_edible();

		$method = new ReflectionMethod( 'ADC_Admin_Tools', 'format_edibles_for_export' );

		$csv_rows  = $method->invoke( null, array( $edible ), true );
		$json_rows = $method->invoke( null, array( $edible ), false );

		$this->assertSame( 50000, $csv_rows[0]['psilocybin'], 'CSV formatter must emit package totals' );
		$this->assertSame( 5000, $json_rows[0]['psilocybin'], 'JSON formatter must stay per-piece' );
		$this->assertSame( 10, $csv_rows[0]['pieces_per_package'] );
	}
}
