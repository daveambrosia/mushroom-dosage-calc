# Tasks: Security Hardening

**Branch**: `001-security-hardening` | **Date**: 2026-03-13  
**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

## Phase 1: Setup

- [x] T001 Read current source files to confirm exact text for edits: `includes/class-adc-rest-api.php`, `includes/class-adc-activator.php`, `includes/class-adc-submissions.php`, `ambrosia-dosage-calculator.php`

## Phase 2: User Story 1 — Admin Permission Enforcement (P1)

**Goal**: Admin REST endpoints require `manage_options` instead of `edit_posts`.
**Independent Test**: Unauthenticated curl to admin endpoint returns 401.

- [x] T002 [US1] Change `current_user_can('edit_posts')` to `current_user_can('manage_options')` in admin permission callback in `includes/class-adc-rest-api.php`

## Phase 3: User Story 2 — Blacklist Schema Fix (P1)

**Goal**: Blacklist table has `is_active` column; existing installs get migration; duplicate method removed.
**Independent Test**: Plugin activates without error; blacklist table has `is_active` column.

- [x] T003 [US2] Add `is_active TINYINT(1) DEFAULT 1` column to blacklist table schema in `create_tables()` in `includes/class-adc-activator.php`
- [x] T004 [US2] Add ALTER TABLE migration in `maybe_migrate_data()` in `includes/class-adc-activator.php` to add `is_active` column if missing
- [x] T005 [US2] Remove `ensure_blacklist_table()` method and its call in `add_to_blacklist()` from `includes/class-adc-submissions.php`

## Phase 4: User Story 3 — REST API Argument Validation (P2)

**Goal**: All public REST endpoints declare `args` with sanitization/validation.
**Independent Test**: Malformed parameters are sanitized; invalid codes are rejected.

- [x] T006 [US3] Add `args` array to GET /strains route in `register_routes()` in `includes/class-adc-rest-api.php`
- [x] T007 [P] [US3] Add `args` array to GET /edibles route in `register_routes()` in `includes/class-adc-rest-api.php`
- [x] T008 [P] [US3] Add `args` array to GET /strains/{code}, GET /edibles/{code}, GET /lookup/{code} routes in `includes/class-adc-rest-api.php`
- [x] T009 [P] [US3] Add `args` array to GET /categories route in `includes/class-adc-rest-api.php`

## Phase 5: User Story 4 — Rate Limiting IP Sanitization (P2)

**Goal**: Proxy-aware IP detection; sanitized user agent capture.
**Independent Test**: Plugin activates; `get_client_ip()` method exists in class.

- [x] T010 [US4] Add `private static function get_client_ip()` method to `ADC_REST_API` class in `includes/class-adc-rest-api.php`
- [x] T011 [US4] Replace `$ip = $_SERVER['REMOTE_ADDR']` with `$ip = self::get_client_ip()` in `submit_custom()` in `includes/class-adc-rest-api.php`
- [x] T012 [US4] Sanitize and truncate user agent capture in `submit_custom()` in `includes/class-adc-rest-api.php`

## Phase 6: Polish & Version Bump

- [x] T013 Update plugin version to 2.12.17 in header and `ADC_VERSION` constant in `ambrosia-dosage-calculator.php`
- [x] T014 Verify plugin activation/deactivation cycle via WP-CLI
- [x] T015 Verify REST API admin endpoint returns 401 for unauthenticated requests

## Dependencies

```
T001 → T002, T003, T004, T005, T006-T009, T010-T012
T002: independent
T003, T004, T005: independent of each other
T006-T009: parallelizable (different route registrations)
T010 → T011, T012 (helper must exist before use)
T013 → T014, T015
```

## Summary

- **Total tasks**: 15 (15 complete)
- **US1 (Permission)**: 1 task ✅
- **US2 (Blacklist)**: 3 tasks ✅
- **US3 (REST Args)**: 4 tasks ✅
- **US4 (IP Sanitization)**: 3 tasks ✅
- **Polish**: 3 tasks ✅
