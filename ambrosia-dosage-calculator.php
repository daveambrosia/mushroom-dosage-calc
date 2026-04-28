<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName,Universal.Files.SeparateFunctionsFromOO.Mixed -- standard WordPress plugin bootstrap pattern.
/**
 * Plugin Name: Ambrosia Dosage Calculator
 * Plugin URI: https://ambrosia.church/calculator
 * Description: Psilocybin dosage calculator with strain & edible management, QR codes, and customizable templates.
 * Version: 2.25.4
 * Author: Church of Ambrosia
 * Author URI: https://ambrosia.church
 * License: GPL v2 or later
 * Text Domain: ambrosia-dosage-calc
 *
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'ADC_VERSION', '2.25.4' );
define( 'ADC_DB_VERSION', '2.0.0' );
define( 'ADC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ADC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class Ambrosia_Dosage_Calculator {

	/**
	 * Singleton instance.
	 *
	 * @var Ambrosia_Dosage_Calculator|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance of the plugin.
	 *
	 * @since 2.0.0
	 * @return Ambrosia_Dosage_Calculator Plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Loads dependencies and initializes hooks.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Detect REST API requests early (before REST_REQUEST is defined).
	 *
	 * @since 2.24.7
	 * @return bool True if the current request targets the REST API.
	 */
	private static function is_rest_request() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}
		$rest_prefix = rest_get_url_prefix();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only used for prefix comparison.
		$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
		return ( false !== strpos( $request_uri, '/' . $rest_prefix . '/' ) );
	}

	/**
	 * Load required plugin dependencies.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function load_dependencies() {
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-constants.php';
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-activator.php';
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-db.php';
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-strains.php';
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-edibles.php';
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-submissions.php';
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-rest-api.php';
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-shortcode.php';
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-qr-handler.php';
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-http-cache.php';
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-github-updater.php';

		// Frontend needs CSS generation only — loads on all requests
		require_once ADC_PLUGIN_DIR . 'includes/class-adc-template-css.php';

		// Google Sheets: needed in admin, cron, CLI, and REST (routes register globally).
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || self::is_rest_request() ) {
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-google-sheets.php';
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-sheets-importer.php';
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-sheets-admin-page.php';
		}

		if ( is_admin() ) {
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-template-builder.php';
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-admin-strains.php';
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-admin-edibles.php';
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-admin-settings.php';
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-admin-submissions.php';
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-admin-tools.php';
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-admin.php';
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-csv-importer.php';
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-json-importer.php';
			require_once ADC_PLUGIN_DIR . 'admin/class-adc-qr-generator.php';
		}
	}

	/**
	 * Initialize WordPress hooks and filters.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( 'ADC_DB', 'init' ), 1 );
		add_action( 'update_option_adc_settings', array( 'ADC_DB', 'invalidate_cache' ) );

		add_action( 'plugins_loaded', array( $this, 'check_db_update' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'rest_api_init', array( 'ADC_HTTP_Cache', 'init' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Google Sheets: cron schedules, importer init, admin routes & scripts.
		// REST routes must register outside is_admin() because REST requests
		// are not admin context. The permission_callback enforces access.
		add_action( 'rest_api_init', array( 'ADC_Sheets_Admin_Page', 'register_routes' ) );
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- interval value defined in ADC_Sheets_Importer::add_cron_schedules().
			add_filter( 'cron_schedules', array( 'ADC_Sheets_Importer', 'add_cron_schedules' ) );
			ADC_Sheets_Importer::init();
			add_action( 'admin_enqueue_scripts', array( 'ADC_Sheets_Admin_Page', 'enqueue_scripts' ) );
		}

		// QR code short URL handler
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_action( 'template_redirect', array( 'ADC_QR_Handler', 'handle_redirect' ) );

		// Template Builder live preview endpoint
		add_action( 'init', array( $this, 'register_preview_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'add_preview_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_preview' ) );

		// Add settings link
		add_filter( 'plugin_action_links_' . ADC_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );

		// GitHub updater -- check for new releases and enable WP-native updates.
		$updater = new ADC_GitHub_Updater( ADC_PLUGIN_BASENAME, ADC_VERSION );
		$updater->init();

		// Add CSP headers to frontend
		add_action( 'send_headers', array( $this, 'add_frontend_security_headers' ) );

		// Fix theme font preload missing crossorigin attribute (BUG-003).
		// Fonts loaded via @font-face use CORS anonymous mode; preload hints
		// must include crossorigin to match. Without it the browser fires a
		// credentials-mode-mismatch warning every 60 seconds.
		add_filter(
			'wp_resource_hints',
			function ( $urls, $relation_type ) {
				if ( 'preload' !== $relation_type ) {
					return $urls;
				}
				foreach ( $urls as &$url ) {
					if ( is_array( $url ) && isset( $url['as'] ) && 'font' === $url['as'] && ! isset( $url['crossorigin'] ) ) {
						$url['crossorigin'] = '';
					}
				}
				return $urls;
			},
			10,
			2
		);
	}

	/**
	 * Check if database needs updating
	 */
	public function check_db_update() {
		$current_db_version = get_option( 'adc_db_version', '1.0.0' );
		if ( version_compare( $current_db_version, ADC_DB_VERSION, '<' ) ) {
			$last_attempt = get_transient( 'adc_db_update_attempt' );
			if ( $last_attempt ) {
				return;
			}
			set_transient( 'adc_db_update_attempt', time(), HOUR_IN_SECONDS );
			ADC_Activator::activate();

			$updated_version = get_option( 'adc_db_version', '1.0.0' );
			if ( version_compare( $updated_version, ADC_DB_VERSION, '<' ) ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-error"><p><strong>Ambrosia Dosage Calculator:</strong> Database update failed. Please deactivate and reactivate the plugin.</p></div>';
					}
				);
			}
		}
	}

	/**
	 * Initialize plugin components on WordPress init.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init() {
		// Load text domain for translations
		load_plugin_textdomain( 'ambrosia-dosage-calc', false, dirname( ADC_PLUGIN_BASENAME ) . '/languages' );

		// Register shortcode
		ADC_Shortcode::register();

		// Initialize admin
		if ( is_admin() ) {
			ADC_Admin::get_instance();
		}
	}

	/**
	 * Register REST API routes for the calculator.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_rest_routes() {
		ADC_REST_API::register_routes();
	}

	/**
	 * Register rewrite rules for short URLs
	 */
	public function register_rewrite_rules() {
		$path = ADC_DB::get_setting( 'short_url_path', 'c' );
		add_rewrite_rule(
			'^' . $path . '/([a-zA-Z0-9-]+)/?$',
			'index.php?adc_code=$matches[1]',
			'top'
		);
		// Bare path for legacy ?data= URLs (e.g. /c/?data=name:X,psilocybin:Y)
		add_rewrite_rule(
			'^' . $path . '/?$',
			'index.php?adc_legacy_data=1',
			'top'
		);
		add_rewrite_tag( '%adc_code%', '([a-zA-Z0-9-]+)' );
		add_rewrite_tag( '%adc_legacy_data%', '([0-9]+)' );
	}

	/**
	 * Register rewrite rule for the template builder preview endpoint.
	 *
	 * @since 2.16.0
	 */
	public function register_preview_rewrite() {
		add_rewrite_rule( '^adc-preview/?$', 'index.php?adc_preview=1', 'top' );
		// Note: flush_rewrite_rules() is called in ADC_Activator::activate() on plugin
		// activation. Do NOT flush here — it runs on every init (every page load) which
		// is O(n) on wp_options and causes ~10ms overhead per request.
	}

	/**
	 * Add adc_preview query var.
	 *
	 * @since 2.16.0
	 * @param array $vars Existing registered query vars.
	 * @return array Modified query vars with adc_preview added.
	 */
	public function add_preview_query_var( $vars ) {
		$vars[] = 'adc_preview';
		return $vars;
	}

	/**
	 * Render bare-bones calculator preview if adc_preview query var is set.
	 *
	 * @since 2.16.0
	 */
	public function maybe_render_preview() {
		if ( ! get_query_var( 'adc_preview' ) ) {
			return;
		}
		// Security: only allow logged-in users with manage_options
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 'Unauthorized', array( 'response' => 403 ) );
		}
		// Disable the admin bar entirely for the preview iframe (BUG-007 fix).
		// This prevents the HTML from rendering, not just CSS-hiding it.
		show_admin_bar( false );
		$this->render_preview_page();
		exit;
	}

	/**
	 * Output a minimal HTML page containing just the calculator shortcode.
	 *
	 * @since 2.16.0
	 */
	private function render_preview_page() {
		// Get the template from query param (for initial render)
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter.
		$template = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';

		// Build shortcode with optional template override
		$shortcode_atts = '[dosage_calculator';
		if ( $template ) {
			$shortcode_atts .= ' template="' . esc_attr( $template ) . '"';
		}
		$shortcode_atts .= ']';

		// Minimal HTML page — no WP chrome, just the calculator
		?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Calculator Preview</title>
		<?php wp_head(); ?>
<style>
	* { box-sizing: border-box; }
	body {
		margin: 0;
		padding: 12px;
		background: transparent;
		font-family: sans-serif;
	}
	/* Hide any WP admin bar that might slip in */
	#wpadminbar { display: none !important; }
	html { margin-top: 0 !important; }
</style>
</head>
<body class="adc-preview-body">
		<?php
		// Output the calculator shortcode
		echo do_shortcode( $shortcode_atts );
		?>
<script>
// Listen for CSS variable injection from parent (template builder)
window.addEventListener('message', function(e) {
	// Accept messages from same origin only
	if (e.origin !== window.location.origin) return;

	var data = e.data;
	if (!data || data.type !== 'adc_preview_vars') return;

	var styleId = 'adc-preview-injected-vars';
	var existing = document.getElementById(styleId);
	if (!existing) {
		existing = document.createElement('style');
		existing.id = styleId;
		document.head.appendChild(existing);
	}

	// Build CSS: override --adc-* vars with specificity matching [data-template]
	var vars = data.vars || {};
	var clearVars = data.clearVars || [];
	var css = '#adc-calculator[data-template] {\n';
	Object.keys(vars).forEach(function(key) {
		css += '    --adc-' + key + ': ' + vars[key] + ' !important;\n';
	});
	// Reset cleared variables to neutral defaults
	clearVars.forEach(function(key) {
		css += '    --adc-' + key + ': initial !important;\n';
	});
	css += '}\n';

	existing.textContent = css;
});

// Tell parent we're ready
window.addEventListener('load', function() {
	if (window.parent && window.parent !== window) {
		window.parent.postMessage({ type: 'adc_preview_ready' }, window.location.origin);
	}
	setTimeout(reportHeight, 400);
});

// Auto-size: report content height to parent so iframe resizes to fit content
function reportHeight() {
	if (window.parent === window) return;
	var h = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
	window.parent.postMessage({ type: 'adc_preview_height', height: h }, window.location.origin);
}

// Re-report on window resize only
var _heightTimer = null;
window.addEventListener('resize', function() {
	clearTimeout(_heightTimer);
	_heightTimer = setTimeout(reportHeight, 200);
});
</script>
		<?php wp_footer(); ?>
</body>
</html>
		<?php
	}

	/**
	 * Enqueue frontend CSS and JavaScript assets.
	 *
	 * Assets are only loaded on pages containing the calculator shortcode.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function enqueue_public_assets() {
		// Use minified assets in production (when WP_DEBUG is false)
		$min = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '' : '.min';

		// Only load when shortcode is present or QR redirect
		global $post;
		$should_load = false;

		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'dosage_calculator' ) ) {
			$should_load = true;
		}
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'adc_calculator' ) ) {
			$should_load = true;
		}
		if ( get_query_var( 'adc_code' ) ) {
			$should_load = true;
		}
		if ( get_query_var( 'adc_preview' ) ) {
			$should_load = true;
		}

		if ( ! $should_load ) {
			return;
		}

		// Self-hosted fonts: only load for templates that use Space Mono / Work Sans
		// Replaced Google Fonts CDN for GDPR compliance and faster loading (v2.12.50)
		$template    = ADC_DB::get_setting( 'template', 'default' );
		$needs_fonts = in_array( $template, array( 'brutal', 'neon' ), true );
		$css_deps    = array();
		if ( $needs_fonts ) {
			wp_enqueue_style(
				'adc-fonts',
				ADC_PLUGIN_URL . 'public/css/adc-fonts' . $min . '.css',
				array(),
				ADC_VERSION
			);
			$css_deps[] = 'adc-fonts';
		}

		wp_enqueue_style(
			'adc-calculator',
			ADC_PLUGIN_URL . 'public/css/calculator' . $min . '.css',
			$css_deps,
			ADC_VERSION
		);

		// Dialog system
		wp_enqueue_style(
			'adc-dialogs',
			ADC_PLUGIN_URL . 'public/css/adc-dialogs' . $min . '.css',
			array(),
			ADC_VERSION
		);

		wp_enqueue_script(
			'adc-dialogs',
			ADC_PLUGIN_URL . 'public/js/adc-dialogs' . $min . '.js',
			array(),
			ADC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_enqueue_script(
			'adc-calculator',
			ADC_PLUGIN_URL . 'public/js/calculator' . $min . '.js',
			array(),
			ADC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// Pass data to JS
		wp_localize_script(
			'adc-calculator',
			'adcData',
			array(
				'restUrl' => rest_url( 'adc/v1/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'version' => ADC_VERSION,
			)
		);
	}

	/**
	 * Enqueue admin CSS and JavaScript assets.
	 *
	 * @since 2.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'dosage-calculator' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'adc-admin',
			ADC_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			ADC_VERSION
		);

		// Dialog system
		wp_enqueue_style(
			'adc-admin-dialogs',
			ADC_PLUGIN_URL . 'admin/css/adc-dialogs.css',
			array(),
			ADC_VERSION
		);

		wp_enqueue_script(
			'adc-admin-dialogs',
			ADC_PLUGIN_URL . 'admin/js/adc-dialogs.js',
			array(),
			ADC_VERSION,
			true
		);

		wp_enqueue_script(
			'adc-admin',
			ADC_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			ADC_VERSION,
			true
		);

		// QR Code library for generator
		wp_enqueue_script(
			'qrcode-js',
			ADC_PLUGIN_URL . 'admin/js/vendor/qrcode.min.js',
			array(),
			'1.5.1',
			true
		);

		wp_localize_script(
			'adc-admin',
			'adcAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'restUrl'     => rest_url( 'adc/v1/' ),
				'nonce'       => wp_create_nonce( 'adc_admin_nonce' ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'adminUrl'    => admin_url( 'admin.php' ),
				'importNonce' => wp_create_nonce( 'adc_import_template' ),
			)
		);
	}

	/**
	 * Add settings link to plugin actions on plugins page.
	 *
	 * @since 2.0.0
	 * @param array $links Existing plugin action links.
	 * @return array Modified action links with settings added.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=dosage-calculator">' . __( 'Settings', 'ambrosia-dosage-calc' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Add security headers to frontend responses.
	 *
	 * Adds Content-Security-Policy, X-Content-Type-Options, X-Frame-Options,
	 * and Referrer-Policy headers to harden the plugin's frontend output.
	 *
	 * @since 2.21.0
	 * @return void
	 */
	public function add_frontend_security_headers() {
		// Only add headers on pages that use the calculator shortcode.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		// X-Content-Type-Options: prevent MIME-type sniffing.
		if ( ! headers_sent() ) {
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		}
	}
}

/**
 * Initialize the plugin.
 *
 * @since 2.0.0
 * @return Ambrosia_Dosage_Calculator Plugin instance.
 */
function adc_init() {
	return Ambrosia_Dosage_Calculator::get_instance();
}
add_action( 'plugins_loaded', 'adc_init' );

// ADC_Activator must be loaded before register_activation_hook fires,
// which happens before plugins_loaded (i.e. before load_dependencies() runs).
require_once plugin_dir_path( __FILE__ ) . 'includes/class-adc-activator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-adc-db.php';
register_activation_hook( __FILE__, array( 'ADC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ADC_Activator', 'deactivate' ) );

/**
 * Get the plugin instance helper.
 *
 * @since 2.0.0
 * @return Ambrosia_Dosage_Calculator Plugin instance.
 */
function adc() {
	return Ambrosia_Dosage_Calculator::get_instance();
}
