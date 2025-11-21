# Data Model: Member Registration System

**Feature**: 011-member-system
**Date**: 2025-11-20
**Version**: 1.0.0

## Overview

This document defines the database schema for the member registration system, including user accounts, roles, permissions, email verification, password reset, API quotas, and identity verification.

## Entity Relationship Diagram

```text
┌──────────────┐         ┌──────────────┐         ┌──────────────┐
│    users     │────────<│   role_user  │>────────│    roles     │
└──────────────┘         └──────────────┘         └──────────────┘
       │                                                   │
       │                                                   │
       │                                                   ▼
       │                                          ┌──────────────────┐
       │                                          │ permission_role  │
       │                                          └──────────────────┘
       │                                                   │
       │                                                   ▼
       │                                          ┌──────────────┐
       │                                          │ permissions  │
       │                                          └──────────────┘
       │
       ├──────────────────────┐
       │                      │
       ▼                      ▼
┌─────────────────────┐  ┌──────────────────────┐
│ email_verification_ │  │ password_reset_      │
│ tokens              │  │ tokens (existing)    │
└─────────────────────┘  └──────────────────────┘
       │
       ├──────────────────────┐
       │                      │
       ▼                      ▼
┌──────────────┐     ┌────────────────────────┐
│ api_quotas   │     │ identity_verifications │
└──────────────┘     └────────────────────────┘
```

## Database Tables

### 1. `users` (MODIFY EXISTING)

**Purpose**: Store user account information including authentication credentials and verification status.

**Modifications to Existing Table**:
```sql
-- Add new columns to existing users table
ALTER TABLE users
ADD COLUMN is_email_verified BOOLEAN DEFAULT FALSE AFTER email_verified_at,
ADD COLUMN has_default_password BOOLEAN DEFAULT TRUE AFTER password,
ADD COLUMN youtube_api_key VARCHAR(255) NULL AFTER has_default_password,
ADD COLUMN last_password_change_at TIMESTAMP NULL AFTER password,
ADD INDEX idx_email_verified (is_email_verified),
ADD INDEX idx_default_password (has_default_password);
```

**Schema**:
| Column | Type | Nullable | Default | Constraints | Description |
|--------|------|----------|---------|-------------|-------------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PRIMARY KEY | User ID |
| name | VARCHAR(255) | NO | - | - | User display name (not used for auth) |
| email | VARCHAR(255) | NO | - | UNIQUE | User email (login identifier) |
| email_verified_at | TIMESTAMP | YES | NULL | - | Laravel default verification timestamp |
| is_email_verified | BOOLEAN | NO | FALSE | INDEX | Custom verification flag |
| password | VARCHAR(255) | NO | - | - | bcrypt hashed password |
| has_default_password | BOOLEAN | NO | TRUE | INDEX | Flag for mandatory password change |
| last_password_change_at | TIMESTAMP | YES | NULL | - | Track password change history |
| youtube_api_key | VARCHAR(255) | YES | NULL | - | User's YouTube API key for video operations |
| remember_token | VARCHAR(100) | YES | NULL | - | Laravel "remember me" token |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | - | Account creation time (UTC) |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP ON UPDATE | - | Last update time (UTC) |

**Validation Rules**:
- `email`: Valid email format, unique
- `password`: Minimum 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
- `youtube_api_key`: Optional, validated when provided

**Relationships**:
- Has many `role_user` (many-to-many with roles)
- Has many `email_verification_tokens`
- Has many `password_reset_tokens` (existing)
- Has one `api_quota`
- Has one `identity_verification`

---

### 2. `roles` (NEW)

**Purpose**: Define the five role types in the system.

**Schema**:
| Column | Type | Nullable | Default | Constraints | Description |
|--------|------|----------|---------|-------------|-------------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PRIMARY KEY | Role ID |
| name | VARCHAR(50) | NO | - | UNIQUE | Role name (enum-like) |
| display_name | VARCHAR(100) | NO | - | - | Human-readable role name (Traditional Chinese) |
| description | TEXT | YES | NULL | - | Role description |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | - | Role creation time (UTC) |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP ON UPDATE | - | Last update time (UTC) |

**Seed Data**:
```sql
INSERT INTO roles (name, display_name, description) VALUES
('visitor', '訪客', 'Unregistered users with limited access'),
('regular_member', '一般會員', 'Registered users with basic access'),
('paid_member', '付費會員', 'Paid members with premium features'),
('website_editor', '網站編輯', 'Content editors with full frontend access'),
('administrator', '管理員', 'System administrators with unrestricted access');
```

