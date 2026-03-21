<?php
/**
 * Test ADC_Strains CRUD operations.
 *
 * @package Ambrosia_Dosage_Calculator
 */

/**
 * Strains test case.
 */
class Test_ADC_Strains extends WP_UnitTestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		// Ensure database tables exist.
		if ( class_exists( 'ADC_Activator' ) ) {
			ADC_Activator::activate();
		}
	}

	/**
	 * Test creating a strain.
	 *
	 * @return void
	 */
	public function test_create_strain(): void {
		$data = array(
			'name'       => 'Test Golden Teacher',
			'psilocybin' => 5000,
			'psilocin'   => 500,
			'category'   => 'standard-potency',
		);

		$id = ADC_Strains::create( $data );

		$this->assertNotWPError( $id );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test retrieving a strain.
	 *
	 * @return void
	 */
	public function test_get_strain(): void {
		$data = array(
			'name'       => 'Test Penis Envy',
			'psilocybin' => 8000,
			'category'   => 'high-potency',
		);

		$id     = ADC_Strains::create( $data );
		$strain = ADC_Strains::get( $id );

		$this->assertIsArray( $strain );
		$this->assertSame( 'Test Penis Envy', $strain['name'] );
		$this->assertEquals( 8000, $strain['psilocybin'] );
		$this->assertSame( 'high-potency', $strain['category'] );
	}

	/**
	 * Test updating a strain.
	 *
	 * @return void
	 */
	public function test_update_strain(): void {
		$data = array(
			'name'       => 'Test Original',
			'psilocybin' => 5000,
		);

		$id = ADC_Strains::create( $data );

		$update_data = array(
			'name'       => 'Test Updated',
			'psilocybin' => 6000,
		);

		$result = ADC_Strains::update( $id, $update_data );
		$this->assertNotWPError( $result );
		$this->assertEquals( $id, $result );

		$strain = ADC_Strains::get( $id );
		$this->assertSame( 'Test Updated', $strain['name'] );
		$this->assertEquals( 6000, $strain['psilocybin'] );
	}

	/**
	 * Test deleting a strain.
	 *
	 * @return void
	 */
	public function test_delete_strain(): void {
		$data = array(
			'name'       => 'Test To Delete',
			'psilocybin' => 5000,
		);

		$id     = ADC_Strains::create( $data );
		$result = ADC_Strains::delete( $id );

		$this->assertNotWPError( $result );
		$this->assertEquals( $id, $result );

		$strain = ADC_Strains::get( $id );
		$this->assertNull( $strain );
	}

	/**
	 * Test input sanitization prevents XSS.
	 *
	 * @return void
	 */
	public function test_sanitization(): void {
		$data = array(
			'name'       => '<script>alert("xss")</script>Test Strain',
			'psilocybin' => 'not-a-number',
			'category'   => '<b>evil</b>',
		);

		$id     = ADC_Strains::create( $data );
		$strain = ADC_Strains::get( $id );

		// Name should be sanitized (no script tags).
		$this->assertStringNotContainsString( '<script>', $strain['name'] );

		// Invalid number should be converted to 0.
		$this->assertEquals( 0, $strain['psilocybin'] );

		// Category should be sanitized.
		$this->assertStringNotContainsString( '<b>', $strain['category'] );
	}

	/**
	 * Test short code auto-generation.
	 *
	 * @return void
	 */
	public function test_short_code_generation(): void {
		$data = array(
			'name'       => 'Test Unique Strain',
			'psilocybin' => 5000,
		);

		$id     = ADC_Strains::create( $data );
		$strain = ADC_Strains::get( $id );

		$this->assertNotEmpty( $strain['short_code'] );
		$this->assertIsString( $strain['short_code'] );
		$this->assertMatchesRegularExpression( '/^ZD-[A-Z0-9]+-\d{3}$/', $strain['short_code'] );
	}

	/**
	 * Test duplicate names get unique short codes.
	 *
	 * @return void
	 */
	public function test_duplicate_name_unique_codes(): void {
		$data = array(
			'name'       => 'Duplicate Test',
			'psilocybin' => 5000,
		);

		$id1 = ADC_Strains::create( $data );
		$id2 = ADC_Strains::create( $data );

		$this->assertNotEquals( $id1, $id2 );

		$strain1 = ADC_Strains::get( $id1 );
		$strain2 = ADC_Strains::get( $id2 );

		// Short codes should be different.
		$this->assertNotEquals( $strain1['short_code'], $strain2['short_code'] );
	}

	/**
	 * Test retrieving all strains.
	 *
	 * @return void
	 */
	public function test_get_all_strains(): void {
		ADC_Strains::create(
			array(
				'name'       => 'Strain A',
				'psilocybin' => 5000,
			)
		);
		ADC_Strains::create(
			array(
				'name'       => 'Strain B',
				'psilocybin' => 6000,
			)
		);
		ADC_Strains::create(
			array(
				'name'       => 'Strain C',
				'psilocybin' => 7000,
			)
		);

		$strains = ADC_Strains::get_all( array( 'use_cache' => false ) );

		$this->assertIsArray( $strains );
		$this->assertGreaterThanOrEqual( 3, count( $strains ) );
	}

	/**
	 * Test filtering strains by category.
	 *
	 * @return void
	 */
	public function test_filter_by_category(): void {
		ADC_Strains::create(
			array(
				'name'       => 'Cubensis Test',
				'category'   => 'standard-potency',
				'psilocybin' => 5000,
			)
		);
		ADC_Strains::create(
			array(
				'name'       => 'High Test',
				'category'   => 'high-potency',
				'psilocybin' => 10000,
			)
		);

		$standard = ADC_Strains::get_all(
			array(
				'category'  => 'standard-potency',
				'use_cache' => false,
			)
		);

		$this->assertGreaterThanOrEqual( 1, count( $standard ) );

		foreach ( $standard as $strain ) {
			$this->assertSame( 'standard-potency', $strain['category'] );
		}
	}

	/**
	 * Test potency range filter.
	 *
	 * @return void
	 */
	public function test_potency_filter(): void {
		ADC_Strains::create(
			array(
				'name'       => 'Low Potency',
				'psilocybin' => 3000,
				'psilocin'   => 0,
			)
		);
		ADC_Strains::create(
			array(
				'name'       => 'High Potency',
				'psilocybin' => 15000,
				'psilocin'   => 5000,
			)
		);

		$high = ADC_Strains::get_all(
			array(
				'min_potency' => 10000,
				'use_cache'   => false,
			)
		);

		$this->assertGreaterThanOrEqual( 1, count( $high ) );
		foreach ( $high as $strain ) {
			$total = intval( $strain['psilocybin'] ) + intval( $strain['psilocin'] );
			$this->assertGreaterThanOrEqual( 10000, $total );
		}
	}

	/**
	 * Test search filter.
	 *
	 * @return void
	 */
	public function test_search_filter(): void {
		ADC_Strains::create(
			array(
				'name'       => 'Unique Search Target XYZ',
				'psilocybin' => 5000,
			)
		);

		$results = ADC_Strains::get_all(
			array(
				'search'    => 'Unique Search Target',
				'use_cache' => false,
			)
		);

		$this->assertGreaterThanOrEqual( 1, count( $results ) );
		$this->assertStringContainsString( 'Unique Search Target', $results[0]['name'] );
	}

	/**
	 * Test API format output.
	 *
	 * @return void
	 */
	public function test_format_for_api(): void {
		$id     = ADC_Strains::create(
			array(
				'name'       => 'API Format Test',
				'psilocybin' => 8000,
				'psilocin'   => 2000,
				'baeocystin' => 500,
			)
		);
		$strain = ADC_Strains::get( $id );
		$api    = ADC_Strains::format_for_api( $strain );

		$this->assertArrayHasKey( 'shortCode', $api );
		$this->assertArrayHasKey( 'compounds', $api );
		$this->assertArrayHasKey( 'activeMcgPerG', $api );
		$this->assertArrayHasKey( 'totalMcgPerG', $api );

		// Active = psilocybin + psilocin.
		$this->assertEquals( 10000, $api['activeMcgPerG'] );

		// Total = all compounds.
		$this->assertEquals( 10500, $api['totalMcgPerG'] );
	}

	/**
	 * Test creating strain with empty name fails validation.
	 *
	 * @return void
	 */
	public function test_empty_name_fails(): void {
		$result = ADC_Strains::create(
			array(
				'name'       => '',
				'psilocybin' => 5000,
			)
		);
		$this->assertWPError( $result );
	}

	/**
	 * Test get_by_code lookup.
	 *
	 * @return void
	 */
	public function test_get_by_code(): void {
		$id     = ADC_Strains::create(
			array(
				'name'       => 'Code Lookup Test',
				'psilocybin' => 5000,
			)
		);
		$strain = ADC_Strains::get( $id );
		$code   = $strain['short_code'];

		$found = ADC_Strains::get_by_code( $code );
		$this->assertIsArray( $found );
		$this->assertEquals( $id, $found['id'] );
	}

	/**
	 * Test update nonexistent strain returns error.
	 *
	 * @return void
	 */
	public function test_update_nonexistent(): void {
		$result = ADC_Strains::update( 999999, array( 'name' => 'Ghost' ) );
		$this->assertWPError( $result );
	}

	/**
	 * Clean up after tests.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		parent::tear_down();
	}
}
