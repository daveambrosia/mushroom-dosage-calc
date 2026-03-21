# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Live Testing Environment

- **URL**: https://members.tail5d8649.ts.net/wordpress/ (Tailscale VPN). Also accessible via https://localhost/wordpress on the server itself.
- **Plugin path**: `/var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator`
- **Before any code change**, create a zip backup: `zip -r /home/dave/backup/ambrosia-dosage-calculator-$(date +%Y%m%d-%H%M%S).zip /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator/`
- **Markdown viewer**: To share a `.md` file with the user for review, copy it to `/home/dave/.openclaw/workspace/` and provide the link: `https://members.tail5d8649.ts.net/viewmd/?p=<filename>.md`
- **PHP version**: 8.3 on server (minimum 8.0)
- **WordPress**: 6.0+ (tested up to 6.4)
- **Admin login**: francis / password123 (testing only)

## Build Commands

JavaScript is built from modules via concatenation (no bundler):

```bash
# Build calculator.js from modules (from plugin root)
bash public/js/build-js.sh

# Minify all JS and CSS for production (requires: npm install -g terser csso-cli)
bash build-min.sh

# Package for distribution
bash build-zip.sh
```

After editing any file in `public/js/modules/`, you must run `build-js.sh` to rebuild `calculator.js`. Do NOT edit `calculator.js` directly — edit the source modules instead.

Minified assets (`.min.js`, `.min.css`) are served in production. Unminified versions are served when `WP_DEBUG` is true.

## Architecture

### Entry Point & Loading

`ambrosia-dosage-calculator.php` — singleton `Ambrosia_Dosage_Calculator` class. Defines constants (`ADC_VERSION`, `ADC_DB_VERSION`, `ADC_PLUGIN_DIR`, `ADC_PLUGIN_URL`), loads all dependencies, registers hooks.

Google Sheets classes (`ADC_Google_Sheets`, `ADC_Sheets_Importer`, `ADC_Sheets_Admin_Page`) only load in admin/cron/CLI contexts — never on frontend.

### Three Layers

1. **Data layer** (`includes/`): `ADC_DB` (shared table names, settings cache), `ADC_Strains`, `ADC_Edibles`, `ADC_Submissions` — all use `$wpdb->prepare()` and transient caching (1hr TTL, versioned keys).

2. **REST API** (`includes/class-adc-rest-api.php`): Namespace `adc/v1`. Public endpoints (GET strains/edibles/categories/compounds) and admin endpoints (POST/DELETE, require `manage_options`). Rate limiting, ETag/Cache-Control headers via `ADC_HTTP_Cache`.

3. **Frontend** (`includes/class-adc-shortcode.php`): Shortcodes `[dosage_calculator]` / `[adc_calculator]`. Renders HTML shell, data loaded via REST API calls from JS. Assets only enqueue on pages containing the shortcode.

### Admin Layer

`ADC_Admin` (singleton) in `admin/class-adc-admin.php` dispatches to sub-modules: `ADC_Admin_Strains`, `ADC_Admin_Edibles`, `ADC_Admin_Settings`, `ADC_Admin_Submissions`, `ADC_Admin_Tools`, `ADC_Template_Builder`.

Importers: `ADC_CSV_Importer`, `ADC_JSON_Importer`, `ADC_Sheets_Importer` (cron-driven).

### Frontend JavaScript

IIFE-wrapped, built from 10 modules in `public/js/modules/` in this order:
`iife-open → constants → state → storage → math → dom → render → modals → collapse → events → init → iife-close`

Key modules:
- `adc-math.js`: Dosage calculations, tolerance curve (linear interpolation days 1-27, baseline at 28+)
- `adc-state.js`: Global mutable state object
- `adc-storage.js`: localStorage persistence for user preferences
- `adc-render.js`: DOM rendering for categories, strains, dose recommendations
- `adc-events.js`: All event bindings (tabs, form inputs, sharing)

`adc-dialogs.js` is a separate standalone file (not part of the module build).

### Database

7 custom tables (prefixed `wp_adc_`): `strains`, `edibles`, `categories`, `product_types`, `compounds`, `submissions`, `blacklist`. Schema created/migrated by `ADC_Activator`. Current DB schema version tracked in `adc_db_version` option.

### Theming

5 built-in themes (brutal, clinical, dark, minimal, mystic) via CSS custom properties (`--adc-*`). Theme CSS files in `templates/`. Custom templates stored in `adc_custom_templates` option, managed by Template Builder admin page. See `docs/THEMES.md` for details.

## Code Conventions

- All DB queries use `$wpdb->prepare()` — no raw interpolation
- ORDER BY columns must use an allowlist, never `esc_sql()` alone
- All REST endpoints require nonce verification and capability checks for write operations
- Input: `sanitize_text_field(wp_unslash(...))`, `sanitize_key()`, `absint()`. Always `wp_unslash()` superglobals before sanitizing.
- Output: `esc_html()`, `esc_attr()`, `esc_url()` for admin_url(), `intval()` for numeric echo. Never skip output escaping.
- JS dialog messages use `textContent` (not innerHTML) to prevent XSS. All dynamic values in HTML string builders must use `escapeHtml()`.
- CSS template slugs validated with `sanitize_key()` + regex, CSS values stripped of `{}` to prevent injection
- Class naming: `ADC_` prefix, one class per file, filename matches class (`class-adc-foo.php` → `ADC_Foo`)
- Version constant `ADC_VERSION` in main plugin file must match `readme.txt` "Stable tag"
- Use `wp_date()` instead of `date()` for admin UI timestamps (respects site timezone)
- Activation/deactivation hooks must be at file scope, not inside `plugins_loaded` callbacks
- localStorage reads/writes must check `state.storageConsent` first (GDPR)

## Git Repository

The plugin directory is a git repo. Baseline commit is `1858e48` (v2.17.20 pre-audit). The audit fix commits follow, one per fix (40 total). Use `git log --oneline` to see the full history.

## Specs & Feature Planning

Feature specs live in `specs/NNN-feature-name/` with `spec.md`, `plan.md`, `tasks.md`, and optional `checklists/`. The `.specify/` directory contains templates for these artifacts. Audit/improvement plans live in `docs/superpowers/plans/`.