**Relationships**:
- Has many `role_user` (many-to-many with users)
- Has many `permission_role` (many-to-many with permissions)

---

### 3. `permissions` (NEW)

**Purpose**: Define granular permissions for access control.

**Schema**:
| Column | Type | Nullable | Default | Constraints | Description |
|--------|------|----------|---------|-------------|-------------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PRIMARY KEY | Permission ID |
| name | VARCHAR(100) | NO | - | UNIQUE | Permission identifier (e.g., 'view_comments_list') |
| display_name | VARCHAR(150) | NO | - | - | Human-readable permission name |
| category | VARCHAR(50) | NO | - | INDEX | Permission category (pages, features, actions) |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | - | Permission creation time (UTC) |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP ON UPDATE | - | Last update time (UTC) |

**Seed Data** (Sample):
```sql
INSERT INTO permissions (name, display_name, category) VALUES
-- Page Access
('view_home', 'View Home Page', 'pages'),
('view_channels_list', 'View Channels List', 'pages'),
('view_videos_list', 'View Videos List', 'pages'),
('view_comments_list', 'View Comments List', 'pages'),
('view_admin_panel', 'View Admin Panel', 'pages'),

-- Feature Access
('use_search_videos', 'Use Videos Search', 'features'),
('use_search_comments', 'Use Comments Search', 'features'),
('use_video_analysis', 'Use Video Analysis', 'features'),
('use_video_update', 'Use Video Update', 'features'),
('use_u_api_import', 'Use U-API Import', 'features'),
('use_official_api_import', 'Use Official API Import', 'features'),

-- Actions
('manage_users', 'Manage Users', 'actions'),
('manage_permissions', 'Manage Permissions', 'actions'),
('change_password', 'Change Password', 'actions');
```

**Relationships**:
- Has many `permission_role` (many-to-many with roles)

---

### 4. `role_user` (NEW)

**Purpose**: Many-to-many pivot table linking users to roles.

**Schema**:
| Column | Type | Nullable | Default | Constraints | Description |
|--------|------|----------|---------|-------------|-------------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PRIMARY KEY | Pivot ID |
| user_id | BIGINT UNSIGNED | NO | - | FOREIGN KEY(users.id) ON DELETE CASCADE, INDEX | User ID |
| role_id | BIGINT UNSIGNED | NO | - | FOREIGN KEY(roles.id) ON DELETE CASCADE, INDEX | Role ID |
| assigned_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | - | Role assignment time (UTC) |
| assigned_by | BIGINT UNSIGNED | YES | NULL | FOREIGN KEY(users.id) ON DELETE SET NULL | Admin who assigned role |

**Unique Constraint**: `UNIQUE(user_id, role_id)` - User can have each role only once

**Relationships**:
- Belongs to `users`
- Belongs to `roles`

---

### 5. `permission_role` (NEW)

**Purpose**: Many-to-many pivot table linking roles to permissions.

**Schema**:
| Column | Type | Nullable | Default | Constraints | Description |
|--------|------|----------|---------|-------------|-------------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PRIMARY KEY | Pivot ID |
| role_id | BIGINT UNSIGNED | NO | - | FOREIGN KEY(roles.id) ON DELETE CASCADE, INDEX | Role ID |
| permission_id | BIGINT UNSIGNED | NO | - | FOREIGN KEY(permissions.id) ON DELETE CASCADE, INDEX | Permission ID |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | - | Permission assignment time (UTC) |

**Unique Constraint**: `UNIQUE(role_id, permission_id)` - Role can have each permission only once

**Relationships**:
- Belongs to `roles`
- Belongs to `permissions`

---

### 6. `email_verification_tokens` (NEW)

**Purpose**: Store email verification tokens with expiration tracking.

**Schema**:
| Column | Type | Nullable | Default | Constraints | Description |
|--------|------|----------|---------|-------------|-------------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PRIMARY KEY | Token ID |
| email | VARCHAR(255) | NO | - | INDEX | Email address to verify |
| token | VARCHAR(255) | NO | - | UNIQUE | SHA-256 hashed verification token |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | INDEX | Token creation time (UTC) |
| used_at | TIMESTAMP | YES | NULL | - | Token usage time (UTC, NULL if unused) |
| expires_at | TIMESTAMP | NO | - | INDEX | Token expiration time (UTC, created_at + 24 hours) |

