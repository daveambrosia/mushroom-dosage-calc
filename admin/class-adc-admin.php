<?php
/**
 * Admin Registry & Menu Setup
 *
 * Thin orchestrator that registers the admin menu and delegates
 * to specialized admin page classes. Split from the original monolith
 * as part of spec 005-admin-refactor.
 *
 * @since 2.0.0 — original monolith
 * @since 2.13.0 — refactored to registry only
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADC_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize sub-modules (registers their own AJAX/hooks)
        ADC_Admin_Strains::get_instance();
        ADC_Admin_Edibles::get_instance();
        ADC_Admin_Settings::get_instance();
        ADC_Admin_Submissions::get_instance();
        ADC_Admin_Tools::get_instance();
        ADC_Template_Builder::get_instance();

        add_action('admin_menu', array($this, 'add_menu'));
    }
    
    public function add_menu() {
        $strains    = ADC_Admin_Strains::get_instance();
        $edibles    = ADC_Admin_Edibles::get_instance();
        $settings   = ADC_Admin_Settings::get_instance();
        $submissions = ADC_Admin_Submissions::get_instance();
        $tools      = ADC_Admin_Tools::get_instance();
        $template_builder = ADC_Template_Builder::get_instance();

        // Main menu
        add_menu_page(
            __('Dosage Calculator', 'ambrosia-dosage-calc'),
            __('Dosage Calc', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator',
            array($this, 'render_dashboard'),
            'dashicons-chart-line',
            30
        );
        
        // Dashboard (same as main)
        add_submenu_page(
            'dosage-calculator',
            __('Dashboard', 'ambrosia-dosage-calc'),
            __('Dashboard', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator',
            array($this, 'render_dashboard')
        );
        
        // Strains
        add_submenu_page(
            'dosage-calculator',
            __('Strains', 'ambrosia-dosage-calc'),
            __('Strains', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator-strains',
            array($strains, 'render_strains')
        );
        
        // Edibles
        add_submenu_page(
            'dosage-calculator',
            __('Edibles', 'ambrosia-dosage-calc'),
            __('Edibles', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator-edibles',
            array($edibles, 'render_edibles')
        );
        
        // Taxonomies (Categories + Product Types)
        add_submenu_page(
            'dosage-calculator',
            __('Taxonomies', 'ambrosia-dosage-calc'),
            __('Taxonomies', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator-taxonomies',
            array($tools, 'render_taxonomies')
        );
        
        // Import / Export (Google Sheets, CSV, JSON, Export)
        add_submenu_page(
            'dosage-calculator',
            __('Import / Export', 'ambrosia-dosage-calc'),
            __('Import / Export', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator-import-export',
            array($tools, 'render_import_export')
        );
        
        // Generate QR Codes
        add_submenu_page(
            'dosage-calculator',
            __('Generate QR Codes', 'ambrosia-dosage-calc'),
            __('Generate QR Codes', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator-qr-generator',
            array('ADC_QR_Generator', 'render_page')
        );
        
        // Submissions
        add_submenu_page(
            'dosage-calculator',
            __('Submissions', 'ambrosia-dosage-calc'),
            __('Submissions', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator-submissions',
            array($submissions, 'render_submissions')
        );
        
        // Settings
        add_submenu_page(
            'dosage-calculator',
            __('Settings', 'ambrosia-dosage-calc'),
            __('Settings', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator-settings',
            array($settings, 'render_settings')
        );

        // Hidden pages (accessible via URL but not in menu)
        // Template Builder (accessed from Settings > Template tab)
        add_submenu_page(
            null,
            __('Template Builder', 'ambrosia-dosage-calc'),
            __('Template Builder', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator-template-builder',
            array($template_builder, 'render_page')
        );
        add_submenu_page(
            null, // hidden
            __('Add Strain', 'ambrosia-dosage-calc'),
            __('Add Strain', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator-add-strain',
            array($strains, 'render_strain_form')
        );
        add_submenu_page(
            null,
            __('Add Edible', 'ambrosia-dosage-calc'),
            __('Add Edible', 'ambrosia-dosage-calc'),
            'manage_options',
            'dosage-calculator-add-edible',
            array($edibles, 'render_edible_form')
        );
        // Keep old slugs working as hidden pages
        add_submenu_page(null, '', '', 'manage_options', 'dosage-calculator-google-sheets', array('ADC_Sheets_Admin_Page', 'render_page'));
        add_submenu_page(null, '', '', 'manage_options', 'dosage-calculator-import', array('ADC_CSV_Importer', 'render_page'));
        add_submenu_page(null, '', '', 'manage_options', 'dosage-calculator-import-json', array('ADC_JSON_Importer', 'render_page'));
        add_submenu_page(null, '', '', 'manage_options', 'dosage-calculator-export', array($tools, 'render_export'));
        add_submenu_page(null, '', '', 'manage_options', 'dosage-calculator-product-types', array($tools, 'render_product_types'));
        add_submenu_page(null, '', '', 'manage_options', 'dosage-calculator-categories', array($tools, 'render_categories'));
    }
    
    /**
     * Dashboard page
     */
    public function render_dashboard() {
        global $wpdb;
        $strains_table = ADC_DB::table('strains');
        $edibles_table = ADC_DB::table('edibles');
        
        // Use COUNT queries instead of fetching all rows just to count
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_strains  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$strains_table}");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $active_strains = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$strains_table} WHERE is_active = 1");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_edibles  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$edibles_table}");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $active_edibles = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$edibles_table} WHERE is_active = 1");
        $submission_counts = ADC_Submissions::count_by_status();
        
        ?>
        <div class="wrap adc-admin">
            <h1>Dosage Calculator v<?php echo ADC_VERSION; ?></h1>
            
            <div class="adc-dashboard-cards">
                <a href="<?php echo admin_url('admin.php?page=dosage-calculator-strains'); ?>" class="adc-card" style="text-decoration:none;">
                    <h3><?php echo $total_strains; ?></h3>
                    <p>Total Strains</p>
                    <span class="adc-card-sub"><?php echo $active_strains; ?> active</span>
                </a>
                <a href="<?php echo admin_url('admin.php?page=dosage-calculator-edibles'); ?>" class="adc-card" style="text-decoration:none;">
                    <h3><?php echo $total_edibles; ?></h3>
                    <p>Total Edibles</p>
                    <span class="adc-card-sub"><?php echo $active_edibles; ?> active</span>
                </a>
                <a href="<?php echo admin_url('admin.php?page=dosage-calculator-submissions'); ?>" class="adc-card <?php echo $submission_counts['pending'] > 0 ? 'adc-card-alert' : ''; ?>" style="text-decoration:none;">
                    <h3><?php echo $submission_counts['pending']; ?></h3>
                    <p>Pending Submissions</p>
                    <span class="adc-card-sub"><?php echo $submission_counts['total']; ?> total</span>
                </a>
            </div>
            
            <div class="adc-quick-actions">
                <h2>Quick Actions</h2>
                <a href="<?php echo admin_url('admin.php?page=dosage-calculator-add-strain'); ?>" class="button button-primary">Add New Strain</a>
                <a href="<?php echo admin_url('admin.php?page=dosage-calculator-add-edible'); ?>" class="button button-primary">Add New Edible</a>
                <a href="<?php echo admin_url('admin.php?page=dosage-calculator-qr-generator'); ?>" class="button">Generate QR Codes</a>
                <a href="<?php echo admin_url('admin.php?page=dosage-calculator-import'); ?>" class="button">Import CSV</a>
                <?php if ($submission_counts['pending'] > 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=dosage-calculator-submissions'); ?>" class="button button-warning">Review Submissions (<?php echo $submission_counts['pending']; ?>)</a>
                <?php endif; ?>
            </div>
            
            <div class="adc-shortcode-help">
                <h2>Usage</h2>
                <p>Use this shortcode to display the calculator on any page or post:</p>
                <code>[dosage_calculator]</code>
                
                <h3>Shortcode Attributes</h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Attribute</th>
                            <th>Default</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>template</code></td><td>brutal</td><td>Template: brutal, minimal, dark, nature, glass, neon, paper, terminal, retro, flat</td></tr>
                        <tr><td><code>default_tab</code></td><td>mushrooms</td><td>Default tab: mushrooms or edibles</td></tr>
                        <tr><td><code>default_strain</code></td><td>(none)</td><td>Pre-select a strain by short code</td></tr>
                        <tr><td><code>default_edible</code></td><td>(none)</td><td>Pre-select an edible by short code</td></tr>
                        <tr><td><code>show_edibles</code></td><td>true</td><td>Show/hide edibles tab</td></tr>
                        <tr><td><code>show_quick_converter</code></td><td>true</td><td>Show/hide quick converter</td></tr>
                        <tr><td><code>show_safety_warning</code></td><td>true</td><td>Show/hide safety warning</td></tr>
                        <tr><td><code>allow_custom</code></td><td>true</td><td>Allow custom strain/edible entry</td></tr>
                    </tbody>
                </table>
                
                <h3>Short URL Format</h3>
                <p>QR codes use short URLs: <code><?php echo home_url('/' . ADC_DB::get_setting('short_url_path', 'c') . '/'); ?>[SHORT_CODE]</code></p>
            </div>
        </div>
        
<?php
    }
}
