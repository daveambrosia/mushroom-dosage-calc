<?php
/**
 * Shortcode handler - Version 2.2
 * Renders the dosage calculator with support for URL params (?t=e) and hash anchors (#edibles)
 * Now includes visual theme support via theme attribute
 *
 * @package Ambrosia_Dosage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ADC_Shortcode class.
 *
 * @package Ambrosia_Dosage_Calculator
 */
class ADC_Shortcode {

	// Compound labels now defined in ADC_Constants::COMPOUND_LABELS

	public static function register() {
		add_shortcode( 'dosage_calculator', array( __CLASS__, 'render' ) );
		add_shortcode( 'adc_calculator', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		// Load all settings once (avoids 12+ individual get_option calls)
		$s = ADC_DB::get_settings();

		// Merge defaults with shortcode attributes
		$atts = shortcode_atts(
			array(
				'template'                => $s['template'] ?? 'default',
				'theme'                   => $s['visual_theme'] ?? '',
				'show_edibles'            => $s['show_edibles'] ?? true,
				'show_mushrooms'          => $s['show_mushrooms'] ?? true,
				'default_tab'             => $s['default_tab'] ?? 'mushrooms',
				'default_strain'          => '',
				'default_edible'          => '',
				'show_quick_converter'    => $s['show_quick_converter'] ?? true,
				'show_compound_breakdown' => $s['show_compound_breakdown'] ?? true,
				'show_safety_warning'     => $s['show_safety_warning'] ?? true,
				'allow_custom'            => $s['allow_custom'] ?? true,
				'allow_submit'            => $s['allow_submit'] ?? true,
			),
			$atts,
			'dosage_calculator'
		);

		// Convert string booleans
		$bool_keys = array(
			'show_edibles',
			'show_mushrooms',
			'show_quick_converter',
			'show_compound_breakdown',
			'show_safety_warning',
			'allow_custom',
			'allow_submit',
		);
		foreach ( $bool_keys as $key ) {
			$atts[ $key ] = filter_var( $atts[ $key ], FILTER_VALIDATE_BOOLEAN );
		}

		// Legacy theme attribute (kept for backward compatibility, not used for styling)
		$theme = sanitize_key( $atts['theme'] );
		// Note: Legacy $theme_class removed in v2.12.50; templates use data-template attribute

		// Always enqueue the templates CSS — it targets data-template="xxx" selectors
		// and must load for ANY template selection to take effect
		wp_enqueue_style(
			'adc-calculator-themes',
			ADC_PLUGIN_URL . 'public/css/calculator-themes' . ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '' : '.min' ) . '.css',
			array( 'adc-calculator' ),
			ADC_VERSION . '-t3'
		);

		// Determine initial tab from URL param (t=e for edibles, t=m for mushrooms)
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only display filter.
		$url_tab = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
		if ( 'e' === $url_tab && $atts['show_edibles'] ) {
			$initial_tab = 'edibles';
		} elseif ( 'm' === $url_tab && $atts['show_mushrooms'] ) {
			$initial_tab = 'mushrooms';
		} else {
			$initial_tab = $atts['default_tab'];
		}
		$is_edibles = ( 'edibles' === $initial_tab );

		// Process legacy QR code redirects
		$legacy_data = ADC_QR_Handler::process_legacy_request();

		// Categories loaded server-side (small payload); strains/edibles lazy-loaded via REST
		$categories = ADC_Strains::get_categories_with_counts();

		// Note: individual template CSS files (templates/*.css) are legacy stubs.
		// All template styles live in calculator-themes.css (enqueued above).

		// Inject custom template CSS (from Template Builder)
		if ( class_exists( 'ADC_Template_CSS' ) ) {
			$custom_template_css = ADC_Template_CSS::generate_all_custom_css();
			if ( $custom_template_css ) {
				wp_add_inline_style( 'adc-calculator-themes', $custom_template_css );
			}
		}

		// Custom CSS from admin settings
		// Sanitize: strip tags and dangerous characters to prevent Stored XSS.
		// wp_strip_all_tags removes </style> injection; the regex further restricts
		// to safe CSS character set (same pattern as sanitize_css_value in Template Builder).
		$custom_css = $s['custom_css'] ?? '';
		if ( $custom_css ) {
			$custom_css = wp_strip_all_tags( $custom_css );
			$custom_css = preg_replace( '/[^a-zA-Z0-9\s#\.\(\),\-\%\/\_\:;\{\}\!\@\*\+\[\]\=\>\~\^\|]/', '', $custom_css );
			wp_add_inline_style( 'adc-calculator', $custom_css );
		}

		// Note: Color overrides removed in v2.13.2. All color customization
		// is now handled through the Template Builder (custom templates).

		// Pass config to JavaScript via inline script (more reliable than wp_localize_script)
		// Strains/edibles are lazy-loaded via REST API (reduces page weight by 10-50KB)
		$config_data = array(
			'strains'    => array(), // Lazy-loaded via REST
			'edibles'    => array(), // Lazy-loaded via REST
			'categories' => $categories,
			'legacyData' => $legacy_data,
			'settings'   => array(
				'showEdibles'           => $atts['show_edibles'],
				'showMushrooms'         => $atts['show_mushrooms'],
				'defaultTab'            => $initial_tab,
				'defaultStrain'         => $atts['default_strain'],
				'defaultEdible'         => $atts['default_edible'],
				'showQuickConverter'    => $atts['show_quick_converter'],
				'showCompoundBreakdown' => $atts['show_compound_breakdown'],
				'showSafetyWarning'     => $atts['show_safety_warning'],
				'allowCustom'           => $atts['allow_custom'],
				'allowSubmit'           => $atts['allow_submit'],
				'theme'                 => $theme,
			),
		);
		wp_add_inline_script(
			'adc-calculator',
			'var adcConfig = ' . wp_json_encode( $config_data ) . ';',
			'before'
		);

		$disclaimer          = $s['disclaimer_text'] ?? 'For educational and spiritual purposes only.';
		$calculator_title    = $s['calculator_title'] ?? '🍄 Psilocybin Dosage Calculator';
		$calculator_subtitle = $s['calculator_subtitle'] ?? 'Psilocybin Dosage Calculator: For use with lab-tested mushrooms and edibles. Every mushroom is different; even ones growing side by side can be twice as strong as the other. Lab tests show some can be up to 80 times stronger than others. Strength depends on the strain, the grower, what they\'re grown on, how they\'re dried, and how they\'re stored.';
		// Safety items: supports both legacy plain-text (one per line) and WYSIWYG HTML
		$safety_raw = $s['safety_items'] ?? "DO NOT drive or operate heavy machinery\nDO NOT mix with alcohol\nRESEARCH all medications before use\nDO NOT take if you have heart problems";
		// Detect whether it's HTML (WYSIWYG) or plain text (legacy line-by-line)
		$safety_is_html = ( strpos( $safety_raw, '<' ) !== false );
		$safety_html    = $safety_is_html ? $safety_raw : '';
		$safety_items   = $safety_is_html ? array() : array_filter( array_map( 'trim', explode( "\n", $safety_raw ) ) );
		$template       = esc_attr( $atts['template'] );
		$theme_attr     = $theme ? ' data-theme="' . esc_attr( $theme ) . '"' : '';

		// Build tolerance options (28 down to 1)
		$tolerance_options = self::build_tolerance_options();

		ob_start();
		?>
		<div class="adc-calculator<?php echo $is_edibles ? ' adc-tab-edibles' : ''; ?>" id="adc-calculator" data-template="<?php echo esc_attr( $template ); ?>"<?php echo $theme_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML attributes verified during generation. ?>>
			<?php // Hash-based tab override (runs on DOMContentLoaded, no document.write) ?>
			<script>document.addEventListener('DOMContentLoaded',function(){var h=location.hash,c=document.getElementById('adc-calculator');if(!c)return;if(h==='#edibles'){c.classList.add('adc-tab-edibles')}else if(h==='#mushrooms'){c.classList.remove('adc-tab-edibles')}var isE=c.classList.contains('adc-tab-edibles'),m=document.getElementById('adc-tab-mushrooms'),e=document.getElementById('adc-tab-edibles');if(m&&e){m.classList.toggle('active',!isE);e.classList.toggle('active',isE);m.setAttribute('aria-selected',!isE);e.setAttribute('aria-selected',isE);m.tabIndex=isE?-1:0;e.tabIndex=isE?0:-1}});</script>
			
			<a href="#adc-input-section" class="adc-skip-link">Skip to calculator inputs</a>
			
			<header class="adc-header">
				<h2 class="adc-title"><?php echo wp_kses_post( $calculator_title ); ?></h2>
				<p class="adc-subtitle"><?php echo wp_kses_post( $calculator_subtitle ); ?></p>
			</header>
			
			<div class="adc-tab-container">
			<?php if ( $atts['show_mushrooms'] && $atts['show_edibles'] ) : ?>
			<div class="adc-tabs" role="tablist" aria-label="Calculator type">
				<button type="button" role="tab" class="adc-tab<?php echo $is_edibles ? '' : ' active'; ?>" id="adc-tab-mushrooms" aria-selected="<?php echo $is_edibles ? 'false' : 'true'; ?>" aria-controls="adc-content-mushrooms" tabindex="<?php echo $is_edibles ? '-1' : '0'; ?>">🍄 Mushrooms</button>
				<button type="button" role="tab" class="adc-tab<?php echo $is_edibles ? ' active' : ''; ?>" id="adc-tab-edibles" aria-selected="<?php echo $is_edibles ? 'true' : 'false'; ?>" aria-controls="adc-content-edibles" tabindex="<?php echo $is_edibles ? '0' : '-1'; ?>">🍫 Edibles</button>
			</div>
			<?php endif; ?>
			
			<div class="adc-body">
			<div class="adc-input-section" id="adc-input-section" tabindex="-1">
				<!-- Weight -->
				<div class="adc-input-group adc-box">
					<label for="adc-weight">Body Weight</label>
					<div class="adc-weight-wrapper">
						<input type="number" id="adc-weight" class="adc-weight-input" min="75" max="600" step="1" aria-describedby="adc-weight-hint">
						<div class="adc-unit-toggle">
							<button type="button" class="active" data-unit="lbs" aria-pressed="true">LBS</button>
							<button type="button" data-unit="kg" aria-pressed="false">KG</button>
						</div>
					</div>
					<p class="adc-hint" id="adc-weight-hint">Affects dosage calculation</p>
				</div>
				
				<!-- Adjustments (combines Tolerance + Sensitivity) -->
				<div class="adc-input-group adc-box" data-collapsible data-section="adjustments">
					<div class="adc-label-row"><label>Adjustments</label></div>
					
					<!-- Combined adjustment percentage -->
					<div class="adc-tolerance-display low" id="adc-tolerance-display">100%</div>
					
					<!-- Days Since Last Dose -->
					<div class="adc-adjustment-subsection">
						<label for="adc-tolerance" class="adc-adj-label">Days Since Last Dose</label>
						<div class="adc-adj-row">
							<select id="adc-tolerance" class="adc-select" aria-describedby="adc-tolerance-hint">
								<?php echo $tolerance_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML options built by safe build_tolerance_options() method. ?>
							</select>
							<button type="button" class="adc-btn adc-reset-btn" data-action="reset-tolerance" title="Reset to 28+ days">Reset</button>
						</div>
						<p class="adc-hint" id="adc-tolerance-hint">Tolerance resets in ~4 weeks</p>
					</div>
					
					<!-- Personal Sensitivity -->
					<div class="adc-adjustment-subsection">
						<label for="adc-sensitivity-slider" id="adc-sensitivity-label" class="adc-adj-label">Personal Sensitivity</label>
						<div class="adc-adj-row">
							<input type="range" class="adc-sensitivity-slider" id="adc-sensitivity-slider" min="10" max="300" step="1" value="100" aria-labelledby="adc-sensitivity-label" aria-valuetext="100% - Normal sensitivity">
							<div class="adc-sensitivity-input-wrapper">
								<input type="number" class="adc-sensitivity-input" id="adc-sensitivity-input" min="10" max="300" value="100" aria-label="Sensitivity percentage">
								<span>%</span>
							</div>
							<button type="button" class="adc-btn adc-reset-btn" data-action="reset-sensitivity" title="Reset to 100%">Reset</button>
						</div>
					</div>
				</div>

				<?php if ( $atts['show_mushrooms'] ) : ?>
				<!-- Mushroom Strain -->
				<div class="adc-input-group adc-box adc-mushroom-input" id="adc-content-mushrooms" role="tabpanel" aria-labelledby="adc-tab-mushrooms" data-collapsible data-section="strain" data-collapse-mode="select-only">
					<label for="adc-strain-select">Mushroom Strain</label>
					<select id="adc-strain-select" class="adc-select">
						<option value="" disabled selected>Select a strain...</option>
					</select>
					<div class="adc-strain-controls" id="adc-strain-controls">
						<button type="button" class="adc-btn adc-add-btn" data-action="open-strain-modal">+ Add</button>
						<button type="button" class="adc-btn adc-edit-btn" style="display:none;" data-action="edit-strain">Edit</button>
						<button type="button" class="adc-btn adc-delete-btn" style="display:none;" data-action="delete-strain">Delete</button>
					</div>
					<div class="adc-potency-display" id="adc-strain-potency"></div>
					<?php echo self::compound_inputs( 'strain' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built by safe compound_inputs() method. ?>
				</div>
				<?php endif; ?>
				
				<?php if ( $atts['show_edibles'] ) : ?>
				<!-- Edible Product -->
				<div class="adc-input-group adc-box adc-edible-input" id="adc-content-edibles" role="tabpanel" aria-labelledby="adc-tab-edibles" data-collapsible data-section="edible-product" data-collapse-mode="select-only">
					<label for="adc-edible-select">Edible Product</label>
					<select id="adc-edible-select" class="adc-select">
						<option value="" disabled selected>Select an edible...</option>
					</select>
					<div class="adc-edible-controls" id="adc-edible-controls">
						<button type="button" class="adc-btn adc-add-btn" data-action="open-edible-modal">+ Add</button>
						<button type="button" class="adc-btn adc-edit-btn" style="display:none;" data-action="edit-edible">Edit</button>
						<button type="button" class="adc-btn adc-delete-btn" style="display:none;" data-action="delete-edible">Delete</button>
					</div>
					<div class="adc-edible-info" id="adc-edible-info"></div>
					<?php echo self::compound_inputs( 'edible' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built by safe compound_inputs() method. ?>
				</div>
				<?php endif; ?>
			</div>
			
			<?php if ( $atts['show_mushrooms'] ) : ?>
			<div class="adc-results-section adc-mushroom-results-section">
				<div class="adc-results-header">
					<h2>Recommended Dosages</h2>
					<div class="adc-results-summary" id="adc-mushroom-summary"></div>
				</div>
				<div class="adc-results-grid" id="adc-mushroom-results" aria-live="polite"></div>
			</div>
			<?php endif; ?>
			
			<?php if ( $atts['show_edibles'] ) : ?>
			<div class="adc-results-section adc-edible-results-section">
				<div class="adc-results-header">
					<h2>Recommended Dosages</h2>
					<div class="adc-results-summary" id="adc-edible-summary"></div>
				</div>
				<div class="adc-results-grid" id="adc-edible-results" aria-live="polite"></div>
			</div>
			<?php endif; ?>
			
			<?php if ( $atts['show_quick_converter'] ) : ?>
			<div class="adc-converter-section adc-box" id="adc-converter-section" data-collapsible data-section="converter">
				<div class="adc-converter-header">
					<h3>Quick Converter</h3>
					<span class="adc-converter-strain" id="adc-converter-strain">Select a strain</span>
				</div>
				<div class="adc-converter-row">
					<div class="adc-converter-input-group">
						<input type="number" class="adc-converter-input" id="adc-mcg-input" placeholder="mcg" min="0" aria-label="Micrograms">
						<span class="adc-converter-label">mcg</span>
					</div>
					<span class="adc-converter-equals">=</span>
					<div class="adc-converter-input-group">
						<input type="number" class="adc-converter-input" id="adc-grams-input" placeholder="grams" min="0" step="0.001" aria-label="Grams">
						<span class="adc-converter-label">g</span>
					</div>
				</div>
				<div class="adc-converter-breakdown" id="adc-converter-breakdown"></div>
			</div>
			<?php endif; ?>
			</div><!-- .adc-body -->
			</div><!-- .adc-tab-container -->
			
			<?php if ( $atts['show_safety_warning'] ) : ?>
			<div class="adc-safety-warning adc-box">
				<h3>⚠️ Safety Information</h3>
				<?php if ( $safety_is_html ) : ?>
					<?php echo wp_kses_post( $safety_html ); ?>
				<?php else : ?>
				<ul>
					<?php foreach ( $safety_items as $item ) : ?>
						<li><?php echo wp_kses_post( $item ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
				<p class="adc-disclaimer"><?php echo esc_html( $disclaimer ); ?></p>
				<div class="adc-data-controls">
					<label>
						<input type="checkbox" id="adc-storage-consent">
						Remember my settings
					</label>
					<button type="button" class="adc-btn adc-btn-danger" data-action="reset-data">Reset All Data</button>
				</div>
			</div>
			<?php endif; ?>
			
			<footer class="adc-footer">
				<p>
					<a href="https://ambrosia.church/magic-mushrooms/levels/" target="_blank" rel="noopener">Dosage Info</a>
					<a href="https://ambrosia.church/safety-guides/" target="_blank" rel="noopener">Safety</a>
					<a href="https://ambrosia.church/" target="_blank" rel="noopener">Church of Ambrosia</a>
				</p>
				<p class="adc-version">v<?php echo esc_html( ADC_VERSION ); ?></p>
			</footer>
			
			<?php echo self::strain_modal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built by safe strain_modal() method. ?>
			<?php echo self::edible_modal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built by safe edible_modal() method. ?>
			
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate tolerance dropdown options
	 */
	private static function build_tolerance_options() {
		$labels  = array(
			28 => '28+ days (No tolerance)',
			21 => '21 days (3 weeks)',
			14 => '14 days (2 weeks)',
			7  => '7 days (1 week)',
			1  => '1 day (yesterday)',
		);
		$options = '';
		for ( $d = 28; $d >= 1; $d-- ) {
			$label    = isset( $labels[ $d ] ) ? $labels[ $d ] : "{$d} days";
			$selected = ( 28 === $d ) ? ' selected' : '';
			$options .= "<option value=\"{$d}\"{$selected}>{$label}</option>\n";
		}
		return $options;
	}

	/**
	 * Generate compound input grid
	 */
	private static function compound_inputs( $type ) {
		$compounds  = ADC_Constants::COMPOUNDS;
		$unit       = ( 'edible' === $type ) ? 'mcg/piece' : '';
		$wrapper_id = "adc-custom-{$type}-wrapper";

		$unit_label = ( 'edible' === $type ) ? '(total mcg for package)' : '(mcg per gram)';
		$html       = "<div class=\"adc-custom-wrapper\" id=\"{$wrapper_id}\" style=\"display:none;\"><p class=\"adc-custom-unit-label\">{$unit_label}</p><div class=\"adc-custom-grid\">";
		foreach ( $compounds as $c ) {
			$label       = ucfirst( $c );
			$placeholder = ( 'psilocybin' === $c ) ? '5000' : '0';
			$html       .= "<div class=\"adc-custom-input\"><label>{$label}</label><input type=\"number\" data-compound=\"{$c}\" placeholder=\"{$placeholder}\" min=\"0\"></div>";
		}
		if ( 'edible' === $type ) {
			$html .= '<div class="adc-custom-input"><label>Pieces/Package</label><input type="number" data-field="piecesPerPackage" placeholder="10" min="1"></div>';
		}
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Strain modal HTML
	 */
	private static function strain_modal() {
		$compounds = ADC_Constants::COMPOUND_LABELS;

		$fields = '';
		foreach ( $compounds as $id => $label ) {
			$fields .= "<div class=\"adc-modal-field\"><label for=\"adc-modal-{$id}\">{$label}</label>";
			$fields .= "<input type=\"number\" id=\"adc-modal-{$id}\" min=\"0\" placeholder=\"0\"></div>";
		}

		return <<<HTML
<div class="adc-modal-overlay" id="adc-strain-modal">
    <div class="adc-modal" role="dialog" aria-modal="true" aria-labelledby="adc-strain-modal-title">
        <div class="adc-modal-header">
            <h3 id="adc-strain-modal-title">Add Custom Strain</h3>
            <button type="button" class="adc-modal-close" aria-label="Close modal" data-action="close-strain-modal">X</button>
        </div>
        <div class="adc-modal-body">
            <div class="adc-modal-field">
                <label for="adc-modal-strain-name">Strain Name</label>
                <input type="text" id="adc-modal-strain-name" placeholder="e.g., My Golden Teacher">
            </div>
            <p class="adc-modal-hint">Enter mcg per gram:</p>
            <div class="adc-modal-compounds">{$fields}</div>
        </div>
        <div class="adc-modal-footer">
            <button type="button" class="adc-btn adc-btn-secondary" data-action="close-strain-modal">Cancel</button>
            <button type="button" class="adc-btn adc-btn-secondary" data-action="submit-strain" title="Submit this strain for review">Submit</button>
            <button type="button" class="adc-btn adc-btn-primary" data-action="save-strain">Save</button>
        </div>
    </div>
</div>
HTML;
	}

	/**
	 * Edible modal HTML
	 */
	private static function edible_modal() {
		$compounds = ADC_Constants::COMPOUND_LABELS;

		$fields = '';
		foreach ( $compounds as $id => $label ) {
			$placeholder = ( 'psilocybin' === $id ) ? '25000' : '0';
			$fields     .= "<div class=\"adc-modal-field\"><label for=\"adc-modal-edible-{$id}\">{$label}</label>";
			$fields     .= "<input type=\"number\" id=\"adc-modal-edible-{$id}\" min=\"0\" placeholder=\"{$placeholder}\"></div>";
		}

		return <<<HTML
<div class="adc-modal-overlay" id="adc-edible-modal">
    <div class="adc-modal" role="dialog" aria-modal="true" aria-labelledby="adc-edible-modal-title">
        <div class="adc-modal-header">
            <h3 id="adc-edible-modal-title">Add Custom Edible</h3>
            <button type="button" class="adc-modal-close" aria-label="Close modal" data-action="close-edible-modal">X</button>
        </div>
        <div class="adc-modal-body">
            <div class="adc-modal-field">
                <label for="adc-modal-edible-name">Product Name</label>
                <input type="text" id="adc-modal-edible-name" placeholder="e.g., Magic Chocolate Bar">
            </div>
            <div class="adc-modal-field">
                <label for="adc-modal-edible-brand">Brand (optional)</label>
                <input type="text" id="adc-modal-edible-brand" placeholder="e.g., Cosmic Confections">
            </div>
            <div class="adc-modal-field">
                <label for="adc-modal-edible-pieces">Pieces per Package</label>
                <input type="number" id="adc-modal-edible-pieces" min="1" placeholder="10">
            </div>
            <p class="adc-modal-hint">Enter total mcg for the package:</p>
            <div class="adc-modal-compounds">{$fields}</div>

        </div>
        <div class="adc-modal-footer">
            <button type="button" class="adc-btn adc-btn-secondary" data-action="close-edible-modal">Cancel</button>
            <button type="button" class="adc-btn adc-btn-secondary" data-action="submit-edible" title="Submit this edible for review">Submit</button>
            <button type="button" class="adc-btn adc-btn-primary" data-action="save-edible">Save</button>
        </div>
    </div>
</div>
HTML;
	}
}
