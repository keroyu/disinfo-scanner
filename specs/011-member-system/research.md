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
- `roles` table: id, name (visitor, regular_member, paid_member, website_editor, administrator)
- `role_user` pivot: user_id, role_id, assigned_at
- Middleware `CheckUserRole` for route protection
- Role checking via `$user->hasRole('paid_member')` helper method
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
- Props: `message` (請登入會員 or 需升級為付費會員), `show` (boolean)
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
