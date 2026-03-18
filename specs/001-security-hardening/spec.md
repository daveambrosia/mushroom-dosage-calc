# Feature Specification: Security Hardening

**Feature Branch**: `001-security-hardening`  
**Created**: 2026-03-13  
**Status**: Draft  
**Input**: Fix 4 security issues: admin permission level bug, blacklist schema mismatch, missing REST API argument validation, and IP sanitization in rate limiting.

## User Scenarios & Testing

### User Story 1 - Admin Permission Enforcement (Priority: P1)

Only WordPress administrators (users with `manage_options` capability) can access admin REST API endpoints for creating/deleting strains, approving submissions, and running bulk imports. Currently, editors (`edit_posts`) can also perform these actions, violating the plugin's security constitution.

**Why this priority**: Critical security issue — unauthorized users can modify plugin data.

**Independent Test**: Attempt admin REST API calls as an editor-role user; all should return 403 Forbidden.

**Acceptance Scenarios**:

1. **Given** a user with the editor role, **When** they call any admin REST endpoint (e.g., POST /adc/v1/admin/strains), **Then** they receive a 403 Forbidden response.
2. **Given** a user with the administrator role, **When** they call admin REST endpoints, **Then** they receive successful responses as before.

---

### User Story 2 - Blacklist Enforcement (Priority: P1)

The blacklist system actually blocks submissions from blacklisted IPs and emails. Currently, the `is_active` column is missing from the database table, causing the blacklist check to silently fail and never block anyone.

**Why this priority**: Critical — the entire blacklist/spam prevention system is non-functional.

**Independent Test**: Add an IP to the blacklist table, then attempt a submission from that IP; submission should be rejected.

**Acceptance Scenarios**:

1. **Given** a fresh plugin install, **When** the plugin activates, **Then** the `adc_blacklist` table includes the `is_active` column with a default of 1.
2. **Given** an existing install without the `is_active` column, **When** the plugin updates and runs migrations, **Then** the `is_active` column is added via ALTER TABLE.
3. **Given** a blacklisted IP with `is_active = 1`, **When** a submission comes from that IP, **Then** the submission is rejected.

---

### User Story 3 - REST API Input Validation (Priority: P2)

All public REST API endpoints validate and sanitize their input parameters to prevent injection attacks and unexpected behavior.

**Why this priority**: Defense-in-depth; prevents malformed input from reaching database queries.

**Independent Test**: Send malformed parameters (special characters, excessive length) to public endpoints; inputs should be sanitized or rejected.

**Acceptance Scenarios**:

1. **Given** a GET /strains request with a `category` parameter containing special characters, **When** the request is processed, **Then** the parameter is sanitized via `sanitize_key`.
2. **Given** a GET /strains/{code} request with a code containing SQL injection attempts, **When** the request is processed, **Then** the code is validated against the pattern `^[a-zA-Z0-9-]{1,50}$` and rejected if invalid.
3. **Given** a GET /categories request with `type=invalid`, **When** processed, **Then** the enum validation limits values to `strain`, `edible`, or `both`.

---

### User Story 4 - Rate Limiting IP Accuracy (Priority: P2)

The rate limiting system correctly identifies individual client IPs even behind reverse proxies, and sanitizes all server-provided values.

**Why this priority**: Without this fix, all users behind a reverse proxy share one rate limit bucket, causing false rate-limit blocks.

**Independent Test**: Behind a reverse proxy, two different clients should have independent rate limit counters.

**Acceptance Scenarios**:

1. **Given** a request through a reverse proxy with `X-Forwarded-For` header, **When** rate limiting checks the IP, **Then** it uses the first public IP from the header.
2. **Given** a request with a spoofed private IP in `X-Forwarded-For`, **When** rate limiting checks the IP, **Then** private/reserved ranges are skipped and REMOTE_ADDR is used as fallback.
3. **Given** a submission request, **When** the user agent is captured, **Then** it is sanitized and truncated to 500 characters.

---

### Edge Cases

- What happens when `X-Forwarded-For` contains multiple IPs? → Use the first valid public IP.
- What happens when all forwarded IPs are private? → Fall back to `REMOTE_ADDR`.
- What happens on existing installs where `adc_blacklist` table already has the `is_active` column? → Migration safely skips the ALTER.
- What happens when the blacklist table doesn't exist at all during migration? → The `SHOW COLUMNS` returns empty/false; migration skips gracefully.

## Requirements

### Functional Requirements

- **FR-001**: Admin REST API permission callback MUST require `manage_options` capability.
- **FR-002**: The `adc_blacklist` table schema MUST include an `is_active TINYINT(1) DEFAULT 1` column.
- **FR-003**: A database migration MUST add the `is_active` column to existing installs that lack it.
- **FR-004**: The duplicate `ensure_blacklist_table()` method MUST be removed from `class-adc-submissions.php`.
- **FR-005**: Public REST API endpoints MUST declare `args` arrays with type, sanitize, and validate callbacks.
- **FR-006**: Rate limiting MUST use proxy-aware IP detection with sanitization.
- **FR-007**: User agent capture MUST be sanitized and length-limited.

### Key Entities

- **Blacklist Entry**: IP, email, user agent, reason, `is_active` flag — controls spam prevention.
- **REST API Route**: WordPress route registration with args validation/sanitization.

## Success Criteria

### Measurable Outcomes

- **SC-001**: Editor-role users receive 403 on all admin endpoints (0% unauthorized access).
- **SC-002**: Blacklisted IPs/emails are correctly blocked (blacklist check returns true when `is_active = 1`).
- **SC-003**: All public REST API parameters are sanitized before reaching handler code.
- **SC-004**: Rate limiting correctly distinguishes clients behind a shared proxy.
- **SC-005**: Plugin activates and deactivates without errors after all changes.
