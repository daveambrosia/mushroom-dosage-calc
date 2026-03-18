<?php
/**
 * Template CSS Generator
 *
 * Handles CSS generation for custom templates on the frontend.
 * Loaded on all requests. Admin UI classes load only in is_admin().
 *
 * @since 2.17.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADC_Template_CSS {

    /**
     * Neutral fallback values for cleared color variables.
     * Must stay in sync with ADC_Template_Builder::$color_fallbacks.
     */
    private static $color_fallbacks = array(
        'bg'                => 'transparent',
        'text'              => 'inherit',
        'accent'            => '#6b7280',
        'surface'           => 'transparent',
        'surface-alt'       => 'transparent',
        'border'            => '#e5e7eb',
        'header-bg'         => 'transparent',
        'header-text'       => 'inherit',
        'tab-bg'            => 'transparent',
        'tab-text'          => 'inherit',
        'tab-active-bg'     => 'transparent',
        'tab-active-text'   => 'inherit',
        'tab-hover-bg'      => 'transparent',
        'body-bg'           => 'transparent',
        'input-bg'          => 'transparent',
        'input-border'      => '#e5e7eb',
        'input-focus-bg'    => 'transparent',
        'btn-bg'            => 'transparent',
        'btn-text'          => 'inherit',
        'btn-border'        => '#e5e7eb',
        'btn-hover-bg'      => 'transparent',
        'btn-primary-bg'    => '#6b7280',
        'btn-primary-text'  => 'inherit',
        'unit-active-bg'    => '#6b7280',
        'unit-active-text'  => 'inherit',
        'converter-bg'      => 'transparent',
        'safety-bg'         => 'transparent',
        'safety-border'     => '#e5e7eb',
        'modal-header-bg'   => 'transparent',
        'modal-header-text' => 'inherit',
    );

    /**
     * Get all custom templates from the database.
     *
     * @return array
     */
    public static function get_custom_templates() {
        $data = get_option('adc_custom_templates', '[]');
        $templates = json_decode($data, true);
        return is_array($templates) ? $templates : array();
    }

    /**
     * Generate CSS for a single custom template.
     *
     * @param array $template Template data array with 'slug' and 'variables'.
     * @return string Generated CSS.
     */
    public static function generate_template_css($template) {
        if (empty($template['variables']) || !is_array($template['variables'])) {
            return '';
        }

        $slug = sanitize_key($template['slug']);
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return '';
        }
        $css = '#adc-calculator[data-template="' . $slug . '"] {' . "\n";

        $stored = $template['variables'];

        foreach ($stored as $key => $value) {
            $safe_key = preg_replace('/[^a-z0-9-]/', '', $key);

            if ($value === '' || $value === null) {
                if (isset(self::$color_fallbacks[$key])) {
                    $css .= '    --adc-' . $safe_key . ': ' . self::$color_fallbacks[$key] . ';' . "\n";
                }
                continue;
            }

            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
                $css .= '    --adc-' . $safe_key . ': ' . sanitize_hex_color($value) . ';' . "\n";
            } else {
                $sanitized_value = preg_replace('/[{}]/', '', wp_strip_all_tags($value));
                $css .= '    --adc-' . $safe_key . ': ' . $sanitized_value . ';' . "\n";
            }
        }

        foreach (self::$color_fallbacks as $key => $fallback) {
            if (!array_key_exists($key, $stored)) {
                $safe_key = preg_replace('/[^a-z0-9-]/', '', $key);
                $css .= '    --adc-' . $safe_key . ': ' . $fallback . ';' . "\n";
            }
        }

        $css .= '}' . "\n";
        return $css;
    }

    /**
     * Generate CSS for all custom templates.
     * Called by the shortcode to inject inline styles.
     *
     * @return string Combined CSS for all custom templates.
     */
    public static function generate_all_custom_css() {
        $templates = self::get_custom_templates();
        if (empty($templates)) {
            return '';
        }

        $css = "/* Custom Templates */\n";
        foreach ($templates as $t) {
            $css .= self::generate_template_css($t);
        }
        return $css;
    }
}
