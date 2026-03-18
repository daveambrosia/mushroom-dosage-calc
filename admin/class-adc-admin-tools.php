<?php
/**
 * Admin Tools — Taxonomies, Import/Export, Categories, Product Types
 *
 * Handles taxonomy management, import/export tabs, export handler, categories, and product types.
 *
 * @since 2.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADC_Admin_Tools {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_adc_export', array($this, 'handle_export'));
    }

    /**
     * Tabbed page: Taxonomies (Categories + Product Types)
     */
    public function render_taxonomies() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'categories';
        $base_url = admin_url('admin.php?page=dosage-calculator-taxonomies');
        ?>
        <div class="wrap">
            <h1>Taxonomies</h1>
            <nav class="nav-tab-wrapper" style="margin-bottom:20px">
                <a href="<?php echo esc_url($base_url . '&tab=categories'); ?>" class="nav-tab <?php echo $tab === 'categories' ? 'nav-tab-active' : ''; ?>">Categories</a>
                <a href="<?php echo esc_url($base_url . '&tab=product-types'); ?>" class="nav-tab <?php echo $tab === 'product-types' ? 'nav-tab-active' : ''; ?>">Product Types</a>
            </nav>
        </div>
        <?php
        if ($tab === 'product-types') {
            $this->render_product_types();
        } else {
            $this->render_categories();
        }
    }

    /**
     * Tabbed page: Import / Export
     */
    public function render_import_export() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'google-sheets';
        $base_url = admin_url('admin.php?page=dosage-calculator-import-export');
        ?>
        <div class="wrap">
            <h1>Import / Export</h1>
            <nav class="nav-tab-wrapper" style="margin-bottom:20px">
                <a href="<?php echo esc_url($base_url . '&tab=google-sheets'); ?>" class="nav-tab <?php echo $tab === 'google-sheets' ? 'nav-tab-active' : ''; ?>">Google Sheets</a>
                <a href="<?php echo esc_url($base_url . '&tab=csv'); ?>" class="nav-tab <?php echo $tab === 'csv' ? 'nav-tab-active' : ''; ?>">CSV</a>
                <a href="<?php echo esc_url($base_url . '&tab=json'); ?>" class="nav-tab <?php echo $tab === 'json' ? 'nav-tab-active' : ''; ?>">JSON</a>
                <a href="<?php echo esc_url($base_url . '&tab=export'); ?>" class="nav-tab <?php echo $tab === 'export' ? 'nav-tab-active' : ''; ?>">Export</a>
            </nav>
        </div>
        <?php
        switch ($tab) {
            case 'csv':
                ADC_CSV_Importer::render_page();
                break;
            case 'json':
                ADC_JSON_Importer::render_page();
                break;
            case 'export':
                $this->render_export();
                break;
            default:
                ADC_Sheets_Admin_Page::render_page();
                break;
        }
    }

    /**
     * Export page
     */
    public function render_export() {
        ?>
        <div class="wrap adc-admin">
            <h1>Export Data</h1>
            <p>Export your calculator data for backup or migration purposes.</p>
            
            <div class="adc-card adc-card-narrow">
                <h2>Export Options</h2>
                
                <table class="form-table">
                    <tr>
                        <th>Strains</th>
                        <td>
                            <a href="<?php echo admin_url('admin-ajax.php?action=adc_export&type=strains&format=csv&_wpnonce=' . wp_create_nonce('adc_export')); ?>" class="button">Export CSV</a>
                            <a href="<?php echo admin_url('admin-ajax.php?action=adc_export&type=strains&format=json&_wpnonce=' . wp_create_nonce('adc_export')); ?>" class="button">Export JSON</a>
                            <p class="description"><?php global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM " . ADC_DB::table('strains')); ?> strains</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Edibles</th>
                        <td>
                            <a href="<?php echo admin_url('admin-ajax.php?action=adc_export&type=edibles&format=csv&_wpnonce=' . wp_create_nonce('adc_export')); ?>" class="button">Export CSV</a>
                            <a href="<?php echo admin_url('admin-ajax.php?action=adc_export&type=edibles&format=json&_wpnonce=' . wp_create_nonce('adc_export')); ?>" class="button">Export JSON</a>
                            <p class="description"><?php echo (int) $wpdb->get_var("SELECT COUNT(*) FROM " . ADC_DB::table('edibles')); ?> edibles</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Settings</th>
                        <td>
                            <a href="<?php echo admin_url('admin-ajax.php?action=adc_export&type=settings&format=json&_wpnonce=' . wp_create_nonce('adc_export')); ?>" class="button">Export JSON</a>
                            <p class="description">Calculator settings and configuration</p>
                        </td>
                    </tr>
                    <tr>
                        <th>All Data</th>
                        <td>
                            <a href="<?php echo admin_url('admin-ajax.php?action=adc_export&type=all&format=json&_wpnonce=' . wp_create_nonce('adc_export')); ?>" class="button button-primary">Export Everything (JSON)</a>
                            <p class="description">Complete backup of strains, edibles, and settings</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Handle export AJAX request
     */
    public function handle_export() {
        global $wpdb;
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('adc_export');
        
        $type = sanitize_text_field($_GET['type'] ?? 'all');
        $format = sanitize_text_field($_GET['format'] ?? 'json');
        
        // Whitelist validation
        $allowed_types = array('strains', 'edibles', 'settings', 'all');
        $allowed_formats = array('csv', 'json');
        if (!in_array($type, $allowed_types, true)) {
            $type = 'all';
        }
        if (!in_array($format, $allowed_formats, true)) {
            $format = 'json';
        }
        
        $data = [];
        $errors = [];
        $filename = 'adc-export-' . $type . '-' . date('Y-m-d');
        
        // Helper function to check for database errors
        $check_db_error = function($result, $name) use ($wpdb, &$errors) {
            if ($result === false || $result === null) {
                $error_msg = $wpdb->last_error ?: 'Unknown database error';
                $errors[] = "$name: $error_msg";
                return true;
            }
            return false;
        };
        
        switch ($type) {
            case 'strains':
                $raw = ADC_Strains::get_all(['active_only' => false]);
                $check_db_error($raw, 'Strains export');
                $data = $raw ? self::format_strains_for_export($raw) : [];
                break;
            case 'edibles':
                $raw = ADC_Edibles::get_all(['active_only' => false]);
                $check_db_error($raw, 'Edibles export');
                $data = $raw ? self::format_edibles_for_export($raw) : [];
                break;
            case 'settings':
                $data = get_option('adc_settings', []);
                $data['gsheets'] = get_option('adc_gsheets_settings', []);
                $format = 'json'; // Settings only as JSON
                break;
            case 'all':
                $raw_strains = ADC_Strains::get_all(['active_only' => false]);
                $check_db_error($raw_strains, 'Strains');
                
                $raw_edibles = ADC_Edibles::get_all(['active_only' => false]);
                $check_db_error($raw_edibles, 'Edibles');
                
                $data = [
                    'strains' => $raw_strains ? self::format_strains_for_export($raw_strains) : [],
                    'edibles' => $raw_edibles ? self::format_edibles_for_export($raw_edibles) : [],
                    'settings' => array_merge(get_option('adc_settings', []), ['gsheets' => get_option('adc_gsheets_settings', [])]),
                    'exported_at' => current_time('mysql'),
                    'version' => ADC_VERSION
                ];
                $format = 'json';
                break;
        }
        
        // If there were database errors, return error page instead of download
        if (!empty($errors)) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>Export Error</title>';
            echo '<style>body{font-family:sans-serif;padding:40px;max-width:600px;margin:0 auto;}';
            echo '.error{background:#f8d7da;border:1px solid #f5c6cb;padding:20px;border-radius:4px;color:#721c24;}';
            echo 'h1{color:#721c24;margin-top:0;} ul{margin:10px 0;padding-left:20px;}';
            echo '.back{margin-top:20px;} a{color:#0073aa;}</style></head><body>';
            echo '<div class="error"><h1>Export Failed</h1>';
            echo '<p>The following database errors occurred during export:</p><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
            echo '<div class="back"><a href="javascript:history.back()">&larr; Go Back</a></div>';
            echo '</body></html>';
            exit;
        }
        
        if ($format === 'csv' && in_array($type, array('strains', 'edibles'), true)) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            
            if ($output === false) {
                wp_die('Error: Could not create CSV output stream.');
            }
            
            // BOM for Excel UTF-8 compatibility
            fwrite($output, "\xEF\xBB\xBF");
            
            if (!empty($data) && is_array($data) && isset($data[0]) && is_array($data[0])) {
                // Header row from export field keys
                fputcsv($output, array_keys($data[0]));
                // Data rows
                foreach ($data as $row) {
                    if (is_array($row)) {
                        fputcsv($output, $row);
                    }
                }
            }
            
            fclose($output);
        } else {
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if ($json === false) {
                echo wp_json_encode([
                    'error' => true,
                    'message' => 'JSON encoding failed: ' . json_last_error_msg()
                ]);
            } else {
                echo $json;
            }
        }
        
        exit;
    }

    /**
     * Format strains for export (matches import format)
     */
    private static function format_strains_for_export($strains) {
        return array_map(function($strain) {
            return array(
                'name' => $strain['name'],
                'short_code' => $strain['short_code'],
                'batch_number' => $strain['batch_number'],
                'category' => $strain['category'],
                'psilocybin' => intval($strain['psilocybin']),
                'psilocin' => intval($strain['psilocin']),
                'norpsilocin' => intval($strain['norpsilocin']),
                'baeocystin' => intval($strain['baeocystin']),
                'norbaeocystin' => intval($strain['norbaeocystin']),
                'aeruginascin' => intval($strain['aeruginascin']),
                'is_active' => intval($strain['is_active']),
                'notes' => $strain['notes'],
                'sort_order' => intval($strain['sort_order']),
            );
        }, $strains);
    }

    /**
     * Format edibles for export (matches import format)
     */
    private static function format_edibles_for_export($edibles) {
        return array_map(function($edible) {
            return array(
                'name' => $edible['name'],
                'short_code' => $edible['short_code'],
                'brand' => $edible['brand'],
                'product_type' => $edible['product_type'],
                'batch_number' => $edible['batch_number'],
                'pieces_per_package' => intval($edible['pieces_per_package']),
                'psilocybin' => intval($edible['psilocybin']),
                'psilocin' => intval($edible['psilocin']),
                'norpsilocin' => intval($edible['norpsilocin']),
                'baeocystin' => intval($edible['baeocystin']),
                'norbaeocystin' => intval($edible['norbaeocystin']),
                'aeruginascin' => intval($edible['aeruginascin']),
                'is_active' => intval($edible['is_active']),
                'notes' => $edible['notes'],
                'sort_order' => intval($edible['sort_order']),
            );
        }, $edibles);
    }

    /**
     * Product Types page
     */
    public function render_product_types() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table = ADC_DB::table('product_types');
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adc_product_type_nonce'])) {
            if (!wp_verify_nonce($_POST['adc_product_type_nonce'], 'adc_save_product_type')) {
                wp_die('Security check failed');
            }
            
            $action = sanitize_key($_POST['pt_action'] ?? 'add');
            
            if ($action === 'add' && !empty($_POST['pt_name'])) {
                $name = sanitize_text_field($_POST['pt_name']);
                $slug = sanitize_key($_POST['pt_slug'] ?: sanitize_title($name));
                $sort_order = intval($_POST['pt_sort_order'] ?? 0);
                $unit_name = sanitize_text_field($_POST['pt_unit_name'] ?? 'pieces');
                
                $wpdb->insert($table, array(
                    'name' => $name,
                    'slug' => $slug,
                    'sort_order' => $sort_order,
                    'unit_name' => $unit_name,
                    'is_active' => 1
                ));
                echo '<div class="notice notice-success"><p>Product type added!</p></div>';
                // Clear cache
                delete_transient('adc_product_types');
                delete_transient('adc_product_types_with_counts');
            } elseif ($action === 'update' && !empty($_POST['pt_id'])) {
                $update_data = array();
                if (isset($_POST['pt_unit_name'])) {
                    $update_data['unit_name'] = sanitize_text_field($_POST['pt_unit_name']);
                }
                if (isset($_POST['pt_name'])) {
                    $update_data['name'] = sanitize_text_field($_POST['pt_name']);
                }
                if (isset($_POST['pt_sort_order'])) {
                    $update_data['sort_order'] = intval($_POST['pt_sort_order']);
                }
                if (!empty($update_data)) {
                    $wpdb->update($table, $update_data, array('id' => intval($_POST['pt_id'])));
                    echo '<div class="notice notice-success"><p>Product type updated!</p></div>';
                    delete_transient('adc_product_types');
                    delete_transient('adc_product_types_with_counts');
                }
            } elseif ($action === 'delete' && !empty($_POST['pt_id'])) {
                $wpdb->delete($table, array('id' => intval($_POST['pt_id'])));
                echo '<div class="notice notice-success"><p>Product type deleted!</p></div>';
                // Clear cache
                delete_transient('adc_product_types');
                delete_transient('adc_product_types_with_counts');
            }
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $product_types = $wpdb->get_results( "SELECT * FROM `" . esc_sql( $table ) . "` ORDER BY sort_order ASC", ARRAY_A );
        
        ?>
        <div class="wrap adc-admin">
            <h1>Product Types</h1>
            <p>Manage product types for edibles (chocolate, gummy, capsule, etc.)</p>
            
            <div class="adc-two-col">
                <div class="adc-col">
                    <h2>Current Product Types</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Unit Name</th>
                                <th>Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($product_types)): ?>
                                <tr><td colspan="5">No product types found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($product_types as $pt): ?>
                                    <tr>
                                        <td data-label="Name">
                                            <strong class="adc-pt-display" data-field="name" data-id="<?php echo intval($pt['id']); ?>"><?php echo esc_html($pt['name']); ?></strong>
                                            <input type="text" class="adc-pt-edit regular-text" data-field="name" data-id="<?php echo intval($pt['id']); ?>" value="<?php echo esc_attr($pt['name']); ?>" hidden>
                                        </td>
                                        <td data-label="Slug"><span class="adc-mobile-label">Slug: </span><code><?php echo esc_html($pt['slug']); ?></code></td>
                                        <td data-label="Unit Name">
                                            <span class="adc-pt-display" data-field="unit_name" data-id="<?php echo intval($pt['id']); ?>"><?php echo esc_html($pt['unit_name'] ?? 'pieces'); ?></span>
                                            <input type="text" class="adc-pt-edit" data-field="unit_name" data-id="<?php echo intval($pt['id']); ?>" value="<?php echo esc_attr($pt['unit_name'] ?? 'pieces'); ?>" style="width:120px" hidden>
                                        </td>
                                        <td data-label="Order">
                                            <span class="adc-mobile-label">Order: </span>
                                            <span class="adc-pt-display" data-field="sort_order" data-id="<?php echo intval($pt['id']); ?>"><?php echo intval($pt['sort_order']); ?></span>
                                            <input type="number" class="adc-pt-edit" data-field="sort_order" data-id="<?php echo intval($pt['id']); ?>" value="<?php echo intval($pt['sort_order']); ?>" style="width:60px" min="0" hidden>
                                        </td>
                                        <td data-label="Actions" class="adc-actions-cell">
                                            <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
                                                <button type="button" class="button button-small adc-pt-edit-btn" data-id="<?php echo intval($pt['id']); ?>">Edit</button>
                                                <form method="post" class="adc-inline-form adc-pt-save-form" data-id="<?php echo intval($pt['id']); ?>" style="display:none;gap:4px;align-items:center">
                                                    <?php wp_nonce_field('adc_save_product_type', 'adc_product_type_nonce'); ?>
                                                    <input type="hidden" name="pt_action" value="update">
                                                    <input type="hidden" name="pt_id" value="<?php echo intval($pt['id']); ?>">
                                                    <input type="hidden" name="pt_name" class="adc-pt-hidden" data-field="name" data-id="<?php echo intval($pt['id']); ?>">
                                                    <input type="hidden" name="pt_unit_name" class="adc-pt-hidden" data-field="unit_name" data-id="<?php echo intval($pt['id']); ?>">
                                                    <input type="hidden" name="pt_sort_order" class="adc-pt-hidden" data-field="sort_order" data-id="<?php echo intval($pt['id']); ?>">
                                                    <button type="submit" class="button button-small button-primary">Save</button>
                                                    <button type="button" class="button button-small adc-pt-cancel-btn" data-id="<?php echo intval($pt['id']); ?>">Cancel</button>
                                                </form>
                                                <form method="post" class="adc-inline-form adc-pt-delete-form" data-id="<?php echo intval($pt['id']); ?>" style="display:inline">
                                                    <?php wp_nonce_field('adc_save_product_type', 'adc_product_type_nonce'); ?>
                                                    <input type="hidden" name="pt_action" value="delete">
                                                    <input type="hidden" name="pt_id" value="<?php echo intval($pt['id']); ?>">
                                                    <button type="submit" class="button button-small adc-btn-delete" onclick="return adcConfirmSync('Delete this product type?', { title: 'Delete Product Type', confirmText: 'Delete', danger: true });">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="adc-col">
                    <h2>Add New Product Type</h2>
                    <form method="post">
                        <?php wp_nonce_field('adc_save_product_type', 'adc_product_type_nonce'); ?>
                        <input type="hidden" name="pt_action" value="add">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="pt_name">Name *</label></th>
                                <td><input type="text" id="pt_name" name="pt_name" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="pt_slug">Slug</label></th>
                                <td>
                                    <input type="text" id="pt_slug" name="pt_slug" class="regular-text" placeholder="Auto-generated from name">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="pt_unit_name">Unit Name</label></th>
                                <td>
                                    <input type="text" id="pt_unit_name" name="pt_unit_name" class="regular-text" value="pieces" placeholder="e.g. gummies, capsules, pieces">
                                    <p class="description">Plural label shown in calculator (e.g. "gummies", "capsules", "cups")</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="pt_sort_order">Sort Order</label></th>
                                <td><input type="number" id="pt_sort_order" name="pt_sort_order" value="0" min="0"></td>
                            </tr>
                        </table>
                        
                        <?php submit_button('Add Product Type'); ?>
                    </form>
                </div>
            </div>
        </div>
        <script>
        (function(){
            document.querySelectorAll('.adc-pt-edit-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var id = this.dataset.id;
                    // Show edit inputs, hide display spans
                    document.querySelectorAll('.adc-pt-display[data-id="'+id+'"]').forEach(function(el){ el.hidden = true; });
                    document.querySelectorAll('.adc-pt-edit[data-id="'+id+'"]').forEach(function(el){ el.hidden = false; });
                    // Show save/cancel, hide edit/delete buttons
                    btn.style.display = 'none';
                    var saveForm = document.querySelector('.adc-pt-save-form[data-id="'+id+'"]');
                    var deleteForm = document.querySelector('.adc-pt-delete-form[data-id="'+id+'"]');
                    if (saveForm) saveForm.style.display = 'flex';
                    if (deleteForm) deleteForm.style.display = 'none';
                });
            });
            document.querySelectorAll('.adc-pt-cancel-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var id = this.dataset.id;
                    document.querySelectorAll('.adc-pt-display[data-id="'+id+'"]').forEach(function(el){ el.hidden = false; });
                    document.querySelectorAll('.adc-pt-edit[data-id="'+id+'"]').forEach(function(el){ el.hidden = true; });
                    var editBtn = document.querySelector('.adc-pt-edit-btn[data-id="'+id+'"]');
                    if (editBtn) editBtn.style.display = '';
                    var saveForm = document.querySelector('.adc-pt-save-form[data-id="'+id+'"]');
                    var deleteForm = document.querySelector('.adc-pt-delete-form[data-id="'+id+'"]');
                    if (saveForm) saveForm.style.display = 'none';
                    if (deleteForm) deleteForm.style.display = 'inline';
                });
            });
            // Copy edit input values to hidden fields before submit
            document.querySelectorAll('.adc-pt-save-form').forEach(function(form){
                form.addEventListener('submit', function(){
                    var id = form.dataset.id;
                    form.querySelectorAll('.adc-pt-hidden').forEach(function(hidden){
                        var field = hidden.dataset.field;
                        var input = document.querySelector('.adc-pt-edit[data-field="'+field+'"][data-id="'+id+'"]');
                        if (input) hidden.value = input.value;
                    });
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * Render categories management page
     */
    public function render_categories() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'adc_categories';
        
        // Handle form submissions
        if (isset($_POST['cat_action']) && wp_verify_nonce($_POST['adc_category_nonce'] ?? '', 'adc_save_category')) {
            $action = sanitize_text_field($_POST['cat_action']);
            
            if ($action === 'add' && !empty($_POST['cat_name'])) {
                $name = sanitize_text_field($_POST['cat_name']);
                $slug = sanitize_key($_POST['cat_slug'] ?: sanitize_title($name));
                $type = sanitize_text_field($_POST['cat_type'] ?? 'both');
                $sort_order = intval($_POST['cat_sort_order'] ?? 0);
                
                $result = $wpdb->insert($table, array(
                    'name' => $name,
                    'slug' => $slug,
                    'type' => $type,
                    'sort_order' => $sort_order,
                    'is_active' => 1
                ));
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>Category added!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Error adding category: ' . esc_html($wpdb->last_error) . '</p></div>';
                }
                delete_transient('adc_categories');
            } elseif ($action === 'edit' && !empty($_POST['cat_id'])) {
                $wpdb->update($table, array(
                    'name' => sanitize_text_field($_POST['cat_name']),
                    'slug' => sanitize_key($_POST['cat_slug']),
                    'type' => sanitize_text_field($_POST['cat_type'] ?? 'both'),
                    'sort_order' => intval($_POST['cat_sort_order'] ?? 0),
                ), array('id' => intval($_POST['cat_id'])));
                echo '<div class="notice notice-success"><p>Category updated!</p></div>';
                delete_transient('adc_categories');
                // Clear strain cache so new sort order takes effect
                ADC_Strains::clear_cache();
            } elseif ($action === 'delete' && !empty($_POST['cat_id'])) {
                $wpdb->delete($table, array('id' => intval($_POST['cat_id'])));
                echo '<div class="notice notice-success"><p>Category deleted!</p></div>';
                delete_transient('adc_categories');
            }
        }
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        
        if (!$table_exists) {
            echo '<div class="wrap adc-admin"><h1>Categories</h1>';
            echo '<div class="notice notice-error"><p>Categories table does not exist. <a href="' . esc_url(admin_url('admin.php?page=dosage-calculator-settings&repair_db=1')) . '">Repair Database</a></p></div>';
            echo '</div>';
            return;
        }
        
        $categories = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY sort_order ASC, name ASC", ARRAY_A);
        
        ?>
        <div class="wrap adc-admin">
            <h1>Strain Categories</h1>
            <style>
                tr.adc-cat-edit-row[hidden],
                .adc-admin tr.adc-cat-edit-row[hidden],
                .wp-list-table tr.adc-cat-edit-row[hidden],
                .widefat tr.adc-cat-edit-row[hidden],
                table tr.adc-cat-edit-row[hidden] { display: none !important; height: 0 !important; overflow: hidden !important; border: 0 !important; padding: 0 !important; margin: 0 !important; }
                tr.adc-cat-view-row[hidden],
                .adc-admin tr.adc-cat-view-row[hidden],
                .wp-list-table tr.adc-cat-view-row[hidden],
                .widefat tr.adc-cat-view-row[hidden],
                table tr.adc-cat-view-row[hidden] { display: none !important; height: 0 !important; overflow: hidden !important; border: 0 !important; padding: 0 !important; margin: 0 !important; }
            </style>
            <p>Manage categories for organizing strains (e.g., Lab Tested, High Potency, Species).</p>
            
            <div class="adc-two-col">
                <div class="adc-col">
                    <h2>Current Categories</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="4">No categories found. Add some below.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr class="adc-cat-view-row" data-cat-id="<?php echo esc_attr($cat['id']); ?>">
                                        <td><strong><?php echo esc_html($cat['name']); ?></strong></td>
                                        <td><code><?php echo esc_html($cat['slug']); ?></code></td>
                                        <td><?php echo esc_html($cat['sort_order']); ?></td>
                                        <td>
                                            <button type="button" class="button button-small adc-cat-edit-btn">Edit</button>
                                            <form method="post" style="display:inline;">
                                                <?php wp_nonce_field('adc_save_category', 'adc_category_nonce'); ?>
                                                <input type="hidden" name="cat_action" value="delete">
                                                <input type="hidden" name="cat_id" value="<?php echo esc_attr($cat['id']); ?>">
                                                <button type="submit" class="button button-small adc-btn-delete" onclick="return confirm('Delete this category?');">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr class="adc-cat-edit-row" data-cat-id="<?php echo esc_attr($cat['id']); ?>" hidden>
                                        <td colspan="4" style="background:#f6f7f7;">
                                            <form method="post" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; padding:4px 0;">
                                                <?php wp_nonce_field('adc_save_category', 'adc_category_nonce'); ?>
                                                <input type="hidden" name="cat_action" value="edit">
                                                <input type="hidden" name="cat_id" value="<?php echo esc_attr($cat['id']); ?>">
                                                <input type="hidden" name="cat_type" value="strain">
                                                <input type="text" name="cat_name" value="<?php echo esc_attr($cat['name']); ?>" placeholder="Name" style="width:140px;" required>
                                                <input type="text" name="cat_slug" value="<?php echo esc_attr($cat['slug']); ?>" placeholder="Slug" style="width:120px;">
                                                <input type="number" name="cat_sort_order" value="<?php echo esc_attr($cat['sort_order']); ?>" min="0" style="width:60px;">
                                                <button type="submit" class="button button-primary button-small">Save</button>
                                                <button type="button" class="button button-small adc-cat-cancel-btn">Cancel</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="adc-col">
                    <h2>Add New Category</h2>
                    <form method="post">
                        <?php wp_nonce_field('adc_save_category', 'adc_category_nonce'); ?>
                        <input type="hidden" name="cat_action" value="add">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="cat_name">Name *</label></th>
                                <td><input type="text" id="cat_name" name="cat_name" class="regular-text" required placeholder="e.g., Lab Tested"></td>
                            </tr>
                            <tr>
                                <th><label for="cat_slug">Slug</label></th>
                                <td>
                                    <input type="text" id="cat_slug" name="cat_slug" class="regular-text" placeholder="Auto-generated from name">
                                    <p class="description">URL-friendly identifier (lowercase, no spaces)</p>
                                </td>
                            </tr>
                            <input type="hidden" name="cat_type" value="strain">
                            <tr>
                                <th><label for="cat_sort_order">Sort Order</label></th>
                                <td><input type="number" id="cat_sort_order" name="cat_sort_order" value="0" min="0"></td>
                            </tr>
                        </table>
                        
                        <?php submit_button('Add Category'); ?>
                    </form>
                </div>
            </div>
        </div>
        <script>
        document.querySelectorAll('.adc-cat-edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.closest('tr').getAttribute('data-cat-id');
                document.querySelector('.adc-cat-view-row[data-cat-id="'+id+'"]').setAttribute('hidden', '');
                document.querySelector('.adc-cat-edit-row[data-cat-id="'+id+'"]').removeAttribute('hidden');
            });
        });
        document.querySelectorAll('.adc-cat-cancel-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.closest('tr').getAttribute('data-cat-id');
                document.querySelector('.adc-cat-edit-row[data-cat-id="'+id+'"]').setAttribute('hidden', '');
                document.querySelector('.adc-cat-view-row[data-cat-id="'+id+'"]').removeAttribute('hidden');
            });
        });
        </script>
        <?php
    }
}
