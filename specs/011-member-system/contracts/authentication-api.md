# Authentication API Contract

**Feature**: 011-member-system
**Version**: 1.0.0
**Date**: 2025-11-20

## Overview

This document defines the HTTP API contracts for user authentication operations including registration, login, email verification, and password management.

---

## 1. User Registration

### Endpoint
```
POST /api/auth/register
```

### Request

**Headers**:
```
Content-Type: application/json
Accept: application/json
X-Requested-With: XMLHttpRequest
```

**Body**:
```json
{
  "email": "user@example.com"
}
```

**Validation Rules**:
- `email`: Required, valid email format, unique in users table

### Response

**Success (201 Created)**:
```json
{
  "success": true,
  "message": "註冊成功，請檢查您的電子郵件以驗證帳號",
  "data": {
    "email": "user@example.com",
    "verification_sent": true,
    "expires_in_hours": 24
  }
}
```

**Error (422 Unprocessable Entity)**:
```json
{
  "success": false,
  "message": "驗證失敗",
  "errors": {
    "email": [
      "電子郵件已被使用"
    ]
  }
}
```

**Error (429 Too Many Requests)**:
```json
{
  "success": false,
  "message": "請求過於頻繁，請稍後再試",
  "retry_after": 3600
}
```

---

## 2. Email Verification

### Endpoint
```
GET /api/auth/verify-email/{token}
```

### Request

**URL Parameters**:
- `token`: Email verification token (64 characters, alphanumeric)

**Headers**:
```
Accept: application/json
```

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "message": "電子郵件驗證成功，您現在可以登入",
  "data": {
    "email": "user@example.com",
    "verified_at": "2025-11-20T06:30:00Z",
    "default_password": "123456"
  }
}
```

**Error (404 Not Found)**:
```json
{
  "success": false,
  "message": "無效或已過期的驗證連結"
}
```

**Error (410 Gone - Already Verified)**:
```json
{
  "success": false,
  "message": "此帳號已經驗證完成"
}
```

---

## 3. Resend Verification Email

### Endpoint
```
POST /api/auth/resend-verification
```

### Request

**Headers**:
```
Content-Type: application/json
Accept: application/json
```

**Body**:
```json
{
  "email": "user@example.com"
}
```

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "message": "驗證郵件已重新發送",
  "data": {
    "email": "user@example.com",
    "expires_in_hours": 24
  }
}
```

**Error (429 Too Many Requests)**:
```json
{
  "success": false,
  "message": "請求過於頻繁，請 1 小時後再試",
  "retry_after": 3600
}
```

---

## 4. User Login

### Endpoint
```
POST /api/auth/login
```

### Request

**Headers**:
```
Content-Type: application/json
Accept: application/json
X-Requested-With: XMLHttpRequest
```

**Body**:
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!",
  "remember": true
}
```

**Validation Rules**:
- `email`: Required, valid email format
- `password`: Required, string
- `remember`: Optional, boolean

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "message": "登入成功",
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "User Name",
      "roles": ["regular_member"],
      "has_default_password": false
    },
    "token": "session_token_here"
  }
}
```

**Success with Mandatory Password Change (200 OK)**:
```json
{
  "success": true,
  "message": "請立即更改您的預設密碼",
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "User Name",
      "roles": ["regular_member"],
      "has_default_password": true
    },
    "requires_password_change": true,
    "redirect_to": "/auth/change-password"
  }
}
```

**Error (401 Unauthorized)**:
```json
{
  "success": false,
  "message": "電子郵件或密碼不正確"
}
```

**Error (403 Forbidden - Email Not Verified)**:
```json
{
  "success": false,
  "message": "請先驗證您的電子郵件",
  "data": {
    "email_verified": false,
    "can_resend_verification": true
  }
}
```

---

## 5. Mandatory Password Change

### Endpoint
```
POST /api/auth/change-password
```

### Request

**Headers**:
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
```

**Body**:
```json
{
  "current_password": "123456",
  "new_password": "NewSecure123!",
  "new_password_confirmation": "NewSecure123!"
}
```

**Validation Rules**:
- `current_password`: Required, must match user's current password
- `new_password`: Required, min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
- `new_password_confirmation`: Required, must match new_password

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "message": "密碼已成功更改",
  "data": {
    "password_changed_at": "2025-11-20T06:35:00Z",
    "redirect_to": "/dashboard"
  }
}
```

**Error (422 Unprocessable Entity)**:
```json
{
  "success": false,
  "message": "密碼驗證失敗",
  "errors": {
    "new_password": [
      "密碼必須至少包含 8 個字符",
      "密碼必須包含至少一個大寫字母",
      "密碼必須包含至少一個特殊字符"
    ]
  }
}
```

---

## 6. Password Reset Request

### Endpoint
```
POST /api/auth/password-reset/request
```

### Request

**Headers**:
```
Content-Type: application/json
Accept: application/json
```

**Body**:
```json
{
  "email": "user@example.com"
}
```

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "message": "密碼重設連結已發送到您的電子郵件",
  "data": {
    "email": "user@example.com",
    "expires_in_hours": 1
  }
}
```

**Error (429 Too Many Requests)**:
```json
{
  "success": false,
  "message": "請求過於頻繁，請 1 小時後再試",
  "retry_after": 3600
}
```

---

## 7. Password Reset Confirmation

### Endpoint
```
POST /api/auth/password-reset/confirm
```

### Request

**Headers**:
```
Content-Type: application/json
Accept: application/json
```

**Body**:
```json
{
  "token": "reset_token_here",
  "email": "user@example.com",
  "password": "NewSecure123!",
  "password_confirmation": "NewSecure123!"
}
```

**Validation Rules**:
- `token`: Required, valid reset token
- `email`: Required, valid email format
- `password`: Required, min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
- `password_confirmation`: Required, must match password

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "message": "密碼已成功重設",
  "data": {
    "email": "user@example.com",
    "password_reset_at": "2025-11-20T06:40:00Z"
  }
}
```

**Error (404 Not Found)**:
```json
{
  "success": false,
  "message": "無效或已過期的重設連結"
}
```

---

## 8. Logout

### Endpoint
```
POST /api/auth/logout
```

### Request

**Headers**:
```
Accept: application/json
Authorization: Bearer {token}
```

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "message": "已成功登出"
}
```

---

## Common Error Responses

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "伺服器錯誤，請稍後再試",
  "trace_id": "uuid-v4-here"
}
```

### 503 Service Unavailable (Email Service Down)
```json
{
  "success": false,
  "message": "無法發送郵件，請稍後再試"
}
```

---

## Rate Limiting

All endpoints are rate-limited to prevent abuse:

- **Registration**: 5 attempts per hour per IP
- **Email Verification Resend**: 3 attempts per hour per email
- **Login**: 10 attempts per hour per IP
- **Password Reset Request**: 3 attempts per hour per email

Rate limit headers included in all responses:
```
X-RateLimit-Limit: 5
X-RateLimit-Remaining: 4
X-RateLimit-Reset: 1637395200 (Unix timestamp)
```

---

## Security Considerations

1. **HTTPS Only**: All authentication endpoints must use HTTPS
2. **CSRF Protection**: Web routes require CSRF token
3. **Password Hashing**: bcrypt with cost factor 10
4. **Token Security**:
   - Email verification tokens: SHA-256 hashed, 64 chars
   - Password reset tokens: Laravel's default token generation
   - Session tokens: Laravel's encrypted session cookies
5. **Rate Limiting**: Prevents brute force attacks
6. **Logging**: All authentication events logged with trace ID

---

**Version**: 1.0.0
**Status**: Ready for implementation
