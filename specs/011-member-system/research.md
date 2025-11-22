# Research: Member Registration System

**Feature**: 011-member-system
**Date**: 2025-11-20
**Status**: Complete

## Overview

This document captures research decisions for implementing a Laravel-based member registration system with email verification, role-based access control, and API quota management.

## Research Questions & Decisions

### 1. Email Service Provider Selection

**Decision**: Use Laravel's built-in Mail facade with SMTP configuration (existing in project)

**Rationale**:
- Laravel 12 has robust mail handling with queue support for async sending
- Project already has `.env` file with mail configuration
- SMTP is provider-agnostic (can use Gmail, SendGrid, AWS SES, Mailgun, etc.)
- Queue workers handle retry logic automatically
- Mailable classes provide testable email templates

**Alternatives Considered**:
- **Direct SMTP integration**: Rejected because Laravel Mail already abstracts this with better error handling
- **Third-party SDK (SendGrid/Mailgun)**: Rejected because SMTP config is more portable and doesn't lock into vendor
- **Custom email queue**: Rejected because Laravel's queue system already solves this problem

**Implementation Notes**:
- Use Laravel's `Mailable` classes for verification and password reset emails
- Configure queue driver (database or Redis) for async email sending
- Email templates in `resources/views/emails/` using Blade syntax
- Rate limiting via Laravel's `RateLimiter` facade

---

### 2. Password Hashing Strategy

**Decision**: Use Laravel's default bcrypt hashing via `Hash` facade

**Rationale**:
- bcrypt is Laravel's battle-tested default since v4
- Cost factor of 10 (Laravel default) provides good security/performance balance
- Laravel automatically uses bcrypt when calling `Hash::make()`
- Compatible with existing `password_reset_tokens` table structure

**Alternatives Considered**:
- **Argon2**: Rejected because bcrypt is sufficient for this use case and Laravel doesn't default to Argon2 in config
- **Custom salt management**: Rejected because Laravel handles salts automatically in bcrypt
- **Plain SHA-256**: Rejected due to insufficient security (no salt, too fast)

**Implementation Notes**:
- Password validation rules: min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special character
- Use `Hash::make($password)` for storing passwords
- Use `Hash::check($input, $hashedPassword)` for verification
- Never log or expose password hashes

---

### 3. Role-Based Access Control (RBAC) Implementation

**Decision**: Custom role-permission system using pivot tables (not Spatie Permission package)

**Rationale**:
- Requirements are straightforward: 5 roles with clear permission boundaries
- Custom implementation avoids package dependency and version lock-in
- Easier to customize for domain-specific needs (API quotas, identity verification)
- Direct database queries faster than package abstraction layer
- Follows Laravel conventions: `roles`, `permissions`, `role_user`, `permission_role` tables

**Alternatives Considered**:
- **Spatie Laravel-Permission**: Rejected because it adds complexity for simple role system
- **Laravel Gates only**: Rejected because we need persistent role assignments in database
- **Single `role` enum on users table**: Rejected because doesn't support role-specific data (quotas, verification)

**Implementation Notes**:
- `roles` table: id, name (visitor, regular_member, premium_Member, website_editor, administrator)
- `role_user` pivot: user_id, role_id, assigned_at
- Middleware `CheckUserRole` for route protection
- Role checking via `$user->hasRole('premium_Member')` helper method
- Permission modals implemented in Blade components with Alpine.js for interactivity

---

### 4. Email Verification Token Storage

**Decision**: Separate `email_verification_tokens` table with expiration tracking

**Rationale**:
- Separation of concerns: authentication tokens separate from password reset tokens
- Allows independent expiration policies (24 hours for both, but may diverge)
- Supports multiple pending verifications (e.g., user requests resend)
- Easy to query for cleanup of expired tokens

**Alternatives Considered**:
- **Reuse password_reset_tokens table**: Rejected due to semantic confusion and different expiration policies
- **Store tokens in users table**: Rejected because doesn't support multiple pending tokens
- **Use signed URLs only**: Rejected because can't track usage/expiration in database

