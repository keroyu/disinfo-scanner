# RBAC 權限系統架構文件

**版本**: 1.0
**更新日期**: 2025-11-30
**功能分支**: `011-member-system`

## 目錄

1. [系統概覽](#系統概覽)
2. [核心元件](#核心元件)
3. [資料模型](#資料模型)
4. [權限檢查流程](#權限檢查流程)
5. [快取機制](#快取機制)
6. [擴展指南](#擴展指南)

---

## 系統概覽

本系統採用 **Role-Based Access Control (RBAC)** 角色為基礎的存取控制架構，支援 5 種使用者角色：

| 角色 | 說明 | 權限範圍 |
|------|------|----------|
| `visitor` | 訪客（未登入） | 首頁、影片列表、影片分析（僅檢視） |
| `regular_member` | 一般會員 | + 頻道列表、留言列表、U-API 匯入、影片更新 |
| `premium_member` | 高級會員 | + 官方 API 匯入（限額）、搜尋功能 |
| `website_editor` | 網站編輯 | 所有前台功能 |
| `administrator` | 管理員 | 所有功能（含後台管理） |

### 架構圖

```
┌─────────────────────────────────────────────────────────────┐
│                      Request                                │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    Middleware Layer                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │CheckEmailVer │  │CheckDefault  │  │CheckPermission   │  │
│  │    ified     │  │  Password    │  │                  │  │
│  └──────────────┘  └──────────────┘  └──────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                      Gate Layer                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              AppServiceProvider                       │  │
│  │  Gate::define('view_channels_list', ...)             │  │
│  │  Gate::define('use_official_api_import', ...)        │  │
│  │  Gate::define('manage_users', ...)                   │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   Service Layer                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │           RolePermissionService                       │  │
│  │  - hasRole($user, $roleName)                         │  │
│  │  - hasPermission($user, $permissionName)             │  │
│  │  - assignRole($user, $roleName, $assignedBy)         │  │
│  │  - getUserPermissions($user)                          │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   Model Layer                               │
│  ┌──────────┐    ┌──────────┐    ┌──────────────┐          │
│  │   User   │◄──►│   Role   │◄──►│  Permission  │          │
│  └──────────┘    └──────────┘    └──────────────┘          │
│       │               │                  │                  │
│       └───────────────┼──────────────────┘                  │
│                       ▼                                     │
│              Pivot Tables:                                  │
│              - role_user                                    │
│              - permission_role                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 核心元件

### 1. Models（模型層）

#### User Model (`app/Models/User.php`)

```php
// 角色關聯
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class, 'role_user')
        ->withPivot('assigned_at', 'assigned_by')
        ->withTimestamps();
}
```

#### Role Model (`app/Models/Role.php`)

```php
// 權限關聯
public function permissions(): BelongsToMany
{
    return $this->belongsToMany(Permission::class, 'permission_role')
        ->withPivot('created_at');
}

// 檢查角色是否有特定權限
public function hasPermission(string $permissionName): bool
```

#### Permission Model (`app/Models/Permission.php`)

```php
// 權限分類 scopes
public function scopePages($query)      // 頁面存取權限
public function scopeFeatures($query)   // 功能使用權限
public function scopeActions($query)    // 操作執行權限
```

### 2. Service Layer（服務層）

#### RolePermissionService (`app/Services/RolePermissionService.php`)

主要方法：

| 方法 | 說明 |
|------|------|
| `hasRole($user, $roleName)` | 檢查使用者是否有指定角色 |
| `hasPermission($user, $permissionName)` | 檢查使用者是否有指定權限 |
| `assignRole($user, $roleName, $assignedBy)` | 指派角色給使用者 |
| `removeRole($user, $roleName)` | 移除使用者角色 |
| `syncRoles($user, $roleNames, $assignedBy)` | 同步使用者角色 |
| `getUserPermissions($user)` | 取得使用者所有權限 |
| `getRolePermissions($roleName)` | 取得角色的所有權限 |
| `isAdministrator($user)` | 檢查是否為管理員 |

### 3. Gates（授權閘道）

定義於 `app/Providers/AppServiceProvider.php`：

```php
// 管理員繞過所有權限檢查
Gate::before(function (User $user, string $ability) {
    if ($rolePermissionService->isAdministrator($user)) {
        return true;
    }
    return null;
});

// 頁面存取 Gates
Gate::define('view_channels_list', ...);
Gate::define('view_comments_list', ...);
Gate::define('view_admin_panel', ...);

// 功能存取 Gates
Gate::define('use_search_videos', ...);
Gate::define('use_official_api_import', ...);
Gate::define('use_video_update', ...);  // 需要設定 YouTube API Key

// 操作 Gates
Gate::define('manage_users', ...);
Gate::define('manage_permissions', ...);
Gate::define('change_password', ...);
```

### 4. Middleware（中介層）

| 中介層 | 檔案位置 | 說明 |
|--------|----------|------|
| CheckEmailVerified | `app/Http/Middleware/CheckEmailVerified.php` | 驗證 email 是否已確認 |
| CheckDefaultPassword | `app/Http/Middleware/CheckDefaultPassword.php` | 強制變更預設密碼 |
| CheckPermission | `app/Http/Middleware/CheckPermission.php` | 權限檢查 |
| CheckAdminRole | `app/Http/Middleware/CheckAdminRole.php` | 管理員角色檢查 |
| CheckApiQuota | `app/Http/Middleware/CheckApiQuota.php` | API 配額檢查 |

---

## 資料模型

### ER Diagram

```
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│    users     │       │   role_user  │       │    roles     │
├──────────────┤       ├──────────────┤       ├──────────────┤
│ id           │◄──────│ user_id      │───────►│ id           │
│ email        │       │ role_id      │       │ name         │
│ password     │       │ assigned_at  │       │ display_name │
│ youtube_api_ │       │ assigned_by  │       │ description  │
│   key        │       └──────────────┘       └──────────────┘
│ ...          │                                     │
└──────────────┘                                     │
                                                     ▼
                       ┌──────────────┐       ┌──────────────┐
                       │permission_   │       │ permissions  │
                       │    role      │       ├──────────────┤
                       ├──────────────┤       │ id           │
                       │ role_id      │───────►│ name         │
                       │ permission_id│◄──────│ display_name │
                       │ created_at   │       │ category     │
                       └──────────────┘       └──────────────┘
```

### 權限分類

| 分類 | Category | 說明 |
|------|----------|------|
| 頁面權限 | `pages` | 控制頁面存取（如：view_channels_list） |
| 功能權限 | `features` | 控制功能使用（如：use_search_videos） |
| 操作權限 | `actions` | 控制操作執行（如：manage_users） |

---

## 權限檢查流程

### 1. Controller 層檢查

```php
// 使用 Gate facade
if (Gate::denies('view_channels_list')) {
    abort(403);
}

// 使用 authorize 方法
$this->authorize('use_official_api_import');

// 使用 @can Blade 指令
@can('manage_users')
    <a href="/admin/users">管理使用者</a>
@endcan
```

### 2. Middleware 檢查

```php
// 在 routes/web.php 中
Route::get('/channels', [ChannelController::class, 'index'])
    ->middleware(['auth', 'verified', 'permission:view_channels_list']);

// 或使用 Gate middleware
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('can:view_admin_panel');
```

### 3. Service 層檢查

```php
$rolePermissionService = app(RolePermissionService::class);

if ($rolePermissionService->hasPermission($user, 'use_official_api_import')) {
    // 執行匯入
}
```

---

## 快取機制

### 權限快取

使用者權限會快取 1 小時以提升效能：

```php
// RolePermissionService.php
const CACHE_TTL = 3600; // 1 hour

public function hasPermission(User $user, string $permissionName): bool
{
    $cacheKey = "user_permissions:{$user->id}";

    $permissions = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
        return $user->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->toArray();
    });

    return in_array($permissionName, $permissions);
}
```

### 快取清除時機

角色變更時自動清除快取：

```php
// 指派角色後
$this->clearUserPermissionCache($user);

// 移除角色後
$this->clearUserPermissionCache($user);

// 同步角色後
$this->clearUserPermissionCache($user);
```

### 手動清除快取

```php
$rolePermissionService->clearUserPermissionCache($user);

// 或直接使用 Cache
Cache::forget("user_permissions:{$user->id}");
```

---

## 擴展指南

### 新增權限

請參閱 [新增權限指南](./add-permissions.md)

### 指派權限給角色

請參閱 [權限指派指南](./assign-permissions.md)

### 角色功能說明

請參閱 [角色功能說明](./role-capabilities.md)

---

## 相關檔案

| 檔案 | 說明 |
|------|------|
| `app/Models/Role.php` | 角色模型 |
| `app/Models/Permission.php` | 權限模型 |
| `app/Services/RolePermissionService.php` | 角色權限服務 |
| `app/Providers/AppServiceProvider.php` | Gates 定義 |
| `database/seeders/RoleSeeder.php` | 角色種子資料 |
| `database/seeders/PermissionSeeder.php` | 權限種子資料 |
| `database/seeders/PermissionRoleMappingSeeder.php` | 權限對應種子資料 |

---

## 測試

```bash
# 執行所有 RBAC 測試
php artisan test --filter=Rbac

# 執行權限矩陣測試
php artisan test tests/Feature/RolePermissionMatrixTest.php

# 執行效能測試
php artisan test tests/Feature/RbacPerformanceTest.php
```

---

**文件維護者**: 開發團隊
**最後更新**: 2025-11-30
