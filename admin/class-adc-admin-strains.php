<?php
/**
 * Admin Strains Management
 *
 * Handles strain list, add/edit form, and delete AJAX.
 *
 * @since 2.13.0
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_Admin_Strains class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_Admin_Strains {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers AJAX hooks.
	 */
	private function __construct() {
		add_action( 'wp_ajax_adc_delete_strain', array( $this, 'ajax_delete_strain' ) );
	}

	/**
	 * Strains list page
	 */
	public function render_strains() {
		$strains = ADC_Strains::get_all(
			array(
				'active_only' => false,
				'orderby'     => 'name',
			)
		);

		?>
		<div class="wrap adc-admin">
			<h1>
				Strains
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dosage-calculator-add-strain' ) ); ?>" class="page-title-action">Add New</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dosage-calculator-import' ) ); ?>" class="page-title-action">Import CSV</a>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=adc_export&type=strains&format=csv&_wpnonce=' . wp_create_nonce( 'adc_export' ) ) ); ?>" class="page-title-action">Export CSV</a>
					<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=adc_export&type=strains&format=json&_wpnonce=' . wp_create_nonce( 'adc_export' ) ) ); ?>" class="page-title-action">Export JSON</a>
				<?php endif; ?>
			</h1>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 25%;">Name</th>
						<th>Short Code</th>
						<th>Category</th>
						<th>Psilocybin</th>
						<th>Psilocin</th>
						<th>Total</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $strains ) ) : ?>
						<tr><td colspan="7">No strains found. <a href="<?php echo esc_url( admin_url( 'admin.php?page=dosage-calculator-add-strain' ) ); ?>">Add one</a>.</td></tr>
					<?php else : ?>
						<?php
						foreach ( $strains as $strain ) :
							$total = intval( $strain['psilocybin'] ) + intval( $strain['psilocin'] );
							?>
							<tr>
								<td data-label="Name"><strong><?php echo esc_html( $strain['name'] ); ?></strong></td>
								<td data-label="Code"><span class="adc-mobile-label">Code: </span><code><?php echo esc_html( $strain['short_code'] ); ?></code></td>
								<td data-label="Category"><span class="adc-mobile-label">Category: </span><?php echo esc_html( $strain['category'] ); ?></td>
								<td data-label="Psilocybin"><span class="adc-mobile-label">Psilocybin: </span><?php echo number_format( $strain['psilocybin'] ); ?></td>
								<td data-label="Psilocin"><span class="adc-mobile-label">Psilocin: </span><?php echo number_format( $strain['psilocin'] ); ?></td>
								<td data-label="Total"><span class="adc-mobile-label">Total: </span><strong><?php echo number_format( $total ); ?></strong> mcg/g</td>
								<td data-label="Status">
									<?php if ( $strain['is_active'] ) : ?>
										<span class="adc-status-active">● Active</span>
									<?php else : ?>
										<span class="adc-status-inactive">○ Inactive</span>
									<?php endif; ?>
								</td>
								<td data-label="Actions" class="adc-actions-cell">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=dosage-calculator-add-strain&id=' . intval( $strain['id'] ) ) ); ?>" class="button button-small">Edit</a>
									<a href="#" class="button button-small adc-delete-strain adc-btn-delete" data-id="<?php echo esc_attr( $strain['id'] ); ?>" data-name="<?php echo esc_attr( $strain['name'] ); ?>">Delete</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Strain add/edit form
	 */
	public function render_strain_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter.
		$id      = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		$strain  = $id ? ADC_Strains::get( $id ) : null;
		$is_edit = ! empty( $strain );

		// Handle form submission
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['adc_strain_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['adc_strain_nonce'] ) ), 'adc_save_strain' ) ) {
				wp_die( 'Security check failed' );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage strains.', 'ambrosia-dosage-calc' ) );
			}

			$data = array(
				'name'          => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'short_code'    => isset( $_POST['short_code'] ) ? sanitize_text_field( wp_unslash( $_POST['short_code'] ) ) : '',
				'batch_number'  => isset( $_POST['batch_number'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_number'] ) ) : '',
				'category'      => isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '',
				'psilocybin'    => isset( $_POST['psilocybin'] ) ? absint( $_POST['psilocybin'] ) : 0,
				'psilocin'      => isset( $_POST['psilocin'] ) ? absint( $_POST['psilocin'] ) : 0,
				'norpsilocin'   => isset( $_POST['norpsilocin'] ) ? absint( $_POST['norpsilocin'] ) : 0,
				'baeocystin'    => isset( $_POST['baeocystin'] ) ? absint( $_POST['baeocystin'] ) : 0,
				'norbaeocystin' => isset( $_POST['norbaeocystin'] ) ? absint( $_POST['norbaeocystin'] ) : 0,
				'aeruginascin'  => isset( $_POST['aeruginascin'] ) ? absint( $_POST['aeruginascin'] ) : 0,
				'is_active'     => isset( $_POST['is_active'] ) ? 1 : 0,
				'notes'         => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
				'sort_order'    => isset( $_POST['sort_order'] ) ? intval( $_POST['sort_order'] ) : 0,
			);

			if ( $is_edit ) {
				$result = ADC_Strains::update( $id, $data );
			} else {
				$result = ADC_Strains::create( $data );
			}

			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>Strain saved successfully!</p></div>';
				if ( ! $is_edit ) {
					$strain  = ADC_Strains::get( $result );
					$is_edit = true;
					$id      = $result;
				} else {
					$strain = ADC_Strains::get( $id );
				}
			}
		}

		// Get categories
		global $wpdb;
		$categories = $wpdb->get_results(
			'SELECT * FROM ' . ADC_DB::table( 'categories' ) . " WHERE type IN ('strain', 'both') AND is_active = 1 ORDER BY sort_order",
			ARRAY_A
		);

		?>
		<div class="wrap adc-admin">
			<h1><?php echo $is_edit ? 'Edit Strain' : 'Add New Strain'; ?></h1>
			
			<form method="post" class="adc-strain-form">
				<?php wp_nonce_field( 'adc_save_strain', 'adc_strain_nonce' ); ?>
				
				<table class="form-table">
					<tr>
						<th><label for="name">Name <span class="required">*</span></label></th>
						<td><input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr( $strain['name'] ?? '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="short_code">Short Code</label></th>
						<td>
							<input type="text" id="short_code" name="short_code" class="regular-text" value="<?php echo esc_attr( $strain['short_code'] ?? '' ); ?>" placeholder="Auto-generated if empty">
							<p class="description">Used for QR codes. Example: ZD-GT-001</p>
						</td>
					</tr>
					<tr>
						<th><label for="batch_number">Batch Number</label></th>
						<td><input type="text" id="batch_number" name="batch_number" class="regular-text" value="<?php echo esc_attr( $strain['batch_number'] ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="category">Category</label></th>
						<td>
							<select id="category" name="category">
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat['slug'] ); ?>" <?php selected( $strain['category'] ?? '', $cat['slug'] ); ?>>
										<?php echo esc_html( $cat['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				
				<h2>Compound Levels (mcg/g)</h2>
				<p class="description">Only Psilocybin + Psilocin are used for dose calculations. Other compounds are displayed for information.</p>
				<table class="form-table">
					<tr>
						<th><label for="psilocybin">Psilocybin <span class="required">*</span></label></th>
						<td><input type="number" id="psilocybin" name="psilocybin" value="<?php echo isset( $strain['id'] ) ? esc_attr( $strain['psilocybin'] ) : ''; ?>" min="0" max="100000" placeholder="0" required> mcg/g</td>
					</tr>
					<tr>
						<th><label for="psilocin">Psilocin</label></th>
						<td><input type="number" id="psilocin" name="psilocin" value="<?php echo isset( $strain['id'] ) ? esc_attr( $strain['psilocin'] ) : ''; ?>" min="0" max="100000" placeholder="0"> mcg/g</td>
					</tr>
					<tr>
						<th><label for="norpsilocin">Norpsilocin</label></th>
						<td><input type="number" id="norpsilocin" name="norpsilocin" value="<?php echo isset( $strain['id'] ) ? esc_attr( $strain['norpsilocin'] ) : ''; ?>" min="0" max="100000" placeholder="0"> mcg/g</td>
					</tr>
					<tr>
						<th><label for="baeocystin">Baeocystin</label></th>
						<td><input type="number" id="baeocystin" name="baeocystin" value="<?php echo isset( $strain['id'] ) ? esc_attr( $strain['baeocystin'] ) : ''; ?>" min="0" max="100000" placeholder="0"> mcg/g</td>
					</tr>
					<tr>
						<th><label for="norbaeocystin">Norbaeocystin</label></th>
						<td><input type="number" id="norbaeocystin" name="norbaeocystin" value="<?php echo isset( $strain['id'] ) ? esc_attr( $strain['norbaeocystin'] ) : ''; ?>" min="0" max="100000" placeholder="0"> mcg/g</td>
					</tr>
					<tr>
						<th><label for="aeruginascin">Aeruginascin</label></th>
						<td><input type="number" id="aeruginascin" name="aeruginascin" value="<?php echo isset( $strain['id'] ) ? esc_attr( $strain['aeruginascin'] ) : ''; ?>" min="0" max="100000" placeholder="0"> mcg/g</td>
					</tr>
				</table>
				
				<h2>Additional Options</h2>
				<table class="form-table">
					<tr>
						<th><label for="is_active">Status</label></th>
						<td>
							<label>
								<input type="checkbox" id="is_active" name="is_active" value="1" <?php checked( $strain['is_active'] ?? 1, 1 ); ?>>
								Active (visible in calculator)
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="sort_order">Sort Order</label></th>
						<td><input type="number" id="sort_order" name="sort_order" value="<?php echo esc_attr( $strain['sort_order'] ?? 0 ); ?>" min="0"></td>
					</tr>
					<tr>
						<th><label for="notes">Internal Notes</label></th>
						<td><textarea id="notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea( $strain['notes'] ?? '' ); ?></textarea></td>
					</tr>
				</table>
				
				<?php submit_button( $is_edit ? 'Update Strain' : 'Add Strain' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * AJAX: Delete strain
	 */
	public function ajax_delete_strain() {
		check_ajax_referer( 'adc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$id = intval( $_POST['id'] ?? 0 );
		if ( $id <= 0 ) {
			wp_send_json_error( 'Invalid strain ID' );
		}

		$result = ADC_Strains::delete( $id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		} else {
			wp_send_json_success();
		}
	}
}
