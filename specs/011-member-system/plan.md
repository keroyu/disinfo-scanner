# Implementation Plan: Export CSV Permission Control

**Branch**: `011-member-system` | **Date**: 2025-11-21 | **Spec**: [spec.md](./spec.md)
**Type**: **INCREMENTAL UPDATE** to existing member system
**Input**: Permission update specification from `/specs/011-member-system/spec.md` (Session 2025-11-21 clarifications)

## Summary

Add role-based permission control to the existing "Export CSV" feature on the Video Analysis page. Currently, the Export CSV button is visible to all users (including visitors). This update will:

1. **Hide Export CSV button from visitors** - display modal "請登入會員" when clicked
2. **Implement rate limiting** - 5 exports per hour (rolling window) for Regular Members, Premium Members, and Website Editors
3. **Implement row limits** - Regular Members: 1,000 rows max, Premium Members & Website Editors: 3,000 rows max, Administrators: unlimited
4. **Allow unlimited access for Administrators** - no rate limiting, no row limits

**Technical Approach**: Add server-side API endpoint for CSV export with permission checks, rate limiting middleware, and quota tracking database table. Frontend JavaScript will call the API instead of generating CSV client-side.

## Technical Context

**Language/Version**: PHP 8.2 with Laravel Framework 12.38.1
**Primary Dependencies**: Laravel Framework (existing), Symfony RateLimiter (Laravel built-in)
**Storage**: MySQL/MariaDB (existing database) - add `csv_export_logs` table for rate limiting tracking
**Testing**: PHPUnit (existing test suite) - add Feature tests for permission gates and rate limiting
**Target Platform**: Web application (Linux server)
**Project Type**: Web (Laravel backend + Blade frontend with JavaScript)
**Performance Goals**: CSV export API response < 2 seconds for 3,000 rows, rate limit check < 50ms
**Constraints**: Rolling window rate limit (60 minutes from first export), row limits enforced before CSV generation
**Scale/Scope**: Existing video analysis feature (~150 lines of controller code, 750 lines of Blade view with JavaScript)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Test-First Development ✅
- **Status**: PASS
- **Evidence**: All new functionality (permission gates, rate limiting, row limits) will have Feature tests written before implementation following Red-Green-Refactor cycle
- **Test Plan**: Feature tests for CSV export API endpoint covering all 5 roles, rate limit enforcement, row limit enforcement, edge cases

### II. API-First Design ✅
- **Status**: PASS
- **Evidence**: New CSV export functionality will be exposed as REST API endpoint `/api/videos/{videoId}/comments/export-csv` with clear contract before implementation
- **API Contract**: POST endpoint accepting `fields[]`, `pattern`, `time_points[]` parameters; returns CSV file download or JSON error response with rate limit/row limit details

### III. Observable Systems ✅
- **Status**: PASS
- **Evidence**: CSV export operations will log to structured logs with trace ID, user ID, video ID, row count, rate limit status
- **Logging Plan**: Log every export attempt (success/failure), rate limit violations, row limit violations with actionable context

### IV. Contract Testing ✅
- **Status**: PASS
- **Evidence**: API contract test will verify CSV export endpoint response format (success: file download, error: JSON with type/message/details)
- **Contract Coverage**: Request validation (fields, pattern, time_points), response structure (CSV headers, error JSON schema), permission boundaries

### V. Semantic Versioning ✅
- **Status**: PASS
- **Evidence**: This is a MINOR version feature (backward-compatible permission control added to existing feature). No breaking changes to video analysis API.
- **Version Impact**: MINOR bump (new permission-controlled API endpoint, existing analysis endpoints unchanged)

### VI. Timezone Consistency ✅
- **Status**: PASS
- **Evidence**: CSV export will include timestamps in GMT+8 (Asia/Taipei) per existing convention. Rate limit tracking uses UTC internally, converted to GMT+8 for error messages.
- **Timezone Handling**:
  - Database: Store `csv_export_logs.exported_at` in UTC
  - Application: Convert to GMT+8 for "Limit resets in X minutes" error messages
  - CSV Export: Include published_at timestamps in GMT+8 format (consistent with existing comment display)

**Constitution Compliance**: ALL GATES PASS ✅

## Project Structure

### Documentation (this feature - incremental update)

```text
specs/011-member-system/
├── spec.md              # Updated with Session 2025-11-21 clarifications
├── plan.md              # This file (INCREMENTAL UPDATE plan)
├── research.md          # Phase 0 output (rate limiting strategies, CSV generation patterns)
├── data-model.md        # Phase 1 output (csv_export_logs table schema)
├── quickstart.md        # Phase 1 output (developer guide for CSV export API)
├── contracts/           # Phase 1 output (API contract for CSV export endpoint)
│   └── csv-export-api.yaml
└── tasks.md             # Phase 2 output (NOT created by /speckit.plan, created by /speckit.tasks)
```

