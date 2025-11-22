# User Management API Contract

**Feature**: 011-member-system
**Version**: 1.0.0
**Date**: 2025-11-20

## Overview

This document defines the HTTP API contracts for user management operations including role assignment, permission management, and admin functions.

---

## 1. List All Users (Admin Only)

### Endpoint
```
GET /api/admin/users
```

### Request

**Headers**:
```
Accept: application/json
Authorization: Bearer {admin_token}
```

**Query Parameters**:
- `page`: Page number (default: 1)
- `per_page`: Results per page (default: 20, max: 100)
- `role`: Filter by role name (optional)
- `search`: Search by email or name (optional)

**Example**:
```
GET /api/admin/users?page=1&per_page=20&role=premium_Member&search=example
```

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "data": {
    "users": [
      {
        "id": 1,
        "email": "user@example.com",
        "name": "User Name",
        "roles": ["regular_member"],
        "is_email_verified": true,
        "has_default_password": false,
        "created_at": "2025-11-20T06:00:00Z",
        "last_login_at": "2025-11-20T06:30:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 150,
      "last_page": 8
    }
  }
}
```

**Error (403 Forbidden)**:
```json
{
  "success": false,
  "message": "您沒有權限執行此操作"
}
```

---

## 2. Get User Details (Admin Only)

### Endpoint
```
GET /api/admin/users/{userId}
```

### Request

**Headers**:
```
Accept: application/json
Authorization: Bearer {admin_token}
```

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "User Name",
      "roles": ["premium_Member"],
      "permissions": [
        "view_comments_list",
        "use_search_comments",
        "use_official_api_import"
      ],
      "is_email_verified": true,
      "has_default_password": false,
      "youtube_api_key": "AIzaSy...",
      "api_quota": {
        "current_month": "2025-11",
        "usage_count": 5,
        "monthly_limit": 10,
        "is_unlimited": false
      },
      "identity_verification": {
        "status": "pending",
        "submitted_at": "2025-11-15T10:00:00Z"
      },
      "created_at": "2025-11-01T06:00:00Z",
      "last_login_at": "2025-11-20T06:30:00Z"
    }
  }
}
```

**Error (404 Not Found)**:
```json
{
  "success": false,
  "message": "找不到該使用者"
}
```

---

## 3. Update User Role (Admin Only)

### Endpoint
```
PUT /api/admin/users/{userId}/role
```

### Request

**Headers**:
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {admin_token}
```

**Body**:
```json
{
  "role": "premium_Member"
}
```

**Validation Rules**:
- `role`: Required, must be one of: regular_member, premium_Member, website_editor, administrator

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "message": "使用者角色已更新",
  "data": {
    "user_id": 1,
    "email": "user@example.com",
    "previous_role": "regular_member",
    "new_role": "premium_Member",
    "updated_at": "2025-11-20T06:45:00Z",
    "updated_by": {
      "id": 2,
      "email": "admin@example.com"
    }
  }
}
```

**Error (403 Forbidden - Self Permission Change)**:
```json
{
  "success": false,
  "message": "您無法更改自己的權限等級，請聯繫其他管理員"
}
```

**Error (422 Unprocessable Entity)**:
```json
{
  "success": false,
  "message": "驗證失敗",
  "errors": {
    "role": [
      "角色必須是有效的角色名稱"
    ]
  }
}
```

---

## 4. Get User Settings (Authenticated User)

### Endpoint
```
GET /api/user/settings
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
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "User Name",
      "roles": ["regular_member"],
      "youtube_api_key": "AIzaSy...",
      "youtube_api_key_configured": true
    }
  }
}
```

---

## 5. Update User Settings (Authenticated User)

### Endpoint
```
PUT /api/user/settings
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
  "youtube_api_key": "AIzaSyNewKey123",
  "current_password": "UserPassword123!",
  "new_password": "NewPassword456!",
  "new_password_confirmation": "NewPassword456!"
}
```

