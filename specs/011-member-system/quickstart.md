# Quickstart Guide: Member Registration System

**Feature**: 011-member-system
**Version**: 1.0.0
**Date**: 2025-11-20

## Prerequisites

- PHP 8.2+
- Composer
- MySQL/MariaDB 5.7+/10.3+
- Laravel 12.38.1 (already installed)
- Email service configured (SMTP)

## Setup Steps

### 1. Environment Configuration

Update `.env` file with email service credentials:

```env
# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# App Timezone (display only, database stores UTC)
APP_TIMEZONE=Asia/Taipei

# Default Admin Credentials (will be seeded)
ADMIN_EMAIL=themustbig@gmail.com
ADMIN_PASSWORD=2025Nov20
```

### 2. Install Dependencies

```bash
# Already installed, but verify:
composer install
```

### 3. Run Database Migrations

Execute migrations in order:

```bash
php artisan migrate

# Migrations will create:
# - Modified users table
# - roles table
# - permissions table
# - role_user pivot table
# - permission_role pivot table
# - email_verification_tokens table
# - api_quotas table
# - identity_verifications table
# - Seeded admin account
```

### 4. Seed Default Data

```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=AdminUserSeeder
```

This seeds:
- 5 roles: visitor, regular_member, paid_member, website_editor, administrator
- Permissions for pages, features, and actions
- Admin account: themustbig@gmail.com / 2025Nov20

### 5. Configure Queue Worker (For Async Email Sending)

Start queue worker in background:

```bash
php artisan queue:work --queue=emails,default --tries=3 &
```

For production, use supervisor or Laravel Horizon.

### 6. Configure Scheduled Tasks

Add to cron (for monthly API quota reset and token cleanup):

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks defined in `app/Console/Kernel.php`:
- Daily: Clean expired verification tokens
- Monthly (1st): Reset API quotas

---

## Testing the System

### 1. Test Admin Login

Visit: `http://localhost/login`

Credentials:
- Email: `themustbig@gmail.com`
- Password: `2025Nov20`

Expected: Login successful, redirect to admin dashboard.

### 2. Test User Registration

Visit: `http://localhost/register`

1. Enter a test email
2. Check email inbox for verification link
3. Click verification link
4. Login with default password `123456`
5. System forces password change

### 3. Test Password Reset

Visit: `http://localhost/password-reset`

1. Enter registered email
2. Check email inbox for reset link
3. Click reset link
4. Enter new password (must meet strength requirements)
5. Login with new password

### 4. Test Role-Based Access

As Regular Member:
- Try to access `/admin` → See modal: "需升級為付費會員"
- Try to use "Official API Import" → See modal: "需升級為付費會員"

As Administrator:
- Visit `/admin/users` → See user list
- Change a user's role → Success

### 5. Test API Quota

As Paid Member:
1. Use "Official API Import" 10 times
2. On 11th attempt → See error: "您已達到本月配額上限 (10/10)"
3. Submit identity verification
4. Admin approves verification
5. Try import again → Success (unlimited)

---

## Running Tests

### Run All Tests

```bash
php artisan test
```

### Run Specific Test Suites

```bash
# Feature tests only
php artisan test --testsuite=Feature

# Unit tests only
php artisan test --testsuite=Unit

# Specific test file
php artisan test tests/Feature/Auth/RegistrationTest.php

# With coverage report
php artisan test --coverage
```

### Test Database

Tests use SQLite in-memory database (configured in `phpunit.xml`):

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

---

## API Usage Examples

### Register New User

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "newuser@example.com"
  }'
```

Response:
```json
{
  "success": true,
  "message": "註冊成功，請檢查您的電子郵件以驗證帳號",
  "data": {
    "email": "newuser@example.com",
    "verification_sent": true,
    "expires_in_hours": 24
  }
}
```

### Login

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "newuser@example.com",
    "password": "SecurePass123!",
    "remember": true
  }'
```

### Get User Permissions

```bash
curl -X GET http://localhost/api/user/permissions \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {your_token}"
```

### Admin: List All Users

```bash
curl -X GET "http://localhost/api/admin/users?page=1&per_page=20" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {admin_token}"
```

### Admin: Change User Role

```bash
curl -X PUT http://localhost/api/admin/users/1/role \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {admin_token}" \
  -d '{
    "role": "paid_member"
  }'
```

---

## Frontend Integration

### Permission Modal Component

Include in your Blade templates:

