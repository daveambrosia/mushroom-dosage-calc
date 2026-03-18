# Research: Security Hardening

**Branch**: `001-security-hardening` | **Date**: 2026-03-13

## Decision 1: Permission Capability

- **Decision**: Use `manage_options` for all admin REST endpoints
- **Rationale**: Constitution Principle II explicitly requires `manage_options`. The `edit_posts` capability includes editors and authors, who should not manage strains, approve submissions, or run imports.
- **Alternatives**: `edit_others_posts` (still too permissive), custom capability (unnecessary complexity)

## Decision 2: Blacklist Migration Strategy

- **Decision**: Add column in `create_tables()` schema AND add ALTER TABLE migration in `maybe_migrate_data()`
- **Rationale**: Fresh installs get the column from dbDelta. Existing installs get it from migration. Both paths must work per Constitution Principle VI.
- **Alternatives**: Only dbDelta (wouldn't fix existing installs), recreate table (data loss risk)

## Decision 3: IP Detection for Reverse Proxies

- **Decision**: Check `X-Forwarded-For`, `X-Real-IP`, `HTTP_CLIENT_IP` before `REMOTE_ADDR`. Validate with `FILTER_VALIDATE_IP` excluding private/reserved ranges. Sanitize with `sanitize_text_field()`.
- **Rationale**: Apache reverse proxy sets `REMOTE_ADDR` to proxy IP. Standard proxy headers provide the real client IP. Private range exclusion prevents header spoofing from injecting internal IPs.
- **Alternatives**: Trust only `REMOTE_ADDR` (broken behind proxy), trust all headers without validation (spoofable)

## Decision 4: REST API Argument Validation

- **Decision**: Add WordPress-standard `args` arrays with `type`, `sanitize_callback`, `validate_callback`, `enum`, and `default` where appropriate.
- **Rationale**: WordPress REST API framework handles validation/sanitization automatically when args are declared. This is the canonical approach per the REST API Handbook.
- **Alternatives**: Manual sanitization in handler functions (already partially done, but args provide defense-in-depth)
