<?php
/**
 * Google Sheets Admin Page — Renders the settings UI and handles REST endpoints.
 *
 * @since 2.12.0
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_Sheets_Admin_Page class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_Sheets_Admin_Page {

	/**
	 * Register REST API routes for preview/import AJAX.
	 */
	public static function register_routes() {
		$namespace = 'adc/v1';

		register_rest_route(
			$namespace,
			'/sheets/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_preview' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			$namespace,
			'/sheets/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_import' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * REST: Preview sheet data.
	 */
	public static function rest_preview( $request ) {
		$url  = sanitize_text_field( $request->get_param( 'url' ) );
		$gid  = sanitize_text_field( $request->get_param( 'gid' ) );
		$type = sanitize_text_field( $request->get_param( 'type' ) );

		if ( empty( $url ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'URL is required.',
				),
				400
			);
		}

		$preview = ADC_Google_Sheets::preview( $url, 5, $gid ?: null );
		if ( is_wp_error( $preview ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $preview->get_error_message(),
				),
				400
			);
		}

		// Add column mapping info
		$column_map = ADC_Sheets_Importer::map_headers( $preview['headers'], $type ?: 'strain' );

		$preview['column_map'] = $column_map;

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $preview,
			)
		);
	}

	/**
	 * REST: Run import.
	 */
	public static function rest_import( $request ) {
		$url  = sanitize_text_field( $request->get_param( 'url' ) );
		$gid  = sanitize_text_field( $request->get_param( 'gid' ) );
		$type = sanitize_text_field( $request->get_param( 'type' ) );
		$mode = sanitize_text_field( $request->get_param( 'mode' ) );

		if ( empty( $url ) || empty( $type ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'URL and type are required.',
				),
				400
			);
		}

		if ( ! in_array( $type, array( 'strain', 'edible' ), true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Type must be strain or edible.',
				),
				400
			);
		}

		$result = ADC_Sheets_Importer::import_type( $type, $url, $gid ?: null, $mode ?: 'update' );

		if ( ! $result['success'] && ! empty( $result['errors'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => implode( '; ', $result['errors'] ),
					'data'    => $result,
				),
				400
			);
		}

		// Log it
		$log = array(
			'time'    => current_time( 'mysql' ),
			'type'    => 'manual',
			'results' => array( $type . 's' => $result ),
		);
		update_option( ADC_Sheets_Importer::OPT_LAST_LOG, $log );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}

	/**
	 * Enqueue admin scripts for the Google Sheets page.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'dosage-calculator-google-sheets' ) === false
			&& strpos( $hook, 'dosage-calculator-import-export' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'adc-google-sheets-admin',
			plugins_url( 'admin/js/google-sheets-admin.js', __DIR__ ),
			array( 'jquery' ),
			ADC_VERSION,
			true
		);

		wp_localize_script(
			'adc-google-sheets-admin',
			'adcSheetsAdmin',
			array(
				'restUrl' => rest_url( 'adc/v1/sheets/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Render the admin page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Handle settings save
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['adc_gsheets_nonce'] ) ) {
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['adc_gsheets_nonce'] ) ), 'adc_gsheets_settings' ) ) {
				$settings_fields = array(
					'strains_url',
					'strains_gid',
					'edibles_url',
					'edibles_gid',
					'import_mode',
					'auto_sync',
					'sync_frequency',
					'notify_admin',
				);
				$settings        = array();
				foreach ( $settings_fields as $field ) {
					if ( isset( $_POST[ $field ] ) ) {
						$settings[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
					}
				}
				ADC_Sheets_Importer::save_settings( $settings );
				echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
			}
		}

		$settings  = ADC_Sheets_Importer::get_settings();
		$last_log  = ADC_Sheets_Importer::get_last_log();
		$next_sync = ADC_Sheets_Importer::get_next_sync();

		?>
		<div class="wrap adc-admin">
			<h1>📊 Google Sheets Import</h1>
			<p>Import strains and edibles from Google Sheets. Sheets must be shared as <strong>"Anyone with the link can view"</strong> — no API key needed.</p>

			<form method="post">
				<?php wp_nonce_field( 'adc_gsheets_settings', 'adc_gsheets_nonce' ); ?>

				<!-- Strains Section -->
				<div class="card" style="max-width:800px;margin-bottom:20px;">
					<h2>🍄 Strains Sheet</h2>
					<table class="form-table">
						<tr>
							<th><label for="strains_url">Google Sheet URL</label></th>
							<td>
								<input type="url" name="strains_url" id="strains_url" class="regular-text"
										value="<?php echo esc_attr( $settings['strains_url'] ); ?>"
										placeholder="https://docs.google.com/spreadsheets/d/...">
								<p class="description">Full sharing URL of the Google Sheet containing strain data.</p>
							</td>
						</tr>
						<tr>
							<th><label for="strains_gid">Sheet Tab (GID)</label></th>
							<td>
								<input type="text" name="strains_gid" id="strains_gid" class="small-text"
										value="<?php echo esc_attr( $settings['strains_gid'] ); ?>"
										placeholder="0">
								<p class="description">Optional. The tab GID (from URL after #gid=). Default: first tab (0).</p>
							</td>
						</tr>
					</table>
					<p>
						<button type="button" id="adc-preview-strains" class="button">Preview</button>
						<button type="button" id="adc-import-strains" class="button button-primary">Import Strains Now</button>
					</p>
					<div id="adc-strains-preview-status"></div>
					<div id="adc-strains-import-status"></div>
					<div id="adc-strains-preview" style="margin-top:15px;overflow-x:auto;"></div>
				</div>

				<!-- Edibles Section -->
				<div class="card" style="max-width:800px;margin-bottom:20px;">
					<h2>🍫 Edibles Sheet</h2>
					<table class="form-table">
						<tr>
							<th><label for="edibles_url">Google Sheet URL</label></th>
							<td>
								<input type="url" name="edibles_url" id="edibles_url" class="regular-text"
										value="<?php echo esc_attr( $settings['edibles_url'] ); ?>"
										placeholder="https://docs.google.com/spreadsheets/d/...">
								<p class="description">Full sharing URL of the Google Sheet containing edible data.</p>
							</td>
						</tr>
						<tr>
							<th><label for="edibles_gid">Sheet Tab (GID)</label></th>
							<td>
								<input type="text" name="edibles_gid" id="edibles_gid" class="small-text"
										value="<?php echo esc_attr( $settings['edibles_gid'] ); ?>"
										placeholder="0">
								<p class="description">Optional. The tab GID (from URL after #gid=). Default: first tab (0).</p>
							</td>
						</tr>
					</table>
					<p>
						<button type="button" id="adc-preview-edibles" class="button">Preview</button>
						<button type="button" id="adc-import-edibles" class="button button-primary">Import Edibles Now</button>
					</p>
					<div id="adc-edibles-preview-status"></div>
					<div id="adc-edibles-import-status"></div>
					<div id="adc-edibles-preview" style="margin-top:15px;overflow-x:auto;"></div>
				</div>

				<!-- Import Settings -->
				<div class="card" style="max-width:800px;margin-bottom:20px;">
					<h2>⚙️ Import Settings</h2>
					<table class="form-table">
						<tr>
							<th><label for="import_mode">Import Mode</label></th>
							<td>
								<select name="import_mode" id="import_mode">
									<option value="add_new" <?php selected( $settings['import_mode'], 'add_new' ); ?>>Add new only (skip existing)</option>
									<option value="update" <?php selected( $settings['import_mode'], 'update' ); ?>>Update existing (match by code/name)</option>
									<option value="replace" <?php selected( $settings['import_mode'], 'replace' ); ?>>Replace all (deactivate existing, import fresh)</option>
								</select>
								<p class="description">
									<strong>Add new:</strong> Only imports rows that don't match existing short codes or names.<br>
									<strong>Update:</strong> Updates existing records if matched by short code or name, creates new ones otherwise.<br>
									<strong>Replace:</strong> Deactivates all existing records, then imports everything as new. <span style="color:#d63638;">Use with caution!</span>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Auto-Sync Settings -->
				<div class="card" style="max-width:800px;margin-bottom:20px;">
					<h2>🔄 Auto-Sync</h2>
					<table class="form-table">
						<tr>
							<th><label for="auto_sync">Enable Auto-Sync</label></th>
							<td>
								<label>
									<input type="checkbox" name="auto_sync" id="auto_sync" value="1"
											<?php checked( $settings['auto_sync'] ); ?>>
									Automatically sync from Google Sheets on a schedule
								</label>
							</td>
						</tr>
						<tr class="adc-sync-options">
							<th><label for="sync_frequency">Sync Frequency</label></th>
							<td>
								<select name="sync_frequency" id="sync_frequency">
									<option value="hourly" <?php selected( $settings['sync_frequency'], 'hourly' ); ?>>Hourly</option>
									<option value="twicedaily" <?php selected( $settings['sync_frequency'], 'twicedaily' ); ?>>Twice Daily</option>
									<option value="daily" <?php selected( $settings['sync_frequency'], 'daily' ); ?>>Daily</option>
									<option value="weekly" <?php selected( $settings['sync_frequency'], 'weekly' ); ?>>Weekly</option>
								</select>
							</td>
						</tr>
						<tr class="adc-sync-options">
							<th>Notify Admin</th>
							<td>
								<label>
									<input type="checkbox" name="notify_admin" value="1"
											<?php checked( $settings['notify_admin'] ); ?>>
									Email sync results to <?php echo esc_html( get_option( 'admin_email' ) ); ?>
								</label>
							</td>
						</tr>
					</table>

					<?php if ( $next_sync ) : ?>
						<p><strong>Next scheduled sync:</strong> <?php echo esc_html( get_date_from_gmt( date( 'Y-m-d H:i:s', $next_sync ), 'M j, Y g:i A' ) ); ?></p>
					<?php endif; ?>
				</div>

				<?php submit_button( 'Save Settings' ); ?>
			</form>

			<!-- Last Sync Log -->
			<?php if ( $last_log ) : ?>
			<div class="card" style="max-width:800px;">
				<h2>📋 Last Sync Log</h2>
				<p><strong>Time:</strong> <?php echo esc_html( $last_log['time'] ); ?>
					<strong>Type:</strong> <?php echo esc_html( $last_log['type'] ); ?></p>

				<?php foreach ( $last_log['results'] as $type => $result ) : ?>
					<h4><?php echo esc_html( ucfirst( $type ) ); ?></h4>
					<ul>
						<li>✅ Imported: <?php echo intval( $result['imported'] ); ?></li>
						<li>🔄 Updated: <?php echo intval( $result['updated'] ); ?></li>
						<li>⏭️ Skipped: <?php echo intval( $result['skipped'] ); ?></li>
						<?php if ( ! empty( $result['deactivated'] ) ) : ?>
							<li>🚫 Deactivated: <?php echo intval( $result['deactivated'] ); ?></li>
						<?php endif; ?>
						<?php if ( ! empty( $result['errors'] ) ) : ?>
							<li style="color:#d63638;">Errors:
								<ul>
									<?php foreach ( $result['errors'] as $err ) : ?>
										<li><?php echo esc_html( $err ); ?></li>
									<?php endforeach; ?>
								</ul>
							</li>
						<?php endif; ?>
					</ul>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<!-- Help Section -->
			<div class="card" style="max-width:800px;">
				<h2>ℹ️ How It Works</h2>
				<ol>
					<li>Create a Google Sheet with your strain or edible data</li>
					<li>Make sure the first row contains column headers</li>
					<li>Share the sheet: <strong>Share → Anyone with the link → Viewer</strong></li>
					<li>Paste the URL above and click <strong>Preview</strong> to verify the data</li>
					<li>Click <strong>Import</strong> to bring the data in</li>
					<li>Enable <strong>Auto-Sync</strong> to keep it updated automatically</li>
				</ol>
				<h3>Recognized Column Headers</h3>
				<p>The importer uses fuzzy matching. These column names are recognized:</p>
				<table class="widefat" style="max-width:600px;">
					<thead><tr><th>Database Field</th><th>Accepted Headers</th></tr></thead>
					<tbody>
						<tr><td>name</td><td>name, strain, strain name, product, product name, title</td></tr>
						<tr><td>short_code</td><td>short_code, shortcode, code, sku, id</td></tr>
						<tr><td>category</td><td>category, cat, strain type, species</td></tr>
						<tr><td>batch_number</td><td>batch, batch number, batch #, lot</td></tr>
						<tr><td>psilocybin</td><td>psilocybin, psilocybin %, psilo (mcg/g)</td></tr>
						<tr><td>psilocin</td><td>psilocin, psilocin % (mcg/g)</td></tr>
						<tr><td>brand</td><td>brand, manufacturer, maker (edibles only)</td></tr>
						<tr><td>product_type</td><td>product type, type (edibles only)</td></tr>
						<tr><td>pieces_per_package</td><td>pieces, count, units (edibles only)</td></tr>
						<tr><td>total_mg</td><td>total mg, mg total (edibles only)</td></tr>
						<tr><td>notes</td><td>notes, comments, description</td></tr>
						<tr><td>is_active</td><td>active, enabled, status (yes/no/1/0)</td></tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
