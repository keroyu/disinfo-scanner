# 新增權限指南

**版本**: 1.0
**更新日期**: 2025-11-30
**功能分支**: `011-member-system`

本文件說明如何在系統中新增權限。

---

## 目錄

1. [概覽](#概覽)
2. [步驟一：定義權限](#步驟一定義權限)
3. [步驟二：更新 Seeder](#步驟二更新-seeder)
4. [步驟三：定義 Gate](#步驟三定義-gate)
5. [步驟四：更新權限對應](#步驟四更新權限對應)
6. [步驟五：測試](#步驟五測試)
7. [範例：新增「匯出報表」權限](#範例新增匯出報表權限)

---

## 概覽

新增權限需要完成以下步驟：

1. 在 `PermissionSeeder` 中定義權限
2. 在 `AppServiceProvider` 中定義 Gate
3. 在 `PermissionRoleMappingSeeder` 中設定角色對應
4. 執行 seeder 並測試

---

## 步驟一：定義權限

### 權限命名規則

| 類型 | 前綴 | 範例 |
|------|------|------|
| 頁面存取 | `view_` | `view_reports_page` |
| 功能使用 | `use_` | `use_export_reports` |
| 操作執行 | `manage_` | `manage_reports` |

### 權限分類

| Category | 說明 |
|----------|------|
| `pages` | 頁面存取權限 |
| `features` | 功能使用權限 |
| `actions` | 操作執行權限 |

---

## 步驟二：更新 Seeder

### 修改 `database/seeders/PermissionSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // 現有權限...

            // 新增權限
            ['name' => 'use_export_reports', 'display_name' => '匯出報表', 'category' => 'features'],
        ];

        // ...
    }
}
```

### 執行 Seeder

```bash
# 僅執行 PermissionSeeder
php artisan db:seed --class=PermissionSeeder

# 或使用 --force 在 production 環境執行
php artisan db:seed --class=PermissionSeeder --force
```

---

## 步驟三：定義 Gate

### 修改 `app/Providers/AppServiceProvider.php`

```php
protected function registerPermissionGates(): void
{
    // 現有 Gates...

    // 新增 Gate
    Gate::define('use_export_reports', function (User $user) {
        return $this->checkPermission($user, 'use_export_reports');
    });
}
```

### Gate 使用方式

```php
// Controller 中
if (Gate::allows('use_export_reports')) {
    // 允許匯出
}

// 或使用 authorize
$this->authorize('use_export_reports');

// Blade 視圖中
@can('use_export_reports')
    <button>匯出報表</button>
@endcan
```

---

## 步驟四：更新權限對應

### 修改 `database/seeders/PermissionRoleMappingSeeder.php`

```php
private array $rolePermissions = [
    'visitor' => [
        // 訪客無此權限
    ],

    'regular_member' => [
        // 現有權限...
        // 一般會員無此權限
    ],

    'premium_member' => [
        // 現有權限...
        'use_export_reports',  // 高級會員可使用
    ],

    'website_editor' => [
        // 現有權限...
        'use_export_reports',  // 網站編輯可使用
    ],

    'administrator' => [
        // 現有權限...
        'use_export_reports',  // 管理員可使用
    ],
];
```

### 執行 Seeder

```bash
php artisan db:seed --class=PermissionRoleMappingSeeder
```

---

## 步驟五：測試

### 建立測試案例

```php
// tests/Feature/ExportReportsPermissionTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExportReportsPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\PermissionRoleMappingSeeder::class,
        ]);
    }

    /** @test */
    public function regular_member_cannot_export_reports()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'regular_member')->first());

        $this->actingAs($user);
        $this->assertFalse($user->can('use_export_reports'));
    }

    /** @test */
    public function premium_member_can_export_reports()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'premium_member')->first());

        $this->actingAs($user);
        $this->assertTrue($user->can('use_export_reports'));
    }

    /** @test */
    public function administrator_can_export_reports()
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'administrator')->first());

        $this->actingAs($user);
        $this->assertTrue($user->can('use_export_reports'));
    }
}
```

### 執行測試

```bash
php artisan test tests/Feature/ExportReportsPermissionTest.php
```

---

## 範例：新增「匯出報表」權限

### 完整實作流程

#### 1. 更新 PermissionSeeder

```php
// database/seeders/PermissionSeeder.php

$permissions = [
    // 現有權限...

    // 新增：匯出報表功能
    ['name' => 'use_export_reports', 'display_name' => '匯出報表', 'category' => 'features'],
];
```

#### 2. 定義 Gate

```php
// app/Providers/AppServiceProvider.php

Gate::define('use_export_reports', function (User $user) {
    return $this->checkPermission($user, 'use_export_reports');
});
```

#### 3. 更新角色權限對應

```php
// database/seeders/PermissionRoleMappingSeeder.php

'premium_member' => [
    // ... 現有權限
    'use_export_reports',
],

'website_editor' => [
    // ... 現有權限
    'use_export_reports',
],

'administrator' => [
    // ... 現有權限
    'use_export_reports',
],
```

#### 4. 執行 Seeder

```bash
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=PermissionRoleMappingSeeder
```

#### 5. 在 Controller 中使用

```php
// app/Http/Controllers/ReportController.php

public function export(Request $request)
{
    $this->authorize('use_export_reports');

    // 執行匯出邏輯...
}
```

#### 6. 在 View 中控制顯示

```blade
{{-- resources/views/reports/index.blade.php --}}

@can('use_export_reports')
    <button type="button" class="btn btn-primary" onclick="exportReport()">
        匯出報表
    </button>
@else
    <button type="button" class="btn btn-secondary" disabled
            title="您的會員等級不支援此功能">
        匯出報表
    </button>
@endcan
```

---

## 檢查清單

- [ ] 權限名稱遵循命名規則 (`view_`/`use_`/`manage_`)
- [ ] 權限已加入 `PermissionSeeder`
- [ ] Gate 已在 `AppServiceProvider` 中定義
- [ ] 權限已對應到適當的角色
- [ ] 已執行 seeder 更新資料庫
- [ ] 已建立並通過測試案例
- [ ] 已更新相關文件

---

## 相關文件

- [RBAC 架構文件](./rbac-architecture.md)
- [權限指派指南](./assign-permissions.md)
- [角色功能說明](./role-capabilities.md)

---

**文件維護者**: 開發團隊
**最後更新**: 2025-11-30
