# Data Model: Security Hardening

**Branch**: `001-security-hardening` | **Date**: 2026-03-13

## Schema Change: `adc_blacklist` Table

### Current Schema (broken)

```sql
CREATE TABLE {prefix}adc_blacklist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    email VARCHAR(255),
    user_agent TEXT,
    reason TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### Updated Schema

```sql
CREATE TABLE {prefix}adc_blacklist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    email VARCHAR(255),
    user_agent TEXT,
    reason TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### Migration

For existing installs: `ALTER TABLE {prefix}adc_blacklist ADD COLUMN is_active TINYINT(1) DEFAULT 1`

Condition: Only if `is_active` column does not exist (checked via `SHOW COLUMNS`).
