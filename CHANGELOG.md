# Changelog — Ambrosia Dosage Calculator

## 2.24.9 — 2026-03-24

### Bug Fixes
- Fixed `esc_html()` on disclaimer text replaced with `wp_kses_post()` so HTML renders correctly in the front-end calculator
- Added `wp_unslash()` at read time for all four content settings (`calculator_title`, `calculator_subtitle`, `safety_items`, `disclaimer_text`) in both the shortcode and admin settings page, preventing stored backslash-escapes from rendering as literal characters
- Added `calculator_title`, `calculator_subtitle`, and `safety_items` to `set_default_options()` so all content defaults are written cleanly to the database on fresh install/activation rather than living only as PHP fallback strings

## 2.23.0 — 2026-03-21

### Security
- Self-hosted Pickr color picker library (was loading from cdn.jsdelivr.net without SRI)
- Added server-side honeypot validation to `/adc/v1/submit` endpoint
- Hardened CSS value sanitizer to block `@import` and IE `behavior:` injection vectors
- Added `wp_unslash()` to `$_SERVER['HTTP_USER_AGENT']` in submission handler

### Accessibility
- Fixed WCAG 2.4.3 violation: replaced positive tabindex values (1, 2, 3...) with 0/-1 in `updateTabStops()`

### Bug Fixes
- Fixed optional chaining syntax (`? .` with space) in `adc-init.js` that caused terser to fail silently
- Wired up `defaultStrain` / `defaultEdible` admin settings: now applied on init when no saved preference exists
- Added JSON.parse error boundary with object structure validation in `checkForSharedDose()`
- Improved share button error handling: notifies user on invalid share data instead of silently falling back
- Replaced `esc_sql()` ORDER BY pattern with column whitelist in `ADC_Submissions::get_all()`

### Performance
- Rebuilt all stale minified assets (calculator.js, calculator.min.js, adc-dialogs.min.js, adc-dialogs.min.css)
- Set `adc_settings` option to autoload=true (saves one DB query per calculator page load)

### Build
- Added PHP syntax lint check to `build-zip.sh` and `build-min.sh` (prevents shipping broken PHP)
- Updated terser to support optional chaining (`?.`) syntax

---

## 2.22.0 — 2026-03-21

### Security
- Fixed all output escaping across admin templates (218 violations resolved)
- Added `wp_unslash()` before all input sanitization functions
- Added `isset()` checks before all superglobal access
- Converted `wp_redirect()` to `wp_safe_redirect()` for all admin redirects
- Added nonce verification annotations for read-only display filters
- Added strict comparison (`true`) to all `in_array()` calls

### Performance
- Added composite database indexes: `idx_ip_created` on submissions, `idx_active_category` on strains, `idx_active_product_type` on edibles
- Removed 4 duplicate database indexes on strains and edibles short_code columns
- Assets already optimized: JS 49% compression, CSS 34% compression

### Code Quality
- Fixed all PHPStan level 5 errors (0 remaining)
- Auto-fixed 12,884 PHPCS violations via PHPCBF (whitespace, alignment, formatting)
- Fixed all Yoda condition violations (WordPress coding standards)
- Fixed `str_pad()` type safety in ADC_DB::generate_short_code()
- Fixed Content-Length header type casting in REST API export endpoints
- Fixed `esc_attr()` float-to-string casting in template builder
- Created `phpcs.xml.dist` with project-specific ruleset configuration
- Created `phpstan.neon` with project-specific analysis configuration
- Updated test files for modern PHPUnit (set_up/tear_down with void return types)

### Added
- Frontend security headers: X-Content-Type-Options, X-Frame-Options, Referrer-Policy
- New PHPUnit tests: potency filter, search filter, API format output, empty name validation, get_by_code lookup, update nonexistent strain error handling
- PHPCompatibilityWP installed for broader compatibility checking

### Infrastructure
- Installed PHPCompatibilityWP 2.1 for PHP version compatibility scanning

---

## 2.21.0 — 2026-03-20

### Added
- Template Builder: visual theme editor for calculator styles
- QR code generator for strains and edibles
- Google Sheets integration for data import/sync

---

## 2.12.24 — 2026-03-14

### Fixed
- Dose level collapse button on wide display: increased card `padding-right` from 36px to 48px, giving a clean 10px gap between the button and Share/Details buttons
- Dose level tooltip (collapsed state): text now ends with "Click to expand." (period added)

## 2.12.22 — 2026-03-14

### Improved
- Mobile UX pass: improved touch targets, tap areas, font sizing, layout stacking, and overflow handling for small screens (375px+). Audited with Lighthouse and UX tools.

## 2.12.21 — 2026-03-14

### Changed
- Recommended Dosages: replaced section-level collapse with per-card collapse. Each dose level (Microdose, Perceivable, Intense, Profound, Breakthrough) now has its own toggle. Collapsed levels stay collapsed across re-renders. State persists in localStorage when "Remember my settings" is enabled.

## 2.12.20 — 2026-03-14

### Added
- Strain and edible product boxes are now collapsible. When collapsed, only the dropdown select is shown. Collapse state persists with "Remember my settings".

## 2.12.19 — 2026-03-14

### Added
- Collapsible sections: each calculator box now has a toggle in the top-right corner. Collapsed state persists in localStorage when "Remember my settings" is enabled.

## 2.12.18 — 2026-03-14

### Fixed
- `GET /compounds` returned empty array: SQL query referenced non-existent column names. Corrected to use actual schema columns. All 6 compounds now return correctly.

## 2.12.17 — 2026-03-13

### Security
- Fixed admin REST API permission check from `edit_posts` to `manage_options`
- Fixed blacklist table schema: `is_active` column was missing from activator
- Added REST API argument validation to all public endpoints
- Fixed rate-limiting IP detection and sanitization

### Added
- `uninstall.php` for proper cleanup on plugin deletion
- Export endpoints: CSV and JSON for strains and edibles
- Search and potency filter params on public endpoints
- HTTP caching: Cache-Control and ETag headers on public GET endpoints
