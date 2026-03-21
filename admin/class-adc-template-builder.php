<?php
/**
 * Custom Template Builder
 *
 * Admin page for creating, editing, and managing custom visual templates
 * that use the same CSS variable system as built-in templates.
 *
 * @since 2.13.0
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_Template_Builder class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_Template_Builder {

	private static $instance = null;

	/**
	 * All CSS variables available for templates, grouped by section.
	 */
	private static $variable_groups = array(
		'Core Colors'       => array(
			'bg'          => array(
				'type'  => 'color',
				'label' => 'Background',
				'desc'  => 'Main calculator background',
			),
			'text'        => array(
				'type'  => 'color',
				'label' => 'Text',
				'desc'  => 'Primary text color',
			),
			'accent'      => array(
				'type'  => 'color',
				'label' => 'Accent',
				'desc'  => 'Links, highlights, active states',
			),
			'surface'     => array(
				'type'  => 'color',
				'label' => 'Surface',
				'desc'  => 'Card and panel backgrounds',
			),
			'surface-alt' => array(
				'type'  => 'color',
				'label' => 'Secondary Background',
				'desc'  => 'Alternate card/panel background (e.g. zebra rows, secondary sections)',
			),
			'border'      => array(
				'type'  => 'color',
				'label' => 'Border Color',
				'desc'  => 'General border color',
			),
		),
		'Header & Tabs'     => array(
			'header-bg'       => array(
				'type'  => 'color',
				'label' => 'Header Background',
			),
			'header-text'     => array(
				'type'  => 'color',
				'label' => 'Header Text',
			),
			'header-border'   => array(
				'type'  => 'text',
				'label' => 'Header Border',
				'desc'  => 'Border below the calculator header. e.g. \'none\' or \'1px solid #ddd\'. Leave blank to use the global Border Color.',
			),
			'tab-bg'          => array(
				'type'  => 'color',
				'label' => 'Tab Background',
			),
			'tab-text'        => array(
				'type'  => 'color',
				'label' => 'Inactive Tab Text',
				'desc'  => 'Text color for unselected tabs',
			),
			'tab-active-bg'   => array(
				'type'  => 'color',
				'label' => 'Active Tab Background',
			),
			'tab-active-text' => array(
				'type'  => 'color',
				'label' => 'Active Tab Text',
			),
			'tab-hover-bg'    => array(
				'type'  => 'color',
				'label' => 'Tab Hover Background',
			),
		),
		'Body & Inputs'     => array(
			'body-bg'        => array(
				'type'  => 'color',
				'label' => 'Body Background',
			),
			'input-bg'       => array(
				'type'  => 'color',
				'label' => 'Input Background',
			),
			'input-border'   => array(
				'type'  => 'color',
				'label' => 'Input Border',
			),
			'input-focus-bg' => array(
				'type'  => 'color',
				'label' => 'Input Focus Background',
			),
			'focus-ring'     => array(
				'type'  => 'text',
				'label' => 'Selection Outline',
				'desc'  => 'Outline shown when a form field is focused. e.g. \'none\' or \'0 0 0 3px rgba(59,130,246,0.5)\'',
			),
		),
		'Buttons'           => array(
			'btn-bg'           => array(
				'type'  => 'color',
				'label' => 'Button Background',
			),
			'btn-text'         => array(
				'type'  => 'color',
				'label' => 'Button Text',
			),
			'btn-border'       => array(
				'type'  => 'color',
				'label' => 'Button Border',
			),
			'btn-hover-bg'     => array(
				'type'  => 'color',
				'label' => 'Button Hover Background',
			),
			'btn-primary-bg'   => array(
				'type'  => 'color',
				'label' => 'Primary Button Background',
			),
			'btn-primary-text' => array(
				'type'  => 'color',
				'label' => 'Primary Button Text',
			),
		),
		'Special Areas'     => array(
			'unit-active-bg'    => array(
				'type'  => 'color',
				'label' => 'Unit Toggle Active Background',
			),
			'unit-active-text'  => array(
				'type'  => 'color',
				'label' => 'Unit Toggle Active Text',
			),
			'safety-bg'         => array(
				'type'  => 'color',
				'label' => 'Safety Warning Background',
			),
			'safety-border'     => array(
				'type'  => 'color',
				'label' => 'Safety Warning Border',
			),
			'warning'           => array(
				'type'  => 'color',
				'label' => 'Warning Color',
				'desc'  => 'Color for warning indicators',
			),
			'warning-bg'        => array(
				'type'  => 'color',
				'label' => 'Warning Background',
				'desc'  => 'Background for warning containers',
			),
			'modal-header-bg'   => array(
				'type'  => 'color',
				'label' => 'Popup Header Background',
			),
			'modal-header-text' => array(
				'type'  => 'color',
				'label' => 'Popup Header Text',
			),
		),
		'Dose Level Colors' => array(
			'color-microdose'    => array(
				'type'  => 'color',
				'label' => 'Microdose',
				'desc'  => 'Background for microdose result cards',
			),
			'color-perceivable'  => array(
				'type'  => 'color',
				'label' => 'Perceivable',
				'desc'  => 'Background for perceivable dose result cards',
			),
			'color-intense'      => array(
				'type'  => 'color',
				'label' => 'Intense',
				'desc'  => 'Background for intense dose result cards',
			),
			'color-profound'     => array(
				'type'  => 'color',
				'label' => 'Profound',
				'desc'  => 'Background for profound dose result cards',
			),
			'color-breakthrough' => array(
				'type'  => 'color',
				'label' => 'Breakthrough',
				'desc'  => 'Background for breakthrough dose result cards',
			),
		),
		'Accent Colors'     => array(
			'accent-yellow' => array(
				'type'  => 'color',
				'label' => 'Accent Yellow',
				'desc'  => 'Highlights, active states',
			),
			'accent-green'  => array(
				'type'  => 'color',
				'label' => 'Accent Green',
				'desc'  => 'Success and positive indicators',
			),
			'accent-blue'   => array(
				'type'  => 'color',
				'label' => 'Accent Blue',
				'desc'  => 'Focus outlines, info highlights',
			),
			'accent-purple' => array(
				'type'  => 'color',
				'label' => 'Accent Purple',
				'desc'  => 'Decorative accents, UI elements',
			),
		),
		'Layout'            => array(
			'border-width'     => array(
				'type'  => 'text',
				'label' => 'Border Width',
				'desc'  => 'e.g. "1px", "2px", "3px"',
			),
			'radius'           => array(
				'type'  => 'text',
				'label' => 'Border Radius',
				'desc'  => 'e.g. "0px", "8px", "16px"',
			),
			'shadow'           => array(
				'type'  => 'text',
				'label' => 'Shadow (Global)',
				'desc'  => 'Default shadow for all elements. e.g. "none" or "0 4px 24px rgba(0,0,0,0.1)"',
			),
			'shadow-offset'    => array(
				'type'  => 'text',
				'label' => 'Shadow Offset',
				'desc'  => 'Offset for solid box shadows (brutal style). e.g. "0px" or "6px"',
			),
			'header-shadow'    => array(
				'type'  => 'text',
				'label' => 'Header Shadow',
				'desc'  => 'Overrides global shadow for the header. e.g. "none" or "0 2px 8px rgba(0,0,0,0.15)"',
			),
			'box-shadow'       => array(
				'type'  => 'text',
				'label' => 'Panel Shadow',
				'desc'  => 'Shadow for input boxes and panels. e.g. \'none\' or \'0 2px 8px rgba(0,0,0,0.1)\'',
			),
			'card-shadow'      => array(
				'type'  => 'text',
				'label' => 'Card Shadow',
				'desc'  => 'Overrides global shadow for dose result cards. e.g. "none"',
			),
			'container-border' => array(
				'type'  => 'text',
				'label' => 'Container Border',
				'desc'  => 'Border around the entire calculator. e.g. \'2px solid #000\' or \'none\'',
			),
		),
		'Fonts'             => array(
			'font-heading' => array(
				'type'  => 'text',
				'label' => 'Heading Font',
				'desc'  => "e.g. 'Inter', sans-serif or 'Space Mono', monospace",
			),
			'font-body'    => array(
				'type'  => 'text',
				'label' => 'Body Font',
				'desc'  => "e.g. 'Work Sans', sans-serif or 'Georgia', serif",
			),
			'font-mono'    => array(
				'type'  => 'text',
				'label' => 'Monospace Font',
				'desc'  => "Used for compound data and code values. e.g. 'Space Mono', monospace",
			),
		),
	);

	/**
	 * Neutral fallback values for cleared color variables.
	 * When a user explicitly clears a color, these override the base defaults.
	 */
	private static $color_fallbacks = array(
		'bg'                 => 'transparent',
		'text'               => 'inherit',
		'accent'             => '#6b7280',
		'surface'            => 'transparent',
		'surface-alt'        => 'transparent',
		'border'             => '#e5e7eb',
		'header-bg'          => 'transparent',
		'header-text'        => 'inherit',
		'tab-bg'             => 'transparent',
		'tab-text'           => 'inherit',
		'tab-active-bg'      => 'transparent',
		'tab-active-text'    => 'inherit',
		'tab-hover-bg'       => 'transparent',
		'body-bg'            => 'transparent',
		'input-bg'           => 'transparent',
		'input-border'       => '#e5e7eb',
		'input-focus-bg'     => 'transparent',
		'btn-bg'             => 'transparent',
		'btn-text'           => 'inherit',
		'btn-border'         => '#e5e7eb',
		'btn-hover-bg'       => 'transparent',
		'btn-primary-bg'     => '#6b7280',
		'btn-primary-text'   => 'inherit',
		'unit-active-bg'     => '#6b7280',
		'unit-active-text'   => 'inherit',
		'safety-bg'          => 'transparent',
		'safety-border'      => '#e5e7eb',
		'warning'            => '#ff5c5c',
		'warning-bg'         => '#ffe0e0',
		'modal-header-bg'    => 'transparent',
		'modal-header-text'  => 'inherit',
		'color-microdose'    => '#5468ad',
		'color-perceivable'  => '#4f9350',
		'color-intense'      => '#e2a03f',
		'color-profound'     => '#c04c32',
		'color-breakthrough' => '#83318a',
		'accent-yellow'      => '#ffde59',
		'accent-green'       => '#7bed9f',
		'accent-blue'        => '#74b9ff',
		'accent-purple'      => '#a29bfe',
	);

	/**
	 * Built-in template defaults (mirrors calculator-themes.css).
	 * Used for "Start from..." feature.
	 */
	private static $builtin_templates = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Handle redirect-based actions early (before headers are sent)
		add_action( 'admin_init', array( $this, 'handle_early_actions' ) );
		// AJAX handler for "Set as Active" button
		add_action( 'wp_ajax_adc_set_active_template', array( $this, 'ajax_set_active_template' ) );
	}

	/**
	 * AJAX handler: Set a custom template as the active template.
	 *
	 * @since 2.13.3
	 */
	public function ajax_set_active_template() {
		check_ajax_referer( 'adc_set_active_template', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'ambrosia-dosage-calc' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template slug.', 'ambrosia-dosage-calc' ) ) );
		}

		// Verify the template exists
		$template = self::get_custom_template( $slug );
		if ( ! $template ) {
			wp_send_json_error( array( 'message' => __( 'Template not found.', 'ambrosia-dosage-calc' ) ) );
		}

		$settings             = ADC_DB::get_settings();
		$settings['template'] = $slug;
		ADC_DB::update_settings( $settings );

		wp_send_json_success(
			array(
				'message' => __( 'Template activated!', 'ambrosia-dosage-calc' ),
				'slug'    => $slug,
			)
		);
	}

	/**
	 * Process actions that need to redirect (delete, duplicate, export, import)
	 * before WordPress sends headers via admin_init.
	 */
	public function handle_early_actions() {
		// Only run on our admin page
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'dosage-calculator-template-builder' !== $page ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked in individual action handlers.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		// GET-based redirect actions
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked in individual action handlers.
		if ( 'delete' === $action && isset( $_GET['slug'] ) ) {
			$this->handle_delete();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked in individual action handlers.
		if ( 'duplicate' === $action && isset( $_GET['slug'] ) ) {
			$this->handle_duplicate();
		}

		// POST-based actions (export downloads, import/save redirect)
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked in individual action handlers.
			$post_action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
			if ( 'export' === $post_action && isset( $_POST['slug'] ) ) {
				$this->handle_export();
			}
			if ( 'import_template' === $post_action ) {
				$this->handle_import();
			}
			// Save (create/update template)
			if ( isset( $_POST['adc_template_builder_nonce'] ) ) {
				$this->handle_save();
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * Get all variable groups and their definitions.
	 */
	public static function get_variable_groups() {
		return self::$variable_groups;
	}

	/**
	 * Get a flat list of all variable keys.
	 */
	public static function get_all_variable_keys() {
		$keys = array();
		foreach ( self::$variable_groups as $vars ) {
			$keys = array_merge( $keys, array_keys( $vars ) );
		}
		return $keys;
	}

	/**
	 * Get built-in template variable values.
	 * Parsed from the themes CSS file on first call, then cached.
	 */
	public static function get_builtin_templates() {
		if ( null !== self::$builtin_templates ) {
			return self::$builtin_templates;
		}

		// Try transient cache first (keyed by version to auto-invalidate on update)
		$cache_key = 'adc_builtin_tpls_' . md5( ADC_VERSION );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			self::$builtin_templates = $cached;
			return self::$builtin_templates;
		}

		$css_file                = ADC_PLUGIN_DIR . 'public/css/calculator-themes.css';
		self::$builtin_templates = array();

		if ( ! file_exists( $css_file ) ) {
			return self::$builtin_templates;
		}

		// Read local CSS file. Use WP_Filesystem direct method to avoid FTP driver issues
		// on servers where WP_Filesystem() defaults to FTP. For local plugin files,
		// file_get_contents() is safe and correct — this is not a remote read.
		$css = file_get_contents( $css_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! $css ) {
			return self::$builtin_templates;
		}

		// Match each template block: #adc-calculator[data-template="xxx"] { ... }
		// Some templates have multiple blocks (main variables + structural overrides).
		// Only the first block (with --adc-* vars) matters; skip subsequent blocks for the same slug.
		if ( preg_match_all( '/\[data-template="([^"]+)"\]\s*\{([^}]+)\}/s', $css, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$slug = $m[1];

				// Skip if we already parsed this template (first block has the variables)
				if ( isset( self::$builtin_templates[ $slug ] ) ) {
					continue;
				}

				$block = $m[2];
				$vars  = array();

				// Extract --adc-xxx: value pairs
				if ( preg_match_all( '/--adc-([a-z-]+)\s*:\s*([^;]+);/', $block, $var_matches, PREG_SET_ORDER ) ) {
					foreach ( $var_matches as $vm ) {
						$vars[ trim( $vm[1] ) ] = trim( $vm[2] );
					}
				}

				$name                             = ucfirst( str_replace( '-', ' ', $slug ) );
				self::$builtin_templates[ $slug ] = array(
					'slug'      => $slug,
					'name'      => $name,
					'variables' => $vars,
					'builtin'   => true,
				);
			}
		}

		// Store in transient after parsing (expires in 24 hours, auto-invalidates on version change)
		if ( ! empty( self::$builtin_templates ) ) {
			set_transient( $cache_key, self::$builtin_templates, DAY_IN_SECONDS );
		}

		return self::$builtin_templates;
	}

	/**
	 * Get all custom templates from the database.
	 */
	public static function get_custom_templates() {
		$data      = get_option( 'adc_custom_templates', '[]' );
		$templates = json_decode( $data, true );
		if ( ! is_array( $templates ) ) {
			return array();
		}
		return $templates;
	}

	/**
	 * Save custom templates to the database.
	 */
	public static function save_custom_templates( $templates ) {
		update_option( 'adc_custom_templates', wp_json_encode( array_values( $templates ) ) );
	}

	/**
	 * Get a single custom template by slug.
	 */
	public static function get_custom_template( $slug ) {
		$templates = self::get_custom_templates();
		foreach ( $templates as $t ) {
			if ( $t['slug'] === $slug ) {
				return $t;
			}
		}
		return null;
	}

	/**
	 * Generate a slug from a template name.
	 */
	private static function make_slug( $name ) {
		$slug = sanitize_title( $name );
		return 'custom-' . $slug;
	}

	/**
	 * Generate CSS for a single custom template.
	 */
	public static function generate_template_css( $template ) {
		if ( empty( $template['variables'] ) || ! is_array( $template['variables'] ) ) {
			return '';
		}

		$slug = esc_attr( $template['slug'] );
		$css  = '#adc-calculator[data-template="' . $slug . '"] {' . "\n";

		$stored = $template['variables'];

		foreach ( $stored as $key => $value ) {
			$safe_key = preg_replace( '/[^a-z0-9-]/', '', $key );

			if ( '' === $value || null === $value ) {
				// Explicitly cleared: emit neutral fallback to override base defaults
				if ( isset( self::$color_fallbacks[ $key ] ) ) {
					$css .= '    --adc-' . $safe_key . ': ' . self::$color_fallbacks[ $key ] . ';' . "\n";
				}
				continue;
			}

			// Color values get sanitized, text values get stripped
			if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) {
				$css .= '    --adc-' . $safe_key . ': ' . sanitize_hex_color( $value ) . ';' . "\n";
			} else {
				$css .= '    --adc-' . $safe_key . ': ' . self::sanitize_css_value( $value ) . ';' . "\n";
			}
		}

		// Emit fallbacks for color keys that were never set in this template.
		// Without this, keys absent from older templates fall through to the brutal
		// defaults in calculator.css (e.g. header-bg: #ffde59).
		foreach ( self::$color_fallbacks as $key => $fallback ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				$safe_key = preg_replace( '/[^a-z0-9-]/', '', $key );
				$css     .= '    --adc-' . $safe_key . ': ' . $fallback . ';' . "\n";
			}
		}

		$css .= '}' . "\n";
		return $css;
	}

	/**
	 * Generate CSS for all custom templates.
	 * Called by the shortcode to inject inline styles.
	 */
	public static function generate_all_custom_css() {
		$templates = self::get_custom_templates();
		if ( empty( $templates ) ) {
			return '';
		}

		$css = "/* Custom Templates */\n";
		foreach ( $templates as $t ) {
			$css .= self::generate_template_css( $t );
		}
		return $css;
	}

	/**
	 * Render the Template Builder admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'ambrosia-dosage-calc' ) );
		}

		// All POST/redirect actions handled in handle_early_actions() via admin_init
		// (before headers are sent, so wp_redirect works properly).

		// Determine view
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';

		echo '<div class="wrap adc-admin adc-template-builder">';
		echo '<h1>🎨 ' . esc_html__( 'Template Builder', 'ambrosia-dosage-calc' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=dosage-calculator-settings' ) ) . '" class="page-title-action">' . esc_html__( 'Settings', 'ambrosia-dosage-calc' ) . '</a></h1>';

		if ( 'edit' === $action || 'new' === $action ) {
			$this->render_edit_view();
		} else {
			$this->render_list_view();
		}

		echo '</div>';
	}

	/**
	 * Handle save/update of a custom template.
	 */
	private function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 'Unauthorized', array( 'response' => 403 ) );
		}

		if ( ! isset( $_POST['adc_template_builder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['adc_template_builder_nonce'] ) ), 'adc_template_builder_save' ) ) {
			wp_die( esc_html__( 'Security check failed', 'ambrosia-dosage-calc' ) );
		}

		$name = isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '';
		if ( empty( $name ) ) {
			add_settings_error( 'adc_template_builder', 'empty_name', esc_html__( 'Template name is required.', 'ambrosia-dosage-calc' ), 'error' );
			return;
		}

		$editing_slug = sanitize_key( wp_unslash( $_POST['editing_slug'] ?? '' ) );
		$new_slug     = self::make_slug( $name );
		$templates    = self::get_custom_templates();

		// Check for duplicate slug (but allow same slug if editing the same template)
		foreach ( $templates as $t ) {
			if ( $t['slug'] === $new_slug && $t['slug'] !== $editing_slug ) {
				add_settings_error( 'adc_template_builder', 'duplicate_slug', __( 'A custom template with that name already exists.', 'ambrosia-dosage-calc' ), 'error' );
				return;
			}
		}

		// Collect variables
		$variables = array();
		$all_keys  = self::get_all_variable_keys();
		foreach ( $all_keys as $key ) {
			$post_key = 'var_' . str_replace( '-', '_', $key );
			$value    = isset( $_POST[ $post_key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) ) : '';

			$type = self::get_variable_type( $key );

			if ( '' === $value ) {
				// For color fields, store empty string to mark as explicitly cleared
				if ( 'color' === $type ) {
					$variables[ $key ] = '';
				}
				continue;
			}

			// Validate based on type
			if ( 'color' === $type ) {
				$sanitized = sanitize_hex_color( $value );
				if ( $sanitized ) {
					$variables[ $key ] = $sanitized;
				}
			} else {
				$variables[ $key ] = self::sanitize_css_value( $value );
			}
		}

		$now = time();

		if ( $editing_slug ) {
			// Update existing
			$found = false;
			foreach ( $templates as &$t ) {
				if ( $t['slug'] === $editing_slug ) {
					$t['slug']      = $new_slug;
					$t['name']      = $name;
					$t['variables'] = $variables;
					$t['modified']  = $now;
					$found          = true;
					break;
				}
			}
			unset( $t );

			if ( ! $found ) {
				add_settings_error( 'adc_template_builder', 'not_found', __( 'Template not found.', 'ambrosia-dosage-calc' ), 'error' );
				return;
			}
		} else {
			// Create new
			$templates[] = array(
				'slug'      => $new_slug,
				'name'      => $name,
				'variables' => $variables,
				'created'   => $now,
				'modified'  => $now,
			);
		}

		self::save_custom_templates( $templates );

		// Redirect to list with success message
		wp_safe_redirect( admin_url( 'admin.php?page=dosage-calculator-settings&saved=1' ) );
		exit;
	}

	/**
	 * Handle template deletion.
	 */
	private function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete templates.', 'ambrosia-dosage-calc' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked immediately below.
		$slug = isset( $_GET['slug'] ) ? sanitize_key( wp_unslash( $_GET['slug'] ) ) : '';

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'adc_delete_template_' . $slug ) ) {
			wp_die( esc_html__( 'Security check failed', 'ambrosia-dosage-calc' ) );
		}

		$templates = self::get_custom_templates();
		$templates = array_filter(
			$templates,
			function ( $t ) use ( $slug ) {
				return $t['slug'] !== $slug;
			}
		);

		self::save_custom_templates( $templates );

		// If this template was the active one, reset to default
		$settings = ADC_DB::get_settings();
		if ( ( $settings['template'] ?? '' ) === $slug ) {
			$settings['template'] = 'default';
			ADC_DB::update_settings( $settings );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=dosage-calculator-template-builder&deleted=1' ) );
		exit;
	}

	/**
	 * Handle template duplication.
	 */
	private function handle_duplicate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'ambrosia-dosage-calc' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked immediately below.
		$slug = isset( $_GET['slug'] ) ? sanitize_key( wp_unslash( $_GET['slug'] ) ) : '';
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'adc_duplicate_template_' . $slug ) ) {
			wp_die( esc_html__( 'Security check failed', 'ambrosia-dosage-calc' ) );
		}

		$original_template = self::get_custom_template( $slug );
		if ( ! $original_template ) {
			wp_die( esc_html__( 'Original template not found.', 'ambrosia-dosage-calc' ) );
		}

		// Generate new name and slug
		$base_name       = $original_template['name'];
		$new_name_prefix = sprintf( __( 'Copy of %s', 'ambrosia-dosage-calc' ), $base_name );
		$new_name        = $new_name_prefix;

		$base_slug          = self::make_slug( $new_name_prefix );
		$new_slug           = $base_slug;
		$i                  = 1;
		$existing_templates = self::get_custom_templates();
		$existing_slugs     = array_column( $existing_templates, 'slug' );

		while ( in_array( $new_slug, $existing_slugs, true ) ) {
			++$i;
			$new_slug = $base_slug . '-' . $i;
			$new_name = $new_name_prefix . ' ' . $i;
		}

		$now                 = time();
		$duplicated_template = array(
			'slug'      => $new_slug,
			'name'      => $new_name,
			'variables' => $original_template['variables'] ?? array(),
			'created'   => $now,
			'modified'  => $now,
		);

		$existing_templates[] = $duplicated_template;
		self::save_custom_templates( $existing_templates );

		wp_safe_redirect( admin_url( 'admin.php?page=dosage-calculator-template-builder&action=edit&slug=' . $new_slug . '&duplicated=1' ) );
		exit;
	}

	/**
	 * Handle template import (JSON upload).
	 */
	private function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'ambrosia-dosage-calc' ) );
		}

		if ( ! isset( $_POST['adc_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['adc_import_nonce'] ) ), 'adc_import_template' ) ) {
			add_settings_error( 'adc_template_builder', 'nonce_fail', esc_html__( 'Security check failed. Please try again.', 'ambrosia-dosage-calc' ), 'error' );
			return;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file upload validation handled below.
		if ( empty( $_FILES['adc_import_file']['tmp_name'] ) ) {
			add_settings_error( 'adc_template_builder', 'no_file', __( 'No file uploaded.', 'ambrosia-dosage-calc' ), 'error' );
			return;
		}

		$file = $_FILES['adc_import_file'];

		// File upload validation
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			add_settings_error( 'adc_template_builder', 'upload_error', sprintf( __( 'File upload error: %s', 'ambrosia-dosage-calc' ), intval( $file['error'] ) ), 'error' );
			// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

		if ( $file['size'] > 1024 * 100 ) { // Max 100KB
			add_settings_error( 'adc_template_builder', 'file_size', __( 'File is too large. Max 100KB allowed.', 'ambrosia-dosage-calc' ), 'error' );
			return;
		}

		$file_type = wp_check_filetype( $file['name'] );
		if ( 'json' !== $file_type['ext'] ) {
			add_settings_error( 'adc_template_builder', 'file_type', __( 'Invalid file type. Please upload a .json file.', 'ambrosia-dosage-calc' ), 'error' );
			return;
		}

		$content       = file_get_contents( $file['tmp_name'] );
		$imported_data = json_decode( $content, true );

		if ( ! is_array( $imported_data ) ) {
			add_settings_error( 'adc_template_builder', 'invalid_json', __( 'Invalid JSON content.', 'ambrosia-dosage-calc' ), 'error' );
			return;
		}

		// Validate JSON structure
		if ( ! isset( $imported_data['adc_template'] ) || (int) 1 !== $imported_data['adc_template'] ) {
			add_settings_error( 'adc_template_builder', 'json_version', __( 'Invalid template file version or format.', 'ambrosia-dosage-calc' ), 'error' );
			return;
		}
		if ( empty( $imported_data['name'] ) || ! is_string( $imported_data['name'] ) ) {
			add_settings_error( 'adc_template_builder', 'json_name', __( 'Template name is missing or invalid.', 'ambrosia-dosage-calc' ), 'error' );
			return;
		}
		if ( ! isset( $imported_data['variables'] ) || ! is_array( $imported_data['variables'] ) ) {
			add_settings_error( 'adc_template_builder', 'json_variables', __( 'Template variables are missing or invalid.', 'ambrosia-dosage-calc' ), 'error' );
			return;
		}

		$template_name      = sanitize_text_field( $imported_data['name'] );
		$template_variables = array();
		$all_known_keys     = self::get_all_variable_keys();

		// Validate and sanitize variables
		foreach ( $imported_data['variables'] as $key => $value ) {
			if ( ! in_array( $key, $all_known_keys, true ) ) {
				add_settings_error( 'adc_template_builder', 'unknown_var', sprintf( __( 'Unknown variable key found: %s', 'ambrosia-dosage-calc' ), esc_html( $key ) ), 'error' );
				return;
			}
			if ( '' === $value ) {
				continue;
			}

			$type = self::get_variable_type( $key );
			if ( 'color' === $type ) {
				$sanitized = sanitize_hex_color( $value );
				if ( $sanitized ) {
					$template_variables[ $key ] = $sanitized;
				} else {
					add_settings_error( 'adc_template_builder', 'invalid_color', sprintf( __( 'Invalid color value for variable %s.', 'ambrosia-dosage-calc' ), esc_html( $key ) ), 'error' );
					return;
				}
			} else {
				$template_variables[ $key ] = self::sanitize_css_value( $value );
			}
		}

		// Generate unique slug
		$base_slug          = self::make_slug( $template_name );
		$new_slug           = $base_slug;
		$i                  = 1;
		$existing_templates = self::get_custom_templates();
		$existing_slugs     = array_column( $existing_templates, 'slug' );

		while ( in_array( $new_slug, $existing_slugs, true ) ) {
			++$i;
			$new_slug = $base_slug . '-' . $i;
		}

		// Create new template entry
		$now          = time();
		$new_template = array(
			'slug'      => $new_slug,
			'name'      => $template_name,
			'variables' => $template_variables,
			'created'   => $now,
			'modified'  => $now,
		);

		$existing_templates[] = $new_template;
		self::save_custom_templates( $existing_templates );

		wp_safe_redirect( admin_url( 'admin.php?page=dosage-calculator-template-builder&imported=1' ) );
		exit;
	}

	/**
	 * Handle template export (JSON download).
	 */
	private function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'ambrosia-dosage-calc' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked immediately below.
		$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'adc_export_template_' . $slug ) ) {
			wp_die( esc_html__( 'Security check failed', 'ambrosia-dosage-calc' ) );
		}

		$template = self::get_custom_template( $slug );
		if ( ! $template ) {
			wp_die( esc_html__( 'Template not found.', 'ambrosia-dosage-calc' ) );
		}

		$export_data = array(
			'adc_template' => 1, // Version marker
			'name'         => $template['name'],
			'variables'    => $template['variables'] ?? array(),
			'exported'     => gmdate( 'Y-m-d\\TH:i:s\\Z' ),
		);

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="adc-template-' . esc_attr( $slug ) . '.json"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Render the list view showing all custom templates.
	 */
	private function render_list_view() {
		$templates        = self::get_custom_templates();
		$current_template = ADC_DB::get_setting( 'template', 'default' );

		// Success notices
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only success messages.
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template saved!', 'ambrosia-dosage-calc' ) . '</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template deleted.', 'ambrosia-dosage-calc' ) . '</p></div>';
		}
		if ( isset( $_GET['imported'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template imported successfully!', 'ambrosia-dosage-calc' ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		settings_errors( 'adc_template_builder' );

		$new_url = admin_url( 'admin.php?page=dosage-calculator-template-builder&action=new' );

		// Enqueue list-view JS/CSS for the template picker modal
		wp_enqueue_style( 'adc-template-builder', ADC_PLUGIN_URL . 'admin/css/adc-template-builder.css', array(), ADC_VERSION );
		wp_enqueue_script( 'adc-template-builder', ADC_PLUGIN_URL . 'admin/js/adc-template-builder.js', array( 'jquery' ), ADC_VERSION, true );
		wp_localize_script(
			'adc-template-builder',
			'adcTemplateBuilderData',
			array(
				'newTemplateUrl'   => $new_url,
				'builtinTemplates' => array(), // not needed on list page
			)
		);

		echo '<p>';
		echo '<button type="button" id="adc-create-new-btn" class="button button-primary">+ ' . esc_html__( 'Create New Template', 'ambrosia-dosage-calc' ) . '</button> ';
		echo '<button type="button" class="button button-secondary" id="adc-toggle-import">' . esc_html__( 'Import Template', 'ambrosia-dosage-calc' ) . ' ▼</button>';
		echo '</p>';

		// Collapsible Import Template Form
		echo '<div id="adc-import-section" style="display:none; margin-top: 10px; padding: 20px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 900px;">';
		echo '<p class="description">' . esc_html__( 'Upload a JSON file to import a custom template.', 'ambrosia-dosage-calc' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" style="margin-top:10px;">';
		wp_nonce_field( 'adc_import_template', 'adc_import_nonce' );
		echo '<input type="hidden" name="action" value="import_template">';
		echo '<input type="file" name="adc_import_file" id="adc_import_file" accept=".json" style="margin-right: 10px;" required>';
		echo '<input type="submit" class="button button-secondary" value="' . esc_attr__( 'Import Now', 'ambrosia-dosage-calc' ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to import this template? It will be added to your custom templates.', 'ambrosia-dosage-calc' ) ) . '\');">';
		echo '</form>';
		echo '</div>';
		echo '<script>
        document.getElementById("adc-toggle-import").addEventListener("click", function() {
            var section = document.getElementById("adc-import-section");
            var btn = this;
            if (section.style.display === "none") {
                section.style.display = "block";
                btn.textContent = "' . esc_js( __( 'Import Template', 'ambrosia-dosage-calc' ) ) . ' ▲";
            } else {
                section.style.display = "none";
                btn.textContent = "' . esc_js( __( 'Import Template', 'ambrosia-dosage-calc' ) ) . ' ▼";
            }
        });
        </script>';

		if ( empty( $templates ) ) {
			echo '<div class="adc-empty-state" style="text-align:center;padding:60px 20px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;margin-top:20px;">';
			echo '<p style="font-size:48px;margin:0;">🎨</p>';
			echo '<h2 style="margin:10px 0 5px;">' . esc_html__( 'No Custom Templates Yet', 'ambrosia-dosage-calc' ) . '</h2>';
			echo '<p style="color:#666;max-width:400px;margin:0 auto 20px;">' . esc_html__( 'Create your first custom template. Pick colors, fonts, and layout options to match your brand.', 'ambrosia-dosage-calc' ) . '</p>';
			echo '<a href="' . esc_url( $new_url ) . '" class="button button-primary button-hero">' . esc_html__( 'Create Your First Template', 'ambrosia-dosage-calc' ) . '</a>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:900px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'ambrosia-dosage-calc' ) . '</th>';
		echo '<th>' . esc_html__( 'Colors', 'ambrosia-dosage-calc' ) . '</th>';
		echo '<th>' . esc_html__( 'Variables', 'ambrosia-dosage-calc' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'ambrosia-dosage-calc' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'ambrosia-dosage-calc' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $templates as $t ) {
			$slug      = esc_attr( $t['slug'] );
			$name      = esc_html( $t['name'] );
			$var_count = count( $t['variables'] ?? array() );
			$is_active = ( $t['slug'] === $current_template );

			$edit_url   = admin_url( 'admin.php?page=dosage-calculator-template-builder&action=edit&slug=' . $slug );
			$delete_url = wp_nonce_url(
				admin_url( 'admin.php?page=dosage-calculator-template-builder&action=delete&slug=' . $slug ),
				'adc_delete_template_' . $slug
			);

			echo '<tr>';

			// Name
			echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $name ) . '</a></strong>';
			echo '<br><code style="font-size:11px;color:#888;">' . esc_html( $slug ) . '</code></td>';

			// Color swatches with accessibility
			echo '<td>';
			$swatch_keys   = array( 'bg', 'text', 'accent', 'surface', 'header-bg', 'border' );
			$swatch_colors = array();
			foreach ( $swatch_keys as $sk ) {
				$color = $t['variables'][ $sk ] ?? '';
				if ( $color && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $color ) ) {
					$swatch_colors[ $sk ] = $color;
				}
			}
			$aria_parts = array();
			foreach ( $swatch_colors as $sk => $color ) {
				$aria_parts[] = $sk . ' ' . $color;
			}
			$aria_label = 'Color palette: ' . implode( ', ', $aria_parts );
			echo '<span style="display:inline-flex;gap:2px;" role="img" aria-label="' . esc_attr( $aria_label ) . '">';
			foreach ( $swatch_colors as $sk => $color ) {
				echo '<span style="display:inline-block;width:18px;height:18px;background:' . esc_attr( $color ) . ';border:1px solid #000;border-radius:2px;" title="' . esc_attr( $sk ) . '"></span>';
			}
			echo '</span>';
			echo '</td>';

			// Variable count
			echo '<td>' . intval( $var_count ) . ' ' . esc_html__( 'set', 'ambrosia-dosage-calc' ) . '</td>';

			// Status
			echo '<td>';
			if ( $is_active ) {
				echo '<span style="color:#2271b1;font-weight:600;">✓ ' . esc_html__( 'Active', 'ambrosia-dosage-calc' ) . '</span>';
			} else {
				echo '<span style="color:#999;">' . esc_html__( 'Inactive', 'ambrosia-dosage-calc' ) . '</span>';
			}
			echo '</td>';

			// Actions
			echo '<td class="adc-tb-actions" data-slug="' . esc_attr( $slug ) . '">';
			echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( 'Edit', 'ambrosia-dosage-calc' ) . '</a> ';

			// Set Active button (only if not currently active)
			if ( ! $is_active ) {
				echo '<button class="button button-small adc-set-active" data-slug="' . esc_attr( $slug ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'adc_set_active_template' ) ) . '">' . esc_html__( 'Set Active', 'ambrosia-dosage-calc' ) . '</button> ';
			}

			// Export button (as a form for POSTing with nonce)
			echo '<form method="post" style="display:inline-block; margin:0;">';
			wp_nonce_field( 'adc_export_template_' . $slug, '_wpnonce', true, true );
			echo '<input type="hidden" name="action" value="export">';
			echo '<input type="hidden" name="slug" value="' . esc_attr( $slug ) . '">';
			echo '<button type="submit" class="button button-small">' . esc_html__( 'Export', 'ambrosia-dosage-calc' ) . '</button>';
			echo '</form> ';

			$duplicate_url = wp_nonce_url(
				admin_url( 'admin.php?page=dosage-calculator-template-builder&action=duplicate&slug=' . $slug ),
				'adc_duplicate_template_' . $slug
			);
			echo '<a href="' . esc_url( $duplicate_url ) . '" class="button button-small">' . esc_html__( 'Duplicate', 'ambrosia-dosage-calc' ) . '</a> ';

			echo '<a href="' . esc_url( $delete_url ) . '" class="button button-small" style="color:#a00;" onclick="return confirm(\'' . esc_js( __( 'Delete this template? This cannot be undone.', 'ambrosia-dosage-calc' ) ) . '\');">' . esc_html__( 'Delete', 'ambrosia-dosage-calc' ) . '</a>';
			echo '</td>';

			echo '</tr>';
		}

		echo '</tbody></table>';

		// Built-in Template Gallery — collapsed by default (modal is the primary entry point)
		echo '<div style="margin-top:40px;max-width:900px;">';
		echo '<button type="button" id="adc-toggle-gallery" class="button button-secondary" style="margin-bottom:10px;">' . esc_html__( 'Browse all built-in templates', 'ambrosia-dosage-calc' ) . ' ▸</button>';
		echo '<div id="adc-builtin-gallery-wrap" style="display:none;">';
		echo '<p class="description">' . esc_html__( 'Use these official templates as a starting point for your custom designs.', 'ambrosia-dosage-calc' ) . '</p>';
		echo '<div class="adc-builtin-gallery">';
		$builtins = self::get_builtin_templates();
		foreach ( $builtins as $slug => $bt ) {
			$name           = esc_html( $bt['name'] );
			$start_from_url = admin_url( 'admin.php?page=dosage-calculator-template-builder&action=new&start_from=' . esc_attr( $slug ) );
			echo '<div class="adc-builtin-card">';
			echo '<h3 class="adc-builtin-card-title">' . esc_html( $name ) . '</h3>';
			echo '<div class="adc-builtin-card-swatches">';
			$swatch_keys = array( 'bg', 'text', 'accent', 'surface', 'header-bg', 'border' );
			foreach ( $swatch_keys as $sk ) {
				$color = $bt['variables'][ $sk ] ?? '';
				if ( $color && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $color ) ) {
					echo '<span style="display:inline-block;width:20px;height:20px;background:' . esc_attr( $color ) . ';border:1px solid #eee;border-radius:3px;" title="' . esc_attr( $sk ) . '"></span>';
				}
			}
			echo '</div>';
			echo '<div class="adc-builtin-card-actions">';
			echo '<a href="' . esc_url( $start_from_url ) . '" class="button button-small">' . esc_html__( 'Use as Starting Point', 'ambrosia-dosage-calc' ) . '</a>';
			echo '</div>';
			echo '</div>';
		}
		echo '</div>'; // .adc-builtin-gallery
		echo '</div>'; // #adc-builtin-gallery-wrap
		echo '</div>';
		echo '<script>
        document.getElementById("adc-toggle-gallery").addEventListener("click", function() {
            var wrap = document.getElementById("adc-builtin-gallery-wrap");
            if (wrap.style.display === "none") {
                wrap.style.display = "block";
                this.textContent = "' . esc_js( __( 'Browse all built-in templates', 'ambrosia-dosage-calc' ) ) . ' ▾";
            } else {
                wrap.style.display = "none";
                this.textContent = "' . esc_js( __( 'Browse all built-in templates', 'ambrosia-dosage-calc' ) ) . ' ▸";
            }
        });
        </script>';

		// Template picker modal
		$this->render_template_picker_modal();

		// "Set Active" AJAX handler
		?>
		<script>
		document.querySelectorAll('.adc-set-active').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var slug = this.dataset.slug;
				var nonce = this.dataset.nonce;
				var button = this;
				button.disabled = true;
				button.textContent = '<?php echo esc_js( __( 'Saving...', 'ambrosia-dosage-calc' ) ); ?>';

				var formData = new FormData();
				formData.append('action', 'adc_set_active_template');
				formData.append('slug', slug);
				formData.append('nonce', nonce);

				fetch(ajaxurl, { method: 'POST', body: formData })
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (data.success) {
							// Update all rows: remove active from others
							document.querySelectorAll('.adc-tb-actions').forEach(function(td) {
								var row = td.closest('tr');
								var statusCell = row.querySelectorAll('td')[3];
								var rowSlug = td.dataset.slug;
								if (rowSlug === slug) {
									// This row is now active
									statusCell.innerHTML = '<span style="color:#2271b1;font-weight:600;">✓ <?php echo esc_js( __( 'Active', 'ambrosia-dosage-calc' ) ); ?></span>';
									button.replaceWith(document.createTextNode(''));
								} else {
									// Reset other rows
									statusCell.innerHTML = '<span style="color:#999;"><?php echo esc_js( __( 'Inactive', 'ambrosia-dosage-calc' ) ); ?></span>';
									// Add Set Active button if missing
									if (!td.querySelector('.adc-set-active')) {
										var editBtn = td.querySelector('.button-small');
										if (editBtn) {
											var newBtn = document.createElement('button');
											newBtn.className = 'button button-small adc-set-active';
											newBtn.dataset.slug = rowSlug;
											newBtn.dataset.nonce = nonce;
											newBtn.textContent = '<?php echo esc_js( __( 'Set Active', 'ambrosia-dosage-calc' ) ); ?>';
											newBtn.addEventListener('click', arguments.callee);
											editBtn.after(document.createTextNode(' '));
											editBtn.after(newBtn);
										}
									}
								}
							});
						} else {
							alert(data.data && data.data.message ? data.data.message : '<?php echo esc_js( __( 'Error activating template.', 'ambrosia-dosage-calc' ) ); ?>');
							button.disabled = false;
							button.textContent = '<?php echo esc_js( __( 'Set Active', 'ambrosia-dosage-calc' ) ); ?>';
						}
					})
					.catch(function() {
						alert('<?php echo esc_js( __( 'Network error. Please try again.', 'ambrosia-dosage-calc' ) ); ?>');
						button.disabled = false;
						button.textContent = '<?php echo esc_js( __( 'Set Active', 'ambrosia-dosage-calc' ) ); ?>';
					});
			});
		});
		</script>
		<?php
	}

	/**
	 * Tier assignments for accordion groups.
	 */
	private static $group_tiers = array(
		'Core Colors'       => 1,
		'Header & Tabs'     => 2,
		'Body & Inputs'     => 2,
		'Buttons'           => 2,
		'Special Areas'     => 3,
		'Dose Level Colors' => 3,
		'Accent Colors'     => 3,
		'Layout'            => 3,
		'Fonts'             => 3,
	);

	/**
	 * Render the edit/create view.
	 */
	private function render_edit_view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter.
		$editing_slug = isset( $_GET['slug'] ) ? sanitize_key( wp_unslash( $_GET['slug'] ) ) : '';
		$template     = null;

		if ( $editing_slug ) {
			$template = self::get_custom_template( $editing_slug );
			if ( ! $template ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Template not found.', 'ambrosia-dosage-calc' ) . '</p></div>';
				return;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter.
		if ( isset( $_GET['duplicated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template duplicated successfully! Now editing the copy.', 'ambrosia-dosage-calc' ) . '</p></div>';
		}

		settings_errors( 'adc_template_builder' );

		$name      = $template ? $template['name'] : '';
		$variables = $template ? ( $template['variables'] ?? array() ) : array();
		$builtins  = self::get_builtin_templates();

		// Enqueue external JS and CSS
		// Self-hosted Pickr: eliminates CDN dependency without SRI (BUG-002).
		wp_enqueue_style( 'pickr-nano', ADC_PLUGIN_URL . 'admin/css/vendor/pickr-nano.min.css', array(), '1.9.1' );
		wp_enqueue_style( 'adc-template-builder', ADC_PLUGIN_URL . 'admin/css/adc-template-builder.css', array( 'pickr-nano' ), ADC_VERSION );
		wp_enqueue_script( 'pickr', ADC_PLUGIN_URL . 'admin/js/vendor/pickr.min.js', array(), '1.9.1', true );
		wp_enqueue_script( 'adc-template-builder', ADC_PLUGIN_URL . 'admin/js/adc-template-builder.js', array( 'jquery', 'pickr' ), ADC_VERSION, true );

		$builtin_json = array();
		foreach ( $builtins as $slug => $bt ) {
			$builtin_json[ $slug ] = $bt['variables'];
		}
		wp_localize_script(
			'adc-template-builder',
			'adcTemplateBuilderData',
			array(
				'builtinTemplates' => $builtin_json,
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter.
				'startFromSlug'    => isset( $_GET['start_from'] ) ? sanitize_key( wp_unslash( $_GET['start_from'] ) ) : '',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter.
				'currentAction'    => isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '',
			)
		);

		$back_url = admin_url( 'admin.php?page=dosage-calculator-template-builder' );

		// Wrap entire editor in a form so the sticky toolbar can contain the submit button
		echo '<form method="post" id="adc-template-builder-form">';
		wp_nonce_field( 'adc_template_builder_save', 'adc_template_builder_nonce' );
		if ( $editing_slug ) {
			echo '<input type="hidden" name="editing_slug" value="' . esc_attr( $editing_slug ) . '">';
		}

		// Sticky top toolbar: back link, template name, start from, save/cancel
		echo '<div class="adc-tb-toolbar">';
		echo '<a href="' . esc_url( $back_url ) . '" class="adc-tb-toolbar-back">&larr; ' . esc_html__( 'Templates', 'ambrosia-dosage-calc' ) . '</a>';
		echo '<input type="text" id="template_name" name="template_name" value="' . esc_attr( $name ) . '" class="adc-tb-toolbar-name" placeholder="' . esc_attr__( 'Template Name', 'ambrosia-dosage-calc' ) . '" required>';
		echo '<select id="adc_start_from" class="adc-tb-toolbar-start" onchange="adcLoadBuiltinTemplate(this.value)">';
		echo '<option value="">' . esc_html__( 'Start from...', 'ambrosia-dosage-calc' ) . '</option>';
		foreach ( $builtins as $slug => $bt ) {
			echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $bt['name'] ) . '</option>';
		}
		echo '</select>';
		echo '<div class="adc-tb-toolbar-actions">';
		submit_button( $editing_slug ? __( 'Update Template', 'ambrosia-dosage-calc' ) : __( 'Create Template', 'ambrosia-dosage-calc' ), 'primary', 'submit', false );
		echo ' <a href="' . esc_url( $back_url ) . '" class="button">' . esc_html__( 'Cancel', 'ambrosia-dosage-calc' ) . '</a>';
		echo '</div>';
		echo '</div>';

		// Two-column layout: form on left, live preview on right
		echo '<div class="adc-tb-layout">';
		echo '<div class="adc-tb-form-col">';

		// Variable sections as tiered accordion
		$last_tier = 0;
		foreach ( self::$variable_groups as $group_label => $group_vars ) {
			$tier         = self::$group_tiers[ $group_label ] ?? 3;
			$section_slug = sanitize_title( $group_label );
			$is_open      = ( 1 === $tier ); // Only Tier 1 is open by default

			// Advanced divider before Tier 3
			if ( 3 === $tier && $last_tier < 3 ) {
				echo '<div class="adc-advanced-divider"><span>' . esc_html__( 'Advanced Options', 'ambrosia-dosage-calc' ) . '</span></div>';
			}
			$last_tier = $tier;

			// Count color vars for badge
			$color_count   = 0;
			$has_text_vars = false;
			foreach ( $group_vars as $def ) {
				if ( ( $def['type'] ?? 'text' ) === 'color' ) {
					++$color_count;
				} else {
					$has_text_vars = true;
				}
			}

			echo '<div class="adc-accordion-section adc-tier-' . intval( $tier ) . '" data-section="' . esc_attr( $section_slug ) . '" data-open="' . ( $is_open ? 'true' : 'false' ) . '">';
			echo '<button type="button" class="adc-accordion-header" aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" aria-controls="adc-section-' . esc_attr( $section_slug ) . '">';
			echo '<span class="adc-accordion-title">' . esc_html( $group_label ) . '</span>';
			if ( $color_count > 0 ) {
				echo '<span class="adc-accordion-badge">' . intval( $color_count ) . ' ' . esc_html__( 'colors', 'ambrosia-dosage-calc' ) . '</span>';
			}
			echo '<span class="adc-accordion-arrow">' . ( $is_open ? '▾' : '▸' ) . '</span>';
			echo '</button>';
			echo '<div class="adc-accordion-body" id="adc-section-' . esc_attr( $section_slug ) . '"' . ( $is_open ? '' : ' style="display:none;"' ) . '>';

			// Render color vars as grid
			if ( $color_count > 0 ) {
				echo '<div class="adc-color-grid">';
				foreach ( $group_vars as $key => $def ) {
					if ( ( $def['type'] ?? 'text' ) !== 'color' ) {
						continue;
					}
					$post_key    = 'var_' . str_replace( '-', '_', $key );
					$current_val = $variables[ $key ] ?? '';
					$label       = $def['label'] ?? $key;
					$desc        = $def['desc'] ?? '';

					echo '<div class="adc-color-card">';
					echo '<label for="' . esc_attr( $post_key ) . '" class="adc-color-label">' . esc_html( $label ) . '</label>';
					echo '<div class="adc-pickr-row">';
					echo '<div class="adc-pickr-trigger" data-for="' . esc_attr( $post_key ) . '"></div>';
					echo '<input type="text" id="' . esc_attr( $post_key ) . '" name="' . esc_attr( $post_key ) . '" value="' . esc_attr( $current_val ) . '" class="adc-color-picker-input" data-key="' . esc_attr( $key ) . '" placeholder="#000000">';
					echo '</div>';
					if ( $desc ) {
						echo '<p class="description">' . esc_html( $desc ) . '</p>';
					}
					echo '</div>';
				}
				echo '</div>';
			}

			// Render text vars as table with specialized controls
			if ( $has_text_vars ) {
				echo '<table class="form-table adc-text-vars-table">';
				foreach ( $group_vars as $key => $def ) {
					if ( ( $def['type'] ?? 'text' ) === 'color' ) {
						continue;
					}
					$post_key    = 'var_' . str_replace( '-', '_', $key );
					$current_val = $variables[ $key ] ?? '';
					$label       = $def['label'] ?? $key;
					$desc        = $def['desc'] ?? '';

					echo '<tr>';
					echo '<th><label for="' . esc_attr( $post_key ) . '">' . esc_html( $label ) . '</label></th>';
					echo '<td>';

					// Specialized controls based on variable key
					if ( 'border-width' === $key ) {
						// Range slider for border width
						$num_val = floatval( preg_replace( '/[^0-9.]/', '', $current_val ) );
						echo '<div class="adc-range-control">';
						echo '<input type="range" min="0" max="5" step="0.5" value="' . esc_attr( (string) $num_val ) . '" class="adc-range-slider" data-target="' . esc_attr( $post_key ) . '" data-unit="px">';
						echo '<span class="adc-range-value">' . esc_html( $current_val ?: '1px' ) . '</span>';
						echo '<input type="hidden" id="' . esc_attr( $post_key ) . '" name="' . esc_attr( $post_key ) . '" value="' . esc_attr( $current_val ) . '" class="adc-tb-text-input" data-key="' . esc_attr( $key ) . '">';
						echo '</div>';
					} elseif ( 'radius' === $key ) {
						// Range slider for border radius
						$num_val = floatval( preg_replace( '/[^0-9.]/', '', $current_val ) );
						echo '<div class="adc-range-control">';
						echo '<input type="range" min="0" max="24" step="1" value="' . esc_attr( (string) $num_val ) . '" class="adc-range-slider" data-target="' . esc_attr( $post_key ) . '" data-unit="px">';
						echo '<span class="adc-range-value">' . esc_html( $current_val ?: '8px' ) . '</span>';
						echo '<input type="hidden" id="' . esc_attr( $post_key ) . '" name="' . esc_attr( $post_key ) . '" value="' . esc_attr( $current_val ) . '" class="adc-tb-text-input" data-key="' . esc_attr( $key ) . '">';
						echo '</div>';
					} elseif ( in_array( $key, array( 'shadow', 'header-shadow', 'box-shadow', 'card-shadow' ), true ) ) {
						// Shadow presets dropdown
						$shadow_presets = array(
							''                            => __( '(Default)', 'ambrosia-dosage-calc' ),
							'none'                        => __( 'None (flat)', 'ambrosia-dosage-calc' ),
							'0 1px 3px rgba(0,0,0,0.08)'  => __( 'Subtle', 'ambrosia-dosage-calc' ),
							'0 4px 12px rgba(0,0,0,0.12)' => __( 'Medium', 'ambrosia-dosage-calc' ),
							'0 8px 24px rgba(0,0,0,0.18)' => __( 'Strong', 'ambrosia-dosage-calc' ),
							'custom'                      => __( 'Custom...', 'ambrosia-dosage-calc' ),
						);
						$is_preset      = array_key_exists( $current_val, $shadow_presets );
						$select_val     = $is_preset ? $current_val : ( '' !== $current_val ? 'custom' : '' );

						echo '<select class="adc-shadow-select" data-target="' . esc_attr( $post_key ) . '">';
						foreach ( $shadow_presets as $val => $plabel ) {
							echo '<option value="' . esc_attr( $val ) . '"' . selected( $select_val, $val, false ) . '>' . esc_html( $plabel ) . '</option>';
						}
						echo '</select>';
						echo '<input type="text" id="' . esc_attr( $post_key ) . '" name="' . esc_attr( $post_key ) . '" value="' . esc_attr( $current_val ) . '" class="regular-text adc-tb-text-input adc-shadow-custom-input" data-key="' . esc_attr( $key ) . '" style="' . ( 'custom' !== $select_val ? 'display:none;margin-top:6px;' : 'margin-top:6px;' ) . '" placeholder="' . esc_attr__( 'Custom CSS shadow value', 'ambrosia-dosage-calc' ) . '">';
					} elseif ( in_array( $key, array( 'header-border', 'container-border' ), true ) ) {
						// Border shorthand with quick-set buttons
						echo '<div class="adc-border-quick-set" style="margin-bottom:6px;">';
						echo '<span style="font-size:11px;color:#888;margin-right:6px;">' . esc_html__( 'Quick set:', 'ambrosia-dosage-calc' ) . '</span>';
						$border_presets = array(
							'none'                   => __( 'None', 'ambrosia-dosage-calc' ),
							'1px solid currentColor' => __( 'Thin', 'ambrosia-dosage-calc' ),
							'2px solid currentColor' => __( 'Medium', 'ambrosia-dosage-calc' ),
							'3px solid currentColor' => __( 'Thick', 'ambrosia-dosage-calc' ),
						);
						foreach ( $border_presets as $bval => $blabel ) {
							echo '<button type="button" class="button button-small adc-border-quick-btn" data-value="' . esc_attr( $bval ) . '" data-target="' . esc_attr( $post_key ) . '">' . esc_html( $blabel ) . '</button> ';
						}
						echo '</div>';
						echo '<input type="text" id="' . esc_attr( $post_key ) . '" name="' . esc_attr( $post_key ) . '" value="' . esc_attr( $current_val ) . '" class="regular-text adc-tb-text-input" data-key="' . esc_attr( $key ) . '">';
					} elseif ( in_array( $key, array( 'font-heading', 'font-body' ), true ) ) {
						// Font family selector + text input
						$font_options = array(
							''                          => __( '(Custom)', 'ambrosia-dosage-calc' ),
							"'Inter', sans-serif"       => 'Inter',
							"'Work Sans', sans-serif"   => 'Work Sans',
							"'Playfair Display', serif" => 'Playfair Display',
							"'Georgia', serif"          => 'Georgia',
							"'Space Mono', monospace"   => 'Space Mono',
							"'Courier New', monospace"  => 'Courier New',
							'system-ui, sans-serif'     => 'System UI',
						);
						echo '<select class="adc-font-select" data-target="' . esc_attr( $post_key ) . '" style="margin-bottom:6px;display:block;">';
						foreach ( $font_options as $fval => $flabel ) {
							$font_selected = ( $fval === $current_val ) ? ' selected' : '';
							echo '<option value="' . esc_attr( $fval ) . '"' . esc_attr( $font_selected ) . '>' . esc_html( $flabel ) . '</option>';
						}
						echo '</select>';
						echo '<input type="text" id="' . esc_attr( $post_key ) . '" name="' . esc_attr( $post_key ) . '" value="' . esc_attr( $current_val ) . '" class="regular-text adc-tb-text-input" data-key="' . esc_attr( $key ) . '" placeholder="' . esc_attr__( 'Or type a custom font family', 'ambrosia-dosage-calc' ) . '">';
					} else {
						// Default text input
						echo '<input type="text" id="' . esc_attr( $post_key ) . '" name="' . esc_attr( $post_key ) . '" value="' . esc_attr( $current_val ) . '" class="regular-text adc-tb-text-input" data-key="' . esc_attr( $key ) . '">';
					}

					if ( $desc ) {
						echo '<p class="description">' . esc_html( $desc ) . '</p>';
					}
					echo '</td>';
					echo '</tr>';
				}
				echo '</table>';
			}

			echo '</div>'; // .adc-accordion-body
			echo '</div>'; // .adc-accordion-section
		}

		// WCAG Contrast Checker Panel
		echo '<div id="adc-contrast-panel" class="adc-contrast-panel">';
		echo '<h3>' . esc_html__( 'Accessibility Check', 'ambrosia-dosage-calc' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Real-time contrast ratios for key color pairs. WCAG AA requires 4.5:1 for normal text.', 'ambrosia-dosage-calc' ) . '</p>';
		echo '<div id="adc-contrast-results"></div>';
		echo '</div>';

		echo '</div>'; // end .adc-tb-form-col

		// Live Preview Panel (right column)
		$this->render_preview_panel();

		echo '</div>'; // end .adc-tb-layout
		echo '</form>'; // end form (wraps both columns + toolbar)
	}

	/**
	 * Render the template picker modal for "Create New Template".
	 *
	 * @since 2.15.0
	 */
	private function render_template_picker_modal() {
		$builtins = self::get_builtin_templates();
		?>
		<div id="adc-picker-modal" class="adc-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="adc-picker-title">
			<div class="adc-modal-box">
				<div class="adc-modal-header">
					<h2 id="adc-picker-title">🎨 <?php esc_html_e( 'Choose a Starting Point', 'ambrosia-dosage-calc' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Pick a built-in template to customize, or start from scratch.', 'ambrosia-dosage-calc' ); ?></p>
					<button type="button" class="adc-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ambrosia-dosage-calc' ); ?>">&times;</button>
				</div>
				<div class="adc-modal-body">
					<div class="adc-picker-grid">
						<!-- Blank option first -->
						<div class="adc-picker-card adc-picker-blank" data-slug="" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'Start from blank template', 'ambrosia-dosage-calc' ); ?>">
							<div class="adc-picker-preview adc-picker-preview-blank">
								<span>✦</span>
							</div>
							<div class="adc-picker-card-name"><?php esc_html_e( 'Blank', 'ambrosia-dosage-calc' ); ?></div>
							<div class="adc-picker-card-desc"><?php esc_html_e( 'Set all values yourself', 'ambrosia-dosage-calc' ); ?></div>
						</div>
						<?php
						foreach ( $builtins as $slug => $bt ) :
							$vars    = $bt['variables'];
							$bg      = esc_attr( $vars['bg'] ?? '#ffffff' );
							$text    = esc_attr( $vars['text'] ?? '#333333' );
							$accent  = esc_attr( $vars['accent'] ?? '#3b82f6' );
							$surface = esc_attr( $vars['surface'] ?? '#f9fafb' );
							$header  = esc_attr( $vars['header-bg'] ?? $bg );
							$tabact  = esc_attr( $vars['tab-active-bg'] ?? $accent );
							$radius  = esc_attr( $vars['radius'] ?? '8px' );
							?>
						<div class="adc-picker-card" data-slug="<?php echo esc_attr( $slug ); ?>" tabindex="0" role="button" aria-label="<?php echo esc_attr( sprintf( __( 'Start from %s template', 'ambrosia-dosage-calc' ), $bt['name'] ) ); ?>">
							<div class="adc-picker-preview" style="
								background:<?php echo esc_attr( $bg ); ?>;
								color:<?php echo esc_attr( $text ); ?>;
								border-radius:<?php echo esc_attr( $radius ); ?>;
								overflow:hidden;
								font-size:9px;
							">
								<!-- Mini calculator mockup inline -->
								<div style="background:<?php echo esc_attr( $header ); ?>;color:<?php echo esc_attr( $text ); ?>;padding:6px 8px;text-align:center;font-weight:700;font-size:10px;">🍄 Calculator</div>
								<div style="display:flex;background:<?php echo esc_attr( $vars['tab-bg'] ?? '#f0f0f0' ); ?>">
									<div style="flex:1;text-align:center;padding:3px;background:<?php echo esc_attr( $tabact ); ?>;color:<?php echo esc_attr( $vars['tab-active-text'] ?? '#fff' ); ?>;font-size:8px;">Mushrooms</div>
									<div style="flex:1;text-align:center;padding:3px;font-size:8px;color:<?php echo esc_attr( $vars['tab-text'] ?? '#666' ); ?>">Edibles</div>
								</div>
								<div style="padding:6px;background:<?php echo esc_attr( $vars['body-bg'] ?? $bg ); ?>">
									<div style="background:<?php echo esc_attr( $surface ); ?>;border-radius:4px;padding:4px 6px;margin-bottom:4px;">
										<div style="font-size:7px;opacity:0.6;text-transform:uppercase;">Body Weight</div>
										<div style="font-weight:600;font-size:11px;">165 lbs</div>
									</div>
									<div style="display:grid;grid-template-columns:1fr 1fr;gap:3px;margin-bottom:4px;">
										<div style="background:<?php echo esc_attr( $surface ); ?>;border-radius:4px;padding:3px;text-align:center;">
											<div style="font-size:7px;opacity:0.6;">Micro</div>
											<div style="color:<?php echo esc_attr( $accent ); ?>;font-weight:700;">0.15g</div>
										</div>
										<div style="background:<?php echo esc_attr( $surface ); ?>;border-radius:4px;padding:3px;text-align:center;outline:1px solid <?php echo esc_attr( $accent ); ?>">
											<div style="font-size:7px;opacity:0.6;">Medium</div>
											<div style="color:<?php echo esc_attr( $accent ); ?>;font-weight:700;">1.5g</div>
										</div>
									</div>
									<div style="background:<?php echo esc_attr( $vars['btn-primary-bg'] ?? $accent ); ?>;color:<?php echo esc_attr( $vars['btn-primary-text'] ?? '#fff' ); ?>;border-radius:4px;padding:4px;text-align:center;font-weight:600;font-size:9px;">Calculate</div>
								</div>
							</div>
							<div class="adc-picker-card-name"><?php echo esc_html( $bt['name'] ); ?></div>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="adc-modal-footer">
					<button type="button" class="adc-modal-close button"><?php esc_html_e( 'Cancel', 'ambrosia-dosage-calc' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the live preview panel with an iframe loading the real calculator.
	 *
	 * @since 2.16.0
	 */
	private function render_preview_panel() {
		$preview_url = esc_url( home_url( '/adc-preview/' ) );
		?>
		<div class="adc-tb-preview-col" aria-hidden="true">
			<div class="adc-tb-preview-sticky">
				<div class="adc-preview-toolbar">
					<h3 class="adc-preview-label">Live Preview</h3>
					<div class="adc-preview-bg-toggle">
						<span><?php esc_html_e( 'Page bg:', 'ambrosia-dosage-calc' ); ?></span>
						<button type="button" class="adc-preview-bg-btn active" data-bg="#ffffff" title="White">☀️</button>
						<button type="button" class="adc-preview-bg-btn" data-bg="#1a1a2e" title="Dark">🌙</button>
						<button type="button" class="adc-preview-bg-btn" data-bg="#f0f4f8" title="Gray">🌫️</button>
					</div>
				</div>
				<div class="adc-preview-iframe-wrapper" style="background:#ffffff;">
					<iframe
						id="adc-preview-iframe"
						src="<?php echo esc_url( $preview_url ); ?>"
						class="adc-preview-iframe"
						title="Calculator Preview"
						scrolling="no"
					></iframe>
					<div class="adc-preview-loading">Loading preview…</div>
				</div>
				<div class="adc-preview-footer-bar">
					<button type="button" id="adc-preview-refresh-btn" class="button button-small">↺ Refresh</button>
	
					<span class="adc-preview-status" id="adc-preview-status">Loading…</span>
				</div>
			</div>
		</div>
		<script>
		// Pass preview URL to JS
		window.adcPreviewUrl = <?php echo wp_json_encode( $preview_url ); ?>;
		</script>
		<?php
	}

	/**
	 * Sanitize a CSS value by stripping unsafe characters.
	 * Only allows: alphanumeric, spaces, #, ., (, ), comma, -, %, ', ", /, _
	 *
	 * @since 2.13.3
	 */
	private static function sanitize_css_value( $value ) {
		$v = trim( $value );
		if ( '' === $v ) {
			return '';
		}
		// Strip null bytes and control characters.
		$v = str_replace( array( "\x00", "\x1F" ), '', $v );
		// Block CSS expression() injection entirely.
		if ( stripos( $v, 'expression(' ) !== false ) {
			return '';
		}
		// Block @import (can load external sheets) and IE behavior: property.
		if ( preg_match( '/@import/i', $v ) || preg_match( '/behavior\s*:/i', $v ) ) {
			return '';
		}
		// Block javascript: in url() values.
		$v = preg_replace( '/url\s*\(\s*["\']?\s*javascript:/i', 'url(#', $v );
		// Block data: URIs in url() — can carry payloads.
		$v = preg_replace( '/url\s*\(\s*["\']?\s*data:/i', 'url(#', $v );
		// Balance single quotes: if odd count, strip them all (prevents unclosed string injection).
		if ( substr_count( $v, "'" ) % 2 !== 0 ) {
			$v = str_replace( "'", '', $v );
		}
		// Balance double quotes: same logic.
		if ( substr_count( $v, '"' ) % 2 !== 0 ) {
			$v = str_replace( '"', '', $v );
		}
		// Allow safe CSS character set.
		$v = preg_replace( '/[^a-zA-Z0-9\s#\.\(\),\-\%\'\"\/\_\:\!\*\+]/', '', $v );
		return $v;
	}

	/**
	 * Get the type (color or text) for a given variable key.
	 */
	private static function get_variable_type( $key ) {
		foreach ( self::$variable_groups as $vars ) {
			if ( isset( $vars[ $key ] ) ) {
				return $vars[ $key ]['type'] ?? 'text';
			}
		}
		return 'text';
	}
}