**Implementation Notes**:
- Table: `email_verification_tokens` (email, token, created_at, used_at)
- Token generation: `Str::random(64)` hashed with SHA-256
- Expiration check: `created_at < now() - 24 hours`
- Unique constraint on `token` column
- Cleanup job runs daily to purge expired tokens

---

### 5. API Quota Tracking Mechanism

**Decision**: Dedicated `api_quotas` table with monthly reset logic

**Rationale**:
- Supports per-user quota tracking with historical data
- Reset logic based on calendar month (1st of month)
- Identity verification flag determines unlimited access
- Can query quota usage for reporting/analytics

**Alternatives Considered**:
- **Redis counter with TTL**: Rejected because loses historical data and harder to debug
- **Store in user record as JSON**: Rejected because difficult to query aggregate usage
- **Rate limiting middleware only**: Rejected because doesn't persist quota across requests

**Implementation Notes**:
- Table: `api_quotas` (user_id, month, usage_count, limit, identity_verified)
- Increment logic: `ApiQuotaService::incrementUsage($userId)`
- Monthly reset: scheduled job on 1st of month sets usage_count to 0
- Check before API call: `$quota->usage_count < $quota->limit || $quota->identity_verified`
- Display in UI: "X/10 imports this month" or "Unlimited (verified)"

---

### 6. Timezone Handling Strategy

**Decision**: Store UTC in database, convert to GMT+8 in presentation layer using Carbon

**Rationale**:
- Aligns with Constitution Principle VI: Timezone Consistency
- Laravel's Carbon library handles timezone conversion seamlessly
- MySQL `timestamp` columns store UTC automatically
- Single source of truth prevents timezone bugs in queries

**Alternatives Considered**:
- **Store GMT+8 in database**: Rejected because violates constitution and causes query problems
- **Client-side timezone detection**: Rejected because adds JavaScript complexity
- **Store both UTC and display timezone**: Rejected as redundant and error-prone

**Implementation Notes**:
- Database: All `timestamp` columns (created_at, updated_at, expires_at) in UTC
- Models: Use `$dates` property to auto-cast to Carbon instances
- Blade templates: `{{ $user->created_at->timezone('Asia/Taipei')->format('Y-m-d H:i (T)') }}`
- Display format: "2025-11-20 14:30 (GMT+8)" with explicit timezone indicator
- Carbon config: `config/app.php` sets default timezone to 'Asia/Taipei' for display only

---

### 7. Modal Dialog Implementation for Permission Denials

**Decision**: Blade component with Alpine.js for modal interactivity

**Rationale**:
- Alpine.js is lightweight (15KB) and integrates well with Laravel
- Blade components provide reusable modal template
- No React/Vue complexity needed for simple modal
- Server-side rendering with progressive enhancement

**Alternatives Considered**:
- **Bootstrap modals**: Rejected because project may not use Bootstrap (check dependencies)
- **Vue.js component**: Rejected as overkill for simple modal
- **Full page redirects**: Rejected because poor UX compared to modal

**Implementation Notes**:
- Component: `resources/views/components/permission-modal.blade.php`
- Props: `message` (請登入會員 or 需升級為高級會員), `show` (boolean)
- Alpine.js directive: `x-show="open"` for show/hide
- Triggered by: Button click with permission check in Blade `@can` directive
- Close button: `@click="open = false"`

---

### 8. Rate Limiting Implementation

**Decision**: Laravel's built-in RateLimiter with database-backed cache

**Rationale**:
- Laravel's `RateLimiter` facade provides simple API
- Supports per-user rate limiting out of the box
- Database cache persists limits across requests
- Configurable decay time (1 hour for 3 attempts)

**Alternatives Considered**:
- **Redis-based rate limiting**: Rejected because adds Redis dependency (project may not have it)
- **Custom rate limiting logic**: Rejected because Laravel's implementation is battle-tested
- **Middleware only**: Rejected because service layer also needs rate checking

**Implementation Notes**:
- Configuration in `routes/web.php` or service method
- Format: `RateLimiter::attempt($key, 3, fn() => true, 3600)`
- Key format: `email_verification:{$email}` or `password_reset:{$email}`
- Error response: 429 Too Many Requests with retry-after header
- Display to user: "Too many requests. Please wait 1 hour before trying again."