**Validation Rules**:
- Token expires after 24 hours
- Token can only be used once (used_at not NULL)
- Email must match pending user registration

**Cleanup**: Daily job deletes tokens where `expires_at < NOW() - 7 days`

**Relationships**:
- Belongs to `users` (via email, not foreign key to allow pending registrations)

---

### 7. `password_reset_tokens` (EXISTING, NO CHANGES)

**Purpose**: Store password reset tokens (already exists in Laravel migration).

**Existing Schema**:
| Column | Type | Nullable | Default | Constraints | Description |
|--------|------|----------|---------|-------------|-------------|
| email | VARCHAR(255) | NO | - | PRIMARY KEY | Email address |
| token | VARCHAR(255) | NO | - | - | Hashed reset token |
| created_at | TIMESTAMP | YES | NULL | - | Token creation time (UTC) |

**Note**: Laravel's default implementation already handles expiration (60 minutes) and token hashing. No modifications needed.

---

### 8. `api_quotas` (NEW)

**Purpose**: Track API import quota usage for Paid Members.

**Schema**:
| Column | Type | Nullable | Default | Constraints | Description |
|--------|------|----------|---------|-------------|-------------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PRIMARY KEY | Quota record ID |
| user_id | BIGINT UNSIGNED | NO | - | FOREIGN KEY(users.id) ON DELETE CASCADE, UNIQUE | User ID |
| current_month | VARCHAR(7) | NO | - | INDEX | Month in format YYYY-MM |
| usage_count | INT UNSIGNED | NO | 0 | - | Number of imports used this month |
| monthly_limit | INT UNSIGNED | NO | 10 | - | Monthly import limit (10 or NULL for unlimited) |
| is_unlimited | BOOLEAN | NO | FALSE | - | True if identity verified (unlimited access) |
| last_import_at | TIMESTAMP | YES | NULL | - | Last import timestamp (UTC) |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | - | Record creation time (UTC) |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP ON UPDATE | - | Last update time (UTC) |

**Validation Rules**:
- `usage_count` increments on each API import
- Reset to 0 on 1st of each month (scheduled job)
- Check `usage_count < monthly_limit OR is_unlimited = TRUE` before allowing import

**Relationships**:
- Belongs to `users`

---

### 9. `identity_verifications` (NEW)

**Purpose**: Track identity verification status for Paid Members to grant unlimited API access.

**Schema**:
| Column | Type | Nullable | Default | Constraints | Description |
|--------|------|----------|---------|-------------|-------------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PRIMARY KEY | Verification record ID |
| user_id | BIGINT UNSIGNED | NO | - | FOREIGN KEY(users.id) ON DELETE CASCADE, UNIQUE | User ID |
| verification_method | VARCHAR(50) | NO | - | - | Verification method (email, id_card, phone, etc.) |
| verification_status | ENUM('pending', 'approved', 'rejected') | NO | 'pending' | INDEX | Verification status |
| submitted_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | - | Submission time (UTC) |
| reviewed_at | TIMESTAMP | YES | NULL | - | Review completion time (UTC) |
| reviewed_by | BIGINT UNSIGNED | YES | NULL | FOREIGN KEY(users.id) ON DELETE SET NULL | Admin who reviewed |
| notes | TEXT | YES | NULL | - | Review notes or rejection reason |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | - | Record creation time (UTC) |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP ON UPDATE | - | Last update time (UTC) |

**State Transitions**:
```text
pending → approved  (admin review)
pending → rejected  (admin review)
approved → pending  (re-verification required)
```

**Business Logic**:
- When status changes to `approved`, set `api_quotas.is_unlimited = TRUE`
- When status changes to `rejected` or `pending`, set `api_quotas.is_unlimited = FALSE`

**Relationships**:
- Belongs to `users`
- Belongs to `users` (reviewed_by)

---

## Indexes

