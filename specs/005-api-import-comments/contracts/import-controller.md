# API Contract: YouTubeApiImportController

**Feature**: YouTube API 官方導入留言 | **Date**: 2025-11-17 | **Status**: 已實作

---

## Overview

YouTubeApiImportController 提供四個主要端點，支持完整的影片導入流程：檢查→預覽→確認→完成。所有端點採用 JSON 格式，包含詳細的錯誤訊息。

---

## Endpoint: GET /api/youtube-import/show-form

**目的**: 返回 import modal Blade 視圖

**Request**:
```http
GET /api/youtube-import/show-form HTTP/1.1
Host: localhost:8000
Accept: text/html
```

**Response** (200 OK):
```html
<!-- Blade 模板: import-modal.blade.php -->
<!-- 包含完整的 modal HTML 和 JavaScript 邏輯 -->
```

**使用場景**:
- 「留言列表」頁面右上角按鈕點擊事件
- 動態載入 modal HTML

---

## Endpoint: POST /api/youtube-import/metadata

**目的**: 檢查影片/頻道存在，取得元資料

**Request**:
```json
POST /api/youtube-import/metadata HTTP/1.1
Host: localhost:8000
Content-Type: application/json
X-CSRF-TOKEN: {csrf_token}

{
  "video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
}
```

**Request 欄位**:
| 欄位 | 型別 | 必需 | 說明 |
|------|------|------|------|
| `video_url` | string | ✓ | YouTube 影片 URL（支援 youtube.com/watch?v= 和 youtu.be 格式） |

**Response (200 OK) - 影片已存在**:
```json
{
  "success": true,
  "status": "existing_video",
  "message": "影片已建檔，請利用更新功能導入留言",
  "data": {
    "video_id": "dQw4w9WgXcQ"
  }
}
```

**Response (202 Accepted) - 新影片 + 新頻道**:
```json
{
  "success": true,
  "status": "metadata_ready",
  "message": "頻道和影片都未建檔！",
  "data": {
    "video_id": "dQw4w9WgXcQ",
    "channel_id": "UCXXXXXXXXXXXXXX",
    "title": "影片標題",
    "channel_name": "頻道名稱",
    "published_at": "2025-11-15 14:30:00",
    "import_mode": "new",
    "next_action": "show_preview"
  }
}
```

**Response (202 Accepted) - 新影片 + 既有頻道**:
```json
{
  "success": true,
  "status": "metadata_ready",
  "message": "這是已存在頻道的新影片",
  "data": {
    "video_id": "dQw4w9WgXcQ",
    "channel_id": "UCXXXXXXXXXXXXXX",
    "title": "影片標題",
    "channel_name": "頻道名稱",
    "published_at": "2025-11-15 14:30:00",
    "import_mode": "incremental",
    "next_action": "show_preview",
    "existing_tags": [
      { "tag_id": 1, "name": "政治", "color_hex": "#FF0000" },
      { "tag_id": 2, "name": "社會", "color_hex": "#0000FF" }
    ]
  }
}
```

**Response (400 Bad Request) - 無效 URL**:
```json
{
  "success": false,
  "error": "無效的 YouTube URL 格式。請輸入有效的 YouTube URL。",
  "status": "invalid_url"
}
```

**Response (502 Bad Gateway) - YouTube API 失敗**:
```json
{
  "success": false,
  "error": "無法連接 YouTube API，請檢查網路連線或稍後重試。",
  "status": "api_error",
  "message": "YouTube API error: quotaExceeded"
}
```

**業務邏輯**:
1. 驗證 URL 格式
2. 抽取 video_id
3. 查詢 videos 表 (是否存在)
4. 若存在 → 回傳 status: "existing_video"
5. 若不存在 → 呼叫 YouTube API 取得元資料
6. 查詢 channels 表 (決定新建或更新)
7. 回傳 status: "metadata_ready" + import_mode (new/incremental)

---

## Endpoint: POST /api/youtube-import/preview

**目的**: 取得 5 則預覽留言，不持久化任何資料

**Request**:
```json
POST /api/youtube-import/preview HTTP/1.1
Host: localhost:8000
Content-Type: application/json
X-CSRF-TOKEN: {csrf_token}

{
  "video_id": "dQw4w9WgXcQ"
}
```

