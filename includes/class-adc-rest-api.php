<?php
/**
 * REST API endpoints - Version 2.0 - Performance Optimized
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADC_REST_API {
    
    /** Cache expiry for reference data */
    const REF_CACHE_EXPIRY = 3600; // 1 hour
    
    public static function register_routes() {
        $namespace = 'adc/v1';
        
        // =========== PUBLIC ROUTES ===========
        
        // Get all strains
        register_rest_route($namespace, '/strains', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_strains'),
            'permission_callback' => '__return_true',
            'args' => array(
                'category' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ),
                'include_inactive' => array(
                    'type' => 'boolean',
                    'default' => false,
                ),
                'search' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return is_string($param) && mb_strlen($param) <= 200;
                    },
                    'description' => 'Search strain names (partial match).',
                ),
                'min_potency' => array(
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && intval($param) >= 0;
                    },
                    'description' => 'Minimum active potency in mcg/g (psilocybin + psilocin).',
                ),
                'max_potency' => array(
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && intval($param) >= 0;
                    },
                    'description' => 'Maximum active potency in mcg/g (psilocybin + psilocin).',
                ),
            ),
        ));
        
        // Get single strain by short code
        register_rest_route($namespace, '/strains/(?P<code>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_strain'),
            'permission_callback' => '__return_true',
            'args' => array(
                'code' => array(
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-zA-Z0-9-]{1,50}$/', $param);
                    },
                ),
            ),
        ));
        
        // Get all edibles
        register_rest_route($namespace, '/edibles', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_edibles'),
            'permission_callback' => '__return_true',
            'args' => array(
                'product_type' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ),
                'include_inactive' => array(
                    'type' => 'boolean',
                    'default' => false,
                ),
                'search' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return is_string($param) && mb_strlen($param) <= 200;
                    },
                    'description' => 'Search edible names (partial match).',
                ),
                'min_potency' => array(
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && intval($param) >= 0;
                    },
                    'description' => 'Minimum active potency in mcg/piece (psilocybin + psilocin).',
                ),
                'max_potency' => array(
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && intval($param) >= 0;
                    },
                    'description' => 'Maximum active potency in mcg/piece (psilocybin + psilocin).',
                ),
            ),
        ));
        
        // Get single edible by short code
        register_rest_route($namespace, '/edibles/(?P<code>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_edible'),
            'permission_callback' => '__return_true',
            'args' => array(
                'code' => array(
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-zA-Z0-9-]{1,50}$/', $param);
                    },
                ),
            ),
        ));
        
        // Lookup by short code (strains or edibles)
        register_rest_route($namespace, '/lookup/(?P<code>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'lookup'),
            'permission_callback' => '__return_true',
            'args' => array(
                'code' => array(
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-zA-Z0-9-]{1,50}$/', $param);
                    },
                ),
            ),
        ));
        
        // Get categories
        register_rest_route($namespace, '/categories', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_categories'),
            'permission_callback' => '__return_true',
            'args' => array(
                'type' => array(
                    'type' => 'string',
                    'enum' => array('strain', 'edible', 'both'),
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => function($param) {
                        return in_array($param, array('strain', 'edible', 'both'), true);
                    },
                ),
            ),
        ));
        
        // Get product types
        register_rest_route($namespace, '/product-types', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_product_types'),
            'permission_callback' => '__return_true',
        ));
        
        // Get compounds
        register_rest_route($namespace, '/compounds', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_compounds'),
            'permission_callback' => '__return_true',
        ));
        
        // Get calculator settings
        register_rest_route($namespace, '/settings', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_settings'),
            'permission_callback' => '__return_true',
        ));
        
        // Submit custom strain/edible
        register_rest_route($namespace, '/submit', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'submit_custom'),
            'permission_callback' => '__return_true',
        ));
        
        // =========== ADMIN ROUTES ===========
        
        // Admin: Create strain
        register_rest_route($namespace, '/admin/strains', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'admin_create_strain'),
            'permission_callback' => array(__CLASS__, 'admin_permission_check'),
        ));
        
        // Admin: Update strain
        register_rest_route($namespace, '/admin/strains/(?P<id>\d+)', array(
            'methods' => array('PUT', 'PATCH'),
            'callback' => array(__CLASS__, 'admin_update_strain'),
            'permission_callback' => array(__CLASS__, 'admin_permission_check'),
        ));
        
        // Admin: Delete strain
        register_rest_route($namespace, '/admin/strains/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array(__CLASS__, 'admin_delete_strain'),
            'permission_callback' => array(__CLASS__, 'admin_permission_check'),
        ));
        
        // Admin: Create edible
        register_rest_route($namespace, '/admin/edibles', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'admin_create_edible'),
            'permission_callback' => array(__CLASS__, 'admin_permission_check'),
        ));
        
        // Admin: Update edible
        register_rest_route($namespace, '/admin/edibles/(?P<id>\d+)', array(
            'methods' => array('PUT', 'PATCH'),
            'callback' => array(__CLASS__, 'admin_update_edible'),
            'permission_callback' => array(__CLASS__, 'admin_permission_check'),
        ));
        
        // Admin: Delete edible
        register_rest_route($namespace, '/admin/edibles/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array(__CLASS__, 'admin_delete_edible'),
            'permission_callback' => array(__CLASS__, 'admin_permission_check'),
        ));
        
        // Admin: Get submissions
        register_rest_route($namespace, '/admin/submissions', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'admin_get_submissions'),
            'permission_callback' => array(__CLASS__, 'admin_permission_check'),
        ));
        
        // Admin: Approve submission
        register_rest_route($namespace, '/admin/submissions/(?P<id>\d+)/approve', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'admin_approve_submission'),
            'permission_callback' => array(__CLASS__, 'admin_permission_check'),
        ));
        
        // Admin: Reject submission
        register_rest_route($namespace, '/admin/submissions/(?P<id>\d+)/reject', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'admin_reject_submission'),
            'permission_callback' => array(__CLASS__, 'admin_permission_check'),
        ));
        
        // Admin: Export strains
        register_rest_route($namespace, '/admin/strains/export', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'admin_export_strains'),
            'permission_callback' => array(__CLASS__, 'admin_permission_check'),
            'args' => array(
                'format' => array(
                    'type' => 'string',
                    'default' => 'json',
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => function($param) {
                        return in_array($param, array('csv', 'json'), true);
                    },
                ),
            ),
        ));
        
        // Admin: Export edibles
        register_rest_route($namespace, '/admin/edibles/export', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'admin_export_edibles'),
            'permission_callback' => array(__CLASS__, 'admin_permission_check'),
            'args' => array(
                'format' => array(
                    'type' => 'string',
                    'default' => 'json',
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => function($param) {
                        return in_array($param, array('csv', 'json'), true);
                    },
                ),
            ),
        ));
    }
    
    /**
     * Check admin permission
     */
    public static function admin_permission_check() {
        return current_user_can('manage_options'); // Admins only
    }
    
    // =========== PUBLIC ENDPOINTS ===========
    
    /**
     * Get all strains
     */
    public static function get_strains($request) {
        $category = $request->get_param('category');
        $include_inactive = $request->get_param('include_inactive');
        $search = $request->get_param('search');
        $min_potency = $request->get_param('min_potency');
        $max_potency = $request->get_param('max_potency');
        
        $args = array(
            'active_only' => !$include_inactive,
            'category' => $category,
            'search' => $search,
            'min_potency' => $min_potency !== null ? absint($min_potency) : null,
            'max_potency' => $max_potency !== null ? absint($max_potency) : null,
        );
        
        $strains = ADC_Strains::get_all($args);
        
        $formatted = array_map(array('ADC_Strains', 'format_for_api'), $strains);
        
        // Group by category
        $grouped = array();
        foreach ($formatted as $strain) {
            $cat = $strain['category'] ?: 'uncategorized';
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = array();
            }
            $grouped[$cat][] = $strain;
        }
        
        return rest_ensure_response(array(
            'strains' => $formatted,
            'grouped' => $grouped,
            'total' => count($formatted),
        ));
    }
    
    /**
     * Get single strain
     */
    public static function get_strain($request) {
        $code = $request->get_param('code');
        
        $strain = ADC_Strains::get_by_code($code);
        
        if (!$strain) {
            return new WP_Error('not_found', 'Strain not found', array('status' => 404));
        }
        
        return rest_ensure_response(ADC_Strains::format_for_api($strain));
    }
    
    /**
     * Get all edibles
     */
    public static function get_edibles($request) {
        $product_type = $request->get_param('product_type');
        $include_inactive = $request->get_param('include_inactive');
        $search = $request->get_param('search');
        $min_potency = $request->get_param('min_potency');
        $max_potency = $request->get_param('max_potency');
        
        $args = array(
            'active_only' => !$include_inactive,
            'product_type' => $product_type,
            'search' => $search,
            'min_potency' => $min_potency !== null ? absint($min_potency) : null,
            'max_potency' => $max_potency !== null ? absint($max_potency) : null,
        );
        
        $edibles = ADC_Edibles::get_all($args);
        
        $formatted = array_map(array('ADC_Edibles', 'format_for_api'), $edibles);
        
        // Group by product type
        $grouped = array();
        foreach ($formatted as $edible) {
            $type = $edible['productType'] ?: 'other';
            if (!isset($grouped[$type])) {
                $grouped[$type] = array();
            }
            $grouped[$type][] = $edible;
        }
        
        // Build unit name map from product_types table
        $product_types = ADC_Edibles::get_product_types();
        $unit_map = array();
        foreach ($product_types as $pt) {
            $unit_map[$pt['slug']] = $pt['unit_name'] ?? 'pieces';
        }

        return rest_ensure_response(array(
            'edibles' => $formatted,
            'grouped' => $grouped,
            'total' => count($formatted),
            'unitMap' => $unit_map,
        ));
    }
    
    /**
     * Get single edible
     */
    public static function get_edible($request) {
        $code = $request->get_param('code');
        
        $edible = ADC_Edibles::get_by_code($code);
        
        if (!$edible) {
            return new WP_Error('not_found', 'Edible not found', array('status' => 404));
        }
        
        return rest_ensure_response(ADC_Edibles::format_for_api($edible));
    }
    
    /**
     * Lookup by short code (strain or edible)
     */
    public static function lookup($request) {
        $code = $request->get_param('code');
        
        // Try strains first
        $strain = ADC_Strains::get_by_code($code);
        if ($strain) {
            return rest_ensure_response(array(
                'type' => 'strain',
                'data' => ADC_Strains::format_for_api($strain),
            ));
        }
        
        // Try edibles
        $edible = ADC_Edibles::get_by_code($code);
        if ($edible) {
            return rest_ensure_response(array(
                'type' => 'edible',
                'data' => ADC_Edibles::format_for_api($edible),
            ));
        }
        
        return new WP_Error('not_found', 'Item not found', array('status' => 404));
    }
    
    /**
     * Get categories (cached)
     */
    public static function get_categories($request) {
        $type = $request->get_param('type'); // strain, edible, or null for all
        
        // Create cache key based on type parameter
        $cache_key = 'adc_categories_' . ($type ?: 'all');
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }
        
        global $wpdb;
        $table = ADC_DB::table('categories');
        
        if ($type) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from ADC_DB::table()
            $categories = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, slug, type, sort_order FROM {$table} WHERE is_active = 1 AND type IN (%s, 'both') ORDER BY sort_order ASC",
                $type
            ), ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from ADC_DB::table()
            $categories = $wpdb->get_results(
                "SELECT id, name, slug, type, sort_order FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC",
                ARRAY_A
            );
        }
        
        // Cache for 1 hour
        set_transient($cache_key, $categories, self::REF_CACHE_EXPIRY);
        
        return rest_ensure_response($categories);
    }
    
    /**
     * Get product types (cached via ADC_Edibles)
     */
    public static function get_product_types($request) {
        $types = ADC_Edibles::get_product_types();
        return rest_ensure_response($types);
    }
    
    /**
     * Get compounds (cached)
     */
    public static function get_compounds($request) {
        $cache_key = 'adc_compounds';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }
        
        global $wpdb;
        $table = ADC_DB::table('compounds');
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from ADC_DB::table()
        $compounds = $wpdb->get_results(
            "SELECT id, compound_key, display_name, unit, description, is_active, sort_order, affects_dosing, calculation_weight FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC",
            ARRAY_A
        );
        
        // Cache for 1 hour
        set_transient($cache_key, $compounds, self::REF_CACHE_EXPIRY);
        
        return rest_ensure_response($compounds);
    }
    
    /**
     * Get public calculator settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_settings($request) {
        $settings = ADC_DB::get_settings();
        
        // Return only public settings (filter out sensitive ones)
        $public_settings = array(
            'visual_theme' => isset($settings['visual_theme']) ? $settings['visual_theme'] : 'default',
            'default_weight_unit' => isset($settings['default_weight_unit']) ? $settings['default_weight_unit'] : 'lbs',
            'show_safety_warning' => isset($settings['show_safety_warning']) ? (bool)$settings['show_safety_warning'] : true,
            'allow_submissions' => isset($settings['allow_submissions']) ? (bool)$settings['allow_submissions'] : true,
            'default_sensitivity' => isset($settings['default_sensitivity']) ? (int)$settings['default_sensitivity'] : 100,
        );
        
        return rest_ensure_response($public_settings);
    }
    
    /**
     * Clear categories cache (call when categories change)
     */
    public static function clear_categories_cache() {
        delete_transient('adc_categories_all');
        delete_transient('adc_categories_strain');
        delete_transient('adc_categories_edible');
    }
    
    /**
     * Clear compounds cache (call when compounds change)
     */
    public static function clear_compounds_cache() {
        delete_transient('adc_compounds');
    }
    
    /**
     * Submit custom strain/edible
     */
    public static function submit_custom($request) {
        $body = $request->get_json_params();
        
        // Validate
        if (empty($body['type']) || !in_array($body['type'], array('strain', 'edible'))) {
            return new WP_Error('invalid_type', 'Invalid submission type', array('status' => 400));
        }
        
        if (empty($body['data'])) {
            return new WP_Error('invalid_data', 'No data provided', array('status' => 400));
        }
        
        // Rate limiting (simple - by IP)
        $ip = self::get_client_ip();
        global $wpdb;
        $table = ADC_DB::table('submissions');
        $recent = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE ip_address = %s AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $ip
        ));
        
        if ($recent >= 5) {
            return new WP_Error('rate_limited', 'Too many submissions. Please try again later.', array('status' => 429));
        }
        
        // Create submission
        $result = ADC_Submissions::create(array(
            'type' => $body['type'],
            'source' => 'user_submit',
            'data' => $body['data'],
            'submitter_name' => $body['name'] ?? '',
            'submitter_email' => $body['email'] ?? '',
            'submitter_notes' => $body['notes'] ?? '',
            'testing_lab' => $body['lab'] ?? '',
            'ip_address' => $ip,
            'user_agent' => sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)),
        ));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Thank you! Your submission has been received and will be reviewed.',
            'id' => $result,
        ));
    }
    
    // =========== ADMIN ENDPOINTS ===========
    
    public static function admin_create_strain($request) {
        $data = $request->get_json_params();
        $result = ADC_Strains::create($data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $strain = ADC_Strains::get($result);
        
        return rest_ensure_response(array(
            'success' => true,
            'strain' => ADC_Strains::format_for_api($strain),
        ));
    }
    
    public static function admin_update_strain($request) {
        $id = $request->get_param('id');
        $data = $request->get_json_params();
        
        $result = ADC_Strains::update($id, $data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $strain = ADC_Strains::get($id);
        
        return rest_ensure_response(array(
            'success' => true,
            'strain' => ADC_Strains::format_for_api($strain),
        ));
    }
    
    public static function admin_delete_strain($request) {
        $id = $request->get_param('id');
        $result = ADC_Strains::delete($id);
        
        if (is_wp_error($result)) {
            return new WP_Error('delete_failed', $result->get_error_message(), array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    public static function admin_create_edible($request) {
        $data = $request->get_json_params();
        $result = ADC_Edibles::create($data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $edible = ADC_Edibles::get($result);
        
        return rest_ensure_response(array(
            'success' => true,
            'edible' => ADC_Edibles::format_for_api($edible),
        ));
    }
    
    public static function admin_update_edible($request) {
        $id = $request->get_param('id');
        $data = $request->get_json_params();
        
        $result = ADC_Edibles::update($id, $data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $edible = ADC_Edibles::get($id);
        
        return rest_ensure_response(array(
            'success' => true,
            'edible' => ADC_Edibles::format_for_api($edible),
        ));
    }
    
    public static function admin_delete_edible($request) {
        $id = $request->get_param('id');
        $result = ADC_Edibles::delete($id);
        
        if (is_wp_error($result)) {
            return new WP_Error('delete_failed', $result->get_error_message(), array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    public static function admin_get_submissions($request) {
        $status = $request->get_param('status');
        $type = $request->get_param('type');
        
        $submissions = ADC_Submissions::get_all(array(
            'status' => $status,
            'type' => $type,
        ));
        
        $formatted = array_map(array('ADC_Submissions', 'format_for_api'), $submissions);
        $counts = ADC_Submissions::count_by_status();
        
        return rest_ensure_response(array(
            'submissions' => $formatted,
            'counts' => $counts,
        ));
    }
    
    public static function admin_approve_submission($request) {
        $id = $request->get_param('id');
        $body = $request->get_json_params();
        
        $result = ADC_Submissions::approve($id, $body['notes'] ?? '');
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'created_id' => $result,
        ));
    }
    
    public static function admin_reject_submission($request) {
        $id = $request->get_param('id');
        $body = $request->get_json_params();
        
        $result = ADC_Submissions::reject($id, $body['notes'] ?? '');
        
        if (!$result) {
            return new WP_Error('reject_failed', 'Failed to reject submission', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    // =========== EXPORT ENDPOINTS ===========
    
    /**
     * Export strains as CSV or JSON
     */
    public static function admin_export_strains($request) {
        $format = $request->get_param('format');
        $strains = ADC_Strains::get_all(array('active_only' => false));
        
        if ($strains === false || $strains === null) {
            return new WP_Error('export_error', 'Failed to retrieve strains from database', array('status' => 500));
        }
        
        $filename = 'adc-strains-export-' . wp_date('Y-m-d');
        
        if ($format === 'csv') {
            return self::export_strains_csv($strains, $filename);
        }
        
        // JSON format matching import structure
        $export_data = array_map(function($strain) {
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
        
        return self::send_file_response($export_data, $filename . '.json', 'application/json');
    }
    
    /**
     * Export edibles as CSV or JSON
     */
    public static function admin_export_edibles($request) {
        $format = $request->get_param('format');
        $edibles = ADC_Edibles::get_all(array('active_only' => false));
        
        if ($edibles === false || $edibles === null) {
            return new WP_Error('export_error', 'Failed to retrieve edibles from database', array('status' => 500));
        }
        
        $filename = 'adc-edibles-export-' . wp_date('Y-m-d');
        
        if ($format === 'csv') {
            return self::export_edibles_csv($edibles, $filename);
        }
        
        // JSON format matching import structure
        $export_data = array_map(function($edible) {
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
        
        return self::send_file_response($export_data, $filename . '.json', 'application/json');
    }
    
    /**
     * Export strains as CSV with proper headers
     */
    private static function export_strains_csv($strains, $filename) {
        $csv_headers = array(
            'name', 'short_code', 'batch_number', 'category',
            'psilocybin', 'psilocin', 'norpsilocin', 'baeocystin', 'norbaeocystin', 'aeruginascin',
            'is_active', 'notes', 'sort_order',
        );
        
        $rows = array();
        foreach ($strains as $strain) {
            $rows[] = array(
                $strain['name'],
                $strain['short_code'],
                $strain['batch_number'],
                $strain['category'],
                intval($strain['psilocybin']),
                intval($strain['psilocin']),
                intval($strain['norpsilocin']),
                intval($strain['baeocystin']),
                intval($strain['norbaeocystin']),
                intval($strain['aeruginascin']),
                intval($strain['is_active']),
                $strain['notes'],
                intval($strain['sort_order']),
            );
        }
        
        return self::send_csv_response($csv_headers, $rows, $filename . '.csv');
    }
    
    /**
     * Export edibles as CSV with proper headers
     */
    private static function export_edibles_csv($edibles, $filename) {
        $csv_headers = array(
            'name', 'short_code', 'brand', 'product_type', 'batch_number', 'pieces_per_package',
            'psilocybin', 'psilocin', 'norpsilocin', 'baeocystin', 'norbaeocystin', 'aeruginascin',
            'is_active', 'notes', 'sort_order',
        );
        
        $rows = array();
        foreach ($edibles as $edible) {
            $rows[] = array(
                $edible['name'],
                $edible['short_code'],
                $edible['brand'],
                $edible['product_type'],
                $edible['batch_number'],
                intval($edible['pieces_per_package']),
                intval($edible['psilocybin']),
                intval($edible['psilocin']),
                intval($edible['norpsilocin']),
                intval($edible['baeocystin']),
                intval($edible['norbaeocystin']),
                intval($edible['aeruginascin']),
                intval($edible['is_active']),
                $edible['notes'],
                intval($edible['sort_order']),
            );
        }
        
        return self::send_csv_response($csv_headers, $rows, $filename . '.csv');
    }
    
    /**
     * Send CSV file response via REST API
     */
    private static function send_csv_response($headers, $rows, $filename) {
        // Build CSV in memory
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return new WP_Error('export_error', 'Could not create CSV output stream', array('status' => 500));
        }
        
        // BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        // Send response with file headers
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($csv_content));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $csv_content;
        exit;
    }
    
    /**
     * Send JSON file response via REST API
     */
    private static function send_file_response($data, $filename, $content_type) {
        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            return new WP_Error('export_error', 'JSON encoding failed: ' . json_last_error_msg(), array('status' => 500));
        }
        
        header('Content-Type: ' . $content_type . '; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $json;
        exit;
    }
    
    /**
     * Get client IP — uses REMOTE_ADDR only (no proxy header trust).
     */
    private static function get_client_ip() {
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