### Performance Indexes:
```sql
-- Users table
CREATE INDEX idx_users_email_verified ON users(is_email_verified);
CREATE INDEX idx_users_default_password ON users(has_default_password);

-- Email verification tokens
CREATE INDEX idx_email_verification_tokens_email ON email_verification_tokens(email);
CREATE INDEX idx_email_verification_tokens_expires ON email_verification_tokens(expires_at);
CREATE INDEX idx_email_verification_tokens_created ON email_verification_tokens(created_at);

-- API quotas
CREATE INDEX idx_api_quotas_month ON api_quotas(current_month);

-- Identity verifications
CREATE INDEX idx_identity_verifications_status ON identity_verifications(verification_status);

-- Role/Permission pivots
CREATE INDEX idx_role_user_user_id ON role_user(user_id);
CREATE INDEX idx_role_user_role_id ON role_user(role_id);
CREATE INDEX idx_permission_role_role_id ON permission_role(role_id);
CREATE INDEX idx_permission_role_permission_id ON permission_role(permission_id);
```

---

## Data Validation Rules

### User Registration:
- Email must be unique and valid format
- Password must meet strength requirements (enforced at application level)
- Default role: `regular_member` assigned automatically

### Email Verification:
- Token must not be expired (< 24 hours old)
- Token must not be used (used_at IS NULL)
- Email must match token

### Password Reset:
- Token must not be expired (< 60 minutes per Laravel default)
- Email must exist in users table

### API Quota:
- Check before import: `usage_count < monthly_limit OR is_unlimited = TRUE`
- Increment after successful import
- Reset on 1st of month

---

## State Management

### User Account States:
```text
Unverified → Verified (email verification)
Default Password → Custom Password (mandatory change)
No Role → Regular Member (auto-assigned)
Regular Member → Paid Member (admin action)
Regular Member → Website Editor (admin action)
Regular Member → Administrator (admin action)
```

### API Quota States:
```text
Within Limit (usage_count < monthly_limit)
Quota Exceeded (usage_count >= monthly_limit AND NOT is_unlimited)
Unlimited (is_unlimited = TRUE after identity verification)
```

### Identity Verification States:
```text
Not Submitted → Pending (user submits verification)
Pending → Approved (admin approves)
Pending → Rejected (admin rejects)
Approved → Pending (re-verification required)
```

---

## Migration Order

Execute migrations in this order to satisfy foreign key constraints:

1. `2025_11_20_000001_update_users_table_for_member_system.php`
2. `2025_11_20_000002_create_roles_table.php`
3. `2025_11_20_000003_create_permissions_table.php`
4. `2025_11_20_000004_create_role_user_table.php`
5. `2025_11_20_000005_create_permission_role_table.php`
6. `2025_11_20_000006_create_email_verification_tokens_table.php`
7. `2025_11_20_000007_create_api_quotas_table.php`
8. `2025_11_20_000008_create_identity_verifications_table.php`
9. `2025_11_20_000009_seed_default_admin_account.php`

---

## Timezone Handling

**All timestamps stored in UTC**:
- `created_at`, `updated_at`, `expires_at`, `submitted_at`, `reviewed_at`, `assigned_at`, `used_at`, `last_import_at`, `last_password_change_at`

**Display Conversion**:
- Backend converts to GMT+8 (Asia/Taipei) using Carbon: `$user->created_at->timezone('Asia/Taipei')`
- Frontend displays with timezone indicator: "2025-11-20 14:30 (GMT+8)"

---

**Version**: 1.1.0 (Incremental Update - CSV Export Permissions)
**Status**: Ready for implementation

---

# ADDENDUM: CSV Export Rate Limiting Data Model

**Date**: 2025-11-21
**Context**: Incremental update - Adding `csv_export_logs` table for rate limiting and audit trail

## Additional Entity Relationship

```text
┌──────────────┐         ┌────────────────────┐
│    users     │────────<│  csv_export_logs   │
└──────────────┘         └────────────────────┘
```

---

### 10. `csv_export_logs` (NEW)

**Purpose**: Track CSV export attempts per user for rolling window rate limiting (5 exports per 60-minute window) and audit trail.