```blade
<x-permission-modal
    message="請登入會員"
    :show="!auth()->check()"
/>
```

### Upgrade Button Component

For Regular Members:

```blade
@if(auth()->user()->hasRole('regular_member'))
    <x-upgrade-button />
@endif
```

### Check Permissions in Blade

```blade
@can('use_official_api_import')
    <button>Official API Import</button>
@else
    <button disabled>Official API Import (需升級為付費會員)</button>
@endcan
```

### Middleware Protection

In routes:

```php
Route::middleware(['auth', 'role:paid_member'])->group(function () {
    Route::post('/api/official-import', [ImportController::class, 'officialImport']);
});
```

---

## Troubleshooting

### Emails Not Sending

Check:
1. `.env` mail configuration is correct
2. Queue worker is running: `ps aux | grep "queue:work"`
3. Check logs: `tail -f storage/logs/laravel.log`
4. Test email config: `php artisan tinker` then `Mail::raw('Test', fn($m) => $m->to('test@example.com'));`

### Password Validation Failing

Ensure password meets all requirements:
- Minimum 8 characters
- At least 1 uppercase letter (A-Z)
- At least 1 lowercase letter (a-z)
- At least 1 number (0-9)
- At least 1 special character (!@#$%^&*...)

Valid example: `SecurePass123!`

### Rate Limiting Errors

If you see "Too Many Requests":
- Wait 1 hour (rate limit resets)
- Or clear rate limits: `php artisan cache:clear`
- Or disable rate limiting in tests (use `RateLimiter::clear()`)

### Timezone Display Issues

Ensure:
1. Database stores timestamps in UTC (automatic with Laravel migrations)
2. `APP_TIMEZONE=Asia/Taipei` in `.env`
3. Blade templates use: `{{ $user->created_at->timezone('Asia/Taipei')->format('Y-m-d H:i (T)') }}`

### Migration Errors

If foreign key constraint fails:
1. Check migration order (see `data-model.md`)
2. Drop all tables: `php artisan migrate:fresh`
3. Re-run migrations: `php artisan migrate`

---

## Development Workflow

### 1. Test-First Development (TDD)

```bash
# 1. Write failing test
php artisan make:test Auth/NewFeatureTest

# 2. Run test (should fail)
php artisan test tests/Feature/Auth/NewFeatureTest.php

# 3. Implement feature code

# 4. Run test (should pass)
php artisan test tests/Feature/Auth/NewFeatureTest.php
```

### 2. Create New Controller

```bash
php artisan make:controller Auth/CustomController
```

### 3. Create New Model

```bash
php artisan make:model CustomModel -m  # -m creates migration
```

### 4. Create New Migration

```bash
php artisan make:migration add_column_to_users_table
```

### 5. Create New Middleware

```bash
php artisan make:middleware CheckCustomPermission
```

---

## Monitoring & Observability

### View Logs

```bash
# Real-time logs
tail -f storage/logs/laravel.log

# Filter security events
tail -f storage/logs/laravel.log | grep "SECURITY"
```

### Check Queue Jobs

```bash
# List failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {job_id}

# Clear all failed jobs
php artisan queue:flush
```

### Database Queries

Monitor slow queries in production (add to `AppServiceProvider`):

```php
DB::listen(function ($query) {
    if ($query->time > 1000) { // Log queries > 1 second
        Log::warning('Slow query', [
            'sql' => $query->sql,
            'time' => $query->time
        ]);
    }
});
```

---

## Production Deployment Checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure production email service (SendGrid, AWS SES, etc.)
- [ ] Set up queue worker with supervisor/Horizon
- [ ] Configure cron for scheduled tasks
- [ ] Enable HTTPS (all auth endpoints)
- [ ] Set secure session/cookie configuration
- [ ] Configure database backups
- [ ] Set up error monitoring (Sentry, Bugsnag)
- [ ] Enable log rotation
- [ ] Test email delivery in production
- [ ] Test rate limiting in production
- [ ] Verify timezone display is correct (GMT+8)
- [ ] Seed production admin account securely

---

## Support & Documentation

- Laravel Docs: https://laravel.com/docs/12.x
- Feature Spec: [spec.md](./spec.md)
- Data Model: [data-model.md](./data-model.md)
- API Contracts: [contracts/](./contracts/)
- Research Decisions: [research.md](./research.md)

---

**Status**: Ready for development
**Last Updated**: 2025-11-20
