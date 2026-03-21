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
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();

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
		// Get initial data and cache
		$request1  = new WP_REST_Request( 'GET', '/adc/v1/strains' );
		$response1 = $this->server->dispatch( $request1 );
		$count1    = $response1->get_data()['total'];

		// Create new strain (should clear cache)
		ADC_Strains::create(
			array(
				'name'       => 'Cache Buster',
				'psilocybin' => 5000,
				'is_active'  => 1,
			)
		);

		// Get data again (cache should be invalidated)
		$request2  = new WP_REST_Request( 'GET', '/adc/v1/strains' );
		$response2 = $this->server->dispatch( $request2 );
		$count2    = $response2->get_data()['total'];

		// Count should have increased
		$this->assertEquals( $count1 + 1, $count2 );
	}

	/**
	 * Test rate limiting on submit endpoint.
	 */
	public function test_rate_limiting() {
		$data = array(
			'type'   => 'strain',
			'source' => 'user_submit',
			'data'   => array(
				'name'       => 'Rate Test',
				'psilocybin' => 5000,
			),
		);

		// Submit 11 times rapidly (should trigger rate limit on 11th)
		for ( $i = 0; $i < 11; $i++ ) {
			$request = new WP_REST_Request( 'POST', '/adc/v1/submit' );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $data ) );

			$response = $this->server->dispatch( $request );

			if ( $i < 10 ) {
				// First 10 should succeed
				$this->assertNotEquals( 429, $response->get_status() );
			} else {
				// 11th should be rate limited
				$this->assertEquals( 429, $response->get_status() );
			}
		}
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
	 */
	public function test_etag_headers() {
		$request  = new WP_REST_Request( 'GET', '/adc/v1/strains' );
		$response = $this->server->dispatch( $request );

		$headers = $response->get_headers();

		$this->assertArrayHasKey( 'ETag', $headers );
		$this->assertArrayHasKey( 'Cache-Control', $headers );
		$this->assertStringContainsString( 'max-age=', $headers['Cache-Control'] );
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down(): void {
		parent::tear_down();

		global $wp_rest_server;
		$wp_rest_server = null;

		// Clear rate limit transients
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_adc_rate_limit_%'" );
	}
}
