<?php
/**
 * Edibles CRUD operations - Version 2.0 - Performance Optimized
 *
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_Edibles class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_Edibles {

	/** Cache key prefix */
	const CACHE_PREFIX      = 'adc_edibles_';
	const CACHE_VERSION_KEY = 'adc_edibles_cache_v';
	const CACHE_EXPIRY      = 3600; // 1 hour

	/** Get versioned cache prefix (works with external object caches like Redis) */
	private static function cache_prefix() {
		$version = (int) get_option( self::CACHE_VERSION_KEY, 1 );
		return self::CACHE_PREFIX . $version . '_';
	}

	/**
	 * Get all edibles (with caching for common queries)
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = ADC_DB::table( 'edibles' );

		$defaults = array(
			'active_only'  => true,
			'product_type' => null,
			'brand'        => null,
			'search'       => null,
			'min_potency'  => null,
			'max_potency'  => null,
			'orderby'      => 'name',
			'order'        => 'ASC',
			'limit'        => 0,
			'use_cache'    => true,
		);

		$args = wp_parse_args( $args, $defaults );

		// Generate deterministic cache key from query params (versioned for object cache compat)
		$cache_key = self::cache_prefix() . 'all_'
			. ( $args['active_only'] ? '1' : '0' ) . '_'
			. ( $args['product_type'] ?: '-' ) . '_'
			. ( $args['brand'] ?: '-' ) . '_'
			. ( $args['search'] ?: '-' ) . '_'
			. ( null !== $args['min_potency'] ? $args['min_potency'] : '-' ) . '_'
			. ( null !== $args['max_potency'] ? $args['max_potency'] : '-' );
		if ( strlen( $cache_key ) > 170 ) {
			$cache_key = self::CACHE_PREFIX . 'all_' . md5( $cache_key );
		}

		// Try to get from cache (only for active_only queries with default ordering)
		if ( $args['use_cache'] && $args['active_only'] && 0 === $args['limit'] ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$where  = array( '1=1' );
		$values = array();

		if ( $args['active_only'] ) {
			$where[] = 'is_active = 1';
		}

		if ( $args['product_type'] ) {
			$where[]  = 'product_type = %s';
			$values[] = $args['product_type'];
		}

		if ( $args['brand'] ) {
			$where[]  = 'brand = %s';
			$values[] = $args['brand'];
		}

		if ( $args['search'] ) {
			$where[]  = 'name LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		if ( null !== $args['min_potency'] ) {
			$where[]  = '(psilocybin + psilocin) >= %d';
			$values[] = $args['min_potency'];
		}

		if ( null !== $args['max_potency'] ) {
			$where[]  = '(psilocybin + psilocin) <= %d';
			$values[] = $args['max_potency'];
		}

		$where_sql       = implode( ' AND ', $where );
		$allowed_orderby = array( 'name', 'id', 'psilocybin', 'psilocin', 'sort_order', 'created_at', 'short_code', 'product_type' );
		$allowed_order   = array( 'ASC', 'DESC' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'sort_order';
		$order           = in_array( strtoupper( $args['order'] ), $allowed_order, true ) ? strtoupper( $args['order'] ) : 'ASC';
		$order_sql       = sprintf( '%s %s', $orderby, $order );

		$sql = "SELECT * FROM $table WHERE $where_sql ORDER BY $order_sql";

		if ( $args['limit'] > 0 ) {
			$sql .= ' LIMIT ' . intval( $args['limit'] );
		}

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Smart sort: base name alphabetical, then mcg ascending.
		if ( 'name' === $args['orderby'] ) {
			usort(
				$results,
				function ( $a, $b ) {
					list($name_a, $mcg_a) = ADC_DB::parse_name_for_sort( $a['name'] );
					list($name_b, $mcg_b) = ADC_DB::parse_name_for_sort( $b['name'] );
					$cmp                  = strcmp( $name_a, $name_b );
					return 0 !== $cmp ? $cmp : ( $mcg_a - $mcg_b );
				}
			);
		}

		// Cache the results
		if ( $args['use_cache'] && $args['active_only'] && 0 === $args['limit'] ) {
			set_transient( $cache_key, $results, self::CACHE_EXPIRY );
		}

		return $results;
	}

	/**
	 * Invalidate edibles cache by bumping the version key.
	 * Works with external object caches (Redis/Memcached) unlike direct SQL DELETE.
	 */
	public static function clear_cache() {
		$version = (int) get_option( self::CACHE_VERSION_KEY, 1 );
		update_option( self::CACHE_VERSION_KEY, $version + 1, true );
		// Also clear product types cache since counts may change
		delete_transient( 'adc_product_types' );
		delete_transient( 'adc_product_types_with_counts' );
		// Clear REST API transient caches
		self::clear_rest_cache();
	}

	/**
	 * Clear all REST API transient caches for edibles.
	 *
	 * @since 2.19.0
	 */
	private static function clear_rest_cache() {
		ADC_DB::clear_rest_transients( 'adc_rest_edibles_' );
	}

	/**
	 * Get edible by ID
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = ADC_DB::table( 'edibles' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d",
				$id
			),
			ARRAY_A
		);
	}

	/**
	 * Get edible by short code
	 */
	public static function get_by_code( $short_code ) {
		global $wpdb;
		$table = ADC_DB::table( 'edibles' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE short_code = %s",
				$short_code
			),
			ARRAY_A
		);
	}

	/**
	 * Create a new edible
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = ADC_DB::table( 'edibles' );

		$defaults = array(
			'short_code'         => '',
			'name'               => '',
			'brand'              => '',
			'product_type'       => 'other',
			'batch_number'       => '',
			'pieces_per_package' => 1,
			'total_mg'           => 0,
			'psilocybin'         => 0,
			'psilocin'           => 0,
			'norpsilocin'        => 0,
			'baeocystin'         => 0,
			'norbaeocystin'      => 0,
			'aeruginascin'       => 0,
			'extra_compounds'    => null,
			'image_id'           => null,
			'is_active'          => 1,
			'notes'              => '',
			'sort_order'         => 0,
		);

		$data = wp_parse_args( $data, $defaults );

		// Generate short code if not provided
		if ( empty( $data['short_code'] ) ) {
			$data['short_code'] = ADC_DB::generate_short_code( 'edible', $data['name'] );
		}

		// Sanitize
		$data = self::sanitize( $data );

		// Validate
		$validation = self::validate( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Insert (exclude generated column)
		unset( $data['mg_per_piece'] );
		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			error_log( '[ADC] Edible create failed: ' . $wpdb->last_error );
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		// Clear cache after create
		self::clear_cache();

		return $wpdb->insert_id;
	}

	/**
	 * Update an edible
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = ADC_DB::table( 'edibles' );

		// Get existing
		$existing = self::get( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', 'Edible not found' );
		}

		// Merge with existing
		$data = wp_parse_args( $data, $existing );

		// Sanitize
		$data = self::sanitize( $data );

		// Validate
		$validation = self::validate( $data, $id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Remove ID and generated columns from data
		unset( $data['id'] );
		unset( $data['created_at'] );
		unset( $data['mg_per_piece'] );
		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update( $table, $data, array( 'id' => $id ) );

		if ( false === $result ) {
			error_log( '[ADC] Edible update failed (id=' . $id . '): ' . $wpdb->last_error );
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		// Clear cache after update
		self::clear_cache();

		return $id;
	}

	/**
	 * Delete an edible
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = ADC_DB::table( 'edibles' );

		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		if ( false === $result ) {
			error_log( '[ADC] Edible delete failed (id=' . $id . '): ' . $wpdb->last_error );
			return new WP_Error( 'db_error', $wpdb->last_error ?: 'Delete failed' );
		}

		self::clear_cache();
		return $id;
	}

	/**
	 * Sanitize edible data
	 */
	private static function sanitize( $data ) {
		$sanitized = array(
			'short_code'         => sanitize_text_field( $data['short_code'] ),
			'name'               => sanitize_text_field( $data['name'] ),
			'brand'              => sanitize_text_field( $data['brand'] ),
			'product_type'       => sanitize_title( $data['product_type'] ),
			'batch_number'       => sanitize_text_field( $data['batch_number'] ),
			'pieces_per_package' => max( 1, absint( $data['pieces_per_package'] ) ),
			'total_mg'           => absint( $data['psilocybin'] ) + absint( $data['psilocin'] ) + absint( $data['norpsilocin'] ) + absint( $data['baeocystin'] ) + absint( $data['norbaeocystin'] ) + absint( $data['aeruginascin'] ),
			'psilocybin'         => absint( $data['psilocybin'] ),
			'psilocin'           => absint( $data['psilocin'] ),
			'norpsilocin'        => absint( $data['norpsilocin'] ),
			'baeocystin'         => absint( $data['baeocystin'] ),
			'norbaeocystin'      => absint( $data['norbaeocystin'] ),
			'aeruginascin'       => absint( $data['aeruginascin'] ),
			'extra_compounds'    => is_array( $data['extra_compounds'] ) ? wp_json_encode( $data['extra_compounds'] ) : $data['extra_compounds'],
			'image_id'           => $data['image_id'] ? absint( $data['image_id'] ) : null,
			'is_active'          => (int) (bool) $data['is_active'],
			'notes'              => sanitize_textarea_field( $data['notes'] ),
			'sort_order'         => intval( $data['sort_order'] ),
		);

		return $sanitized;
	}

	/**
	 * Validate edible data
	 */
	private static function validate( $data, $exclude_id = null ) {
		global $wpdb;
		$table = ADC_DB::table( 'edibles' );

		// Required fields
		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'validation_error', 'Name is required' );
		}

		if ( empty( $data['short_code'] ) ) {
			return new WP_Error( 'validation_error', 'Short code is required' );
		}

		if ( empty( $data['product_type'] ) ) {
			return new WP_Error( 'validation_error', 'Product type is required' );
		}

		// Check unique short code
		$sql    = "SELECT id FROM $table WHERE short_code = %s";
		$params = array( $data['short_code'] );

		if ( $exclude_id ) {
			$sql     .= ' AND id != %d';
			$params[] = $exclude_id;
		}

		$existing = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );

		if ( $existing ) {
			return new WP_Error( 'validation_error', 'Short code already exists' );
		}

		// Validate total_mg
		if ( $data['total_mg'] <= 0 ) {
			return new WP_Error( 'validation_error', 'At least one compound value must be greater than 0' );
		}

		return true;
	}

	/**
	 * Get product types with counts (cached)
	 */
	public static function get_product_types_with_counts() {
		$cache_key = 'adc_product_types_with_counts';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$edibles_table = ADC_DB::table( 'edibles' );
		$types_table   = ADC_DB::table( 'product_types' );

		$sql = "SELECT pt.*, COUNT(e.id) as edible_count 
                FROM $types_table pt 
                LEFT JOIN $edibles_table e ON e.product_type = pt.slug AND e.is_active = 1
                WHERE pt.is_active = 1
                GROUP BY pt.id
                ORDER BY pt.sort_order ASC";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Cache for 1 hour
		set_transient( $cache_key, $results, self::CACHE_EXPIRY );

		return $results;
	}

	/**
	 * Get all product types (cached)
	 */
	public static function get_product_types() {
		$cache_key = 'adc_product_types';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = ADC_DB::table( 'product_types' );

		$results = $wpdb->get_results(
			"SELECT id, name, slug, unit_name, sort_order FROM $table WHERE is_active = 1 ORDER BY sort_order ASC",
			ARRAY_A
		);

		// Cache for 1 hour
		set_transient( $cache_key, $results, self::CACHE_EXPIRY );

		return $results;
	}

	/**
	 * Format edible for API response
	 */
	public static function format_for_api( $edible ) {
		// Note: compound values are stored as mcg PER PIECE
		return array(
			'id'               => intval( $edible['id'] ),
			'shortCode'        => $edible['short_code'],
			'name'             => $edible['name'],
			'brand'            => $edible['brand'],
			'productType'      => $edible['product_type'],
			'batchNumber'      => $edible['batch_number'],
			'piecesPerPackage' => intval( $edible['pieces_per_package'] ),
			// Compound values in mcg per piece
			'psilocybin'       => intval( $edible['psilocybin'] ),
			'psilocin'         => intval( $edible['psilocin'] ),
			'norpsilocin'      => intval( $edible['norpsilocin'] ),
			'baeocystin'       => intval( $edible['baeocystin'] ),
			'norbaeocystin'    => intval( $edible['norbaeocystin'] ),
			'aeruginascin'     => intval( $edible['aeruginascin'] ),
			'imageUrl'         => $edible['image_id'] ? wp_get_attachment_url( $edible['image_id'] ) : null,
		);
	}
}