---

### 9. Testing Strategy

**Decision**: Feature tests for flows, unit tests for logic, contract tests for boundaries

**Rationale**:
- Aligns with Constitution Principle I: Test-First Development
- Feature tests validate end-to-end user journeys (registration → email → login)
- Unit tests validate password rules, token expiration, quota calculation
- Contract tests ensure email service and role permission boundaries stable

**Alternatives Considered**:
- **End-to-end browser tests (Dusk)**: Deferred to later phase, feature tests sufficient for MVP
- **Only unit tests**: Rejected because doesn't validate integration points
- **Manual testing only**: Rejected as violates TDD principle

**Implementation Notes**:
- PHPUnit configuration in `phpunit.xml`
- Feature tests: `php artisan test --filter=Feature`
- Coverage target: 80%+ for critical authentication paths
- Mock email sending in tests using `Mail::fake()`
- Test database: SQLite in-memory for speed
- Factories for test data generation

---

## Technology Stack Summary

| Component | Technology | Version | Rationale |
|-----------|-----------|---------|-----------|
| Framework | Laravel | 12.38.1 | Existing project framework |
| Language | PHP | 8.2 | Laravel 12 requirement |
| Database | MySQL/MariaDB | 5.7+/10.3+ | Existing project database |
| Email | SMTP (Laravel Mail) | N/A | Provider-agnostic, queue support |
| Hashing | bcrypt | N/A | Laravel default, secure |
| Frontend | Blade + Alpine.js | 3.x | Lightweight, no build step |
| Testing | PHPUnit | 10.x | Laravel's testing framework |
| Queue | Database/Redis | N/A | Async email sending |
| Rate Limiting | Laravel RateLimiter | N/A | Built-in, database-backed |

---

## Open Questions / Future Enhancements

These items are out of scope for MVP but noted for future consideration:

