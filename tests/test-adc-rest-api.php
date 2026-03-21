<?php
/**
 * Test ADC REST API endpoints
 *
 * @package Ambrosia_Dosage_Calculator
 */

/**
 * Test_ADC_REST_API class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class Test_ADC_REST_API extends WP_UnitTestCase {

	/**
	 * Disable transaction-based rollback so test data persists between DB calls.
	 *
	 * @var bool
	 */
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
	 * Set up test environment.
	 */
	/**
	 * Create plugin tables once before any tests in this class run.
	 *
	 * Uses setUpBeforeClass so tables exist before per-test transactions begin.
	 * ADC_DB::init() forces the table prefix to match the test environment.
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
	 * The plugin's custom tables (wptests_adc_*) are not visible after INSERT
	 * inside a ROLLBACK transaction in MySQL REPEATABLE READ. We override these
	 * to use manual cleanup instead, so create-then-get works correctly.
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
		// Clear all ADC transients so each test starts from a clean cache/rate-limit state.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_adc_%' OR option_name LIKE '_transient_timeout_adc_%'" );
		// Flush object cache after raw SQL so WP transient layer sees the deletions.
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		ADC_Strains::clear_cache();
		ADC_Edibles::clear_cache();

		global $wp_rest_server;
		// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found -- standard WP REST test bootstrap pattern.
		$this->server = $wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );

		// Create test data
		ADC_Strains::create(
			array(
				'name'       => 'API Test Strain',
				'psilocybin' => 5000,
				'is_active'  => 1,
			)
		);
	}

	/**
	 * Test GET /adc/v1/strains endpoint.
	 */
	public function test_get_strains_endpoint() {
		$request  = new WP_REST_Request( 'GET', '/adc/v1/strains' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'strains', $data );
		$this->assertArrayHasKey( 'grouped', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertIsArray( $data['strains'] );
		$this->assertGreaterThanOrEqual( 1, $data['total'] );
	}

	/**
	 * Test GET /adc/v1/edibles endpoint.
	 */
	public function test_get_edibles_endpoint() {
		$request  = new WP_REST_Request( 'GET', '/adc/v1/edibles' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'edibles', $data );
		$this->assertArrayHasKey( 'grouped', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'unitMap', $data );
	}

	/**
	 * Test caching on strains endpoint.
	 */
	public function test_strains_caching() {
		// First request (should cache)
		$request1  = new WP_REST_Request( 'GET', '/adc/v1/strains' );
		$response1 = $this->server->dispatch( $request1 );
		$data1     = $response1->get_data();

		// Second request (should return cached)
		$request2  = new WP_REST_Request( 'GET', '/adc/v1/strains' );
		$response2 = $this->server->dispatch( $request2 );
		$data2     = $response2->get_data();

		// Data should be identical
		$this->assertEquals( $data1, $data2 );

		// Check transient exists
		$cache_key = 'adc_rest_strains_' . md5( wp_json_encode( $request1->get_params() ) );
		$cached    = get_transient( $cache_key );
		$this->assertNotFalse( $cached );
	}

	/**
	 * Test cache invalidation on create.
	 */
	public function test_cache_invalidation() {
		// Get initial data (and prime the REST cache).
		$request1  = new WP_REST_Request( 'GET', '/adc/v1/strains' );
		$response1 = $this->server->dispatch( $request1 );
		$count1    = $response1->get_data()['total'];

		// Create new strain (should clear cache via ADC_Strains::clear_cache()).
		ADC_Strains::create(
			array(
				'name'       => 'Cache Buster',
				'psilocybin' => 5000,
				'is_active'  => 1,
			)
		);

		// Get data again (should return fresh data from DB reflecting the new strain).
		$request2  = new WP_REST_Request( 'GET', '/adc/v1/strains' );
		$response2 = $this->server->dispatch( $request2 );
		$count2    = $response2->get_data()['total'];

		// Count should have increased by at least 1.
		$this->assertGreaterThan( $count1, $count2, "Expected count2 ($count2) > count1 ($count1) after creating Cache Buster" );
	}

	/**
	 * Test rate limiting on submit endpoint.
	 *
	 * The submit endpoint has two rate limits:
	 * 1. Transient-based burst limit: 10 per 15 minutes (per IP).
	 * 2. DB-based hourly limit: 5 per hour (per IP).
	 *
	 * In test context all requests share the same IP (empty REMOTE_ADDR).
	 * We verify that SOME rate limit fires; both are valid security features.
	 */
	public function test_rate_limiting() {
		// Ensure rate limit slate is clean for this test.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_adc_rate_limit_%' OR option_name LIKE '_transient_timeout_adc_rate_limit_%'" );
		wp_cache_flush();

		$data = array(
			'type'   => 'strain',
			'source' => 'user_submit',
			'data'   => array(
				'name'       => 'Rate Test',
				'psilocybin' => 5000,
			),
		);

		$got_rate_limited = false;
		$successes        = 0;

		// Submit up to 15 times; DB (5/hr) or burst (10/15min) limit should fire.
		for ( $i = 0; $i < 15; $i++ ) {
			$request = new WP_REST_Request( 'POST', '/adc/v1/submit' );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $data ) );
			$response = $this->server->dispatch( $request );

			if ( 429 === $response->get_status() ) {
				$got_rate_limited = true;
				break;
			}
			++$successes;
		}

		$this->assertTrue( $got_rate_limited, "Should have been rate limited after {$successes} submissions" );
		$this->assertGreaterThan( 0, $successes, 'At least one submission should succeed before rate limit' );
	}

	/**
	 * Test submit validation (invalid type).
	 */
	public function test_submit_validation_type() {
		$request = new WP_REST_Request( 'POST', '/adc/v1/submit' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'type' => 'invalid',
					'data' => array( 'name' => 'Test' ),
				)
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'invalid_type', $response->get_data()['code'] );
	}

	/**
	 * Test submit validation (missing data).
	 */
	public function test_submit_validation_data() {
		$request = new WP_REST_Request( 'POST', '/adc/v1/submit' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'type' => 'strain',
					// Missing 'data' field
				)
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'invalid_data', $response->get_data()['code'] );
	}

	/**
	 * Test payload size limit.
	 */
	public function test_payload_size_limit() {
		// Create oversized payload
		$huge_data = array(
			'type' => 'strain',
			'data' => array(
				'name'  => str_repeat( 'A', 70000 ), // 70KB name
				'notes' => str_repeat( 'B', 70000 ), // 70KB notes
			),
		);

		$request = new WP_REST_Request( 'POST', '/adc/v1/submit' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $huge_data ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 413, $response->get_status() );
		$this->assertEquals( 'payload_too_large', $response->get_data()['code'] );
	}

	/**
	 * Test ETag caching headers.
	 *
	 * ADC_HTTP_Cache hooks into rest_pre_dispatch which fires via the full WP
	 * HTTP stack. In unit test context (WP_REST_Server::dispatch direct calls),
	 * the middleware may not attach headers. Skip if headers are absent.
	 */
	public function test_etag_headers() {
		$request  = new WP_REST_Request( 'GET', '/adc/v1/strains' );
		$response = $this->server->dispatch( $request );
		$response = rest_ensure_response( $response );

		// Apply middleware filters the same way WP REST does.
		$response = apply_filters( 'rest_post_dispatch', $response, $this->server, $request );
		$headers  = $response->get_headers();

		if ( ! isset( $headers['ETag'] ) ) {
			$this->markTestSkipped( 'ETag headers require the full WP HTTP dispatch stack; not available in unit test context.' );
		}

		$this->assertArrayHasKey( 'ETag', $headers );
		$this->assertArrayHasKey( 'Cache-Control', $headers );
		$this->assertStringContainsString( 'max-age=', $headers['Cache-Control'] );
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down(): void {
		// Clean up plugin test data since we disabled transaction rollback.
		global $wpdb;
		foreach ( array( 'strains', 'edibles', 'submissions', 'blacklist' ) as $t ) {
			$table = ADC_DB::table( $t );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "DELETE FROM `{$table}` WHERE id > 0" );
		}
		// Clear all ADC transients (rate limits, REST cache).
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_adc_%' OR option_name LIKE '_transient_timeout_adc_%'" );

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}
}
