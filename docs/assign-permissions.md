# 權限指派指南

**版本**: 1.0
**更新日期**: 2025-11-30
**功能分支**: `011-member-system`

本文件說明如何將權限指派給角色，以及如何管理使用者角色。

---

## 目錄

1. [概覽](#概覽)
2. [透過 Seeder 指派權限](#透過-seeder-指派權限)
3. [透過程式碼指派權限](#透過程式碼指派權限)
4. [透過 Artisan 指令](#透過-artisan-指令)
5. [使用者角色管理](#使用者角色管理)
6. [最佳實踐](#最佳實踐)

---

## 概覽

### 權限指派階層

```
Permission (權限)
     │
     ▼
   Role (角色)     ← 權限指派給角色
     │
     ▼
   User (使用者)   ← 角色指派給使用者
```

### 指派方式

| 方式 | 使用時機 | 適用對象 |
|------|----------|----------|
| Seeder | 初始化系統、版本更新 | 開發/部署人員 |
| 程式碼 | 動態授權、業務邏輯 | 開發人員 |
| Artisan 指令 | 維運操作、除錯 | 維運人員 |
| Admin Panel | 日常管理 | 管理員 |

---

## 透過 Seeder 指派權限

### 修改 PermissionRoleMappingSeeder

```php
// database/seeders/PermissionRoleMappingSeeder.php

private array $rolePermissions = [
    // 訪客：最小權限
    'visitor' => [
        'view_home',
        'view_videos_list',
        'use_video_analysis',
    ],

    // 一般會員：基本功能
    'regular_member' => [
        'view_home',
        'view_videos_list',
        'view_channels_list',
        'view_comments_list',
        'use_video_analysis',
        'use_video_update',
        'use_u_api_import',
        'change_password',
    ],

    // 高級會員：進階功能 + 配額限制
    'premium_member' => [
        'view_home',
        'view_videos_list',
        'view_channels_list',
        'view_comments_list',
        'use_video_analysis',
        'use_video_update',
        'use_u_api_import',
        'use_official_api_import',  // 每月限額 10 次
        'use_search_videos',
        'use_search_comments',
        'change_password',
    ],

    // 網站編輯：所有前台功能
    'website_editor' => [
        'view_home',
        'view_videos_list',
        'view_channels_list',
        'view_comments_list',
        'use_video_analysis',
        'use_video_update',
        'use_u_api_import',
        'use_official_api_import',
        'use_search_videos',
        'use_search_comments',
        'change_password',
    ],

    // 管理員：完整權限
    'administrator' => [
        'view_home',
        'view_videos_list',
        'view_channels_list',
        'view_comments_list',
        'view_admin_panel',
        'use_video_analysis',
        'use_video_update',
        'use_u_api_import',
        'use_official_api_import',
        'use_search_videos',
        'use_search_comments',
        'change_password',
        'manage_users',
        'manage_permissions',
    ],
];
```

### 執行 Seeder

```bash
# 開發環境
php artisan db:seed --class=PermissionRoleMappingSeeder

# 生產環境（需要 --force）
php artisan db:seed --class=PermissionRoleMappingSeeder --force
```

---

## 透過程式碼指派權限

### 使用 Role Model 方法

```php
use App\Models\Role;
use App\Models\Permission;

// 取得角色
$role = Role::where('name', 'premium_member')->first();

// 指派單一權限
$role->grantPermission('use_export_reports');

// 撤銷權限
$role->revokePermission('use_export_reports');

// 檢查是否有權限
if ($role->hasPermission('use_export_reports')) {
    // ...
}
```

### 直接操作資料表

```php
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\Permission;

$role = Role::where('name', 'premium_member')->first();
$permission = Permission::where('name', 'use_export_reports')->first();

// 新增對應
DB::table('permission_role')->insert([
    'role_id' => $role->id,
    'permission_id' => $permission->id,
    'created_at' => now(),
]);

// 移除對應
DB::table('permission_role')
    ->where('role_id', $role->id)
    ->where('permission_id', $permission->id)
    ->delete();
```

### 使用 Eloquent 關聯

```php
$role = Role::where('name', 'premium_member')->first();
$permission = Permission::where('name', 'use_export_reports')->first();

// 附加權限
$role->permissions()->attach($permission->id, ['created_at' => now()]);

// 分離權限
$role->permissions()->detach($permission->id);

// 同步權限（取代所有現有權限）
$permissionIds = Permission::whereIn('name', [
    'view_home',
    'view_videos_list',
    'use_export_reports',
])->pluck('id')->toArray();

$role->permissions()->sync($permissionIds);
```

---

## 透過 Artisan 指令

### 列出所有權限

```bash
php artisan permissions:list
```

輸出範例：
```
+----+------------------------+------------------+----------+
| ID | Name                   | Display Name     | Category |
+----+------------------------+------------------+----------+
| 1  | view_home              | View Home Page   | pages    |
| 2  | view_channels_list     | View Channels... | pages    |
| 3  | view_videos_list       | View Videos...   | pages    |
| ...                                                       |
+----+------------------------+------------------+----------+
```

### 檢查使用者權限

```bash
php artisan permissions:check {user_id}
```

輸出範例：
```
User: john@example.com (ID: 42)
Roles: premium_member

Permissions:
+------------------------+------------------+----------+
| Name                   | Display Name     | Category |
+------------------------+------------------+----------+
| view_home              | View Home Page   | pages    |
| view_channels_list     | View Channels... | pages    |
| use_official_api_import| Use Official...  | features |
| ...                                                  |
+------------------------+------------------+----------+
```

### 同步權限

```bash
php artisan permissions:sync
```

---

## 使用者角色管理

### 使用 RolePermissionService

```php
use App\Services\RolePermissionService;

$service = app(RolePermissionService::class);

// 指派角色給使用者
$service->assignRole($user, 'premium_member', $adminUser);

// 移除使用者角色
$service->removeRole($user, 'regular_member');

// 同步使用者角色（取代所有現有角色）
$service->syncRoles($user, ['premium_member'], $adminUser);

// 檢查使用者角色
if ($service->hasRole($user, 'administrator')) {
    // ...
}

// 取得使用者所有權限
$permissions = $service->getUserPermissions($user);
```

### 透過 Admin Panel

管理員可透過後台管理介面：

1. 進入「會員管理」頁面
2. 選擇要修改的使用者
3. 在「角色」欄位選擇新角色
4. 儲存變更

角色變更會立即生效，無需使用者重新登入。

---

## 最佳實踐

### 1. 使用 Seeder 管理基礎權限

```php
// 優點：版本控制、可重現、團隊協作

// database/seeders/PermissionRoleMappingSeeder.php
class PermissionRoleMappingSeeder extends Seeder
{
    // 集中管理所有權限對應
}
```

### 2. 權限變更記錄

```php
// RolePermissionService 已內建日誌
Log::info('SECURITY: Role assigned', [
    'user_id' => $user->id,
    'email' => $user->email,
    'role' => $roleName,
    'assigned_by' => $assignedBy?->id,
    'assigned_at' => now()->toIso8601String(),
]);
```

### 3. 快取清除

角色變更後自動清除權限快取：

```php
// RolePermissionService 自動處理
public function assignRole(User $user, string $roleName, ?User $assignedBy = null): bool
{
    // ... 指派角色邏輯

    // 自動清除快取
    $this->clearUserPermissionCache($user);

    return true;
}
```

### 4. 測試覆蓋

每次新增或修改權限對應時，確保有對應的測試：

```php
/** @test */
public function premium_member_has_search_permission()
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'premium_member')->first());

    $this->assertTrue($user->can('use_search_videos'));
    $this->assertTrue($user->can('use_search_comments'));
}
```

### 5. 遵循最小權限原則

只給予角色完成工作所需的最小權限集合：

```php
// 不要這樣做
'regular_member' => Permission::all()->pluck('name')->toArray(),

// 應該這樣做
'regular_member' => [
    'view_home',
    'view_videos_list',
    'view_channels_list',
    'view_comments_list',
    // 只給予必要權限
],
```

---

## 疑難排解

### 權限未生效

1. 檢查快取是否清除：
```php
$service->clearUserPermissionCache($user);
```

2. 確認 Seeder 已執行：
```bash
php artisan db:seed --class=PermissionRoleMappingSeeder
```

3. 確認角色已正確指派：
```bash
php artisan permissions:check {user_id}
```

### 權限檢查失敗

1. 確認 Gate 已定義：
```php
// app/Providers/AppServiceProvider.php
Gate::define('permission_name', function (User $user) {
    return $this->checkPermission($user, 'permission_name');
});
```

2. 確認權限存在於資料庫：
```bash
php artisan permissions:list
```

---

## 相關文件

- [RBAC 架構文件](./rbac-architecture.md)
- [新增權限指南](./add-permissions.md)
- [角色功能說明](./role-capabilities.md)

---

**文件維護者**: 開發團隊
**最後更新**: 2025-11-30
