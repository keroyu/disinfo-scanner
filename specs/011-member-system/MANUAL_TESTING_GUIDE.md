# Manual Testing Guide - Member Registration System

**Feature**: 011-member-system
**Date**: 2025-11-20
**Purpose**: Step-by-step guide for manual integration testing

---

## Prerequisites

Before starting manual tests, ensure:

- [ ] Database migrations have been run: `php artisan migrate`
- [ ] Database has been seeded: `php artisan db:seed`
- [ ] Mail configuration is set in `.env` file
- [ ] Queue worker is running: `php artisan queue:work`
- [ ] Application server is running: `php artisan serve`

---

## Test 1: Complete Registration to Login Flow (T042)

### Objective
Verify the entire user journey from registration to successful login.

### Steps

#### 1.1 User Registration

**API Request:**
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "testuser@example.com"}'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "註冊成功，請檢查您的電子郵件以驗證帳號",
  "data": {
    "email": "testuser@example.com",
    "verification_sent": true,
    "expires_in_hours": 24
  }
}
```

**Verification:**
- [ ] Response status: `201 Created`
- [ ] User record created in database
- [ ] User has `is_email_verified = false`
- [ ] User has `has_default_password = true`
- [ ] User assigned `regular_member` role
- [ ] Verification token created in database

#### 1.2 Check Email Verification Token

**Database Query:**
```sql
SELECT * FROM email_verification_tokens
WHERE email = 'testuser@example.com'
ORDER BY created_at DESC
LIMIT 1;
```

**Verification:**
- [ ] Token exists
- [ ] `used_at` is NULL
- [ ] `expires_at` is 24 hours from `created_at`
- [ ] Token is hashed (64 hex characters)

#### 1.3 Attempt Login Before Verification (Should Fail)

**API Request:**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "testuser@example.com",
    "password": "123456"
  }'
```

**Expected Response:**
```json
{
  "success": false,
  "message": "請先驗證您的電子郵件"
}
```

**Verification:**
- [ ] Response status: `403 Forbidden`
- [ ] User is NOT authenticated
- [ ] Session is NOT created

#### 1.4 Verify Email

**Get Token from Database:**
```sql
SELECT token FROM email_verification_tokens
WHERE email = 'testuser@example.com'
AND used_at IS NULL;
```

**Note:** In production, the raw token would be in the email. For testing, generate a test token or extract from email logs.

**API Request:**
```bash
curl -X GET "http://localhost:8000/api/auth/verify-email?email=testuser@example.com&token={RAW_TOKEN}" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "success": true,
  "message": "電子郵件驗證成功！您現在可以登入了"
}
```

**Verification:**
- [ ] Response status: `200 OK`
- [ ] User record: `is_email_verified = true`
- [ ] User record: `email_verified_at` is set
- [ ] Token record: `used_at` is set

#### 1.5 Successful Login After Verification

**API Request:**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -c cookies.txt \
  -d '{
    "email": "testuser@example.com",
    "password": "123456",
    "remember": false
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "登入成功",
  "data": {
    "user": {
      "id": 1,
      "email": "testuser@example.com",
      "name": "testuser@example.com",
      "is_email_verified": true,
      "has_default_password": true
    }
  }
}
```

**Verification:**
- [ ] Response status: `200 OK`
- [ ] Session cookie is set
- [ ] User object returned with correct data

#### 1.6 Access Authenticated Endpoint

**API Request:**
```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Accept: application/json" \
  -b cookies.txt
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "testuser@example.com",
      "name": "testuser@example.com",
      "is_email_verified": true,
      "has_default_password": true,
      "roles": ["regular_member"]
    }
  }
}
```

**Verification:**
- [ ] Response status: `200 OK`
- [ ] User data matches authenticated user
- [ ] Roles array includes "regular_member"

#### 1.7 Logout

**API Request:**
```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Accept: application/json" \
  -b cookies.txt
```

**Expected Response:**
```json
{
  "success": true,
  "message": "登出成功"
}
```

**Verification:**
- [ ] Response status: `200 OK`
- [ ] Session is invalidated
- [ ] Accessing `/api/auth/me` returns 401

---

## Test 2: Email Delivery Verification (T043)

### Objective
Verify that emails are properly sent through SMTP.

### Prerequisites

Ensure `.env` has valid SMTP configuration:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Steps

#### 2.1 Start Queue Worker

```bash
php artisan queue:work --queue=emails,default --tries=3
```

**Verification:**
- [ ] Queue worker starts without errors
- [ ] Worker is listening for jobs

#### 2.2 Trigger Verification Email

**API Request:**
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "your-real-email@example.com"}'
```

#### 2.3 Monitor Queue Worker Output

**Expected Output:**
```
[2025-11-20 14:30:00] Processing: App\Mail\VerificationEmail
[2025-11-20 14:30:02] Processed:  App\Mail\VerificationEmail
```

**Verification:**
- [ ] Job is processed successfully
- [ ] No errors in queue worker log
- [ ] Job completes within 2 minutes

#### 2.4 Check Email Inbox

**Verification:**
- [ ] Email received within 2 minutes
- [ ] Subject: "電子郵件驗證 - DISINFO_SCANNER"
- [ ] Email contains verification link
- [ ] Link format: `http://localhost/auth/verify-email?email=...&token=...`
- [ ] Email is in Traditional Chinese
- [ ] Expiration notice: "24 小時內有效"

#### 2.5 Click Verification Link

**Verification:**
- [ ] Link redirects to verification success page
- [ ] User account is verified in database
- [ ] Token is marked as used

#### 2.6 Test Resend Functionality

