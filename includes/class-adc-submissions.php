<?php
/**
 * Submissions management - Version 2.3 (with blacklist support)
 *
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_Submissions class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_Submissions {

	/**
	 * Get all submissions
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = ADC_DB::table( 'submissions' );

		$defaults = array(
			'status'  => null,
			'type'    => null,
			'source'  => null,
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 100,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( $args['type'] ) {
			$where[]  = 'type = %s';
			$values[] = $args['type'];
		}

		if ( $args['source'] ) {
			$where[]  = 'source = %s';
			$values[] = $args['source'];
		}

		$where_sql = implode( ' AND ', $where );

		// Whitelist ORDER BY columns and direction; esc_sql() is for values, not identifiers (BUG-006).
		$allowed_orderby = array( 'created_at', 'updated_at', 'id', 'type', 'source', 'status', 'submitter_name', 'ip_address' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$order_sql       = $orderby . ' ' . $order;

		$sql = "SELECT * FROM $table WHERE $where_sql ORDER BY $order_sql";

		if ( $args['limit'] > 0 ) {
			$sql .= sprintf( ' LIMIT %d OFFSET %d', intval( $args['limit'] ), intval( $args['offset'] ) );
		}

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get submission by ID
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = ADC_DB::table( 'submissions' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d",
				$id
			),
			ARRAY_A
		);
	}

	/**
	 * Count submissions by status
	 */
	public static function count_by_status() {
		global $wpdb;
		$table = ADC_DB::table( 'submissions' );

		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM $table GROUP BY status",
			ARRAY_A
		);

		$counts = array(
			'pending'  => 0,
			'approved' => 0,
			'rejected' => 0,
			'total'    => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row['status'] ] = intval( $row['count'] );
			$counts['total']         += intval( $row['count'] );
		}

		return $counts;
	}

	/**
	 * Create a new submission
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = ADC_DB::table( 'submissions' );

		$defaults = array(
			'type'            => 'strain',
			'source'          => 'user_submit',
			'data'            => array(),
			'submitter_name'  => '',
			'submitter_email' => '',
			'submitter_notes' => '',
			'testing_lab'     => '',
			'status'          => 'pending',
			'ip_address'      => '',
			'user_agent'      => '',
		);

		$data = wp_parse_args( $data, $defaults );

		// Check blacklist
		if ( ! empty( $data['ip_address'] ) ) {
			if ( self::is_blacklisted( 'ip', $data['ip_address'] ) ) {
				return new WP_Error( 'blacklisted', 'Submissions from this IP address are not allowed.' );
			}
		}

		if ( ! empty( $data['submitter_email'] ) ) {
			if ( self::is_blacklisted( 'email', $data['submitter_email'] ) ) {
				return new WP_Error( 'blacklisted', 'Submissions from this email address are not allowed.' );
			}
		}

		// Sanitize
		$insert_data = array(
			'type'            => in_array( $data['type'], array( 'strain', 'edible' ), true ) ? $data['type'] : 'strain',
			'source'          => in_array( $data['source'], array( 'user_submit', 'qr_scan', 'unknown' ), true ) ? $data['source'] : 'user_submit',
			'data'            => is_array( $data['data'] ) ? wp_json_encode( $data['data'] ) : $data['data'],
			'submitter_name'  => sanitize_text_field( $data['submitter_name'] ),
			'submitter_email' => sanitize_email( $data['submitter_email'] ),
			'submitter_notes' => sanitize_textarea_field( $data['submitter_notes'] ),
			'testing_lab'     => sanitize_text_field( $data['testing_lab'] ),
			'status'          => 'pending',
			'ip_address'      => sanitize_text_field( $data['ip_address'] ),
			'user_agent'      => sanitize_text_field( substr( $data['user_agent'] ?? '', 0, 500 ) ),
		);

		$result = $wpdb->insert( $table, $insert_data );

		if ( false === $result ) {
			error_log( '[ADC] Submission create failed: ' . $wpdb->last_error );
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		$id = $wpdb->insert_id;

		// Send notification email if configured
		self::maybe_send_notification( $id, $insert_data );

		return $id;
	}

	/**
	 * Approve a submission and move to system
	 */
	public static function approve( $id, $admin_notes = '' ) {
		global $wpdb;
		$table = ADC_DB::table( 'submissions' );

		$submission = self::get( $id );
		if ( ! $submission ) {
			return new WP_Error( 'not_found', 'Submission not found' );
		}

		if ( 'pending' !== $submission['status'] ) {
			return new WP_Error( 'invalid_status', 'Submission is not pending' );
		}

		// Parse the data
		$data = json_decode( $submission['data'], true );
		if ( ! $data ) {
			return new WP_Error( 'invalid_data', 'Could not parse submission data' );
		}

		// Mark as community submission
		$data['category'] = 'community';
		$data['notes']    = sprintf(
			"User submitted by %s (%s)\n%s",
			$submission['submitter_name'] ?: 'Anonymous',
			$submission['submitter_email'] ?: 'No email',
			$submission['submitter_notes'] ?: ''
		);

		// Create the strain or edible
		if ( 'strain' === $submission['type'] ) {
			$result = ADC_Strains::create( $data );
		} else {
			$result = ADC_Edibles::create( $data );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update submission status
		$wpdb->update(
			$table,
			array(
				'status'      => 'approved',
				'admin_notes' => sanitize_textarea_field( $admin_notes ),
				'reviewed_by' => get_current_user_id(),
				'reviewed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		return $result; // Returns the new strain/edible ID
	}

	/**
	 * Reject a submission
	 */
	public static function reject( $id, $admin_notes = '' ) {
		global $wpdb;
		$table = ADC_DB::table( 'submissions' );

		$submission = self::get( $id );
		if ( ! $submission ) {
			return new WP_Error( 'not_found', 'Submission not found' );
		}

		$result = $wpdb->update(
			$table,
			array(
				'status'      => 'rejected',
				'admin_notes' => sanitize_textarea_field( $admin_notes ),
				'reviewed_by' => get_current_user_id(),
				'reviewed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		return false !== $result;
	}

	/**
	 * Delete a submission
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = ADC_DB::table( 'submissions' );

		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Bulk approve submissions
	 */
	public static function bulk_approve( $ids ) {
		$results = array(
			'success' => 0,
			'failed'  => 0,
		);

		foreach ( $ids as $id ) {
			$result = self::approve( intval( $id ) );
			if ( is_wp_error( $result ) ) {
				++$results['failed'];
			} else {
				++$results['success'];
			}
		}

		return $results;
	}

	/**
	 * Bulk reject submissions
	 */
	public static function bulk_reject( $ids ) {
		$results = array(
			'success' => 0,
			'failed'  => 0,
		);

		foreach ( $ids as $id ) {
			$result = self::reject( intval( $id ) );
			if ( $result ) {
				++$results['success'];
			} else {
				++$results['failed'];
			}
		}

		return $results;
	}

	/**
	 * Bulk delete submissions
	 */
	public static function bulk_delete( $ids ) {
		$results = array(
			'success' => 0,
			'failed'  => 0,
		);

		foreach ( $ids as $id ) {
			$result = self::delete( intval( $id ) );
			if ( $result ) {
				++$results['success'];
			} else {
				++$results['failed'];
			}
		}

		return $results;
	}

	// =========================================================================
	// BLACKLIST METHODS
	// =========================================================================

	/**
	 * Check if IP or email is blacklisted
	 */
	public static function is_blacklisted( $type, $value ) {
		global $wpdb;
		$table = ADC_DB::table( 'blacklist' );

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
			return false;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE type = %s AND value = %s AND is_active = 1",
				$type,
				strtolower( trim( $value ) )
			)
		);

		return intval( $count ) > 0;
	}

	/**
	 * Add to blacklist
	 */
	public static function add_to_blacklist( $type, $value, $reason = '' ) {
		global $wpdb;
		$table = ADC_DB::table( 'blacklist' );

		$result = $wpdb->insert(
			$table,
			array(
				'type'       => $type,
				'value'      => strtolower( trim( $value ) ),
				'reason'     => sanitize_textarea_field( $reason ),
				'created_by' => get_current_user_id(),
				'is_active'  => 1,
			)
		);

		return false !== $result ? $wpdb->insert_id : false;
	}

	/**
	 * Remove from blacklist
	 */
	public static function remove_from_blacklist( $id ) {
		global $wpdb;
		$table = ADC_DB::table( 'blacklist' );

		$result = $wpdb->delete( $table, array( 'id' => intval( $id ) ) );

		return false !== $result;
	}

	/**
	 * Get all blacklist entries
	 */
	public static function get_blacklist( $type = null ) {
		global $wpdb;
		$table = ADC_DB::table( 'blacklist' );

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
			return array();
		}

		if ( $type ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE type = %s ORDER BY created_at DESC",
					$type
				),
				ARRAY_A
			);
		} else {
			$results = $wpdb->get_results(
				"SELECT * FROM $table ORDER BY type, created_at DESC",
				ARRAY_A
			);
		}

		return $results ?: array();
	}

	/**
	 * Block submitter (add IP and/or email to blacklist)
	 */
	public static function block_submitter( $submission_id, $block_ip = true, $block_email = true, $reason = '' ) {
		$submission = self::get( $submission_id );
		if ( ! $submission ) {
			return false;
		}

		$blocked = array();

		if ( $block_ip && ! empty( $submission['ip_address'] ) ) {
			$result = self::add_to_blacklist( 'ip', $submission['ip_address'], $reason );
			if ( $result ) {
				$blocked[] = 'ip';
			}
		}

		if ( $block_email && ! empty( $submission['submitter_email'] ) ) {
			$result = self::add_to_blacklist( 'email', $submission['submitter_email'], $reason );
			if ( $result ) {
				$blocked[] = 'email';
			}
		}

		return $blocked;
	}

	/**
	 * Send notification email for new submission
	 */
	private static function maybe_send_notification( $id, $data ) {
		$email = ADC_DB::get_setting( 'submission_notification_email', '' );

		if ( empty( $email ) || ! is_email( $email ) ) {
			return;
		}

		$parsed_data = json_decode( $data['data'], true );
		$name        = $parsed_data['name'] ?? 'Unknown';

		$subject = sprintf( '[Dosage Calculator] New %s submission: %s', ucfirst( $data['type'] ), $name );

		$message = sprintf(
			"A new %s has been submitted for review.\n\n" .
			"Name: %s\n" .
			"Submitted by: %s (%s)\n" .
			"IP Address: %s\n\n" .
			"Notes: %s\n\n" .
			'Review at: %s',
			$data['type'],
			$name,
			$data['submitter_name'] ?: 'Anonymous',
			$data['submitter_email'] ?: 'No email',
			$data['ip_address'],
			$data['submitter_notes'] ?: 'None',
			admin_url( 'admin.php?page=dosage-calculator-submissions' )
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Format submission for API response
	 */
	public static function format_for_api( $submission ) {
		return array(
			'id'             => intval( $submission['id'] ),
			'type'           => $submission['type'],
			'source'         => $submission['source'],
			'data'           => json_decode( $submission['data'], true ),
			'submitterName'  => $submission['submitter_name'] ?? '',
			'submitterEmail' => $submission['submitter_email'],
			'submitterNotes' => $submission['submitter_notes'] ?? '',
			'testingLab'     => $submission['testing_lab'],
			'status'         => $submission['status'],
			'adminNotes'     => $submission['admin_notes'],
			'createdAt'      => $submission['created_at'],
			'reviewedAt'     => $submission['reviewed_at'],
			'ipAddress'      => $submission['ip_address'],
		);
	}
}
