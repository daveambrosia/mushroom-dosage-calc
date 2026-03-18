# Ambrosia Dosage Calculator Constitution

## Core Principles

### I. WordPress Standards First
All code MUST follow WordPress coding standards and the Plugin Handbook. Use WordPress APIs: REST API for data, Settings API for configuration, Transients for caching. Singleton pattern for the main class. Class-per-file architecture. Proper hook registration.

### II. Security is Non-Negotiable
Every admin endpoint checks `manage_options` capability. Every REST route has a `permission_callback`. Every input is sanitized via WordPress functions. Every output is escaped. Every SQL query uses `$wpdb->prepare()`. Public endpoints are read-only. Rate limiting on submission endpoints. Blacklist system for spam prevention.

### III. Data Accuracy
Compound values (psilocybin, psilocin, etc.) are stored in mcg/g as integers for precision. Dosage calculations must use the correct weight factors from the compounds table. Short codes are unique and auto-generated. QR codes resolve to the correct item. Google Sheets sync validates data before import. Submissions require admin review before going live.

### IV. User Experience
The calculator must load fast (assets only on pages with the shortcode). Five theme options for visual customization. Responsive design for mobile use. QR scanning resolves seamlessly to the right strain/edible. Clear safety disclaimers. Compound breakdowns are educational, not medical advice.

### V. Extensibility
The compound system is dynamic (admins add/remove compounds). Categories and product types are admin-managed. Theme system uses separate CSS files. Settings control feature visibility. REST API enables external integrations. Import supports CSV, JSON, and Google Sheets.

### VI. Production Safety
This plugin is LIVE at ambrosia.church/calculator. Database schema changes require version tracking and safe migration via `dbDelta()`. Never drop tables without explicit admin action. Activation must be idempotent (safe to reactivate). Deactivation must not lose data. Database health check and repair tools for recovery.

### VII. Simplicity
Prefer WordPress patterns over custom frameworks. No external PHP dependencies (no Composer). Minimal JavaScript dependencies (no React/Vue, use vanilla JS). CDN resources (Google Fonts, QRCode.js) must have fallbacks. Each class has a single responsibility. Remove .bak files and dead code.

## Technology Stack

- **Language**: PHP 8.0+ (8.3 on server)
- **Frontend**: Vanilla JavaScript, CSS with theme system
- **Database**: MySQL via $wpdb, 7 custom tables with `adc_` prefix
- **APIs**: WordPress REST API (`/wp-json/adc/v1/`)
- **External**: Google Sheets API, Google Fonts CDN, QRCode.js CDN
- **Cron**: WordPress cron for scheduled Google Sheets import

## Development Workflow

1. All changes start with a spec-kit specification
2. Implementation follows the task list
3. Every file change must pass the WordPress Plugin Handbook checklist
4. Test activation on fresh install AND upgrade from current version
5. Verify REST API responses with curl
6. Test QR code generation and resolution
7. Verify all 5 themes render correctly
8. Check mobile responsiveness

## Governance

This constitution governs all development on the Ambrosia Dosage Calculator. The plugin is in active production use. Breaking changes to the REST API or database schema require careful migration planning. All code must be reviewed against these principles before deployment.

**Version**: 1.0.0 | **Ratified**: 2026-03-11 | **Last Amended**: 2026-03-11