**API Request:**
```bash
curl -X POST http://localhost:8000/api/auth/verify-email/resend \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "your-real-email@example.com"}'
```

**Verification:**
- [ ] Response status: `200 OK` or `400` (if already verified)
- [ ] New email received (if not already verified)

---

## Test 3: Rate Limiting Verification (T044)

### Objective
Verify that rate limiting prevents abuse (3 verification emails per hour).

### Steps

#### 3.1 Register User

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "ratelimitest@example.com"}'
```

**Verification:**
- [ ] First request succeeds (201 Created)

#### 3.2 Attempt Multiple Resends

```bash
# Second attempt
curl -X POST http://localhost:8000/api/auth/verify-email/resend \
  -H "Content-Type: application/json" \
  -d '{"email": "ratelimitest@example.com"}'

# Third attempt
curl -X POST http://localhost:8000/api/auth/verify-email/resend \
  -H "Content-Type: application/json" \
  -d '{"email": "ratelimitest@example.com"}'

# Fourth attempt (should be blocked)
curl -X POST http://localhost:8000/api/auth/verify-email/resend \
  -H "Content-Type: application/json" \
  -d '{"email": "ratelimitest@example.com"}'
```

**Expected Response (4th attempt):**
```json
{
  "success": false,
  "message": "您已達到驗證郵件發送次數上限，請稍後再試"
}
```

**Verification:**
- [ ] First 3 attempts succeed (200 OK)
- [ ] 4th attempt is blocked (429 Too Many Requests)
- [ ] Error message is in Traditional Chinese
- [ ] Database shows exactly 3 tokens within last hour

#### 3.3 Verify Rate Limit Resets

**Wait 1 hour, then:**

```bash
curl -X POST http://localhost:8000/api/auth/verify-email/resend \
  -H "Content-Type: application/json" \
  -d '{"email": "ratelimitest@example.com"}'
```

**Verification:**
- [ ] Request succeeds after 1 hour
- [ ] Rate limit counter resets

#### 3.4 Verify Per-Email Rate Limiting

Test with different email addresses:

```bash
# User 1 (already rate limited)
curl -X POST http://localhost:8000/api/auth/register \
  -d '{"email": "user1@example.com"}'  # Should be blocked

# User 2 (fresh email)
curl -X POST http://localhost:8000/api/auth/register \
  -d '{"email": "user2@example.com"}'  # Should succeed
```

**Verification:**
- [ ] User 1 is rate limited
- [ ] User 2 is NOT rate limited
- [ ] Rate limits are independent per email

---

## Test 4: Security Verification

### Objective
Verify security measures are in place.

### Steps

#### 4.1 Test Password Hashing

**Database Query:**
```sql
SELECT password FROM users WHERE email = 'testuser@example.com';
```

**Verification:**
- [ ] Password is hashed (not "123456" plaintext)
- [ ] Hash starts with "$2y$" (bcrypt)
- [ ] Hash is 60 characters long

#### 4.2 Test Token Hashing

**Database Query:**
```sql
SELECT token FROM email_verification_tokens LIMIT 1;
```

**Verification:**
- [ ] Token is hashed (SHA-256)
- [ ] Token is 64 hex characters
- [ ] Token is not the raw token sent in email

#### 4.3 Test Invalid Token Rejection

```bash
curl -X GET "http://localhost:8000/api/auth/verify-email?email=test@example.com&token=invalid123" \
  -H "Accept: application/json"
```

**Verification:**
- [ ] Response status: `400 Bad Request`
- [ ] Error message: "驗證連結無效"

#### 4.4 Test Expired Token Rejection

**Manually expire token in database:**
```sql
UPDATE email_verification_tokens
SET expires_at = NOW() - INTERVAL 1 HOUR
WHERE email = 'testuser@example.com';
```

**Then attempt verification:**

**Verification:**
- [ ] Response status: `400 Bad Request`
- [ ] Error message: "驗證連結已過期"

---

## Automated Test Execution

Run all integration tests:

```bash
# Run all integration tests
php artisan test --group=integration

# Run specific test suite
php artisan test tests/Integration/RegistrationToLoginFlowTest.php

# Run with coverage
php artisan test --coverage --min=80
```

---

## Troubleshooting

### Email not received

1. Check queue worker is running:
   ```bash
   ps aux | grep "queue:work"
   ```

2. Check failed jobs:
   ```bash
   php artisan queue:failed
   ```

3. Check mail logs:
   ```bash
   tail -f storage/logs/laravel.log | grep "MAIL"
   ```

4. Test email configuration:
   ```bash
   php artisan tinker
   >>> Mail::raw('Test', function($m) { $m->to('your-email@example.com'); });
   ```

### Rate limiting issues

1. Clear rate limit cache:
   ```bash
   php artisan cache:clear
   ```

2. Check token count:
   ```sql
   SELECT COUNT(*) FROM email_verification_tokens
   WHERE email = 'test@example.com'
   AND created_at >= NOW() - INTERVAL 1 HOUR;
   ```

### Authentication issues

1. Clear sessions:
   ```bash
   php artisan session:clear
   ```

2. Verify user is verified:
   ```sql
   SELECT email, is_email_verified, email_verified_at
   FROM users
   WHERE email = 'test@example.com';
   ```

---

## Test Completion Checklist

- [ ] T042: Complete registration-to-login flow tested manually
- [ ] T043: Email delivery verified with real SMTP
- [ ] T044: Rate limiting verified (3 per hour enforced)
- [ ] All automated integration tests pass
- [ ] Security measures verified
- [ ] No errors in application logs

---

**Status**: Ready for Phase 4 (User Story 2 - Password Management)
**Last Updated**: 2025-11-20
