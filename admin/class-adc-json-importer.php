<?php
/**
 * JSON Importer for backup restoration
 *
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_JSON_Importer class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_JSON_Importer {

	/**
	 * Render the import page
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		$result = null;

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['adc_json_import_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['adc_json_import_nonce'] ) ), 'adc_json_import' ) ) {
				wp_die( 'Security check failed' );
			}
			$result = self::handle_upload();
		}

		?>
		<div class="wrap adc-admin">
			<h1>Import JSON Backup</h1>
			<p>Import data from a JSON file exported from this plugin.</p>
			
			<?php if ( $result ) : ?>
				<?php if ( is_wp_error( $result ) ) : ?>
					<div class="notice notice-error"><p><?php echo esc_html( $result->get_error_message() ); ?></p></div>
				<?php else : ?>
					<?php
					// Check if there were any errors during import
					$has_errors   = ! empty( $result['strain_errors'] ) || ! empty( $result['edible_errors'] );
					$notice_class = $has_errors ? 'notice-warning' : 'notice-success';
					?>
					<div class="notice <?php echo esc_attr( $notice_class ); ?>">
						<p><strong>Import <?php echo $has_errors ? 'completed with errors' : 'complete'; ?>!</strong></p>
						<ul>
							<?php if ( isset( $result['strains'] ) ) : ?>
								<li>Strains: <?php echo intval( $result['strains'] ); ?> imported
								<?php
								if ( ! empty( $result['strains_skipped'] ) ) :
									?>
										, <?php echo intval( $result['strains_skipped'] ); ?> skipped
										<?php
									endif;
								?>
								</li>
							<?php endif; ?>
							<?php if ( isset( $result['edibles'] ) ) : ?>
								<li>Edibles: <?php echo intval( $result['edibles'] ); ?> imported
								<?php
								if ( ! empty( $result['edibles_skipped'] ) ) :
									?>
										, <?php echo intval( $result['edibles_skipped'] ); ?> skipped
										<?php
									endif;
								?>
								</li>
							<?php endif; ?>
							<?php if ( isset( $result['settings'] ) && $result['settings'] ) : ?>
								<li>Settings: updated</li>
							<?php endif; ?>
						</ul>
						
						<?php if ( ! empty( $result['strain_errors'] ) ) : ?>
							<p><strong>Strain Errors:</strong></p>
							<ul style="color: #b32d2e;">
								<?php foreach ( $result['strain_errors'] as $error ) : ?>
									<li><?php echo esc_html( $error ); ?></li>
								<?php endforeach; ?>
								<?php if ( count( $result['strain_errors'] ) >= 10 ) : ?>
									<li><em>...and possibly more errors (showing first 10)</em></li>
								<?php endif; ?>
							</ul>
						<?php endif; ?>
						
						<?php if ( ! empty( $result['edible_errors'] ) ) : ?>
							<p><strong>Edible Errors:</strong></p>
							<ul style="color: #b32d2e;">
								<?php foreach ( $result['edible_errors'] as $error ) : ?>
									<li><?php echo esc_html( $error ); ?></li>
								<?php endforeach; ?>
								<?php if ( count( $result['edible_errors'] ) >= 10 ) : ?>
									<li><em>...and possibly more errors (showing first 10)</em></li>
								<?php endif; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
			
			<div class="adc-card adc-card-narrow">
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'adc_json_import', 'adc_json_import_nonce' ); ?>
					
					<table class="form-table">
						<tr>
							<th><label for="json_file">JSON File</label></th>
							<td>
								<input type="file" name="json_file" id="json_file" accept=".json" required>
								<p class="description">Upload a JSON file exported from the Export page.</p>
							</td>
						</tr>
						<tr>
							<th>Import Options</th>
							<td>
								<label><input type="checkbox" name="import_strains" value="1" checked> Import Strains</label><br>
								<label><input type="checkbox" name="import_edibles" value="1" checked> Import Edibles</label><br>
								<label><input type="checkbox" name="import_settings" value="1"> Import Settings (overwrites current)</label>
								<p class="description">Select what to import from the JSON file.</p>
							</td>
						</tr>
						<tr>
							<th>Duplicate Handling</th>
							<td>
								<select name="duplicate_handling">
									<option value="skip">Skip duplicates (by short code)</option>
									<option value="update">Update existing entries</option>
									<option value="import">Import all (may create duplicates)</option>
								</select>
							</td>
						</tr>
					</table>
					
					<?php submit_button( 'Import JSON' ); ?>
				</form>
			</div>
			
			<div class="adc-card adc-card-narrow adc-mt-20">
				<h2>Supported Formats</h2>
				<p>This importer accepts JSON files in the following formats:</p>
				<ul>
					<li><strong>Full backup</strong> - Contains strains, edibles, and settings</li>
					<li><strong>Strains export</strong> - Array of strain objects</li>
					<li><strong>Edibles export</strong> - Array of edible objects</li>
					<li><strong>Settings export</strong> - Settings object</li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the JSON file upload
	 */
	private static function handle_upload() {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- nonce verified in render_page; file validation below.
		if ( ! isset( $_FILES['json_file'] ) || ! isset( $_FILES['json_file']['error'] ) || UPLOAD_ERR_OK !== $_FILES['json_file']['error'] ) {
			$upload_errors = array(
				UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize directive',
				UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE directive',
				UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
				UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
				UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
				UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
				UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension',
			);
			$error_code    = isset( $_FILES['json_file']['error'] ) ? intval( $_FILES['json_file']['error'] ) : UPLOAD_ERR_NO_FILE;
			$error_msg     = $upload_errors[ $error_code ] ?? 'Unknown upload error';
			return new WP_Error( 'upload_error', 'File upload failed: ' . $error_msg );
		}

		// Security: Validate file extension
		if ( ! isset( $_FILES['json_file']['name'] ) ) {
			return new WP_Error( 'upload_error', 'File name missing.' );
		}
		$filename = sanitize_file_name( wp_unslash( $_FILES['json_file']['name'] ) );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'json' !== $ext ) {
			return new WP_Error( 'invalid_file', 'Only JSON files are allowed.' );
		}

		// Security: Check file size (max 5MB)
		if ( ! isset( $_FILES['json_file']['size'] ) || $_FILES['json_file']['size'] > 5 * 1024 * 1024 ) {
			return new WP_Error( 'file_too_large', 'File size exceeds 5MB limit.' );
		}

		if ( ! isset( $_FILES['json_file']['tmp_name'] ) ) {
			return new WP_Error( 'upload_error', 'Temporary file missing.' );
		}
		$content = file_get_contents( sanitize_text_field( wp_unslash( $_FILES['json_file']['tmp_name'] ) ) );
		if ( false === $content ) {
			return new WP_Error( 'read_error', 'Could not read uploaded file.' );
		}

		$data = json_decode( $content, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'json_error', 'Invalid JSON file: ' . json_last_error_msg() );
		}

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new WP_Error( 'empty_data', 'JSON file contains no importable data.' );
		}

		$result             = array();
		$duplicate_handling = isset( $_POST['duplicate_handling'] ) ? sanitize_text_field( wp_unslash( $_POST['duplicate_handling'] ) ) : 'skip';

		// Detect format
		$strains_data  = isset( $data['strains'] ) ? $data['strains'] : null;
		$edibles_data  = isset( $data['edibles'] ) ? $data['edibles'] : null;
		$settings_data = isset( $data['settings'] ) ? $data['settings'] : null;

		// Check if it's a direct array export
		if ( ! $strains_data && ! $edibles_data && isset( $data[0] ) ) {
			if ( isset( $data[0]['category'] ) ) {
				$strains_data = $data;
			} elseif ( isset( $data[0]['pieces_per_package'] ) || isset( $data[0]['piecesPerPackage'] ) ) {
				$edibles_data = $data;
			}
		}

		// Import strains
		if ( isset( $_POST['import_strains'] ) && ! empty( $strains_data ) ) {
			// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
			$imported = 0;
			$skipped  = 0;
			$errors   = array();

			foreach ( $strains_data as $index => $strain ) {
				if ( empty( $strain['name'] ) ) {
					++$skipped;
					continue;
				}

				$short_code = $strain['short_code'] ?? $strain['shortCode'] ?? '';
				$existing   = ! empty( $short_code ) ? self::find_by_code( $short_code, 'strain' ) : null;

				if ( $existing && 'skip' === $duplicate_handling ) {
					++$skipped;
					continue;
				}

				$strain_data = array(
					'name'          => sanitize_text_field( $strain['name'] ),
					'short_code'    => sanitize_text_field( $short_code ),
					'batch_number'  => sanitize_text_field( $strain['batch_number'] ?? '' ),
					'category'      => sanitize_text_field( $strain['category'] ?? 'standard-potency' ),
					'psilocybin'    => intval( $strain['psilocybin'] ?? 0 ),
					'psilocin'      => intval( $strain['psilocin'] ?? 0 ),
					'norpsilocin'   => intval( $strain['norpsilocin'] ?? 0 ),
					'baeocystin'    => intval( $strain['baeocystin'] ?? 0 ),
					'norbaeocystin' => intval( $strain['norbaeocystin'] ?? 0 ),
					'aeruginascin'  => intval( $strain['aeruginascin'] ?? 0 ),
					'is_active'     => isset( $strain['is_active'] ) ? intval( $strain['is_active'] ) : 1,
				);

				if ( $existing && 'update' === $duplicate_handling ) {
					$op_result = ADC_Strains::update( $existing['id'], $strain_data );
				} else {
					$op_result = ADC_Strains::create( $strain_data );
				}

				if ( is_wp_error( $op_result ) ) {
					$errors[] = "Strain '" . esc_html( $strain['name'] ) . "': " . $op_result->get_error_message();
					++$skipped;
				} else {
					++$imported;
				}
			}
			$result['strains']         = $imported;
			$result['strains_skipped'] = $skipped;
			if ( ! empty( $errors ) ) {
				$result['strain_errors'] = array_slice( $errors, 0, 10 );
			}
		}

		// Import edibles
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in render_page.
		if ( isset( $_POST['import_edibles'] ) && ! empty( $edibles_data ) ) {
			$imported = 0;
			$skipped  = 0;
			$errors   = array();

			foreach ( $edibles_data as $index => $edible ) {
				if ( empty( $edible['name'] ) ) {
					++$skipped;
					continue;
				}

				$short_code = $edible['short_code'] ?? $edible['shortCode'] ?? '';
				$existing   = ! empty( $short_code ) ? self::find_by_code( $short_code, 'edible' ) : null;

				if ( $existing && 'skip' === $duplicate_handling ) {
					++$skipped;
					continue;
				}

				$edible_data = array(
					'name'               => sanitize_text_field( $edible['name'] ),
					'short_code'         => sanitize_text_field( $short_code ),
					'brand'              => sanitize_text_field( $edible['brand'] ?? '' ),
					'product_type'       => sanitize_text_field( $edible['product_type'] ?? $edible['productType'] ?? 'other' ),
					'pieces_per_package' => intval( $edible['pieces_per_package'] ?? $edible['piecesPerPackage'] ?? 1 ),
					'psilocybin'         => intval( $edible['psilocybin'] ?? 0 ),
					'psilocin'           => intval( $edible['psilocin'] ?? 0 ),
					'norpsilocin'        => intval( $edible['norpsilocin'] ?? 0 ),
					'baeocystin'         => intval( $edible['baeocystin'] ?? 0 ),
					'norbaeocystin'      => intval( $edible['norbaeocystin'] ?? 0 ),
					'aeruginascin'       => intval( $edible['aeruginascin'] ?? 0 ),
					'is_active'          => isset( $edible['is_active'] ) ? intval( $edible['is_active'] ) : 1,
				);

				if ( $existing && 'update' === $duplicate_handling ) {
					$op_result = ADC_Edibles::update( $existing['id'], $edible_data );
				} else {
					$op_result = ADC_Edibles::create( $edible_data );
				}

				if ( is_wp_error( $op_result ) ) {
					$errors[] = "Edible '" . esc_html( $edible['name'] ) . "': " . $op_result->get_error_message();
					++$skipped;
				} else {
					++$imported;
				}
			}
			$result['edibles']         = $imported;
			$result['edibles_skipped'] = $skipped;
			if ( ! empty( $errors ) ) {
				$result['edible_errors'] = array_slice( $errors, 0, 10 );
			}
		}

		// Import settings
		if ( isset( $_POST['import_settings'] ) && ! empty( $settings_data ) ) {
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			// Extract and save Google Sheets settings separately
			$gsheets_data = null;
			if ( isset( $settings_data['gsheets'] ) && is_array( $settings_data['gsheets'] ) ) {
				$gsheets_data = $settings_data['gsheets'];
				unset( $settings_data['gsheets'] );
			}

			// Sanitize settings before saving
			$sanitized_settings = self::sanitize_settings( $settings_data );
			$settings_result    = update_option( 'adc_settings', $sanitized_settings );

			// Restore Google Sheets settings
			if ( $gsheets_data ) {
				$sanitized_gsheets = array(
					'strains_url'    => esc_url_raw( $gsheets_data['strains_url'] ?? '' ),
					'strains_gid'    => sanitize_text_field( $gsheets_data['strains_gid'] ?? '' ),
					'edibles_url'    => esc_url_raw( $gsheets_data['edibles_url'] ?? '' ),
					'edibles_gid'    => sanitize_text_field( $gsheets_data['edibles_gid'] ?? '' ),
					'import_mode'    => in_array( $gsheets_data['import_mode'] ?? '', array( 'update', 'replace' ), true ) ? $gsheets_data['import_mode'] : 'update',
					'auto_sync'      => ! empty( $gsheets_data['auto_sync'] ),
					'sync_frequency' => sanitize_key( $gsheets_data['sync_frequency'] ?? 'daily' ),
					'notify_admin'   => ! empty( $gsheets_data['notify_admin'] ),
				);
				update_option( 'adc_gsheets_settings', $sanitized_gsheets );
			}

			$result['settings'] = true;
		}

		return $result;
	}

	/**
	 * Sanitize settings data from JSON import.
	 *
	 * @param array $data Raw settings data from JSON.
	 * @return array Sanitized settings array.
	 */
	private static function sanitize_settings( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$sanitized = array();

		// String keys with sanitize_key
		$key_fields = array( 'template', 'default_tab', 'short_url_path' );
		foreach ( $key_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_key( $data[ $field ] );
			}
		}

		// Boolean fields
		$bool_fields = array(
			'show_edibles',
			'show_mushrooms',
			'show_quick_converter',
			'show_compound_breakdown',
			'show_safety_warning',
			'allow_custom',
			'allow_submit',
			'auto_submit_unknown_qr',
		);
		foreach ( $bool_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = (bool) $data[ $field ];
			}
		}

		// Text fields
		if ( isset( $data['short_code_prefix'] ) ) {
			$sanitized['short_code_prefix'] = strtoupper( sanitize_text_field( $data['short_code_prefix'] ) );
		}
		if ( isset( $data['disclaimer_text'] ) ) {
			$sanitized['disclaimer_text'] = sanitize_textarea_field( $data['disclaimer_text'] );
		}
		if ( isset( $data['custom_css'] ) ) {
			$sanitized['custom_css'] = wp_strip_all_tags( $data['custom_css'] );
		}
		if ( isset( $data['submission_notification_email'] ) ) {
			$sanitized['submission_notification_email'] = sanitize_email( $data['submission_notification_email'] );
		}

		return $sanitized;
	}

	/**
	 * Find existing entry by short code.
	 *
	 * @param string $code Short code to look up.
	 * @param string $type Entry type ('strain' or 'edible').
	 * @return array|null Existing record or null if not found.
	 */
	private static function find_by_code( $code, $type ) {
		if ( empty( $code ) ) {
			return null;
		}

		global $wpdb;
		$table = 'strain' === $type ? ADC_DB::table( 'strains' ) : ADC_DB::table( 'edibles' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE short_code = %s",
				$code
			),
			ARRAY_A
		);
	}
}
