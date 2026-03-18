<?php
/**
 * Admin Edibles Management
 *
 * Handles edible list, add/edit form, and delete AJAX.
 *
 * @since 2.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADC_Admin_Edibles {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_adc_delete_edible', array($this, 'ajax_delete_edible'));
    }

    /**
     * Edibles list page
     */
    public function render_edibles() {
        $edibles = ADC_Edibles::get_all(array('active_only' => false, 'orderby' => 'name'));
        
        ?>
        <div class="wrap adc-admin">
            <h1>
                Edibles
                <a href="<?php echo admin_url('admin.php?page=dosage-calculator-add-edible'); ?>" class="page-title-action">Add New</a>
                <a href="<?php echo admin_url('admin.php?page=dosage-calculator-import'); ?>" class="page-title-action">Import CSV</a>
                <?php if (current_user_can('manage_options')): ?>
                    <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=adc_export&type=edibles&format=csv&_wpnonce=' . wp_create_nonce('adc_export'))); ?>" class="page-title-action">Export CSV</a>
                    <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=adc_export&type=edibles&format=json&_wpnonce=' . wp_create_nonce('adc_export'))); ?>" class="page-title-action">Export JSON</a>
                <?php endif; ?>
            </h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 25%;">Name</th>
                        <th>Short Code</th>
                        <th>Brand</th>
                        <th>Type</th>
                        <th>Psilocybin</th>
                        <th>Pieces</th>
                        <th>mcg/pkg</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($edibles)): ?>
                        <tr><td colspan="9">No edibles found. <a href="<?php echo admin_url('admin.php?page=dosage-calculator-add-edible'); ?>">Add one</a>.</td></tr>
                    <?php else: ?>
                        <?php foreach ($edibles as $edible): 
                            $mcg_per_piece = ($edible['psilocybin'] ?? 0) + ($edible['psilocin'] ?? 0);
                        ?>
                            <tr>
                                <td data-label="Name"><strong><?php echo esc_html($edible['name']); ?></strong></td>
                                <td data-label="Code"><span class="adc-mobile-label">Code: </span><code><?php echo esc_html($edible['short_code']); ?></code></td>
                                <td data-label="Brand"><span class="adc-mobile-label">Brand: </span><?php echo esc_html($edible['brand'] ?: '-'); ?></td>
                                <td data-label="Type"><span class="adc-mobile-label">Type: </span><?php echo esc_html(ucfirst($edible['product_type'])); ?></td>
                                <td data-label="Psilocybin"><span class="adc-mobile-label">Psilocybin: </span><?php echo number_format($edible['psilocybin'] ?? 0); ?></td>
                                <td data-label="Pieces"><span class="adc-mobile-label">Pieces: </span><?php echo $edible['pieces_per_package']; ?></td>
                                <td data-label="mcg/pkg"><span class="adc-mobile-label">mcg/pkg: </span><strong><?php echo number_format($mcg_per_piece); ?></strong></td>
                                <td data-label="Status">
                                    <?php if ($edible['is_active']): ?>
                                        <span class="adc-status-active">● Active</span>
                                    <?php else: ?>
                                        <span class="adc-status-inactive">○ Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions" class="adc-actions-cell">
                                    <a href="<?php echo admin_url('admin.php?page=dosage-calculator-add-edible&id=' . $edible['id']); ?>" class="button button-small">Edit</a>
                                    <a href="#" class="button button-small adc-delete-edible adc-btn-delete" data-id="<?php echo $edible['id']; ?>" data-name="<?php echo esc_attr($edible['name']); ?>">Delete</a>
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
     * Edible add/edit form
     */
    public function render_edible_form() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $edible = $id ? ADC_Edibles::get($id) : null;
        $is_edit = !empty($edible);
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adc_edible_nonce'])) {
            if (!wp_verify_nonce($_POST['adc_edible_nonce'], 'adc_save_edible')) {
                wp_die('Security check failed');
            }
            
            $data = array(
                'name' => sanitize_text_field(wp_unslash($_POST['name'])),
                'short_code' => sanitize_text_field(wp_unslash($_POST['short_code'])),
                'brand' => sanitize_text_field(wp_unslash($_POST['brand'])),
                'product_type' => sanitize_key(wp_unslash($_POST['product_type'])),
                'batch_number' => sanitize_text_field(wp_unslash($_POST['batch_number'])),
                'pieces_per_package' => max(1, absint($_POST['pieces_per_package'])),
                'psilocybin' => absint($_POST['psilocybin'] ?? 0),
                'psilocin' => absint($_POST['psilocin'] ?? 0),
                'norpsilocin' => absint($_POST['norpsilocin'] ?? 0),
                'baeocystin' => absint($_POST['baeocystin'] ?? 0),
                'norbaeocystin' => absint($_POST['norbaeocystin'] ?? 0),
                'aeruginascin' => absint($_POST['aeruginascin'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'notes' => sanitize_textarea_field($_POST['notes']),
                'sort_order' => intval($_POST['sort_order']),
            );
            
            if ($is_edit) {
                $result = ADC_Edibles::update($id, $data);
            } else {
                $result = ADC_Edibles::create($data);
            }
            
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Edible saved successfully!</p></div>';
                if (!$is_edit) {
                    $edible = ADC_Edibles::get($result);
                    $is_edit = true;
                    $id = $result;
                } else {
                    $edible = ADC_Edibles::get($id);
                }
            }
        }
        
        // Get product types
        $product_types = ADC_Edibles::get_product_types();
        
        ?>
        <div class="wrap adc-admin">
            <h1><?php echo $is_edit ? 'Edit Edible' : 'Add New Edible'; ?></h1>
            
            <form method="post">
                <?php wp_nonce_field('adc_save_edible', 'adc_edible_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="name">Name <span class="required">*</span></label></th>
                        <td><input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr($edible['name'] ?? ''); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="short_code">Short Code</label></th>
                        <td>
                            <input type="text" id="short_code" name="short_code" class="regular-text" value="<?php echo esc_attr($edible['short_code'] ?? ''); ?>" placeholder="Auto-generated if empty">
                            <p class="description">Used for QR codes. Example: ZD-E-CH-001</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="brand">Brand</label></th>
                        <td><input type="text" id="brand" name="brand" class="regular-text" value="<?php echo esc_attr($edible['brand'] ?? ''); ?>" placeholder="Optional"></td>
                    </tr>
                    <tr>
                        <th><label for="product_type">Product Type <span class="required">*</span></label></th>
                        <td>
                            <select id="product_type" name="product_type" required>
                                <?php foreach ($product_types as $type): ?>
                                    <option value="<?php echo esc_attr($type['slug']); ?>" <?php selected($edible['product_type'] ?? '', $type['slug']); ?>>
                                        <?php echo esc_html($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="batch_number">Batch Number</label></th>
                        <td><input type="text" id="batch_number" name="batch_number" class="regular-text" value="<?php echo esc_attr($edible['batch_number'] ?? ''); ?>"></td>
                    </tr>
                </table>
                
                <h2>Dosage Information</h2>
                <p class="description">Enter compound amounts in <strong>mcg (micrograms) PER PACKAGE</strong>.</p>
                <table class="form-table">
                    <tr>
                        <th><label for="pieces_per_package">Pieces per Package <span class="required">*</span></label></th>
                        <td><input type="number" id="pieces_per_package" name="pieces_per_package" value="<?php echo esc_attr($edible['pieces_per_package'] ?? 1); ?>" min="1" required></td>
                    </tr>
                </table>
                
                <h3>Compound Amounts (mcg per package)</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="psilocybin">Psilocybin <span class="required">*</span></label></th>
                        <td><input type="number" id="psilocybin" name="psilocybin" value="<?php echo isset($edible['id']) ? esc_attr($edible['psilocybin']) : ''; ?>" min="0" placeholder="0"> mcg/pkg</td>
                    </tr>
                    <tr>
                        <th><label for="psilocin">Psilocin</label></th>
                        <td><input type="number" id="psilocin" name="psilocin" value="<?php echo isset($edible['id']) ? esc_attr($edible['psilocin']) : ''; ?>" min="0" placeholder="0"> mcg/pkg</td>
                    </tr>
                    <tr>
                        <th><label for="norpsilocin">Norpsilocin</label></th>
                        <td><input type="number" id="norpsilocin" name="norpsilocin" value="<?php echo isset($edible['id']) ? esc_attr($edible['norpsilocin']) : ''; ?>" min="0" placeholder="0"> mcg/pkg</td>
                    </tr>
                    <tr>
                        <th><label for="baeocystin">Baeocystin</label></th>
                        <td><input type="number" id="baeocystin" name="baeocystin" value="<?php echo isset($edible['id']) ? esc_attr($edible['baeocystin']) : ''; ?>" min="0" placeholder="0"> mcg/pkg</td>
                    </tr>
                    <tr>
                        <th><label for="norbaeocystin">Norbaeocystin</label></th>
                        <td><input type="number" id="norbaeocystin" name="norbaeocystin" value="<?php echo isset($edible['id']) ? esc_attr($edible['norbaeocystin']) : ''; ?>" min="0" placeholder="0"> mcg/pkg</td>
                    </tr>
                    <tr>
                        <th><label for="aeruginascin">Aeruginascin</label></th>
                        <td><input type="number" id="aeruginascin" name="aeruginascin" value="<?php echo isset($edible['id']) ? esc_attr($edible['aeruginascin']) : ''; ?>" min="0" placeholder="0"> mcg/pkg</td>
                    </tr>
                    <tr>
                        <th>Total mcg/pkg</th>
                        <td>
                            <strong id="total_mcg_pkg" class="adc-total-display"><?php 
                                $total_mcg = ($edible['psilocybin'] ?? 0) + ($edible['psilocin'] ?? 0);
                                echo number_format($total_mcg);
                            ?></strong> mcg
                            <p class="description">Sum of Psilocybin + Psilocin (used for dose calculation).</p>
                        </td>
                    </tr>
                </table>
                
                <h3>Per Piece Breakdown</h3>
                <p class="description adc-mb-10">Calculated from package totals ÷ pieces per package</p>
                <?php $pieces = max(1, $edible['pieces_per_package'] ?? 1); ?>
                <div id="per-piece-breakdown" class="adc-breakdown-box">
                    <p class="adc-breakdown-row"><strong>Psilocybin:</strong> <span id="pp_psilocybin"><?php echo number_format(($edible['psilocybin'] ?? 0) / $pieces); ?></span> mcg/piece</p>
                    <p class="adc-breakdown-row"><strong>Psilocin:</strong> <span id="pp_psilocin"><?php echo number_format(($edible['psilocin'] ?? 0) / $pieces); ?></span> mcg/piece</p>
                    <p class="adc-breakdown-row"><strong>Norpsilocin:</strong> <span id="pp_norpsilocin"><?php echo number_format(($edible['norpsilocin'] ?? 0) / $pieces); ?></span> mcg/piece</p>
                    <p class="adc-breakdown-row"><strong>Baeocystin:</strong> <span id="pp_baeocystin"><?php echo number_format(($edible['baeocystin'] ?? 0) / $pieces); ?></span> mcg/piece</p>
                    <p class="adc-breakdown-row"><strong>Norbaeocystin:</strong> <span id="pp_norbaeocystin"><?php echo number_format(($edible['norbaeocystin'] ?? 0) / $pieces); ?></span> mcg/piece</p>
                    <p class="adc-breakdown-row"><strong>Aeruginascin:</strong> <span id="pp_aeruginascin"><?php echo number_format(($edible['aeruginascin'] ?? 0) / $pieces); ?></span> mcg/piece</p>
                    <p class="adc-breakdown-total"><strong class="adc-text-success">Total (Psilo):</strong> <span id="pp_total" class="adc-text-success adc-text-bold"><?php echo number_format($total_mcg / $pieces); ?></span> mcg/piece</p>
                </div>
                
                <h2>Additional Options</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="is_active">Status</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_active" name="is_active" value="1" <?php checked($edible['is_active'] ?? 1, 1); ?>>
                                Active (visible in calculator)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sort_order">Sort Order</label></th>
                        <td><input type="number" id="sort_order" name="sort_order" value="<?php echo esc_attr($edible['sort_order'] ?? 0); ?>" min="0"></td>
                    </tr>
                    <tr>
                        <th><label for="notes">Internal Notes</label></th>
                        <td><textarea id="notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea($edible['notes'] ?? ''); ?></textarea></td>
                    </tr>
                </table>
                
                <?php submit_button($is_edit ? 'Update Edible' : 'Add Edible'); ?>
            </form>
        </div>
        
        <?php
    }

    /**
     * AJAX: Delete edible
     */
    public function ajax_delete_edible() {
        check_ajax_referer('adc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error('Invalid edible ID');
        }
        
        $result = ADC_Edibles::delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success();
        }
    }
}