**Request 欄位**:
| 欄位 | 型別 | 必需 | 說明 |
|------|------|------|------|
| `video_id` | string | ✓ | YouTube 影片 ID（由 metadata 端點取得） |

**Response (200 OK)**:
```json
{
  "success": true,
  "status": "preview_ready",
  "message": "預覽留言已準備好",
  "data": {
    "preview_comments": [
      {
        "comment_id": "CjIKGXdJd3d...",
        "text": "很好的影片！",
        "author_name": "User123",
        "author_channel_id": "UCYYYYYYYYYYYYYYY",
        "like_count": 42,
        "published_at": "2025-11-15 10:00:00",
        "reply_count": 3,
        "depth": 0
      },
      {
        "comment_id": "CjMKGXdJd3d...",
        "text": "感謝分享",
        "author_name": "User456",
        "author_channel_id": "UCZZZZZZZZZZZZZZZ",
        "like_count": 15,
        "published_at": "2025-11-15 09:30:00",
        "reply_count": 0,
        "depth": 0
      }
    ],
    "total_preview_count": 5,
    "estimated_total_comments": 156
  }
}
```

**Response (422 Unprocessable Entity) - 無效 video_id**:
```json
{
  "success": false,
  "error": "無效的 YouTube 影片 ID 格式。",
  "status": "invalid_video_id"
}
```

**Response (502 Bad Gateway) - API 失敗 + 重試按鈕**:
```json
{
  "success": false,
  "error": "無法取得預覽留言。請檢查網路連線或稍後重試。",
  "status": "api_error",
  "retry_available": true,
  "message": "YouTube API error: accessDenied"
}
```

**業務邏輯**:
1. 驗證 video_id 格式
2. 呼叫 YouTubeApiService.fetchPreviewComments()
3. 返回 5 則留言（按發佈時間遞減）
4. **重要**: 不持久化任何資料到資料庫

---

## Endpoint: POST /api/youtube-import/confirm-import

**目的**: 執行完整導入流程，包括所有留言和回覆

**Request**:
```json
POST /api/youtube-import/confirm-import HTTP/1.1
Host: localhost:8000
Content-Type: application/json
X-CSRF-TOKEN: {csrf_token}

{
  "video_id": "dQw4w9WgXcQ",
  "video_metadata": {
    "title": "影片標題",
    "channel_id": "UCXXXXXXXXXXXXXX",
    "channel_name": "頻道名稱",
    "published_at": "2025-11-15 14:30:00"
  },
  "selected_tags": [1, 3, 5],
  "import_mode": "new"
}
```

**Request 欄位**:
| 欄位 | 型別 | 必需 | 說明 |
|------|------|------|------|
| `video_id` | string | ✓ | YouTube 影片 ID |
| `video_metadata` | object | ✓ | 影片和頻道元資料（由 metadata 端點取得） |
| `selected_tags` | array[int] | ✓ | 選定的標籤 ID 列表（新頻道至少 1 個） |
| `import_mode` | string | ✓ | "new" 或 "incremental" |

**Response (200 OK) - 導入成功**:
```json
{
  "success": true,
  "status": "import_complete",
  "message": "成功導入",
  "data": {
    "video_id": "dQw4w9WgXcQ",
    "channel_id": "UCXXXXXXXXXXXXXX",
    "comments_imported": 142,
    "replies_imported": 28,
    "total_imported": 170,
    "import_mode": "new",
    "import_duration_seconds": 12.5,
    "timestamp": "2025-11-17 15:45:32"
  }
}
```

**Response (400 Bad Request) - 缺少必需欄位**:
```json
{
  "success": false,
  "error": "缺少必需的欄位：video_id, video_metadata, selected_tags",
  "status": "validation_error"
}
```

**Response (400 Bad Request) - 標籤驗證失敗（新頻道）**:
```json
{
  "success": false,
  "error": "新頻道必須選擇至少一個標籤。",
  "status": "invalid_tags",
  "validation_errors": {
    "selected_tags": ["至少需要選擇 1 個標籤"]
  }
}
```

