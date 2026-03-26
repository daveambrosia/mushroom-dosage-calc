<?php
/**
 * CSV Importer with smart header detection - Version 2.0
 *
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_CSV_Importer class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_CSV_Importer {

	/**
	 * Header mappings for fuzzy matching
	 *
	 * @var array<string,string[]>
	 */
	private static $header_mappings = array(
		'name'               => array( 'name', 'strain', 'strain name', 'product', 'product name', 'title' ),
		'batch_number'       => array( 'batch', 'batch number', 'batch_number', 'batch #', 'lot', 'lot number' ),
		'category'           => array( 'category', 'cat', 'type', 'strain type', 'species' ),
		'psilocybin'         => array( 'psilocybin', 'psilocybin %', 'psilocybin_mcg', 'psilocybin mcg/g', 'psilo' ),
		'psilocin'           => array( 'psilocin', 'psilocin %', 'psilocin_mcg', 'psilocin mcg/g' ),
		'norpsilocin'        => array( 'norpsilocin', 'norpsilocin %', 'norpsilocin_mcg', 'norpsilo' ),
		'baeocystin'         => array( 'baeocystin', 'baeocystin %', 'baeocystin_mcg', 'baeo' ),
		'norbaeocystin'      => array( 'norbaeocystin', 'norbaeocystin %', 'norbaeocystin_mcg', 'norbaeo' ),
		'aeruginascin'       => array( 'aeruginascin', 'aeruginascin %', 'aeruginascin_mcg', 'aerug' ),
		'brand'              => array( 'brand', 'manufacturer', 'maker', 'company' ),
		'product_type'       => array( 'product type', 'product_type', 'type', 'edible type' ),
		'pieces_per_package' => array( 'pieces', 'pieces per package', 'pieces_per_package', 'count', 'units' ),
		'total_mg'           => array( 'total mg', 'total_mg', 'mg total', 'total psilocybin mg', 'mg' ),
		'notes'              => array( 'notes', 'note', 'comments', 'description' ),
	);

	/**
	 * Render the import page
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		// Handle file upload
		$result = null;
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['adc_import_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['adc_import_nonce'] ) ), 'adc_csv_import' ) ) {
				wp_die( 'Security check failed' );
			}
			$result = self::handle_upload();
		}

		?>
		<div class="wrap adc-admin">
			<h1>Import CSV</h1>
			
			<?php if ( $result ) : ?>
				<?php if ( is_wp_error( $result ) ) : ?>
					<div class="notice notice-error"><p><?php echo esc_html( $result->get_error_message() ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-success">
						<p>Import complete! <?php echo intval( $result['imported'] ); ?> items imported, <?php echo intval( $result['skipped'] ); ?> skipped.</p>
						<?php if ( ! empty( $result['errors'] ) ) : ?>
							<p>Errors:</p>
							<ul>
								<?php foreach ( $result['errors'] as $error ) : ?>
									<li><?php echo esc_html( $error ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
			
			<div class="adc-import-section">
				<h2>Upload CSV File</h2>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'adc_csv_import', 'adc_import_nonce' ); ?>
					
					<table class="form-table">
						<tr>
							<th><label for="import_type">Import Type</label></th>
							<td>
								<select name="import_type" id="import_type">
									<option value="strain">Strains</option>
									<option value="edible">Edibles</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="csv_file">CSV File</label></th>
							<td>
								<input type="file" name="csv_file" id="csv_file" accept=".csv,.txt" required>
								<p class="description">Upload a CSV file with strain or edible data.</p>
							</td>
						</tr>
						<tr>
							<th><label for="default_category">Default Category</label></th>
							<td>
								<select name="default_category" id="default_category">
									<?php
									global $wpdb;
									$categories = $wpdb->get_results(
										'SELECT * FROM ' . ADC_DB::table( 'categories' ) . ' WHERE is_active = 1 ORDER BY sort_order',
										ARRAY_A
									);
									foreach ( $categories as $cat ) :
										?>
										<option value="<?php echo esc_attr( $cat['slug'] ); ?>"><?php echo esc_html( $cat['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Used when category is not in the CSV.</p>
							</td>
						</tr>
						<tr>
							<th><label for="skip_duplicates">Duplicate Handling</label></th>
							<td>
								<select name="skip_duplicates" id="skip_duplicates">
									<option value="skip">Skip duplicates (by name)</option>
									<option value="update">Update existing (by name)</option>
									<option value="import">Import all (may create duplicates)</option>
								</select>
							</td>
						</tr>
					</table>
					
					<?php submit_button( 'Upload and Preview' ); ?>
				</form>
			</div>
			
			<div class="adc-import-help">
				<h2>CSV Format Help</h2>
				<p>The importer will automatically detect column headers. Supported columns:</p>
				
				<h3>For Strains:</h3>
				<table class="widefat">
					<thead>
						<tr>
							<th>Field</th>
							<th>Accepted Headers</th>
							<th>Required</th>
						</tr>
					</thead>
					<tbody>
						<tr><td>Name</td><td>name, strain, strain name, product, title</td><td>Yes</td></tr>
						<tr><td>Psilocybin (mcg/g)</td><td>psilocybin, psilocybin %, psilocybin_mcg</td><td>Yes</td></tr>
						<tr><td>Psilocin (mcg/g)</td><td>psilocin, psilocin %, psilocin_mcg</td><td>No</td></tr>
						<tr><td>Batch Number</td><td>batch, batch number, lot</td><td>No</td></tr>
						<tr><td>Category</td><td>category, type, species</td><td>No</td></tr>
					</tbody>
				</table>
				
				<h3>For Edibles:</h3>
				<table class="widefat" style="margin-top: 1rem;">
					<thead>
						<tr>
							<th>Field</th>
							<th>Accepted Headers</th>
							<th>Required</th>
						</tr>
					</thead>
					<tbody>
						<tr><td>Name</td><td>name, product, product name, title</td><td>Yes</td></tr>
						<tr><td>Psilocybin (mcg/piece)</td><td>psilocybin, psilocybin_mcg, psilo</td><td>Yes</td></tr>
						<tr><td>Total mg</td><td>total mg, total_mg, mg total</td><td>No (auto-calculated)</td></tr>
						<tr><td>Pieces</td><td>pieces, pieces per package, count, units</td><td>Yes</td></tr>
						<tr><td>Brand</td><td>brand, manufacturer, maker</td><td>No</td></tr>
						<tr><td>Product Type</td><td>product type, type, edible type</td><td>No</td></tr>
					</tbody>
				</table>
				
				<h3>Example CSV (Strains):</h3>
				<pre style="background: #f0f0f0; padding: 1rem; overflow-x: auto;">name,psilocybin,psilocin,baeocystin,batch,category
Golden Teacher,6200,800,350,GT-2024-001,standard-potency
Penis Envy,12000,3000,600,PE-2024-001,high-potency</pre>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle file upload
	 */
	private static function handle_upload() {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- nonce verified in render_page before calling this method; file validation handled below.
		if ( ! isset( $_FILES['csv_file'] ) || ! isset( $_FILES['csv_file']['error'] ) || UPLOAD_ERR_OK !== $_FILES['csv_file']['error'] ) {
			return new WP_Error( 'upload_error', 'File upload failed.' );
		}

		// Security: Validate file extension
		if ( ! isset( $_FILES['csv_file']['name'] ) ) {
			return new WP_Error( 'upload_error', 'File name missing.' );
		}
		$filename = sanitize_file_name( wp_unslash( $_FILES['csv_file']['name'] ) );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			return new WP_Error( 'invalid_file', 'Only CSV files are allowed.' );
		}

		// Security: Check file size (max 5MB)
		if ( ! isset( $_FILES['csv_file']['size'] ) || $_FILES['csv_file']['size'] > 5 * 1024 * 1024 ) {
			return new WP_Error( 'file_too_large', 'File size exceeds 5MB limit.' );
		}

		if ( ! isset( $_FILES['csv_file']['tmp_name'] ) ) {
			return new WP_Error( 'upload_error', 'Temporary file missing.' );
		}
		$file               = sanitize_text_field( wp_unslash( $_FILES['csv_file']['tmp_name'] ) );
		$type               = isset( $_POST['import_type'] ) ? sanitize_key( wp_unslash( $_POST['import_type'] ) ) : '';
		$default_category   = isset( $_POST['default_category'] ) ? sanitize_key( wp_unslash( $_POST['default_category'] ) ) : '';
		$duplicate_handling = isset( $_POST['skip_duplicates'] ) ? sanitize_key( wp_unslash( $_POST['skip_duplicates'] ) ) : '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing

		// Read CSV
		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'read_error', 'Could not read file.' );
		}

		// Get headers
		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle );
			return new WP_Error( 'parse_error', 'Could not parse CSV headers.' );
		}

		// Map headers
		$column_map = self::map_headers( $headers );

		// Validate required fields
		if ( ! isset( $column_map['name'] ) ) {
			fclose( $handle );
			return new WP_Error( 'missing_field', 'CSV must contain a "name" column.' );
		}

		if ( 'strain' === $type && ! isset( $column_map['psilocybin'] ) ) {
			fclose( $handle );
			return new WP_Error( 'missing_field', 'Strain CSV must contain a "psilocybin" column.' );
		}

		if ( 'edible' === $type && ! isset( $column_map['pieces_per_package'] ) ) {
			fclose( $handle );
			return new WP_Error( 'missing_field', 'Edible CSV must contain "pieces" or "pieces_per_package" column.' );
		}

		// Process rows
		$imported = 0;
		$skipped  = 0;
		$errors   = array();
		$row_num  = 1;

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			++$row_num;

			// Skip empty rows
			if ( empty( array_filter( $row ) ) ) {
				continue;
			}

			// Map row data
			$data             = self::map_row( $row, $column_map, $headers );
			$data['category'] = $data['category'] ?: $default_category;

			// Calculate total_mg from psilocybin + psilocin if not provided (for edibles)
			if ( 'edible' === $type && empty( $data['total_mg'] ) ) {
				$data['total_mg'] = ( isset( $data['psilocybin'] ) ? intval( $data['psilocybin'] ) : 0 )
									+ ( isset( $data['psilocin'] ) ? intval( $data['psilocin'] ) : 0 );
			}

			// Skip if name is empty
			if ( empty( $data['name'] ) ) {
				$errors[] = "Row $row_num: Missing name, skipped.";
				++$skipped;
				continue;
			}

			// Check for duplicates
			if ( 'import' !== $duplicate_handling ) {
				$existing = self::find_existing( $data['name'], $type );

				if ( $existing ) {
					if ( 'skip' === $duplicate_handling ) {
						++$skipped;
						continue;
					} elseif ( 'update' === $duplicate_handling ) {
						// Update existing
						if ( 'strain' === $type ) {
							$result = ADC_Strains::update( $existing['id'], $data );
						} else {
							$result = ADC_Edibles::update( $existing['id'], $data );
						}

						if ( is_wp_error( $result ) ) {
							$errors[] = "Row $row_num: " . $result->get_error_message();
							++$skipped;
						} else {
							++$imported;
						}
						continue;
					}
				}
			}

			// Create new
			if ( 'strain' === $type ) {
				$result = ADC_Strains::create( $data );
			} else {
				$result = ADC_Edibles::create( $data );
			}

			if ( is_wp_error( $result ) ) {
				$errors[] = "Row $row_num: " . $result->get_error_message();
				++$skipped;
			} else {
				++$imported;
			}
		}

		fclose( $handle );

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => array_slice( $errors, 0, 10 ), // Limit errors shown
		);
	}

	/**
	 * Map CSV headers to field names using fuzzy matching
	 *
	 * @param array $headers CSV header row.
	 */
	private static function map_headers( $headers ) {
		$map = array();

		// First pass: exact matches only
		foreach ( $headers as $index => $header ) {
			$header_lower = strtolower( trim( $header ) );

			foreach ( self::$header_mappings as $field => $variants ) {
				if ( isset( $map[ $field ] ) ) {
					continue; // Don't overwrite existing mappings
				}

				foreach ( $variants as $variant ) {
					if ( $header_lower === $variant ) {
						$map[ $field ] = $index;
						break 2;
					}
				}
			}
		}

		// Second pass: fuzzy matches for unmapped headers
		foreach ( $headers as $index => $header ) {
			// Skip if this column is already mapped
			if ( in_array( $index, $map, true ) ) {
				continue;
			}

			$header_lower = strtolower( trim( $header ) );

			foreach ( self::$header_mappings as $field => $variants ) {
				if ( isset( $map[ $field ] ) ) {
					continue; // Don't overwrite existing mappings
				}

				foreach ( $variants as $variant ) {
					// Fuzzy match: header contains variant OR similar text
					if ( strpos( $header_lower, $variant ) === 0 || // Must START with variant
						similar_text( $header_lower, $variant ) > strlen( $variant ) * 0.8 ) {
						$map[ $field ] = $index;
						break 2;
					}
				}
			}
		}

		return $map;
	}

	/**
	 * Map a row of data using the column map
	 *
	 * @param array $row        Single CSV data row.
	 * @param array $column_map Header-to-index mapping.
	 * @param array $headers    Original CSV header row.
	 */
	private static function map_row( $row, $column_map, $headers ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$data = array();

		$fields = array(
			'name',
			'batch_number',
			'category',
			'psilocybin',
			'psilocin',
			'norpsilocin',
			'baeocystin',
			'norbaeocystin',
			'aeruginascin',
			'brand',
			'product_type',
			'pieces_per_package',
			'total_mg',
			'notes',
		);

		foreach ( $fields as $field ) {
			if ( isset( $column_map[ $field ] ) && isset( $row[ $column_map[ $field ] ] ) ) {
				$value = trim( $row[ $column_map[ $field ] ] );

				// Convert numeric fields
				if ( in_array( $field, array( 'psilocybin', 'psilocin', 'norpsilocin', 'baeocystin', 'norbaeocystin', 'aeruginascin', 'pieces_per_package', 'total_mg' ), true ) ) {
					// Handle percentage format (e.g., "0.8%" -> 8000 mcg/g)
					if ( strpos( $value, '%' ) !== false ) {
						$value = floatval( str_replace( '%', '', $value ) ) * 10000;
					}
					$data[ $field ] = intval( $value );
				} else {
					$data[ $field ] = sanitize_text_field( $value );
				}
			}
		}

		return $data;
	}

	/**
	 * Find existing strain/edible by name
	 *
	 * @param string $name Strain or edible name.
	 * @param string $type Import type ('strain' or 'edible').
	 */
	private static function find_existing( $name, $type ) {
		global $wpdb;
		$table = 'strain' === $type ? ADC_DB::table( 'strains' ) : ADC_DB::table( 'edibles' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE name = %s",
				$name
			),
			ARRAY_A
		);
	}
}
