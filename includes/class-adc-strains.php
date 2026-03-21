<?php
/**
 * Strains CRUD operations - Performance Optimized
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADC_Strains {

	/** Cache key prefix */
	const CACHE_PREFIX      = 'adc_strains_';
	const CACHE_VERSION_KEY = 'adc_strains_cache_v';
	const CACHE_EXPIRY      = 3600; // 1 hour

	/** Get versioned cache prefix (works with external object caches like Redis) */
	private static function cache_prefix() {
		$version = (int) get_option( self::CACHE_VERSION_KEY, 1 );
		return self::CACHE_PREFIX . $version . '_';
	}

	/**
	 * Get all strains (with caching for common queries)
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = ADC_DB::table( 'strains' );

		$defaults = array(
			'active_only' => true,
			'category'    => null,
			'search'      => null,
			'min_potency' => null,
			'max_potency' => null,
			'orderby'     => 'name',
			'order'       => 'ASC',
			'limit'       => 0,
			'use_cache'   => true,
		);

		$args = wp_parse_args( $args, $defaults );

		// Allowlist orderby/order to prevent accidental SQL interpolation if usage changes.
		$allowed_orderby = array( 'name', 'psilocybin', 'psilocin', 'sort_order', 'created_at', 'id' );
		if ( ! in_array( $args['orderby'], $allowed_orderby, true ) ) {
			$args['orderby'] = 'name';
		}
		$args['order'] = ( 'DESC' === strtoupper( $args['order'] ) ) ? 'DESC' : 'ASC';

		// Generate deterministic cache key from query params (versioned for object cache compat)
		$cache_key = self::cache_prefix() . 'all_'
			. ( $args['active_only'] ? '1' : '0' ) . '_'
			. ( $args['category'] ?: '-' ) . '_'
			. ( $args['search'] ?: '-' ) . '_'
			. ( null !== $args['min_potency'] ? $args['min_potency'] : '-' ) . '_'
			. ( null !== $args['max_potency'] ? $args['max_potency'] : '-' );
		// Transient keys max 172 chars; truncate if needed
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

		if ( $args['category'] ) {
			$where[]  = 'category = %s';
			$values[] = $args['category'];
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

		// Prefix where clauses with table alias
		$where     = array_map(
			function ( $w ) {
				$w = str_replace( 'is_active =', 's.is_active =', $w );
				$w = str_replace( 'category =', 's.category =', $w );
				$w = str_replace( 'name LIKE', 's.name LIKE', $w );
				$w = str_replace( '(psilocybin', '(s.psilocybin', $w );
				$w = str_replace( 'psilocin)', 's.psilocin)', $w );
				return $w;
			},
			$where
		);
		$where_sql = implode( ' AND ', $where );
		$cat_table = ADC_DB::table( 'categories' );

		// Join categories to get sort_order (used by PHP usort below)
		// Note: SQL ORDER BY is redundant with the PHP usort but kept as a fallback
		$sql = "SELECT s.*, COALESCE(c.sort_order, 999) as cat_sort_order 
                FROM $table s 
                LEFT JOIN $cat_table c ON s.category = c.slug 
                WHERE $where_sql";

		if ( $args['limit'] > 0 ) {
			$sql .= ' LIMIT ' . intval( $args['limit'] );
		}

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Smart sort: category sort_order first, then base name alpha, then mcg ascending
		usort(
			$results,
			function ( $a, $b ) {
				// Category order first
				$catA = (int) ( $a['cat_sort_order'] ?? 999 );
				$catB = (int) ( $b['cat_sort_order'] ?? 999 );
				if ( $catB !== $catA ) {
					return $catA - $catB;
				}

				// Then base name + mcg
				$parse              = function ( $name ) {
					if ( preg_match( '/^(.+?)\s*\(\s*([\d,]+)\s*mcg\s*\)\s*$/', $name, $m ) ) {
						return array( strtolower( trim( $m[1] ) ), (int) str_replace( ',', '', $m[2] ) );
					}
					return array( strtolower( trim( $name ) ), 0 );
				};
				list($nameA, $mcgA) = $parse( $a['name'] );
				list($nameB, $mcgB) = $parse( $b['name'] );
				$cmp                = strcmp( $nameA, $nameB );
				return 0 !== $cmp ? $cmp : ( $mcgA - $mcgB );
			}
		);

		// Cache the results
		if ( $args['use_cache'] && $args['active_only'] && 0 === $args['limit'] ) {
			set_transient( $cache_key, $results, self::CACHE_EXPIRY );
		}

		return $results;
	}

	/**
	 * Clear strains cache (call after create/update/delete)
	 */
	/**
	 * Invalidate strains cache by bumping the version key.
	 * Works with external object caches (Redis/Memcached) unlike direct SQL DELETE.
	 */
	public static function clear_cache() {
		$version = (int) get_option( self::CACHE_VERSION_KEY, 1 );
		update_option( self::CACHE_VERSION_KEY, $version + 1, true );
		// Also clear the categories cache since strain counts may change
		delete_transient( 'adc_categories_with_counts' );
		// Clear REST API transient caches (pattern-based deletion)
		self::clear_rest_cache();
	}

	/**
	 * Clear all REST API transient caches for strains.
	 *
	 * @since 2.19.0
	 */
	private static function clear_rest_cache() {
		global $wpdb;
		// Delete all transients matching 'adc_rest_strains_*' pattern
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_adc_rest_strains_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_adc_rest_strains_%'" );
	}

	/**
	 * Get strain by ID
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = ADC_DB::table( 'strains' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d",
				$id
			),
			ARRAY_A
		);
	}

	/**
	 * Get strain by short code
	 */
	public static function get_by_code( $short_code ) {
		global $wpdb;
		$table = ADC_DB::table( 'strains' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE short_code = %s",
				$short_code
			),
			ARRAY_A
		);
	}

	/**
	 * Create a new strain
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = ADC_DB::table( 'strains' );

		$defaults = array(
			'short_code'      => '',
			'name'            => '',
			'batch_number'    => '',
			'category'        => 'uncategorized',
			'psilocybin'      => 0,
			'psilocin'        => 0,
			'norpsilocin'     => 0,
			'baeocystin'      => 0,
			'norbaeocystin'   => 0,
			'aeruginascin'    => 0,
			'extra_compounds' => null,
			'is_active'       => 1,
			'notes'           => '',
			'sort_order'      => 0,
		);

		$data = wp_parse_args( $data, $defaults );

		// Generate short code if not provided
		if ( empty( $data['short_code'] ) ) {
			$data['short_code'] = ADC_DB::generate_short_code( 'strain', $data['name'] );
		}

		// Sanitize
		$data = self::sanitize( $data );

		// Validate
		$validation = self::validate( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Insert
		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			error_log( '[ADC] Strain create failed: ' . $wpdb->last_error );
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		// Clear cache after create
		self::clear_cache();

		return $wpdb->insert_id;
	}

	/**
	 * Update a strain
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = ADC_DB::table( 'strains' );

		// Get existing
		$existing = self::get( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', 'Strain not found' );
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

		// Remove ID from data
		unset( $data['id'] );
		unset( $data['created_at'] );
		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update( $table, $data, array( 'id' => $id ) );

		if ( false === $result ) {
			error_log( '[ADC] Strain update failed (id=' . $id . '): ' . $wpdb->last_error );
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		// Clear cache after update
		self::clear_cache();

		return $id;
	}

	/**
	 * Delete a strain
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = ADC_DB::table( 'strains' );

		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		if ( false === $result ) {
			error_log( '[ADC] Strain delete failed (id=' . $id . '): ' . $wpdb->last_error );
			return new WP_Error( 'db_error', $wpdb->last_error ?: 'Delete failed' );
		}

		self::clear_cache();
		return $id;
	}

	/**
	 * Sanitize strain data
	 */
	private static function sanitize( $data ) {
		return array(
			'short_code'      => sanitize_text_field( $data['short_code'] ),
			'name'            => sanitize_text_field( $data['name'] ),
			'batch_number'    => sanitize_text_field( $data['batch_number'] ),
			'category'        => sanitize_key( $data['category'] ),
			'psilocybin'      => absint( $data['psilocybin'] ),
			'psilocin'        => absint( $data['psilocin'] ),
			'norpsilocin'     => absint( $data['norpsilocin'] ),
			'baeocystin'      => absint( $data['baeocystin'] ),
			'norbaeocystin'   => absint( $data['norbaeocystin'] ),
			'aeruginascin'    => absint( $data['aeruginascin'] ),
			'extra_compounds' => is_array( $data['extra_compounds'] ) ? wp_json_encode( $data['extra_compounds'] ) : $data['extra_compounds'],
			'is_active'       => (int) (bool) $data['is_active'],
			'notes'           => sanitize_textarea_field( $data['notes'] ),
			'sort_order'      => intval( $data['sort_order'] ),
		);
	}

	/**
	 * Validate strain data
	 */
	private static function validate( $data, $exclude_id = null ) {
		global $wpdb;
		$table = ADC_DB::table( 'strains' );

		// Required fields
		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'validation_error', 'Name is required' );
		}

		if ( empty( $data['short_code'] ) ) {
			return new WP_Error( 'validation_error', 'Short code is required' );
		}

		// Check unique short code
		$sql  = "SELECT id FROM $table WHERE short_code = %s";
		$args = array( $data['short_code'] );
		if ( $exclude_id ) {
			$sql   .= ' AND id != %d';
			$args[] = $exclude_id;
		}
		$existing = $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) );

		if ( $existing ) {
			return new WP_Error( 'validation_error', 'Short code already exists' );
		}

		// Validate potency values
		if ( $data['psilocybin'] < 0 || $data['psilocybin'] > 100000 ) {
			return new WP_Error( 'validation_error', 'Invalid psilocybin value' );
		}

		return true;
	}

	/**
	 * Get categories with counts (cached)
	 */
	public static function get_categories_with_counts() {
		$cache_key = 'adc_categories_with_counts';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$strains_table = ADC_DB::table( 'strains' );
		$cat_table     = ADC_DB::table( 'categories' );

		$sql = "SELECT c.*, COUNT(s.id) as strain_count 
                FROM $cat_table c 
                LEFT JOIN $strains_table s ON s.category = c.slug AND s.is_active = 1
                WHERE c.type IN ('strain', 'both') AND c.is_active = 1
                GROUP BY c.id
                ORDER BY c.sort_order ASC";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Cache for 1 hour
		set_transient( $cache_key, $results, self::CACHE_EXPIRY );

		return $results;
	}

	/**
	 * Format strain for API response
	 */
	public static function format_for_api( $strain ) {
		// Active potency: only psilocybin + psilocin are used in dose calculation
		$active_mcg = intval( $strain['psilocybin'] ) + intval( $strain['psilocin'] );

		// Total all compounds (for display)
		$total_mcg = $active_mcg +
					intval( $strain['norpsilocin'] ) +
					intval( $strain['baeocystin'] ) +
					intval( $strain['norbaeocystin'] ) +
					intval( $strain['aeruginascin'] );

		return array(
			'id'            => intval( $strain['id'] ),
			'shortCode'     => $strain['short_code'],
			'name'          => $strain['name'],
			'category'      => $strain['category'],
			'batchNumber'   => $strain['batch_number'],
			'compounds'     => array(
				'psilocybin'    => intval( $strain['psilocybin'] ),
				'psilocin'      => intval( $strain['psilocin'] ),
				'norpsilocin'   => intval( $strain['norpsilocin'] ),
				'baeocystin'    => intval( $strain['baeocystin'] ),
				'norbaeocystin' => intval( $strain['norbaeocystin'] ),
				'aeruginascin'  => intval( $strain['aeruginascin'] ),
			),
			'activeMcgPerG' => $active_mcg, // Used for dose calculations (psilocybin + psilocin)
			'totalMcgPerG'  => $total_mcg,   // All compounds total (for display)
		);
	}
}
