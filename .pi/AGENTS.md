# Ambrosia Dosage Calculator - Pi Development Guide

## What This Plugin Does

A psilocybin dosage calculator for the Church of Ambrosia. Members look up strains or edibles (by name or QR code), see compound breakdowns (psilocybin, psilocin, etc.), and calculate dosages. Admins manage strains, edibles, categories, compounds, and import data from Google Sheets or CSV/JSON.

## Architecture

**Singleton pattern** with proper class separation:

```
ambrosia-dosage-calculator.php   # Main file: Ambrosia_Dosage_Calculator singleton,
                                 # hooks, asset loading, rewrite rules

includes/
  class-adc-activator.php        # Activation/deactivation: table creation (7 tables),
                                 # default data seeding, migration, database health checks
  class-adc-db.php               # ADC_DB helper: table names, settings, short code generation
  class-adc-strains.php          # Strain CRUD operations
  class-adc-edibles.php          # Edible CRUD operations
  class-adc-submissions.php      # User submission handling
  class-adc-rest-api.php         # REST API: /adc/v1/ endpoints for strains, edibles,
                                 # lookup, categories, product types, compounds, settings,
                                 # admin CRUD, submissions, import/export
  class-adc-shortcode.php        # [dosage_calculator] and [adc_calculator] shortcodes
  class-adc-qr-handler.php       # QR code short URL redirect handler (/c/{code})

admin/
  class-adc-admin.php            # Admin pages: strain management, edible management,
                                 # submissions, settings, database tools
  class-adc-csv-importer.php     # CSV import for strains/edibles
  class-adc-json-importer.php    # JSON import for strains/edibles
  class-adc-qr-generator.php     # QR code generation for strain/edible codes
  class-adc-google-sheets.php    # Google Sheets API client
  class-adc-sheets-importer.php  # Google Sheets auto-import with cron scheduling
  class-adc-sheets-admin-page.php # Google Sheets admin settings page
  css/admin.css                  # Admin styles
  css/adc-dialogs.css            # Admin dialog styles
  js/admin.js                    # Admin JavaScript
  js/adc-dialogs.js              # Custom dialog system (admin)
  js/google-sheets-admin.js      # Google Sheets admin JS

public/
  css/calculator.css             # Calculator frontend styles
  css/calculator-themes.css      # Theme variations
  css/adc-dialogs.css            # Frontend dialog styles
  js/calculator.js               # Calculator frontend logic
  js/adc-dialogs.js              # Custom dialog system (frontend)

templates/
  brutal.css                     # Theme: Brutal
  clinical.css                   # Theme: Clinical
  dark.css                       # Theme: Dark
  minimal.css                    # Theme: Minimal
  mystic.css                     # Theme: Mystic
```

## Database Tables (7 tables, `adc_` prefix)

1. `adc_strains` - Mushroom strains with compound levels (psilocybin, psilocin, etc.)
2. `adc_edibles` - Edible products with compound levels and packaging info
3. `adc_categories` - Categories for strains (lab-tested, species, potency levels)
4. `adc_product_types` - Product types for edibles (chocolate, gummy, capsule, etc.)
5. `adc_compounds` - Compound definitions (which affect dosing, calculation weights)
6. `adc_submissions` - User-submitted strain/edible data (pending/approved/rejected)
7. `adc_blacklist` - Blocked IPs, emails, user agents for spam prevention

## REST API Endpoints (`/wp-json/adc/v1/`)

**Public (no auth):**
- `GET /strains` - All active strains
- `GET /strains/{code}` - Single strain by short code
- `GET /edibles` - All active edibles
- `GET /edibles/{code}` - Single edible by short code
- `GET /lookup/{code}` - Lookup any short code (strain or edible)
- `GET /categories` - All categories
- `GET /product-types` - All product types
- `GET /compounds` - All compounds
- `GET /settings` - Calculator display settings

**Admin (requires `manage_options`):**
- Full CRUD for strains, edibles, categories, product types, compounds
- Submission management (approve/reject)
- Import/export endpoints
- Database health and repair

## Key Features

- **Short codes**: Every strain/edible has a unique code (e.g., `ZD-GT-001`) with QR support
- **QR redirect**: `/c/{code}` resolves to the calculator pre-loaded with that item
- **5 CSS themes**: Selectable in settings, loaded dynamically
- **Google Sheets sync**: Auto-import strains/edibles on schedule via cron
- **Submission system**: Members submit new strains/edibles for admin review
- **Custom dialog system**: No jQuery UI dependency, custom modal implementation
- **Compound system**: Extensible; admins add custom compounds beyond the 6 defaults

## Settings (stored in `adc_settings` option)

- template, default_tab, show_edibles, show_mushrooms
- show_quick_converter, show_compound_breakdown, show_safety_warning
- allow_custom, allow_submit, short_url_path, short_code_prefix
- disclaimer_text, custom_css, auto_submit_unknown_qr
- submission_notification_email

## Development Notes

- **Version**: 2.12.16 (DB version 2.0.0)
- **LIVE in production** at ambrosia.church/calculator
- **Text domain**: `ambrosia-dosage-calc`
- **Requires PHP 8.0+**, WordPress 6.0+
- Uses WordPress REST API (not AJAX) for frontend data
- Google Fonts loaded externally (Space Mono, Work Sans)
- QRCode.js loaded from CDN for admin QR generation

## Improvement Areas

- No uninstall.php
- No i18n translation loading (text domain declared but not loaded)
- Some `.bak` files in the codebase (should be cleaned up)
- REST API caching uses transients (could add ETags/304 support)
- No rate limiting on public endpoints
- Frontend calculator is a single large JS file
- Missing REST API schema/arg validation on some endpoints

## Spec-Kit

This project has spec-kit initialized. Use the spec-driven development workflow:
1. `/skill:speckit.specify <improvement description>`
2. `/skill:speckit.plan`
3. `/skill:speckit.tasks`
4. `/skill:speckit.implement`