### Source Code (repository root - existing Laravel structure)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── VideoAnalysisController.php          # [EXISTING] Add showAnalysisPage permission check
│   │   └── Api/
│   │       └── CsvExportController.php           # [NEW] Handle CSV export with permissions
│   ├── Middleware/
│   │   └── CheckCsvExportRateLimit.php          # [NEW] Rate limiting middleware
│   └── Requests/
│       └── CsvExportRequest.php                  # [NEW] Validate CSV export parameters
├── Models/
│   ├── User.php                                  # [EXISTING] Add hasPermission helper methods
│   ├── Role.php                                  # [EXISTING] No changes needed
│   └── CsvExportLog.php                          # [NEW] Track export attempts for rate limiting
├── Services/
│   ├── CsvExportService.php                      # [NEW] CSV generation logic with row limits
│   └── RateLimitService.php                      # [NEW] Rolling window rate limit checks
└── Exceptions/
    ├── CsvExportRateLimitException.php           # [NEW] Rate limit exceeded exception
    └── CsvExportRowLimitException.php            # [NEW] Row limit exceeded exception

database/
└── migrations/
    └── 2025_11_21_create_csv_export_logs_table.php  # [NEW] Rate limit tracking

resources/
└── views/
    └── videos/
        └── analysis.blade.php                    # [MODIFY] Update Export CSV button to call API

public/
└── js/
    └── comment-pattern.js                        # [MODIFY] Update exportToCSV() to call API endpoint

routes/
├── web.php                                       # [MODIFY] Add permission middleware to analysis page route
└── api.php                                       # [MODIFY] Add CSV export API route

tests/
├── Feature/
│   ├── CsvExportPermissionTest.php               # [NEW] Test role-based permissions
│   ├── CsvExportRateLimitTest.php                # [NEW] Test rate limiting (rolling window)
│   └── CsvExportRowLimitTest.php                 # [NEW] Test row limits per role
└── Contract/
    └── CsvExportApiContractTest.php              # [NEW] Validate API contract compliance