**Validation Rules**:
- `youtube_api_key`: Optional, string, YouTube API key format
- `current_password`: Required if changing password
- `new_password`: Optional, min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
- `new_password_confirmation`: Required if new_password provided, must match

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "message": "設定已更新",
  "data": {
    "youtube_api_key_updated": true,
    "password_updated": true,
    "updated_at": "2025-11-20T06:50:00Z"
  }
}
```

**Error (422 Unprocessable Entity)**:
```json
{
  "success": false,
  "message": "驗證失敗",
  "errors": {
    "current_password": [
      "目前密碼不正確"
    ],
    "youtube_api_key": [
      "無效的 YouTube API 金鑰格式"
    ]
  }
}
```

---

## 6. Check API Quota (Authenticated User)

### Endpoint
```
GET /api/user/api-quota
```

### Request

**Headers**:
```
Accept: application/json
Authorization: Bearer {token}
```

### Response

**Success (200 OK) - Limited Access**:
```json
{
  "success": true,
  "data": {
    "quota": {
      "current_month": "2025-11",
      "usage_count": 7,
      "monthly_limit": 10,
      "remaining": 3,
      "is_unlimited": false,
      "identity_verification_status": "not_submitted",
      "can_import": true
    }
  }
}
```

**Success (200 OK) - Unlimited Access**:
```json
{
  "success": true,
  "data": {
    "quota": {
      "current_month": "2025-11",
      "usage_count": 25,
      "monthly_limit": null,
      "remaining": null,
      "is_unlimited": true,
      "identity_verification_status": "approved",
      "can_import": true
    }
  }
}
```

**Success (200 OK) - Quota Exceeded**:
```json
{
  "success": true,
  "data": {
    "quota": {
      "current_month": "2025-11",
      "usage_count": 10,
      "monthly_limit": 10,
      "remaining": 0,
      "is_unlimited": false,
      "identity_verification_status": "not_submitted",
      "can_import": false,
      "message": "您已達到本月配額上限 (10/10)，請完成身份驗證以獲得無限次數"
    }
  }
}
```

---

## 7. Submit Identity Verification (Premium Member)

### Endpoint
```
POST /api/user/identity-verification
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
  "verification_method": "id_card",
  "notes": "申請無限次數 API 導入"
}
```

**Validation Rules**:
- `verification_method`: Required, string (e.g., "email", "id_card", "phone")
- `notes`: Optional, text

### Response

**Success (201 Created)**:
```json
{
  "success": true,
  "message": "身份驗證申請已提交，請等待管理員審核",
  "data": {
    "verification_id": 1,
    "status": "pending",
    "submitted_at": "2025-11-20T07:00:00Z"
  }
}
```

**Error (403 Forbidden - Not Premium Member)**:
```json
{
  "success": false,
  "message": "需升級為高級會員才能申請身份驗證"
}
```

---

## 8. Review Identity Verification (Admin Only)

### Endpoint
```
PUT /api/admin/identity-verifications/{verificationId}
```

### Request

**Headers**:
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {admin_token}
```

**Body**:
```json
{
  "status": "approved",
  "notes": "身份驗證通過"
}
```

**Validation Rules**:
- `status`: Required, enum: approved, rejected
- `notes`: Optional, text (required if rejected)

### Response

**Success (200 OK)**:
```json
{
  "success": true,
  "message": "身份驗證審核完成",
  "data": {
    "verification_id": 1,
    "user_id": 1,
    "status": "approved",
    "reviewed_at": "2025-11-20T07:10:00Z",
    "reviewed_by": {
      "id": 2,
      "email": "admin@example.com"
    },
    "api_quota_updated": true
  }
}
```

**Error (404 Not Found)**:
```json
{
  "success": false,
  "message": "找不到該身份驗證申請"
}
```

---

## Permission Check Endpoints

### 9. Check User Permissions

### Endpoint
```
GET /api/user/permissions
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
  "data": {
    "permissions": {
      "pages": {
        "home": true,
        "channels_list": true,
        "videos_list": true,
        "comments_list": true,
        "admin_panel": false
      },
      "features": {
        "search_videos": false,
        "search_comments": false,
        "video_analysis": true,
        "video_update": true,
        "u_api_import": true,
        "official_api_import": false
      },
      "actions": {
        "manage_users": false,
        "manage_permissions": false,
        "change_password": true
      }
    },
    "role": "regular_member"
  }
}
```

---

## Common Error Responses

### 401 Unauthorized
```json
{
  "success": false,
  "message": "未授權，請先登入"
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "您沒有權限執行此操作"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "伺服器錯誤，請稍後再試",
  "trace_id": "uuid-v4-here"
}
```

---

## Authorization

All endpoints require authentication via Bearer token. Role-based access control:

- **Admin Only**: `/api/admin/*` endpoints require `administrator` role
- **Authenticated User**: `/api/user/*` endpoints require any authenticated user
- **Role-Specific**: Some endpoints check specific roles (e.g., identity verification requires `premium_Member`)

---

**Version**: 1.0.0
**Status**: Ready for implementation
