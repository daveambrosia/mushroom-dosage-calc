<?php
/**
 * Admin Settings Page
 *
 * Handles calculator settings, database status, data reset, and admin notices.
 *
 * @since 2.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADC_Admin_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('adc_settings_group', 'adc_settings');
    }

    /**
     * Settings page
     */
    public function render_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Handle data reset requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adc_reset_nonce'])) {
            if (wp_verify_nonce($_POST['adc_reset_nonce'], 'adc_reset_data')) {
                global $wpdb;
                $reset_type = sanitize_text_field($_POST['reset_type'] ?? '');
                $messages = array();

                if ($reset_type === 'strains') {
                    $table = ADC_DB::table('strains');
                    $wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
                    $wpdb->query("DELETE FROM `" . esc_sql($table) . "`");
                    $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` AUTO_INCREMENT = 1");
                    $wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
                    delete_transient('adc_strains_by_category');
                    ADC_Strains::clear_cache();
                    $remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM `" . esc_sql($table) . "`");
                    $messages[] = $remaining === 0 ? 'All strain data has been cleared.' : "Warning: $remaining strains could not be deleted.";
                } elseif ($reset_type === 'edibles') {
                    $table = ADC_DB::table('edibles');
                    $wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
                    $wpdb->query("DELETE FROM `" . esc_sql($table) . "`");
                    $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` AUTO_INCREMENT = 1");
                    $wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
                    delete_transient('adc_edibles_by_type');
                    $remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM `" . esc_sql($table) . "`");
                    $messages[] = $remaining === 0 ? 'All edible data has been cleared.' : "Warning: $remaining edibles could not be deleted.";
                } elseif ($reset_type === 'categories') {
                    $table = ADC_DB::table('categories');
                    $wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
                    $wpdb->query("DELETE FROM `" . esc_sql($table) . "`");
                    $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` AUTO_INCREMENT = 1");
                    $wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
                    delete_transient('adc_categories');
                    ADC_Strains::clear_cache();
                    $messages[] = 'All categories have been cleared.';
                } elseif ($reset_type === 'product_types') {
                    $table = ADC_DB::table('product_types');
                    $wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
                    $wpdb->query("DELETE FROM `" . esc_sql($table) . "`");
                    $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` AUTO_INCREMENT = 1");
                    $wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
                    delete_transient('adc_product_types');
                    delete_transient('adc_product_types_with_counts');
                    $messages[] = 'All product types have been cleared.';
                }

                if (!empty($messages)) {
                    foreach ($messages as $msg) {
                        echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
                    }
                }
            }
        }

        // Handle database repair request
        if (isset($_GET['repair_db']) && $_GET['repair_db'] === '1') {
            check_admin_referer('adc_repair_db');
            if (class_exists('ADC_Activator')) {
                $result = ADC_Activator::repair_database();
                delete_transient('adc_db_health'); // Force fresh health check after repair
                if ($result['success']) {
                    delete_option('adc_activation_errors');
                    echo '<div class="notice notice-success"><p>Database repair completed successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Database repair encountered errors:</p><ul>';
                    foreach ($result['errors'] as $err) {
                        echo '<li>' . esc_html($err) . '</li>';
                    }
                    echo '</ul></div>';
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adc_settings_nonce'])) {
            if (!wp_verify_nonce($_POST['adc_settings_nonce'], 'adc_save_settings')) {
                wp_die('Security check failed');
            }
            
            $settings = array(
                'template' => sanitize_key($_POST['template']),
                'default_tab' => sanitize_key($_POST['default_tab']),
                'show_edibles' => isset($_POST['show_edibles']),
                'show_mushrooms' => isset($_POST['show_mushrooms']),
                'show_quick_converter' => isset($_POST['show_quick_converter']),
                'show_compound_breakdown' => isset($_POST['show_compound_breakdown']),
                'show_safety_warning' => isset($_POST['show_safety_warning']),
                'allow_custom' => isset($_POST['allow_custom']),
                'allow_submit' => isset($_POST['allow_submit']),
                'short_url_path' => sanitize_key($_POST['short_url_path']),
                'short_code_prefix' => strtoupper(sanitize_text_field($_POST['short_code_prefix'])),
                'disclaimer_text' => wp_kses_post($_POST['disclaimer_text'] ?? ''),
                'custom_css' => wp_strip_all_tags($_POST['custom_css'] ?? ''),
                'auto_submit_unknown_qr' => isset($_POST['auto_submit_unknown_qr']),
                'submission_notification_email' => sanitize_email($_POST['submission_notification_email']),
                'calculator_title' => wp_kses_post($_POST['calculator_title'] ?? ''),
                'calculator_subtitle' => wp_kses_post($_POST['calculator_subtitle'] ?? ''),
                'safety_items' => wp_kses_post($_POST['safety_items'] ?? ''),
            );
            
            // Only flush rewrite rules if short URL path actually changed
            $old_path = ADC_DB::get_setting('short_url_path', 'c');
            
            ADC_DB::update_settings($settings);
            
            if ($settings['short_url_path'] !== $old_path) {
                flush_rewrite_rules();
            }
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $settings = ADC_DB::get_settings();
        
        ?>
        <div class="wrap adc-admin">
            <h1>⚙️ Calculator Settings</h1>
            
            <form method="post" id="adc-settings-form">
                <?php wp_nonce_field('adc_save_settings', 'adc_settings_nonce'); ?>
                
                <nav class="nav-tab-wrapper adc-settings-tabs">
                    <a href="#tab-features" class="nav-tab nav-tab-active" data-tab="features">⚙️ Features</a>
                    <a href="#tab-content" class="nav-tab" data-tab="content">📝 Customize</a>
                    <a href="#tab-template" class="nav-tab" data-tab="template">🎨 Template</a>
                    <a href="#tab-qr" class="nav-tab" data-tab="qr">📱 QR Codes</a>
                    <a href="#tab-database" class="nav-tab" data-tab="database">🗄️ Database</a>
                    <a href="#tab-reset" class="nav-tab" data-tab="reset">🗑️ Reset Data</a>
                </nav>

                <!-- TAB: Template -->
                <div id="tab-template" class="adc-tab-panel" style="display:none">
                    <h2>Visual Template</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="template">Active Template</label></th>
                            <td>
                                <select id="template" name="template">
                                    <?php
                                    $builtins = ADC_Template_Builder::get_builtin_templates();
                                    $custom_templates = ADC_Template_Builder::get_custom_templates();
                                    $current = $settings['template'] ?? 'brutal';
                                    ?>
                                    <optgroup label="<?php esc_attr_e('Built-in', 'ambrosia-dosage-calc'); ?>">
                                    <?php foreach ($builtins as $slug => $bt) : ?>
                                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($current, $slug); ?>><?php echo esc_html($bt['name']); ?><?php echo $slug === 'brutal' ? ' (Default)' : ''; ?></option>
                                    <?php endforeach; ?>
                                    </optgroup>
                                    <?php if (!empty($custom_templates)) : ?>
                                    <optgroup label="<?php esc_attr_e('Custom', 'ambrosia-dosage-calc'); ?>">
                                        <?php foreach ($custom_templates as $ct) : ?>
                                        <option value="<?php echo esc_attr($ct['slug']); ?>" <?php selected($current, $ct['slug']); ?>><?php echo esc_html($ct['name']); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endif; ?>
                                </select>
                                <?php
                                // Show color swatches for the current template
                                $swatch_vars = array();
                                if (strpos($current, 'custom-') === 0) {
                                    $ct = ADC_Template_Builder::get_custom_template($current);
                                    if ($ct) $swatch_vars = $ct['variables'];
                                } elseif (isset($builtins[$current])) {
                                    $swatch_vars = $builtins[$current]['variables'];
                                }
                                $swatch_keys = array('bg', 'text', 'accent', 'surface', 'header-bg', 'border');
                                ?>
                                <span style="display:inline-flex;gap:3px;margin-left:10px;vertical-align:middle;">
                                    <?php foreach ($swatch_keys as $sk) :
                                        $color = $swatch_vars[$sk] ?? '';
                                        if ($color && preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) : ?>
                                        <span style="width:20px;height:20px;border:1px solid #ccc;display:inline-block;border-radius:2px;background:<?php echo esc_attr($color); ?>" title="<?php echo esc_attr($sk); ?>"></span>
                                    <?php endif; endforeach; ?>
                                </span>
                                <p class="description"><?php _e('Choose the visual style for your calculator.', 'ambrosia-dosage-calc'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top:30px;"><?php _e('Custom Templates', 'ambrosia-dosage-calc'); ?></h3>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=dosage-calculator-template-builder&action=new')); ?>" class="button button-primary">+ <?php _e('Create New Template', 'ambrosia-dosage-calc'); ?></a>
                    </p>
                    <?php
                    if (!empty($custom_templates)) :
                    ?>
                    <table class="widefat striped" style="max-width:800px;">
                        <thead><tr>
                            <th><?php _e('Name', 'ambrosia-dosage-calc'); ?></th>
                            <th><?php _e('Colors', 'ambrosia-dosage-calc'); ?></th>
                            <th><?php _e('Actions', 'ambrosia-dosage-calc'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($custom_templates as $ct) :
                            $ct_slug = esc_attr($ct['slug']);
                            $is_active = ($current === $ct['slug']);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($ct['name']); ?></strong>
                                <?php if ($is_active) : ?><span style="color:#2271b1;font-weight:600;margin-left:6px;">✓ Active</span><?php endif; ?>
                            </td>
                            <td>
                                <span style="display:inline-flex;gap:2px;">
                                <?php foreach ($swatch_keys as $sk) :
                                    $color = $ct['variables'][$sk] ?? '';
                                    if ($color && preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) : ?>
                                    <span style="display:inline-block;width:16px;height:16px;background:<?php echo esc_attr($color); ?>;border:1px solid #ccc;border-radius:2px;" title="<?php echo esc_attr($sk); ?>"></span>
                                <?php endif; endforeach; ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=dosage-calculator-template-builder&action=edit&slug=' . $ct_slug)); ?>" class="button button-small"><?php _e('Edit', 'ambrosia-dosage-calc'); ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                    <p style="color:#666;"><?php _e('No custom templates yet. Create one to customize colors, fonts, and layout.', 'ambrosia-dosage-calc'); ?></p>
                    <?php endif; ?>

                    <h3 style="margin-top:30px;"><?php _e('Built-in Templates', 'ambrosia-dosage-calc'); ?></h3>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;">
                    <?php foreach ($builtins as $slug => $bt) :
                        $start_url = admin_url('admin.php?page=dosage-calculator-template-builder&action=new&start_from=' . esc_attr($slug));
                    ?>
                        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:6px;padding:10px 14px;width:140px;text-align:center;">
                            <div style="font-weight:600;font-size:13px;margin-bottom:6px;"><?php echo esc_html($bt['name']); ?></div>
                            <div style="display:flex;gap:2px;justify-content:center;margin-bottom:8px;">
                            <?php foreach ($swatch_keys as $sk) :
                                $color = $bt['variables'][$sk] ?? '';
                                if ($color && preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) : ?>
                                <span style="display:inline-block;width:14px;height:14px;background:<?php echo esc_attr($color); ?>;border:1px solid #ddd;border-radius:2px;"></span>
                            <?php endif; endforeach; ?>
                            </div>
                            <a href="<?php echo esc_url($start_url); ?>" class="button button-small" style="font-size:11px;"><?php _e('Customize', 'ambrosia-dosage-calc'); ?></a>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>

                <!-- TAB: Features -->
                <div id="tab-features" class="adc-tab-panel">
                    <h2>Features</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="default_tab">Default Tab</label></th>
                            <td>
                                <select id="default_tab" name="default_tab">
                                    <option value="mushrooms" <?php selected($settings['default_tab'] ?? '', 'mushrooms'); ?>>Mushrooms</option>
                                    <option value="edibles" <?php selected($settings['default_tab'] ?? '', 'edibles'); ?>>Edibles</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Features</th>
                            <td>
                                <label><input type="checkbox" name="show_mushrooms" <?php checked($settings['show_mushrooms'] ?? true); ?>> Show mushroom calculator</label><br>
                                <label><input type="checkbox" name="show_edibles" <?php checked($settings['show_edibles'] ?? true); ?>> Show edibles calculator</label><br>
                                <label><input type="checkbox" name="show_quick_converter" <?php checked($settings['show_quick_converter'] ?? true); ?>> Show quick converter</label><br>
                                <label><input type="checkbox" name="show_compound_breakdown" <?php checked($settings['show_compound_breakdown'] ?? true); ?>> Show compound breakdown</label><br>
                                <label><input type="checkbox" name="show_safety_warning" <?php checked($settings['show_safety_warning'] ?? true); ?>> Show safety warning</label><br>
                            </td>
                        </tr>
                        <tr>
                            <th>User Features</th>
                            <td>
                                <label><input type="checkbox" name="allow_custom" <?php checked($settings['allow_custom'] ?? true); ?>> Allow custom strain/edible entry</label><br>
                                <label><input type="checkbox" name="allow_submit" <?php checked($settings['allow_submit'] ?? true); ?>> Allow submissions to church</label><br>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- TAB: QR Codes -->
                <div id="tab-qr" class="adc-tab-panel" style="display:none">
                    <h2>QR Code Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="short_url_path">Short URL Path</label></th>
                            <td>
                                <code><?php echo home_url('/'); ?></code>
                                <input type="text" id="short_url_path" name="short_url_path" value="<?php echo esc_attr($settings['short_url_path'] ?? 'c'); ?>" class="adc-input-short">
                                <code>/[SHORT_CODE]</code>
                                <p class="description">Path used for QR code short URLs.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="short_code_prefix">Short Code Prefix</label></th>
                            <td>
                                <input type="text" id="short_code_prefix" name="short_code_prefix" value="<?php echo esc_attr($settings['short_code_prefix'] ?? 'ZD'); ?>" class="adc-input-short">
                                <p class="description">Prefix for generated short codes. Example: ZD for "Zide Door".</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Auto-Submit Unknown QR</th>
                            <td>
                                <label><input type="checkbox" name="auto_submit_unknown_qr" <?php checked($settings['auto_submit_unknown_qr'] ?? true); ?>> Automatically submit unknown QR codes to review queue</label>
                                <p class="description">When someone scans a QR code not in your database, the data is saved for review.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="submission_notification_email">Notification Email</label></th>
                            <td>
                                <input type="email" id="submission_notification_email" name="submission_notification_email" class="regular-text" value="<?php echo esc_attr($settings['submission_notification_email'] ?? ''); ?>">
                                <p class="description">Receive email when new submissions arrive. Leave empty to disable.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- TAB: Content -->
                <div id="tab-content" class="adc-tab-panel" style="display:none">
                    <h2>Customize</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="calculator_title">Calculator Title</label></th>
                            <td>
                                <input type="text" id="calculator_title" name="calculator_title" class="large-text"
                                    value="<?php echo esc_attr($settings['calculator_title'] ?? '🍄 Psilocybin Dosage Calculator'); ?>">
                                <p class="description">The heading shown at the top of the calculator. Emoji welcome.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Calculator Description</label></th>
                            <td>
                                <?php
                                wp_editor(
                                    $settings['calculator_subtitle'] ?? 'Psilocybin Dosage Calculator: For use with lab-tested mushrooms and edibles. Every mushroom is different; even ones growing side by side can be twice as strong as the other. Lab tests show some can be up to 80 times stronger than others. Strength depends on the strain, the grower, what they\'re grown on, how they\'re dried, and how they\'re stored.',
                                    'calculator_subtitle',
                                    array(
                                        'textarea_name' => 'calculator_subtitle',
                                        'media_buttons' => false,
                                        'textarea_rows' => 5,
                                        'teeny'         => true,
                                        'quicktags'     => true,
                                    )
                                );
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Safety Information</label></th>
                            <td>
                                <p class="description">Shown in the Safety Information section of the calculator. Use bold for emphasis.</p>
                                <?php
                                $safety_default = '<ul><li><strong>DO NOT</strong> drive or operate heavy machinery</li><li><strong>DO NOT</strong> mix with alcohol</li><li>RESEARCH all medications before use</li><li><strong>DO NOT</strong> take if you have heart problems</li></ul>';
                                $safety_val = $settings['safety_items'] ?? '';
                                // Migrate legacy plain-text to HTML on first display
                                if (!empty($safety_val) && strpos($safety_val, '<') === false) {
                                    $lines = array_filter(array_map('trim', explode("\n", $safety_val)));
                                    $safety_val = '<ul><li>' . implode('</li><li>', array_map('esc_html', $lines)) . '</li></ul>';
                                }
                                wp_editor(
                                    $safety_val ?: $safety_default,
                                    'safety_items',
                                    array(
                                        'textarea_name' => 'safety_items',
                                        'media_buttons' => false,
                                        'textarea_rows' => 6,
                                        'teeny'         => true,
                                        'quicktags'     => true,
                                    )
                                );
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Disclaimer Text</label></th>
                            <td>
                                <?php
                                wp_editor(
                                    $settings['disclaimer_text'] ?? 'For educational and spiritual purposes only.',
                                    'disclaimer_text',
                                    array(
                                        'textarea_name' => 'disclaimer_text',
                                        'media_buttons' => false,
                                        'textarea_rows' => 3,
                                        'teeny'         => true,
                                        'quicktags'     => true,
                                    )
                                );
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="custom_css">Custom CSS</label></th>
                            <td>
                                <textarea id="custom_css" name="custom_css" rows="10" class="large-text code"><?php echo esc_textarea($settings['custom_css'] ?? ''); ?></textarea>
                                <p class="description">Additional CSS applied after the template. Use <code>#adc-calculator</code> to scope rules.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Submit bar — hidden for non-settings tabs -->
                <div id="adc-submit-bar">
                    <?php submit_button('Save Settings'); ?>
                </div>
            </form>

            <!-- TAB: Database Status (outside the settings form) -->
            <div id="tab-database" class="adc-tab-panel" style="display:none">
                <h2>Database Status</h2>
                <?php
                if (class_exists('ADC_Activator')) {
                    $health = ADC_Activator::check_database_health();
                    if ($health['ok']) {
                        echo '<div class="notice notice-success inline"><p>✅ All database tables are healthy.</p></div>';
                    } else {
                        echo '<div class="notice notice-error inline"><p>❌ Database issues detected:</p><ul>';
                        foreach ($health['errors'] as $error) {
                            echo '<li>' . esc_html($error) . '</li>';
                        }
                        echo '</ul></div>';
                    }
                    echo '<table class="widefat" style="margin-top: 15px; max-width: 600px;">';
                    echo '<thead><tr><th>Table</th><th>Status</th><th>Rows</th></tr></thead><tbody>';
                    foreach ($health['tables'] as $table => $info) {
                        $status = $info['exists'] ? '✅ OK' : '❌ Missing';
                        $rows = $info['exists'] ? $info['row_count'] : '-';
                        echo '<tr><td>' . esc_html($table) . '</td><td>' . $status . '</td><td>' . esc_html($rows) . '</td></tr>';
                    }
                    echo '</tbody></table>';
                }
                ?>
                <p style="margin-top: 15px;">
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=dosage-calculator-settings&repair_db=1'), 'adc_repair_db')); ?>" class="button">🔧 Repair Database</a>
                </p>
            </div>

            <!-- TAB: Reset Data (outside the settings form) -->
            <div id="tab-reset" class="adc-tab-panel" style="display:none">
                <h2>⚠️ Reset Data</h2>
                <p>Clear data from specific tables. <strong>This action cannot be undone.</strong></p>
                <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:20px;">
                    <form method="post" style="margin:0">
                        <?php wp_nonce_field('adc_reset_data', 'adc_reset_nonce'); ?>
                        <input type="hidden" name="reset_type" value="strains">
                        <button type="submit" class="button adc-btn-delete" onclick="return confirm('⚠️ This will permanently delete ALL strain data. Are you sure?');">🗑️ Clear All Strains</button>
                    </form>
                    <form method="post" style="margin:0">
                        <?php wp_nonce_field('adc_reset_data', 'adc_reset_nonce'); ?>
                        <input type="hidden" name="reset_type" value="edibles">
                        <button type="submit" class="button adc-btn-delete" onclick="return confirm('⚠️ This will permanently delete ALL edible data. Are you sure?');">🗑️ Clear All Edibles</button>
                    </form>
                    <form method="post" style="margin:0">
                        <?php wp_nonce_field('adc_reset_data', 'adc_reset_nonce'); ?>
                        <input type="hidden" name="reset_type" value="categories">
                        <button type="submit" class="button" onclick="return confirm('⚠️ This will permanently delete ALL categories. Strains will lose their category assignments. Are you sure?');">🗑️ Clear Categories</button>
                    </form>
                    <form method="post" style="margin:0">
                        <?php wp_nonce_field('adc_reset_data', 'adc_reset_nonce'); ?>
                        <input type="hidden" name="reset_type" value="product_types">
                        <button type="submit" class="button" onclick="return confirm('⚠️ This will permanently delete ALL product types. Edibles will lose their type assignments. Are you sure?');">🗑️ Clear Product Types</button>
                    </form>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var SETTINGS_TABS = ['features', 'content', 'template', 'qr'];
                var tabs   = document.querySelectorAll('.adc-settings-tabs .nav-tab');
                var panels = document.querySelectorAll('.adc-tab-panel');
                var submitBar = document.getElementById('adc-submit-bar');

                var activeTab = localStorage.getItem('adc_settings_tab') || 'features';

                function activateTab(tabName) {
                    tabs.forEach(function(t) {
                        t.classList.toggle('nav-tab-active', t.dataset.tab === tabName);
                    });
                    panels.forEach(function(p) {
                        p.style.display = p.id === 'tab-' + tabName ? '' : 'none';
                    });
                    if (submitBar) {
                        submitBar.style.display = SETTINGS_TABS.indexOf(tabName) >= 0 ? '' : 'none';
                    }
                    localStorage.setItem('adc_settings_tab', tabName);

                    if (tabName === 'content' && window.tinyMCE) {
                        ['calculator_subtitle', 'safety_items', 'disclaimer_text'].forEach(function(id) {
                            var ed = tinyMCE.get(id);
                            if (ed) ed.fire('resize');
                        });
                    }
                }

                tabs.forEach(function(tab) {
                    tab.addEventListener('click', function(e) {
                        e.preventDefault();
                        activateTab(this.dataset.tab);
                    });
                });

                activateTab(activeTab);
            });
            </script>
        </div>
        <?php
    }

    /**
     * Display admin notices for activation errors and database issues
     */
    public function display_admin_notices() {
        // Only show on plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'dosage-calculator') === false) {
            return;
        }
        
        // Check for activation errors
        $errors = get_option('adc_activation_errors', array());
        $warnings = get_option('adc_activation_warnings', array());
        
        if (!empty($errors)) {
            echo '<div class="notice notice-error"><p><strong>Dosage Calculator - Setup Errors:</strong></p><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul><p><a href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=dosage-calculator-settings&repair_db=1'), 'adc_repair_db')) . '" class="button">Repair Database</a></p></div>';
        }
        
        if (!empty($warnings)) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>Dosage Calculator - Warnings:</strong></p><ul>';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul></div>';
        }
        
        // Check database health on main plugin pages (cached 5 min to avoid 12+ queries per page load)
        if (class_exists('ADC_Activator')) {
            $health = get_transient('adc_db_health');
            if ($health === false) {
                $health = ADC_Activator::check_database_health();
                set_transient('adc_db_health', $health, 5 * MINUTE_IN_SECONDS);
            }
            if (!$health['ok'] && empty($errors)) {
                echo '<div class="notice notice-error"><p><strong>Dosage Calculator - Database Issues Detected:</strong></p><ul>';
                foreach ($health['errors'] as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul><p><a href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=dosage-calculator-settings&repair_db=1'), 'adc_repair_db')) . '" class="button button-primary">Repair Database</a></p></div>';
            }
        }
    }
}
