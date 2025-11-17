# Service Contract: YouTubeApiService

**Feature**: YouTube API 官方導入留言 | **Date**: 2025-11-17 | **Status**: 已實作

---

## Overview

YouTubeApiService 是與 YouTube Data API v3 交互的核心服務層。負責影片元資料查詢、留言（含回覆）的遞迴取得、API 異常處理。所有方法為同步，支持進度回呼。

---

## Service Location

**檔案**: `/app/Services/YouTubeApiService.php`

**依賴注入**:
```php
use App\Services\YouTubeApiService;

class YourController extends Controller {
    public function __construct(YouTubeApiService $youtubeService) {
        $this->youtubeService = $youtubeService;
    }
}
```

---

## Method: fetchVideoMetadata()

**目的**: 取得影片和頻道的基本資訊

**簽名**:
```php
public function fetchVideoMetadata(string $videoId): array
```

**Parameters**:
| 參數 | 型別 | 說明 |
|------|------|------|
| `$videoId` | string | YouTube 影片 ID（11 字元） |

**Returns**:
```php
[
    'title' => '影片標題',
    'channel_id' => 'UCXXXXXXXXXXXXXX',
    'channel_name' => '頻道名稱',
    'published_at' => Carbon\Carbon object,  // 自動轉換為 YYYY-MM-DD HH:MM:SS
]
```

**Exceptions**:
- `YouTubeApiException`: API 呼叫失敗（超時、配額、網路）
- `InvalidVideoIdException`: 無效影片 ID 格式
- `AuthenticationException`: 缺少或無效 API 金鑰

**API Usage** (YouTube API):
- Endpoint: `youtube.videos().list()`
- Parameters: `part='snippet'`, `id=$videoId`
- Quota cost: 1

**使用場景**:
```php
try {
    $metadata = $this->youtubeService->fetchVideoMetadata('dQw4w9WgXcQ');
    // {title: '...', channel_id: '...', channel_name: '...', published_at: Carbon}
} catch (InvalidVideoIdException $e) {
    // 顯示錯誤: 無效影片 ID
} catch (YouTubeApiException $e) {
    // 顯示錯誤: API 失敗，請重試
}
```

---

## Method: fetchPreviewComments()

**目的**: 取得影片的 5 則最新留言（用於預覽）

**簽名**:
```php
public function fetchPreviewComments(string $videoId): array
```

**Parameters**:
| 參數 | 型別 | 說明 |
|------|------|------|
| `$videoId` | string | YouTube 影片 ID（11 字元） |

**Returns**:
```php
[
    [
        'comment_id' => 'CjIKGXdJd3d...',
        'text' => '很好的影片！',
        'author_channel_id' => 'UCYYYYYYYYYYYYYYY',
        'author_name' => 'User123',
        'like_count' => 42,
        'published_at' => Carbon\Carbon object,
        'reply_count' => 3,  // 該留言的回覆數
        'depth' => 0  // 0 = 頂層, 1+ = 回覆層級
    ],
    // ... 最多 5 筆
]
```

**排序**: 按 published_at 遞減（最新留言優先）

**Exceptions**:
- `YouTubeApiException`: API 失敗
- `InvalidVideoIdException`: 無效 ID

**API Usage**:
- Endpoint: `youtube.commentThreads().list()`
- Parameters: `part='snippet,replies'`, `videoId=$videoId`, `order='time'`, `maxResults=5`
- Quota cost: 1

**重要特性**:
- 每筆結果自動包含最多 20 則回覆（YouTube API 預設）
- 回覆數超過 20 時，reply_count 會標記
- **不進行遞迴取得** (故意，只用於預覽)

**使用場景**:
```php
$preview = $this->youtubeService->fetchPreviewComments('dQw4w9WgXcQ');
// 返回 5 筆，用於 modal 預覽顯示
```

---

## Method: fetchAllComments()

**目的**: 取得影片的所有留言及回覆（遞迴至 3 層深度）

