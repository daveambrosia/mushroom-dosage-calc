# Ambrosia Dosage Calculator - Developer Documentation

## Setup

### Prerequisites
- PHP 8.0+
- WordPress 6.0+
- MySQL 8.0+ or MariaDB 10.5+
- Node.js 18+ (for build tools)
- Composer 2.x

### Installation
```bash
# Clone plugin
cd wp-content/plugins
git clone [repository] ambrosia-dosage-calculator

# Install PHP dependencies (if any)
composer install

# Install JS dependencies (optional - build tools are global)
npm install -g terser
```

### Building Assets
```bash
# Build JavaScript (concatenates modules + minifies)
bash public/js/build-js.sh

# Build CSS (minifies)
bash public/css/build.sh

# Build both
bash public/js/build-js.sh && bash public/css/build.sh
```

## Architecture

### Directory Structure
```
ambrosia-dosage-calculator/
├── admin/                  # Admin UI controllers
│   ├── class-adc-admin.php              # Main admin menu
│   ├── class-adc-admin-strains.php      # Strains CRUD UI
│   ├── class-adc-admin-edibles.php      # Edibles CRUD UI
│   ├── class-adc-admin-submissions.php  # User submissions review
│   ├── class-adc-admin-settings.php     # Plugin settings
│   ├── class-adc-admin-tools.php        # Import/Export tools
│   └── css/                             # Admin stylesheets
├── includes/               # Core classes (models)
│   ├── class-adc-db.php                 # Database abstraction
│   ├── class-adc-strains.php            # Strains model
│   ├── class-adc-edibles.php            # Edibles model
│   ├── class-adc-submissions.php        # Submissions model
│   ├── class-adc-rest-api.php           # REST API endpoints
│   ├── class-adc-shortcode.php          # [dosage_calculator] shortcode
│   ├── class-adc-qr-handler.php         # QR code URL routing
│   └── class-adc-http-cache.php         # HTTP caching headers
├── public/                 # Frontend assets
│   ├── js/
│   │   ├── modules/                     # JS source modules
│   │   ├── calculator.js                # Built/concatenated JS
│   │   └── calculator.min.js            # Minified production JS
│   └── css/
│       ├── calculator.css               # Source CSS
│       └── calculator.min.css           # Minified production CSS
├── tests/                  # PHPUnit tests
│   ├── bootstrap.php
│   ├── test-adc-strains.php
│   ├── test-adc-submissions.php
│   └── test-adc-rest-api.php
└── ambrosia-dosage-calculator.php      # Main plugin file
```

### Database Schema
```
wp_adc_strains           # Mushroom strains with compound data
wp_adc_edibles           # Edible products
wp_adc_submissions       # User-submitted data (moderation queue)
wp_adc_categories        # Strain categories
wp_adc_product_types     # Edible product types
wp_adc_compounds         # Tryptamine compound definitions
wp_adc_blacklist         # Blocked IPs/emails
```

## Development Workflow

### Code Standards
```bash
# Check PHP code style (WordPress standards)
phpcs --standard=WordPress .

# Auto-fix PHP code style
phpcbf --standard=WordPress .

# Run static analysis
phpstan analyse --level=5 includes/ admin/

# Check complexity
phpmd includes/,admin/ text codesize,unusedcode
```

### Testing
```bash
# Run PHPUnit tests (requires SVN installed)
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
phpunit

# Run specific test
phpunit tests/test-adc-strains.php

# Run with coverage (requires xdebug)
phpunit --coverage-html coverage/
```

### JavaScript Linting
```bash
# Check JavaScript (requires eslint.config.mjs)
npx eslint public/js/calculator.js

# Auto-fix issues
npx eslint public/js/calculator.js --fix
```

## REST API

### Public Endpoints

#### GET /wp-json/adc/v1/strains
Get all strains (cached 5min).

**Parameters:**
- `category` (string): Filter by category
- `search` (string): Search by name
- `min_potency` (int): Minimum psilocybin (mcg)
- `max_potency` (int): Maximum psilocybin (mcg)
- `include_inactive` (bool): Include inactive strains

**Response:**
```json
{
  "strains": [
    {
      "id": 1,
      "shortCode": "GT-ABC123",
      "name": "Golden Teacher",
      "category": "cubensis",
      "psilocybin": 5000,
      "psilocin": 500,
      "compounds": {...}
    }
  ],
  "grouped": {...},
  "total": 42
}
```

#### GET /wp-json/adc/v1/edibles
Get all edibles (cached 5min).

**Response:**
```json
{
  "edibles": [...],
  "grouped": {...},
  "total": 12,
  "unitMap": {...}
}
```

#### POST /wp-json/adc/v1/submit
Submit custom strain/edible for review.

**Rate Limit:** 10 requests/15min (burst), 5 requests/hour (sustained)

**Request:**
```json
{
  "type": "strain",
  "data": {
    "name": "Custom Strain",
    "psilocybin": 5000,
    "psilocin": 500,
    "batch_number": "ABC123"
  },
  "name": "John Doe",
  "email": "john@example.com",
  "notes": "Lab tested by..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Thank you! Your submission has been received...",
  "id": 42
}
```

## Hooks & Filters

### Actions

**Plugin Initialization:**
- `adc_plugin_loaded` - After all classes loaded
- `adc_rest_api_init` - When REST routes registered

