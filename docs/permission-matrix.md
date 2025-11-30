# 權限矩陣參考表

**版本**: 1.0
**更新日期**: 2025-11-30
**功能分支**: `011-member-system`

本文件列出所有角色與權限的對應關係。

---

## 角色說明

| 角色 | 英文名稱 | 說明 |
|------|----------|------|
| 訪客 | `visitor` | 未登入使用者 |
| 一般會員 | `regular_member` | 已驗證的基本會員 |
| 高級會員 | `premium_member` | 付費或升級的會員 |
| 網站編輯 | `website_editor` | 內容編輯人員 |
| 管理員 | `administrator` | 系統管理員 |

---

## 權限矩陣

### 頁面存取權限 (Pages)

| 權限 | 說明 | 訪客 | 一般會員 | 高級會員 | 網站編輯 | 管理員 |
|------|------|:----:|:--------:|:--------:|:--------:|:------:|
| `view_home` | 首頁 | ✅ | ✅ | ✅ | ✅ | ✅ |
| `view_videos_list` | 影片列表 | ✅ | ✅ | ✅ | ✅ | ✅ |
| `view_channels_list` | 頻道列表 | ❌ | ✅ | ✅ | ✅ | ✅ |
| `view_comments_list` | 留言列表 | ❌ | ✅ | ✅ | ✅ | ✅ |
| `view_admin_panel` | 管理後台 | ❌ | ❌ | ❌ | ❌ | ✅ |

### 功能使用權限 (Features)

| 權限 | 說明 | 訪客 | 一般會員 | 高級會員 | 網站編輯 | 管理員 |
|------|------|:----:|:--------:|:--------:|:--------:|:------:|
| `use_search_videos` | 影片搜尋 | ❌ | ❌ | ✅ | ✅ | ✅ |
| `use_search_comments` | 留言搜尋 | ❌ | ❌ | ✅ | ✅ | ✅ |
| `use_video_analysis` | 影片分析 | ✅ | ✅ | ✅ | ✅ | ✅ |
| `use_video_update` | 影片更新 | ❌ | ✅* | ✅ | ✅ | ✅ |
| `use_u_api_import` | U-API 匯入 | ❌ | ✅ | ✅ | ✅ | ✅ |
| `use_official_api_import` | 官方 API 匯入 | ❌ | ❌ | ✅** | ✅ | ✅ |

\* 需要設定 YouTube API Key
\** 每月限額 10 次，身分驗證後無限制

### 操作執行權限 (Actions)

| 權限 | 說明 | 訪客 | 一般會員 | 高級會員 | 網站編輯 | 管理員 |
|------|------|:----:|:--------:|:--------:|:--------:|:------:|
| `change_password` | 變更密碼 | ❌ | ✅ | ✅ | ✅ | ✅ |
| `manage_users` | 管理使用者 | ❌ | ❌ | ❌ | ❌ | ✅ |
| `manage_permissions` | 管理權限 | ❌ | ❌ | ❌ | ❌ | ✅ |

---

## 權限統計

| 角色 | 頁面權限 | 功能權限 | 操作權限 | 總計 |
|------|:--------:|:--------:|:--------:|:----:|
| 訪客 | 2 | 1 | 0 | **3** |
| 一般會員 | 4 | 4 | 1 | **9** |
| 高級會員 | 4 | 6 | 1 | **11** |
| 網站編輯 | 4 | 6 | 1 | **11** |
| 管理員 | 5 | 6 | 3 | **14** |

---

## 特殊條件說明

### 1. 影片更新功能 (`use_video_update`)

一般會員及以上角色可使用，但需要額外條件：

```
權限條件：hasPermission('use_video_update') && !empty($user->youtube_api_key)
```

未設定 YouTube API Key 時，會顯示提示訊息引導設定。

### 2. 官方 API 匯入 (`use_official_api_import`)

高級會員有配額限制：

| 狀態 | 每月限額 |
|------|:--------:|
| 未身分驗證 | 10 次 |
| 已身分驗證 | 無限制 |

網站編輯和管理員無配額限制。

### 3. 管理員權限繞過

管理員自動繞過所有權限檢查：

```php
// AppServiceProvider.php
Gate::before(function (User $user, string $ability) {
    if ($rolePermissionService->isAdministrator($user)) {
        return true;
    }
    return null;
});
```

---

## 權限檢查範例

### Blade 視圖

```blade
@can('view_channels_list')
    <a href="{{ route('channels.index') }}">頻道列表</a>
@endcan

@cannot('use_search_videos')
    <span class="text-muted">搜尋功能需要高級會員權限</span>
@endcannot
```

### Controller

```php
public function index()
{
    $this->authorize('view_channels_list');

    // 或使用 Gate
    if (Gate::denies('view_channels_list')) {
        abort(403, '無權存取頻道列表');
    }
}
```

### Middleware

```php
// routes/web.php
Route::get('/channels', [ChannelController::class, 'index'])
    ->middleware('can:view_channels_list');
```

---

## 權限對應程式碼參考

```php
// database/seeders/PermissionRoleMappingSeeder.php

private array $rolePermissions = [
    'visitor' => [
        'view_home',
        'view_videos_list',
        'use_video_analysis',
    ],

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

    'premium_member' => [
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

---

## 相關文件

- [RBAC 架構文件](./rbac-architecture.md)
- [新增權限指南](./add-permissions.md)
- [權限指派指南](./assign-permissions.md)
- [角色功能說明](./role-capabilities.md)

---

**文件維護者**: 開發團隊
**最後更新**: 2025-11-30
