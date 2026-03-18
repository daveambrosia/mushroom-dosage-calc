# Changelog — Ambrosia Dosage Calculator

## 2.12.24 — 2026-03-14

### Fixed
- Dose level collapse button on wide display: increased card `padding-right` from 36px to 48px, giving a clean 10px gap between the button and Share/Details buttons
- Dose level tooltip (collapsed state): text now ends with "Click to expand." (period added)

## 2.12.22 — 2026-03-14

### Improved
- Mobile UX pass: improved touch targets, tap areas, font sizing, layout stacking, and overflow handling for small screens (375px+). Audited with Lighthouse and UX tools.

## 2.12.21 — 2026-03-14

### Changed
- Recommended Dosages: replaced section-level collapse with per-card collapse. Each dose level (Microdose, Perceivable, Intense, Profound, Breakthrough) now has its own ▾/▴ toggle. Collapsed levels stay collapsed across re-renders (weight/sensitivity/tolerance changes). State persists in localStorage when "Remember my settings" is enabled.

## 2.12.20 — 2026-03-14

### Added
- Strain and edible product boxes are now collapsible. When collapsed, only the dropdown select is shown (with a ▴ toggle to expand). Collapse state persists with "Remember my settings".

## 2.12.19 — 2026-03-14

### Added
- Collapsible sections: each calculator box (except body weight and strain/edible) now has a ▾/▴ toggle in the top-right corner to collapse/expand the section. Collapsed state persists in localStorage when "Remember my settings" is enabled.

---

## 2.12.18 — 2026-03-14

### Fixed
- **`GET /compounds` returned empty array** — SQL query in `get_compounds()` referenced non-existent column names (`name`, `slug`, `abbreviation`, `is_active_compound`). Corrected to use actual schema columns (`display_name`, `compound_key`, `unit`, `is_active`). All 6 compounds now return correctly.

---

## 2.12.17 — 2026-03-13

### Security
- Fixed admin REST API permission check from `edit_posts` to `manage_options` — editors can no longer create/delete strains or approve submissions
- Fixed blacklist table schema: `is_active` column was missing from the activator's `CREATE TABLE` statement, causing `is_blacklisted()` to silently fail on all lookups. Added column and DB migration for existing installs
- Added REST API argument validation (`args` schemas with `sanitize_callback` and `validate_callback`) to all public endpoints
- Fixed rate-limiting IP detection to use proxy header fallback (`HTTP_X_FORWARDED_FOR`, `HTTP_X_REAL_IP`) and sanitize all IP/user-agent values

### Added
- `uninstall.php` — properly removes all 7 DB tables, options, and transients when plugin is deleted
- Export endpoints: `GET /admin/strains/export` and `GET /admin/edibles/export` (CSV and JSON)
- Search and potency filter params on public endpoints: `?search=`, `?min_potency=`, `?max_potency=`
- HTTP caching: `Cache-Control: public, max-age=300` and `ETag` headers on all public GET endpoints

### Fixed
- `load_plugin_textdomain()` now called on `init` — translation support was silently broken
- `product_types` table schema now includes `unit_name` column; migration added for existing installs
- QRCode.js CDN script now only loads on the QR Generator admin page (was loading on all admin pages)
- Google Sheets admin class no longer loads on frontend page requests
- Removed all `.bak` files from plugin directory

### Changed
- `class-adc-admin.php` (2,050 lines) split into focused admin classes: `class-adc-admin-strains.php`, `class-adc-admin-edibles.php`, `class-adc-admin-settings.php`, `class-adc-admin-submissions.php`, `class-adc-admin-tools.php`