**Database:**
- `adc_strain_created` - `(int $id, array $data)`
- `adc_strain_updated` - `(int $id, array $data)`
- `adc_strain_deleted` - `(int $id)`
- `adc_edible_created` - `(int $id, array $data)`
- `adc_edible_updated` - `(int $id, array $data)`
- `adc_edible_deleted` - `(int $id)`

**Submissions:**
- `adc_submission_created` - `(int $id, array $data)`
- `adc_submission_approved` - `(int $submission_id, int $item_id, string $type)`
- `adc_submission_rejected` - `(int $id, string $reason)`

### Filters

**Data Processing:**
- `adc_strain_data_before_save` - `(array $data, int $id|null)` - Modify strain data before insert/update
- `adc_edible_data_before_save` - `(array $data, int $id|null)` - Modify edible data before insert/update
- `adc_submission_data_before_save` - `(array $data)` - Modify submission before save

**Display:**
- `adc_shortcode_attributes` - `(array $atts)` - Modify shortcode attributes
- `adc_calculator_html` - `(string $html, array $atts)` - Modify calculator output HTML
- `adc_compound_breakdown` - `(array $compounds, int $mcg)` - Modify compound breakdown display

**Cache:**
- `adc_cache_ttl` - `(int $seconds, string $cache_type)` - Modify cache duration (default 300s)
- `adc_rest_cache_key` - `(string $key, array $params)` - Modify REST cache key

**Security:**
- `adc_rate_limit_exceeded` - `(bool $exceeded, string $ip, int $count)` - Override rate limit check
- `adc_blacklist_check` - `(bool $is_blacklisted, string $type, string $value)` - Override blacklist check

## Common Tasks

### Add a New Compound
```php
// In database or via admin UI
global $wpdb;
$wpdb->insert(
  ADC_DB::table('compounds'),
  array(
    'name' => 'New Compound',
    'slug' => 'new-compound',
    'unit' => 'mcg',
    'sort_order' => 10
  )
);
```

### Add a New Template
```php
// See admin/class-adc-template-builder.php
ADC_Template_Builder::get_instance()->save_template(
  'my-template',
  array(
    'name' => 'My Template',
    'colors' => array(...),
    'fonts' => array(...)
  )
);
```

### Clear All Caches
```bash
# WP-CLI
wp transient delete --all

# Or specific pattern
wp db query "DELETE FROM wp_options WHERE option_name LIKE '_transient_adc_%'"
```

### Debug Mode
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Then check wp-content/debug.log
```

## Performance Optimization

### Caching Strategy
- **REST API**: 5min transient cache (auto-invalidated on CRUD)
- **Database queries**: Cache versioning (incremented on change)
- **HTTP headers**: ETag + Cache-Control on public endpoints

### Database Indexes
All frequently queried columns have indexes:
- `short_code` (UNIQUE)
- `category`
- `is_active`
- `product_type`

### Front-End Optimization
- Scripts loaded with `defer` strategy
- CSS/JS minified in production
- Lazy-loading of strain/edible data via REST

## Security

### Input Validation
- All `$_POST` / `$_GET` sanitized via `sanitize_text_field()`, `absint()`, etc.
- All database queries use `$wpdb->prepare()`
- All output escaped via `esc_html()`, `esc_attr()`, `esc_url()`

### CSRF Protection
- All forms use `wp_nonce_field()` / `wp_verify_nonce()`
- REST API includes nonce in `X-WP-Nonce` header

### Rate Limiting
- Dual-layer: transient (burst) + database (sustained)
- Configurable limits via filters

### Security Headers
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `X-XSS-Protection: 1; mode=block`

## Troubleshooting

### Calculator not appearing
1. Check shortcode: `[dosage_calculator]` or `[adc_calculator]`
2. Clear transient cache: `wp transient delete --all`
3. Check JavaScript console for errors
4. Verify `WP_DEBUG` is off (minified assets loaded)

### Rate limit errors (429)
- Wait 15 minutes or clear transient: `wp transient delete adc_rate_limit_<IP_HASH>`
- Increase limits via filter: `add_filter('adc_rate_limit_burst', function() { return 20; });`

### Stale data after update
- Cache should auto-clear, but if not: `wp transient delete --all`
- Or pattern delete: `wp db query "DELETE FROM wp_options WHERE option_name LIKE '_transient_adc_rest_%'"`

### PHPCS errors after update
- Run auto-fix: `phpcbf --standard=WordPress .`
- Check for syntax errors: `find . -name "*.php" -exec php -l {} \;`

## Contributing

### Before Submitting PR
1. Run `phpcbf --standard=WordPress .`
2. Run `phpcs --standard=WordPress .` (should be <100 errors)
3. Run `phpunit` (all tests passing)
4. Update version in plugin header
5. Rebuild minified assets

### Commit Messages
- Use conventional commits: `feat:`, `fix:`, `docs:`, `refactor:`, `test:`
- Reference issues: `fix: rate limiting on submit endpoint (#42)`

### Code Review Checklist
- [ ] Security: nonces, sanitization, escaping
- [ ] Performance: caching, query optimization
- [ ] Compatibility: WordPress 6.0+, PHP 8.0+
- [ ] Tests: unit tests for new features
- [ ] Documentation: inline comments, README updates

## License

GPL v2 or later

---

**Last Updated:** March 21, 2026  
**Plugin Version:** 2.20.0  
**WordPress Compatibility:** 6.0+  
**PHP Compatibility:** 8.0+