**簽名**:
```php
public function fetchAllComments(
    string $videoId,
    ?Carbon\Carbon $afterDate = null,
    array $existingCommentIds = [],
    ?callable $progressCallback = null
): array
```

**Parameters**:
| 參數 | 型別 | 說明 |
|------|------|------|
| `$videoId` | string | YouTube 影片 ID |
| `$afterDate` | Carbon\|null | 增量導入：只取得此時間戳後的留言；null = 取全部 |
| `$existingCommentIds` | array | 已存在的評論 ID 清單，用於避免重複（可選） |
| `$progressCallback` | callable\|null | 進度回呼函數 `fn($progress) => null`（可選） |

**Returns**:
```php
[
    [
        'comment_id' => 'CjIKGXdJd3d...',
        'text' => '很好的影片！',
        'author_channel_id' => 'UCYYYYYYYYYYYYYYY',
        'author_name' => 'User123',
        'like_count' => 42,
        'published_at' => Carbon\Carbon object,
        'parent_comment_id' => null,  // null 表示頂層; 否則為父留言 ID
        'depth' => 0  // 回覆層級 (0 = 頂層, 1-3 = 回覆)
    ],
    // ... 包含所有頂層 + 回覆
]
```

**排序**: 按 published_at 遞減（最新優先）

**Exceptions**:
- `YouTubeApiException`: API 失敗
- `InvalidVideoIdException`: 無效 ID

**API Usage**:
- Endpoint: `youtube.commentThreads().list()` (分頁) + `youtube.comments().list()` (回覆)
- 分頁大小: 100 結果/頁
- Quota cost: ~1 per request + 1 per reply request

**停止條件** (雙重):
1. **時間戳停止**: 遇到 published_at < $afterDate 的留言停止
2. **重複檢測停止**: 遇到已存在於 $existingCommentIds 的留言停止

**遞迴深度管理**:
```
層級 0: 頂層留言 (top-level comment)
層級 1: 對頂層留言的直接回覆
層級 2: 對層級 1 回覆的回覆（也稱「回覆的回覆」）
層級 3: 對層級 2 回覆的回覆（最深層）
層級 4+: 被忽略（不導入）
```

**進度回呼格式**:
```php
$callback = function (array $progress) {
    // $progress 結構:
    // [
    //     'stage' => 'fetching_top_level' | 'fetching_replies' | 'filtering' | 'complete',
    //     'current' => 50,
    //     'total' => null | 200,  // null 時表示未知
    //     'page' => 2,
    //     'depth' => 1,
    //     'elapsed_ms' => 3500,
    //     'estimated_remaining_ms' => 8500
    // ]
};
```

**增量導入邏輯**:
```php
// 例子：上次導入於 2025-11-15 10:00:00
$lastImportAt = Carbon::parse('2025-11-15 10:00:00');
$existingIds = Comment::where('video_id', $videoId)
    ->pluck('comment_id')
    ->toArray();

$allComments = $this->youtubeService->fetchAllComments(
    'dQw4w9WgXcQ',
    $lastImportAt,
    $existingIds,
    function ($progress) {
        Log::info('Import progress', $progress);
    }
);

// 結果: 只返回時間戳 > 2025-11-15 10:00:00 的新留言
```

**使用場景**:
```php
// 完整導入（首次）
$comments = $this->youtubeService->fetchAllComments('dQw4w9WgXcQ');

// 增量導入（後續更新）
$video = Video::find('dQw4w9WgXcQ');
$lastImportAt = $video->channel->last_import_at;
$existingIds = $video->comments()->pluck('comment_id')->toArray();

$newComments = $this->youtubeService->fetchAllComments(
    'dQw4w9WgXcQ',
    $lastImportAt,
    $existingIds
);
```

---

## Method: fetchRepliesRecursive()

**目的**: 遞迴取得單個評論的所有回覆（內部方法）

