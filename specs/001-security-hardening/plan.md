# Implementation Plan: Security Hardening

**Branch**: `001-security-hardening` | **Date**: 2026-03-13 | **Spec**: [spec.md](spec.md)

## Summary

Fix 4 security issues in the Ambrosia Dosage Calculator: (1) tighten admin permission from `edit_posts` to `manage_options`, (2) add missing `is_active` column to blacklist table with migration, (3) add REST API argument validation/sanitization, (4) implement proxy-aware IP detection with sanitization for rate limiting.

## Technical Context

**Language/Version**: PHP 8.0+ (8.3 on server)
**Primary Dependencies**: WordPress 6.0+, WordPress REST API
**Storage**: MySQL via $wpdb, 7 custom tables with `adc_` prefix
**Testing**: Manual via WP-CLI and curl; activation/deactivation cycle
**Target Platform**: WordPress on Apache with potential reverse proxy
**Project Type**: WordPress plugin (production)
**Constraints**: Zero downtime — plugin is live at ambrosia.church/calculator

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| II. Security is Non-Negotiable — `manage_options` on admin endpoints | ❌ FIXING | Currently uses `edit_posts` |
| II. Security is Non-Negotiable — Input sanitization | ❌ FIXING | REST args lack validation; IP unsanitized |
| II. Security is Non-Negotiable — Blacklist system | ❌ FIXING | Schema mismatch breaks blacklist |
| VI. Production Safety — Safe migration | ✅ PASS | Will use ALTER TABLE with existence check |
| VII. Simplicity — WordPress patterns | ✅ PASS | Using WP sanitize functions |

All 4 fixes directly address Constitution Principle II violations. No new dependencies introduced.

## Project Structure

### Documentation

```text
specs/001-security-hardening/
├── plan.md              # This file
├── research.md          # Research (minimal — fixes are well-defined)
├── data-model.md        # Schema change for blacklist table
├── tasks.md             # Task list
└── checklists/
    └── requirements.md  # Quality checklist
```

### Source Code (files modified)

```text
includes/
├── class-adc-rest-api.php      # Fixes 1, 3, 4
├── class-adc-activator.php     # Fix 2A (schema), Fix 2B (migration)
└── class-adc-submissions.php   # Fix 2C (remove duplicate method)
ambrosia-dosage-calculator.php  # Version bump
```

## Complexity Tracking

No constitution violations to justify. All changes reduce complexity and improve compliance.