**Schema**:
| Column | Type | Nullable | Default | Constraints | Description |
|--------|------|----------|---------|-------------|-------------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PRIMARY KEY | Log entry ID |
| user_id | BIGINT UNSIGNED | NO | - | FOREIGN KEY(users.id) ON DELETE CASCADE, INDEX | User who attempted export |
| video_id | VARCHAR(255) | NO | - | INDEX | YouTube video ID |
| exported_at | DATETIME | NO | - | INDEX | Export attempt time (UTC) |
| row_count | INT UNSIGNED | NO | - | - | Number of rows in export (0 if failed) |
| pattern | VARCHAR(50) | YES | NULL | - | Comment pattern filter (daytime/night/late_night/all) |
| time_points_filter | TEXT | YES | NULL | - | JSON array of time point timestamps |
| status | ENUM('success', 'rate_limited', 'row_limited', 'error') | NO | - | INDEX | Export status |
| trace_id | CHAR(36) | NO | - | INDEX | UUID for log correlation |
| error_message | TEXT | YES | NULL | - | Error details if status != success |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | - | Record creation time (UTC) |

**Composite Indexes**:
```sql
CREATE INDEX idx_csv_export_logs_user_exported ON csv_export_logs(user_id, exported_at);
CREATE INDEX idx_csv_export_logs_trace_id ON csv_export_logs(trace_id);
CREATE INDEX idx_csv_export_logs_status ON csv_export_logs(status);
CREATE INDEX idx_csv_export_logs_video ON csv_export_logs(video_id);
```

**Validation Rules**:
- `user_id` must exist in users table
- `exported_at` must be valid UTC datetime
- `row_count` must be >= 0
- `status` must be one of enum values
- `trace_id` must be valid UUID format (validated at application level)
- `pattern` must be one of: daytime, night, late_night, all (if not NULL)

**Rate Limiting Logic**:
```sql
-- Check rate limit before export (rolling 60-minute window)
SELECT COUNT(*) as export_count
FROM csv_export_logs
WHERE user_id = ?
  AND exported_at >= NOW() - INTERVAL 60 MINUTE
  AND status = 'success';

-- If export_count >= 5, reject with 429 status
-- Administrators bypass this check entirely
```

**Rolling Window Calculation**:
- **Fixed window from first export**: When user makes first export at 10:15:00, they can make 4 more exports until 11:15:00
- **Window reset**: At 11:15:00, all 5 slots become available again
- **Implementation**: Query finds first `exported_at` in current window, calculates reset time as `first_export + 60 minutes`

**State Transitions**:
```text
New Export Attempt
    ├─> Check last 60 minutes of logs
    ├─> Count WHERE status = 'success'
    │
    ├─> If count >= 5 (AND user is not administrator)
    │   └─> Insert log with status='rate_limited'
    │   └─> Reject with 429 error
    │
    ├─> If row_count > role_limit (AND user is not administrator)
    │   └─> Insert log with status='row_limited'
    │   └─> Reject with 413 error
    │
    └─> If all checks pass
        └─> Generate CSV
        └─> Insert log with status='success', row_count=actual_count
        └─> Return CSV file
```

**Cleanup Strategy**:
- **Daily cron job**: Delete logs older than 7 days for audit retention
- **Query**: `DELETE FROM csv_export_logs WHERE created_at < NOW() - INTERVAL 7 DAY`
- **Rationale**: Logs older than 7 days not needed for rate limiting (60-minute window), but retained briefly for security audit

**Relationships**:
- Belongs to `users` (many-to-one)

**Security & Observability**:
- Every export attempt (success or failure) creates a log entry
- `trace_id` links to application logs for debugging
- Failed attempts (`rate_limited`, `row_limited`, `error`) logged for security analysis
- Audit trail persists for 7 days minimum

**Performance Considerations**:
- `idx_user_exported` composite index optimizes rate limit query (30-50ms per check)
- `exported_at` stored as DATETIME (not TIMESTAMP) to avoid MySQL TIMESTAMP range limitations (1970-2038)
- `time_points_filter` stored as TEXT (JSON) for flexibility, not normalized (acceptable for audit log)

---

## Updated Migration Order

Execute migrations in this order (new migration added at end):

1-9. *(Existing migrations from initial member system - no changes)*

10. **NEW**: `2025_11_21_000010_create_csv_export_logs_table.php`

---

## Timezone Handling (Updated)

**All timestamps stored in UTC** (includes new table):
- `csv_export_logs.exported_at`: UTC datetime
- `csv_export_logs.created_at`: UTC timestamp

**Display Conversion**:
- Rate limit error messages convert to GMT+8: "Limit resets at 2025-11-21T15:15:00+08:00"
- CSV export log display (if exposed in UI): Convert `exported_at` to GMT+8 using Carbon

---

**Version**: 1.1.0 (CSV Export Permissions)
**Status**: Ready for implementation