**簽名**:
```php
protected function fetchRepliesRecursive(
    string $videoId,
    string $parentCommentId,
    int $depth = 0,
    ?Carbon\Carbon $afterDate = null,
    array &$seenCommentIds = []
): array
```

**Parameters**:
| 參數 | 型別 | 說明 |
|------|------|------|
| `$videoId` | string | YouTube 影片 ID |
| `$parentCommentId` | string | 父留言 ID |
| `$depth` | int | 目前深度（0 = 頂層） |
| `$afterDate` | Carbon\|null | 時間篩選 |
| `$seenCommentIds` | array | 已見過的 ID（避免環） |

**Returns**: 同 fetchAllComments，但只返回該評論的回覆鏈

**深度限制**:
```php
if ($depth >= 3) {
    return [];  // 停止遞迴，不返回更深的回覆
}
```

**備註**:
- 這是 `fetchAllComments()` 內部使用的方法
- 不應直接調用，除非進行低層級操作
- 支持環偵測（避免無限遞迴）

---

## Method: parseCommentData()

**目的**: 將 YouTube API 回應解析為標準格式

**簽名**:
```php
protected function parseCommentData(
    object $youtubeComment,
    ?string $parentCommentId = null
): array
```

**Parameters**:
| 參數 | 型別 | 說明 |
|------|------|------|
| `$youtubeComment` | object | YouTube API comment object |
| `$parentCommentId` | string\|null | 若為回覆，提供父留言 ID |

**Returns**:
```php
[
    'comment_id' => '...',
    'author_channel_id' => '...',
    'text' => '...',
    'like_count' => 42,
    'published_at' => Carbon\Carbon,
    'parent_comment_id' => null | '...',
]
```

**備註**:
- 內部方法，不應直接調用
- 自動處理 ISO 8601 → Carbon 轉換

---

## Method: validateVideoId()

**目的**: 驗證 YouTube 影片 ID 格式

**簽名**:
```php
public function validateVideoId(string $videoId): bool
```

**Parameters**:
| 參數 | 型別 | 說明 |
|------|------|------|
| `$videoId` | string | 待驗證的 ID |

**Returns**: true 若有效，否則拋出 InvalidVideoIdException

**驗證規則**:
```
長度: 必須為 11 字元
字符: 只允許 [a-zA-Z0-9_-]
```

**使用場景**:
```php
try {
    $this->youtubeService->validateVideoId('dQw4w9WgXcQ');  // true
    $this->youtubeService->validateVideoId('invalid');      // exception
} catch (InvalidVideoIdException $e) {
    // 處理
}
```

---

## Exception Classes

### YouTubeApiException

**父類**: `Exception`

**使用情況**: YouTube API 調用失敗

```php
try {
    $metadata = $this->youtubeService->fetchVideoMetadata('...');
} catch (YouTubeApiException $e) {
    $errorCode = $e->getErrorCode();  // 'quotaExceeded', 'accessDenied', 'notFound', ...
    $message = $e->getMessage();      // 中文錯誤訊息
    // 記錄日誌，顯示給用戶
}
```

**常見 errorCode**:
- `quotaExceeded`: 配額用盡，提示用戶稍後重試
- `accessDenied`: 無權限，檢查 API 金鑰
- `notFound`: 影片不存在或已刪除
- `networkError`: 網路連接失敗
- `timeout`: 請求超時

---

### InvalidVideoIdException

**父類**: `Exception`

**使用情況**: 影片 ID 格式無效

```php
try {
    $this->youtubeService->validateVideoId('invalid-id-format');
} catch (InvalidVideoIdException $e) {
    $message = $e->getMessage();  // '影片 ID 格式無效'
}
```

---

### AuthenticationException

**父類**: `Exception`

**使用情況**: 缺少或無效 API 金鑰

```php
try {
    $metadata = $this->youtubeService->fetchVideoMetadata('...');
} catch (AuthenticationException $e) {
    // 處理：提示系統管理員配置 YOUTUBE_API_KEY
}
```