1. **Two-Factor Authentication (2FA)**: Not required by spec, but common for security
2. **Social Login (OAuth)**: Not mentioned in requirements
3. **Account Deletion/GDPR**: Edge case noted but not specified
4. **Password History**: Prevent password reuse (not required)
5. **Login Attempt Lockout**: Rate limiting covers this but could be more sophisticated
6. **Session Management UI**: Show active sessions, allow remote logout (nice-to-have)
7. **Email Template Customization**: Admin UI to edit email templates (future)
8. **Audit Log UI**: Display security event logs to admins (observation exists, UI doesn't)

---

## References

- Laravel 12 Documentation: https://laravel.com/docs/12.x
- Laravel Mail: https://laravel.com/docs/12.x/mail
- Laravel Authentication: https://laravel.com/docs/12.x/authentication
- Laravel Authorization: https://laravel.com/docs/12.x/authorization
- Carbon Timezone Handling: https://carbon.nesbot.com/docs/#api-timezone
- Alpine.js Documentation: https://alpinejs.dev/

---

**Status**: All NEEDS CLARIFICATION items resolved. Ready for Phase 1 (Design & Contracts).

---

# ADDENDUM: CSV Export Permission Control Research

**Date**: 2025-11-21
**Context**: Incremental update - Adding permission control, rate limiting, and row limits to existing Video Analysis CSV export feature
**Related Spec Section**: Session 2025-11-21 clarifications (Export CSV permissions)

## Overview

This addendum documents technical decisions for implementing role-based permission control on the existing client-side CSV export feature in the Video Analysis page. Key additions: authentication gates, rate limiting (5 exports/hour rolling window), and row limits (1,000/3,000/unlimited based on role).

---

## Decision 10: Rate Limiting Strategy for CSV Exports

### Research Question
How to implement 60-minute rolling window rate limiting in Laravel with per-user tracking for CSV export feature?

### Options Evaluated

**Option A: Laravel's Built-in RateLimiter (Cache-backed)**
- **Pros**: Native Laravel support, simple API (`RateLimiter::attempt()`), automatic cleanup
- **Cons**: Not designed for exact rolling windows (uses fixed time buckets), cache clearing loses rate limit state, lacks audit trail
- **Performance**: <10ms per check (Redis/Memcached)
- **Accuracy**: Good for fixed windows, poor for rolling windows (resets at bucket boundaries)

**Option B: Database-backed Custom Rate Limiter**
- **Pros**: Precise rolling window (query last 60 minutes), permanent audit trail, survives cache clearing, supports compliance logging
- **Cons**: Slower than cache (~30-50ms per check), requires manual cleanup of old logs
- **Performance**: 30-50ms per check with indexed queries
- **Accuracy**: Exact to the second for rolling windows

**Option C: Hybrid (Cache + Database)**
- **Pros**: Fast checks (cache), fallback to DB for accuracy, audit trail preserved
- **Cons**: Complex synchronization, cache invalidation challenges, increased implementation complexity
- **Performance**: 10-50ms depending on cache hit/miss
- **Accuracy**: High (DB source of truth)

### Decision: **Option B - Database-backed Custom Rate Limiter**

**Rationale**:
1. **Accuracy**: Spec requires rolling window (e.g., first export at 10:15 → can export again at 11:15). Laravel's built-in RateLimiter uses fixed time buckets which would allow "gaming" by exporting at 10:59 and 11:00 (2 exports in 1 minute across different buckets).

2. **Audit Trail**: Constitution Principle III (Observable Systems) requires logging all operations with trace IDs. Database solution provides permanent audit trail for security analysis.

3. **Performance Acceptable**: 30-50ms for rate limit check is well within 2-second target for total CSV export response time (including DB query, CSV generation, network transfer).

4. **Simplicity**: Avoids cache synchronization complexity while meeting all requirements.

**Implementation Approach**:
- Create `csv_export_logs` table with `(user_id, exported_at)` composite index
- On each export attempt: `SELECT COUNT(*) FROM csv_export_logs WHERE user_id = ? AND exported_at >= NOW() - INTERVAL 60 MINUTE AND status = 'success'`
- If count >= 5: reject with 429 status
- If allowed: insert new log entry after successful export
- Cleanup: Daily cron job to delete logs older than 7 days (audit retention, not needed for rate limiting)

**Rolling Window Calculation**: Fixed window from first export (matches spec clarification). Track first export timestamp, window resets exactly 60 minutes later for all 5 slots.

```php
class RateLimitService
{
    public function checkLimit(User $user): void
    {
        $windowStart = now()->subMinutes(60);

        $exports = CsvExportLog::where('user_id', $user->id)
            ->where('exported_at', '>=', $windowStart)
            ->where('status', 'success')
            ->orderBy('exported_at', 'asc')
            ->get();

        if ($exports->count() >= 5) {
            $firstExport = $exports->first()->exported_at;
            $resetAt = $firstExport->copy()->addMinutes(60);
            $resetInMinutes = now()->diffInMinutes($resetAt, false);

            throw new CsvExportRateLimitException(
                currentUsage: 5,
                limit: 5,
                resetAt: $resetAt,
                resetInMinutes: max(0, $resetInMinutes)
            );
        }
    }
}
```

---

## Decision 11: CSV Generation Performance

### Research Question
How to efficiently generate CSV files with row limits (up to 3,000 rows) while maintaining <2 second response time and <200MB memory usage?

### Options Evaluated

**Option A: Laravel Response::streamDownload() with Generator**
- **Memory**: O(1) - constant memory (~10MB regardless of row count)
- **Performance**: Streams data as generated, good for large datasets
- **Code Complexity**: Medium (requires generator pattern)
- **Laravel Support**: Native `Response::streamDownload()`

**Option B: Collection->toCsv() with In-Memory Buffer**
- **Memory**: O(n) - scales with row count (~50-100MB for 3,000 rows)
- **Performance**: Faster for small datasets (<1000 rows)
- **Code Complexity**: Low (simple fluent API)
- **Laravel Support**: Requires League\Csv package

**Option C: Temporary File with fputcsv()**
- **Memory**: O(1) - constant (file buffering)
- **Performance**: Fast writes, disk I/O overhead
- **Code Complexity**: Medium (file management, cleanup)
- **Laravel Support**: Native PHP functions

### Decision: **Option A - Laravel Response::streamDownload() with Generator**

**Rationale**:
1. **Memory Efficiency**: O(1) memory usage ensures well under 200MB limit even for unlimited administrator exports

2. **Scalability**: If row limits increase in future (e.g., Premium Members upgraded to 10,000 rows), streaming handles it without code changes

3. **Laravel Integration**: `Response::streamDownload()` is native Laravel, no external dependencies

4. **Performance**: For 3,000 rows, streaming adds ~100-200ms overhead vs in-memory, but total response time still <2 seconds:
   - DB query: 200-500ms (indexed query on comments table)
   - CSV generation (streaming): 300-500ms
   - Network transfer: 200-500ms (client-dependent)
   - **Total**: 700ms - 1,500ms (within 2s target)

**Implementation Approach**:
```php
public function export(Request $request, string $videoId)
{
    // ... permission checks, rate limiting ...

    $comments = $this->getComments($videoId, $request->pattern, $request->time_points);

    return Response::streamDownload(function () use ($comments, $request) {
        $handle = fopen('php://output', 'w');

        // Write CSV header
        fputcsv($handle, $this->getHeaderRow($request->fields));

        // Stream rows using generator
        foreach ($this->generateCsvRows($comments, $request->fields) as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }, "video_{$videoId}_comments_" . now()->format('Ymd_His') . ".csv", [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
}

private function generateCsvRows($comments, $fields): \Generator
{
    foreach ($comments->cursor() as $comment) { // cursor() for memory efficiency
        yield $this->formatRow($comment, $fields);
    }
}
```

---

## Decision 12: Permission Check Architecture

### Research Question
Where should role-based permission checks be implemented - middleware, controller, or service layer?

### Decision: **Hybrid (Middleware for Auth + Controller for Authorization)**

**Rationale**:
1. **Separation of Concerns**:
   - **Middleware**: Authentication (`auth:sanctum`) - ensures user logged in
   - **Controller**: Authorization (role checks) and rate limiting logic
   - **Service**: Business logic (CSV generation, row limit enforcement)

2. **Consistency**: Existing codebase uses `auth:sanctum` middleware for API routes with permission checks in controllers

3. **Testability**:
   - Middleware testable via HTTP tests
   - Controller authorization testable via Feature tests
   - Service business logic testable via Unit tests

4. **Granularity**: Controller can access request parameters (fields, pattern, time_points) for context-aware authorization

**Implementation Approach**:
```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/videos/{videoId}/comments/export-csv', [CsvExportController::class, 'export']);
});

// App\Http\Controllers\Api\CsvExportController
public function export(CsvExportRequest $request, string $videoId)
{
    // 1. Authentication already enforced by middleware

    // 2. Authorization: Require authenticated user
    if (!$request->user()) {
        return response()->json([
            'error' => [
                'type' => 'Unauthorized',
                'message' => '請登入會員',
                'details' => ['timestamp' => now('Asia/Taipei')->toIso8601String()]
            ]
        ], 401);
    }

    // 3. Rate limiting check (administrators bypass)
    if (!$request->user()->hasRole('administrator')) {
        $this->rateLimitService->checkLimit($request->user());
    }

    // 4. Row limit check (administrators bypass)
    $comments = $this->getComments($videoId, $request->pattern, $request->time_points);
    $rowLimit = $this->getRowLimitForRole($request->user());

    if ($rowLimit && $comments->count() > $rowLimit) {
        throw new CsvExportRowLimitException($comments->count(), $rowLimit, $request->user()->roles->first()->name);
    }

    // 5. Generate CSV (service layer)
    return $this->csvExportService->generate($comments, $request->fields);
}
```

---

## Decision 13: Error Response Format

### Research Question
What JSON structure for rate limit and row limit errors to ensure observability and actionable feedback?

### Decision: **Extend Existing Pattern with Limit-Specific Fields**

**Existing Pattern** (from `VideoAnalysisController`):
```json
{
  "error": {
    "type": "NotFound|DatabaseQueryException|InternalServerError",
    "message": "Human-readable message",
    "details": {
      "trace_id": "uuid",
      "timestamp": "ISO8601 with GMT+8",
      ...additional context
    }
  }
}
```

**New Format for CSV Export Errors**:

```json
// Rate Limit Exceeded (429)
{
  "error": {
    "type": "RateLimitExceeded",
    "message": "已達到匯出次數限制 (5/5)，請稍後再試",
    "details": {
      "current_usage": 5,
      "limit": 5,
      "reset_in_minutes": 42,
      "reset_at": "2025-11-21T15:15:00+08:00",
      "trace_id": "550e8400-e29b-41d4-a716-446655440000",
      "timestamp": "2025-11-21T14:33:00+08:00"
    }
  }
}

// Row Limit Exceeded (413)
{
  "error": {
    "type": "RowLimitExceeded",
    "message": "資料包含 2,500 筆，您的權限限制為 1,000 筆。請套用篩選條件或聯繫管理員。",
    "details": {
      "row_count": 2500,
      "row_limit": 1000,
      "role": "Regular Member",
      "trace_id": "550e8400-e29b-41d4-a716-446655440001",
      "timestamp": "2025-11-21T14:33:00+08:00",
      "suggestions": [
        "套用時間篩選以減少資料量",
        "升級為高級會員以提高限制至 3,000 筆",
        "聯繫管理員申請無限制匯出"
      ]
    }
  }
}
```

**Rationale**:
1. **Consistency**: Matches existing `VideoAnalysisController` error format
2. **Actionable**: `reset_in_minutes`/`reset_at` for rate limits, `suggestions` array for row limits
3. **Observability**: `trace_id` enables log correlation per Constitution Principle III
4. **Frontend Integration**: JavaScript can parse `error.type` to display appropriate modal

---

## Technology Stack (No New Dependencies)

| Component | Technology | Notes |
|-----------|-----------|-------|
| Rate Limiting | Custom DB implementation | Using existing MySQL |
| CSV Generation | Laravel `Response::streamDownload()` | Built-in |
| Authentication | Laravel Sanctum / Session | Existing |
| Logging | Laravel Log facade | Existing |
| Testing | PHPUnit | Existing |

### Performance Budget (2-second target)

| Operation | Budget | Implementation |
|-----------|--------|----------------|
| Rate limit check | 50ms | Indexed DB query (`idx_user_exported_at`) |
| Permission & role check | 10ms | In-memory (eager-loaded roles) |
| Comment query | 500ms | Indexed query on `comments` table |
| Row count check | 10ms | `COUNT()` on filtered query |
| CSV generation | 500ms | Streaming generator (3,000 rows) |
| Network transfer | 200-500ms | Client-dependent |
| **Total** | **1,270-1,570ms** | **✅ Within 2s** |

---

## Security Considerations

1. **Rate Limit Bypass Prevention**: Rate limit is per-user (user_id), not per IP. Multiple accounts require email verification (existing defense).

2. **Row Limit Bypass Prevention**: Row count check performed server-side after query execution, before CSV generation. Frontend parameters not trusted.

3. **Data Exposure**: `auth:sanctum` middleware enforces authentication. Unauthenticated requests receive 401 before any data queries.

4. **Audit Trail**: Every export attempt logged to `csv_export_logs` with trace_id. Failed attempts (rate limited, row limited) logged for security analysis.

---

## References

- Laravel HTTP Responses (Streaming): https://laravel.com/docs/12.x/responses#streamed-downloads
- Laravel Rate Limiting: https://laravel.com/docs/12.x/rate-limiting
- PHP Generators: https://www.php.net/manual/en/language.generators.overview.php
- Constitution Principle III (Observable Systems): `.specify/memory/constitution.md`
- Constitution Principle VI (Timezone Consistency): `.specify/memory/constitution.md`

---

**Status**: All CSV export technical decisions documented. Ready for Phase 1 (Data Model & API Contract generation).