```

**Structure Decision**: This is an **incremental update** to the existing Laravel web application structure. The member system (011-member-system) is already implemented and tested. This update adds permission control to an existing feature (Video Analysis Export CSV) by:

1. **Backend**: New API controller (`Api/CsvExportController`), new service layer (`CsvExportService`, `RateLimitService`), new database table (`csv_export_logs`), new middleware (`CheckCsvExportRateLimit`)
2. **Frontend**: Minimal JavaScript modification to call API endpoint instead of client-side CSV generation
3. **Database**: Single new table for rate limit tracking with rolling window support

This follows the existing Laravel MVC + Service layer pattern already established in the codebase (e.g., `CommentDensityAnalysisService`, `VideoIncrementalUpdateService`).

## Complexity Tracking

> **No constitutional violations detected.** This section is empty as all Constitution Check gates pass.

## Phase 0: Research & Decisions

### Research Questions

1. **Rate Limiting Strategy**: How to implement rolling window rate limiting in Laravel?
   - Research Laravel's built-in RateLimiter
   - Evaluate database-backed vs cache-backed rate limiting for accuracy
   - Decision criteria: Must support 60-minute rolling window, per-user tracking

2. **CSV Generation Performance**: How to efficiently generate CSV with row limits?
   - Research Laravel Response::streamDownload() vs Collection->toCsv()
   - Evaluate memory usage for 3,000 row exports
   - Decision criteria: <200MB memory usage, <2 second response time

3. **Permission Gate Pattern**: Best practice for role-based CSV export permissions in Laravel?
   - Research Laravel Gates vs Policies for feature-level permissions
   - Evaluate middleware vs controller-level permission checks
   - Decision criteria: Consistent with existing member system RBAC pattern

### Technical Decisions to Document in research.md

- Rate limiting implementation approach (database `csv_export_logs` table vs Redis cache)
- CSV generation method (streaming vs in-memory) for performance
- Permission check location (middleware vs service layer)
- Error response format for rate limit/row limit violations (JSON structure)
- Rolling window calculation method (first export timestamp tracking)

**Output**: `research.md` with documented decisions and rationale

## Phase 1: API Contract & Data Model

### Data Model: `csv_export_logs` Table

**Purpose**: Track CSV export attempts per user for rolling window rate limiting

**Schema**:
```sql
CREATE TABLE csv_export_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    video_id VARCHAR(255) NOT NULL,
    exported_at DATETIME NOT NULL COMMENT 'UTC timestamp',
    row_count INT UNSIGNED NOT NULL,
    pattern VARCHAR(50) NULL COMMENT 'daytime/night/late_night/all',
    time_points_filter TEXT NULL COMMENT 'JSON array of time points',
    status ENUM('success', 'rate_limited', 'row_limited', 'error') NOT NULL,
    trace_id CHAR(36) NOT NULL COMMENT 'UUID for log tracing',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_exported_at (user_id, exported_at),
    INDEX idx_trace_id (trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships**:
- `csv_export_logs.user_id` → `users.id` (many-to-one)

**State Transitions**:
- New export attempt → Check last 60 minutes of logs → Count successes → Allow/deny
- Successful export → Insert log with `status='success'`
- Rate limited → Insert log with `status='rate_limited'` for audit trail
- Row limited → Insert log with `status='row_limited'` for audit trail

**Validation Rules**:
- `user_id` must exist in users table
- `exported_at` must be UTC datetime
- `row_count` must be >= 0
- `status` must be one of enum values
- `trace_id` must be valid UUID format

### API Contract: CSV Export Endpoint

**Endpoint**: `POST /api/videos/{videoId}/comments/export-csv`

**Authentication**: Required (Laravel Sanctum or session-based auth)

**Authorization**:
- Visitors: 403 Forbidden with modal message
- Regular Members+: Check rate limit (5/hour rolling window)
- Check row limit based on role
- Administrators: No limits

**Request Parameters**:
```json
{
  "fields": ["author_channel_id", "published_at", "text", "like_count"],
  "pattern": "daytime|night|late_night|all",
  "time_points": ["2025-11-21T10:00:00", "2025-11-21T11:00:00"] // optional
}
```

**Success Response** (200 OK):
```
Content-Type: text/csv
Content-Disposition: attachment; filename="video_{videoId}_comments_{timestamp}.csv"

[CSV data with selected fields]
```

**Error Responses**:

```json
// 401 Unauthorized - Not logged in
{
  "error": {
    "type": "Unauthorized",
    "message": "請登入會員",
    "details": {
      "timestamp": "2025-11-21T14:30:00+08:00"
    }
  }
}

// 429 Too Many Requests - Rate limit exceeded
{
  "error": {
    "type": "RateLimitExceeded",
    "message": "已達到匯出次數限制 (5/5)，請稍後再試",
    "details": {
      "current_usage": 5,
      "limit": 5,
      "reset_in_minutes": 42,
      "reset_at": "2025-11-21T15:15:00+08:00",
      "trace_id": "uuid-here"
    }
  }
}

// 413 Payload Too Large - Row limit exceeded
{
  "error": {
    "type": "RowLimitExceeded",
    "message": "資料包含 2,500 筆，您的權限限制為 1,000 筆。請套用篩選條件或聯繫管理員。",
    "details": {
      "row_count": 2500,
      "row_limit": 1000,
      "role": "Regular Member",
      "trace_id": "uuid-here",
      "suggestions": [
        "套用時間篩選以減少資料量",
        "升級為高級會員以提高限制至 3,000 筆",
        "聯繫管理員申請無限制匯出"
      ]
    }
  }
}

// 404 Not Found - Video not found
{
  "error": {
    "type": "NotFound",
    "message": "找不到指定的影片",
    "details": {
      "video_id": "xyz",
      "trace_id": "uuid-here"
    }
  }
}

// 422 Unprocessable Entity - Validation error
{
  "error": {
    "type": "ValidationError",
    "message": "請求參數驗證失敗",
    "details": {
      "fields": ["至少需要選擇一個欄位"],
      "pattern": ["pattern 必須是 daytime、night、late_night 或 all 之一"]
    }
  }
}
```

**Output**: `contracts/csv-export-api.yaml` (OpenAPI 3.0 spec)

## Phase 1: Implementation Guide (quickstart.md)

**Purpose**: Developer guide for implementing and testing CSV export permission control

**Sections**:
1. **Prerequisites**: Existing member system, video analysis feature, authentication
2. **Database Setup**: Run migration for `csv_export_logs` table
3. **Service Layer**: Implement `CsvExportService` (CSV generation with row limits) and `RateLimitService` (rolling window checks)
4. **Middleware**: Implement `CheckCsvExportRateLimit` for route protection
5. **Controller**: Implement `Api/CsvExportController@export` with permission gates
6. **Frontend Integration**: Update `comment-pattern.js` to call API endpoint
7. **Testing**: Run Feature tests for permissions, rate limits, row limits
8. **Deployment**: Migration, cache clear, route cache clear

**Output**: `quickstart.md` with step-by-step implementation instructions

## Agent Context Update

After Phase 1 artifacts are generated, run:

```bash
.specify/scripts/bash/update-agent-context.sh claude
```

This will update `CLAUDE.md` with:
- New CSV export permission control feature entry
- Technology: PHP 8.2 + Laravel 12.38.1 (no new technologies added, using existing stack)
- Recent changes entry for Export CSV permission control implementation

## Next Steps

After Phase 1 completion (this plan):

1. **Phase 2**: Run `/speckit.tasks` to generate `tasks.md` - incremental task breakdown
2. **Implementation**: Execute tasks from `tasks.md` following TDD approach
3. **Testing**: Run Feature tests and Contract tests to validate implementation
4. **Integration**: Manual testing with all 5 user roles (Visitor, Regular, Paid, Website Editor, Admin)
5. **Deployment**: Apply database migration, deploy code changes

---

**Plan Status**: ✅ Ready for Phase 0 (Research) execution
**Next Command**: Agent will now proceed to Phase 0 - Generate `research.md`