---

## Configuration

**環境變數** (`.env`):
```env
YOUTUBE_API_KEY=AIzaSyD...          # 必需，YouTube Data API v3 金鑰
YOUTUBE_IMPORT_MAX_DEPTH=3          # 遞迴深度限制（預設 3）
YOUTUBE_API_TIMEOUT=30              # API 請求超時秒數（預設 30）
YOUTUBE_MAX_RESULTS_PER_PAGE=100    # 每頁最大結果數（預設 100）
```

**透過 .env 調整配置**:
```php
// config/youtube.php (應該存在或建立)
return [
    'api_key' => env('YOUTUBE_API_KEY'),
    'max_depth' => env('YOUTUBE_IMPORT_MAX_DEPTH', 3),
    'timeout' => env('YOUTUBE_API_TIMEOUT', 30),
    'max_results_per_page' => env('YOUTUBE_MAX_RESULTS_PER_PAGE', 100),
];
```

---

## Logging

**日誌檔案**: `storage/logs/youtube-import.log`

**日誌格式** (JSON):
```json
{
  "timestamp": "2025-11-17 15:45:32",
  "level": "INFO|WARNING|ERROR",
  "trace_id": "12345678-1234-1234-1234-123456789012",
  "method": "fetchVideoMetadata",
  "video_id": "dQw4w9WgXcQ",
  "duration_ms": 1234,
  "status": "success|failed",
  "error_code": "quotaExceeded",
  "error_message": "YouTube API error: quotaExceeded"
}
```

**日誌點**:
- 每個方法呼叫的開始和結束
- API 異常和重試嘗試
- 分頁和遞迴進度
- 進度回呼觸發

---

## Performance Considerations

### API 配額消耗

| 操作 | 配額成本 | 說明 |
|------|--------|------|
| fetchVideoMetadata() | 1 | 單一 API 呼叫 |
| fetchPreviewComments() | 1 | 單一 API 呼叫（含前 20 回覆） |
| fetchAllComments() 頂層 | ~(total_comments / 100) | 分頁呼叫 |
| fetchAllComments() 回覆 | ~(total_reply_threads) | 每個有回覆的留言 1 個呼叫 |

**估計**: 1000 條留言的影片，約 15-20 配額消耗

### 效能目標

| 操作 | 目標時間 | 注意 |
|------|--------|------|
| fetchVideoMetadata() | < 1s | 直接 API 呼叫 |
| fetchPreviewComments() | < 2s | 前 5 條 + 回覆 |
| fetchAllComments() 小型（< 100 留言） | < 5s | 直接分頁 |
| fetchAllComments() 中型（100-1000） | < 15s | 多個分頁 + 回覆 |
| fetchAllComments() 大型（> 1000） | < 60s | 多個分頁 + 深度遞迴 |

### 優化策略

1. **批量 API 呼叫**: 使用 batch requests（若 Google API 支持）
2. **進度回呼**: 讓 UI 顯示進度，避免超時感
3. **快取**: 可考慮快取 API 回應（但本設計不強制）
4. **超時處理**: 部分失敗允許繼續（見 CommentImportService）

---

## Contract Testing

**測試檔案**: `/tests/Unit/YouTubeApiServiceTest.php`

**契約測試覆蓋**:
- [ ] fetchVideoMetadata(): 有效和無效 ID
- [ ] fetchPreviewComments(): 返回 5 條和少於 5 條
- [ ] fetchAllComments(): 空影片、小影片、大影片、深度限制
- [ ] validateVideoId(): 有效和無效格式
- [ ] 異常處理: 所有 3 種異常類
- [ ] 增量導入: afterDate 和 existingCommentIds 邏輯

**Mock YouTube API**: 使用 `youtube-api-responses.json` fixture

---

**Service 契約版本**: 1.0 | **最後確認**: 2025-11-17 | **實作狀態**: ✅ 已實作
