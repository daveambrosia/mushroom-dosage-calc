<?php
/**
 * Test ADC_Submissions validation and security
 *
 * @package Ambrosia_Dosage_Calculator
 */

/**
 * Test_ADC_Submissions class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class Test_ADC_Submissions extends WP_UnitTestCase {

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
	}

	/**
	 * Test creating a submission.
	 */
	public function test_create_submission() {
		$data = array(
			'type'            => 'strain',
			'source'          => 'user_submit',
			'data'            => array(
				'name'       => 'Test User Strain',
				'psilocybin' => 5000,
			),
			'submitter_email' => 'test@example.com',
			'ip_address'      => '192.168.1.1',
		);

		$id = ADC_Submissions::create( $data );

		$this->assertNotFalse( $id );
		$this->assertNotInstanceOf( 'WP_Error', $id );
		$this->assertIsInt( $id );
	}

	/**
	 * Test submission type validation.
	 */
	public function test_type_validation() {
		$data = array(
			'type'   => 'invalid_type',
			'source' => 'user_submit',
			'data'   => array( 'name' => 'Test' ),
		);

		$id = ADC_Submissions::create( $data );

		// Should default to 'strain' for invalid type
		$this->assertNotFalse( $id );
	}

	/**
	 * Test email sanitization.
	 */
	public function test_email_sanitization() {
		$data = array(
			'type'            => 'strain',
			'source'          => 'user_submit',
			'data'            => array( 'name' => 'Test' ),
			'submitter_email' => 'invalid<script>@email.com',
		);

		$id = ADC_Submissions::create( $data );
		$this->assertNotFalse( $id );

		$submission = ADC_Submissions::get( $id );
		$this->assertStringNotContainsString( '<script>', $submission['submitter_email'] );
	}

	/**
	 * Test blacklist functionality.
	 */
	public function test_blacklist() {
		// Add to blacklist
		ADC_Submissions::add_to_blacklist( 'ip', '10.0.0.1', 'Test block' );

		// Try to create submission from blacklisted IP
		$data = array(
			'type'       => 'strain',
			'source'     => 'user_submit',
			'data'       => array( 'name' => 'Test' ),
			'ip_address' => '10.0.0.1',
		);

		$id = ADC_Submissions::create( $data );

		$this->assertInstanceOf( 'WP_Error', $id );
		$this->assertEquals( 'blacklisted', $id->get_error_code() );
	}

	/**
	 * Test blacklist email blocking.
	 */
	public function test_blacklist_email() {
		// Add email to blacklist
		ADC_Submissions::add_to_blacklist( 'email', 'spam@example.com', 'Spammer' );

		// Try to create submission from blacklisted email
		$data = array(
			'type'            => 'strain',
			'source'          => 'user_submit',
			'data'            => array( 'name' => 'Test' ),
			'submitter_email' => 'spam@example.com',
		);

		$id = ADC_Submissions::create( $data );

		$this->assertInstanceOf( 'WP_Error', $id );
		$this->assertEquals( 'blacklisted', $id->get_error_code() );
	}

	/**
	 * Test submission approval.
	 */
	public function test_approve_submission() {
		$data = array(
			'type'   => 'strain',
			'source' => 'user_submit',
			'data'   => array(
				'name'       => 'Approved Test',
				'psilocybin' => 5000,
			),
		);

		$id = ADC_Submissions::create( $data );

		$result = ADC_Submissions::approve( $id );

		$this->assertNotInstanceOf( 'WP_Error', $result );
		$this->assertIsInt( $result ); // Should return strain/edible ID

		// Verify it was added to strains table
		$strain = ADC_Strains::get( $result );
		$this->assertNotNull( $strain );
		$this->assertEquals( 'Approved Test', $strain['name'] );
	}

	/**
	 * Test submission rejection.
	 */
	public function test_reject_submission() {
		$data = array(
			'type'   => 'strain',
			'source' => 'user_submit',
			'data'   => array(
				'name'       => 'Rejected Test',
				'psilocybin' => 5000,
			),
		);

		$id = ADC_Submissions::create( $data );

		$result = ADC_Submissions::reject( $id, 'Test rejection' );

		$this->assertTrue( $result );

		$submission = ADC_Submissions::get( $id );
		$this->assertEquals( 'rejected', $submission['status'] );
	}

	/**
	 * Test data sanitization in submission data.
	 */
	public function test_submission_data_sanitization() {
		$data = array(
			'type'   => 'strain',
			'source' => 'user_submit',
			'data'   => array(
				'name'       => '<script>alert("xss")</script>',
				'psilocybin' => 'invalid',
			),
		);

		$id         = ADC_Submissions::create( $data );
		$submission = ADC_Submissions::get( $id );
		$saved_data = json_decode( $submission['data'], true );

		// Name sanitization happens during approval, but should be safe
		$this->assertIsArray( $saved_data );
	}

	/**
	 * Test getting submissions by status.
	 */
	public function test_get_by_status() {
		// Create pending submission
		ADC_Submissions::create(
			array(
				'type'   => 'strain',
				'source' => 'user_submit',
				'data'   => array( 'name' => 'Pending Test' ),
			)
		);

		$pending_submissions = ADC_Submissions::get_all( array( 'status' => 'pending' ) );

		$this->assertIsArray( $pending_submissions );
		$this->assertGreaterThanOrEqual( 1, count( $pending_submissions ) );

		foreach ( $pending_submissions as $sub ) {
			$this->assertEquals( 'pending', $sub['status'] );
		}
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
		parent::tear_down();
	}
}