**Response (502 Bad Gateway) - YouTube API 失敗**:
```json
{
  "success": false,
  "error": "在取得留言時發生錯誤。部分資料可能已導入，請稍後重試以完成導入。",
  "status": "api_error",
  "partially_imported": true,
  "data": {
    "channel_created": true,
    "video_created": true,
    "comments_imported": 45,
    "total_comments": null
  }
}
```

**Response (500 Internal Server Error) - 資料庫錯誤（自動回滾）**:
```json
{
  "success": false,
  "error": "資料庫操作失敗，導入已回滾。請稍後重試。",
  "status": "database_error"
}
```

**業務邏輯** (事務內):
1. 驗證 video_id, 元資料, 標籤
2. 新頻道: 驗證至少 1 個標籤已選
3. 呼叫 YouTubeApiService.fetchAllComments() (所有留言 + 回覆)
4. 建立 Channel (若不存在)
5. 建立 Video
6. 批量建立 Authors (get or create)
7. 批量建立 Comments (帶 parent_comment_id)
8. 計算 comment_count = top_level + replies
9. 寫入 videos.comment_count
10. 更新 channel_tags (新增或替換)
11. 更新 channels.last_import_at
12. 事務提交 → 成功回應
13. 異常 → 回滾 + 錯誤回應

---

## Common Response Headers

所有端點回應包含以下 header:

```http
HTTP/1.1 200 OK
Content-Type: application/json; charset=utf-8
X-Request-ID: {uuid}
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
```

---

## Error Response Format

**標準錯誤格式**:
```json
{
  "success": false,
  "error": "中文錯誤訊息（用戶可見）",
  "status": "error_code",
  "message": "（可選）詳細說明",
  "validation_errors": {
    "field_name": ["錯誤訊息 1", "錯誤訊息 2"]
  }
}
```

**HTTP 狀態碼對應**:
| 狀態碼 | 情況 | 使用者動作 |
|-------|------|----------|
| 200 OK | 操作成功 | 無 |
| 202 Accepted | 元資料準備就緒，等待確認 | 檢視預覽或下一步 |
| 400 Bad Request | 驗證失敗或無效輸入 | 修正輸入 |
| 422 Unprocessable Entity | 業務規則驗證失敗 | 修正輸入或重試 |
| 500 Internal Server Error | 伺服器錯誤 | 聯繫支持 |
| 502 Bad Gateway | YouTube API 或外部服務失敗 | 重試或稍後 |

---

## Rate Limiting

目前無速率限制實裝，但應考慮：
- YouTube API 配額限制（每日 10,000 配額單位）
- 每個用戶每分鐘最多 3 個導入請求
- 大型影片（>5000 留言）應提示警告

---

## Authentication & Security

**CSRF Protection**: 所有 POST 請求必須包含 X-CSRF-TOKEN header 或表單欄位

**Authorization**: 目前允許所有已登入用戶使用此功能（未來可加 Permission 控制）

**Validation**:
- 所有輸入在伺服器端驗證
- 不信任客戶端提供的元資料（重新從 YouTube API 驗證）

---

## Implementation Notes

### 實作位置
- **Controller**: `/app/Http/Controllers/YouTubeApiImportController.php`
- **Service**: `/app/Services/CommentImportService.php`, `/app/Services/YouTubeApiService.php`
- **Routes**: `/routes/api.php` (前綴 `/api`)

### 事務處理
```php
DB::transaction(function () {
    // 所有資料庫操作
}, attempts: 3);
```

### 日誌記錄
所有操作記錄到 `storage/logs/youtube-import.log`:
```json
{
  "timestamp": "2025-11-17 15:45:32",
  "trace_id": "uuid-123",
  "action": "import_start|channel_created|video_created|comments_imported|import_complete|import_failed",
  "video_id": "...",
  "comments_count": 170,
  "duration_ms": 12500
}
```

### 進度回呼（未來）
```php
// YouTubeApiService 支持 progressCallback
fetchAllComments($videoId, $afterDate, $existingIds, function ($progress) {
    // $progress: ['current' => 50, 'total' => 200, 'stage' => 'fetching_replies']
    Log::info('Import progress', $progress);
});
```

---

**API 契約版本**: 1.0 | **最後確認**: 2025-11-17 | **實作狀態**: ✅ 已實作
