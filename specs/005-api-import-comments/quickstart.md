# Quick Start Guide: YouTube API 官方導入留言

**Feature**: YouTube API 官方導入留言 | **Date**: 2025-11-17 | **For**: Developers

---

## 5-Minute Setup

### Prerequisites

- Laravel 10+ 環境已配置
- YouTube Data API v3 API 金鑰已申請
- PHP 8.1+ 已安裝
- MySQL/SQLite 資料庫已就緒

### Step 1: 配置 API 金鑰

編輯 `.env`:
```env
YOUTUBE_API_KEY=AIzaSyD9sq8rTQBjeEXfV6qQxOEiVdC4o-zN29o
YOUTUBE_IMPORT_MAX_DEPTH=3
YOUTUBE_API_TIMEOUT=30
```

### Step 2: 執行遷移

```bash
php artisan migrate --path=database/migrations/[timestamp]_add_comment_count_to_videos.php
```

**預期結果**: `videos` 表新增 `comment_count` 欄位

### Step 3: 驗證功能

訪問 `/comments` 頁面，檢查右上角是否有「官方API導入」按鈕。點擊後應出現 modal。

---

## User Flow Walkthrough

### Scenario 1: 新影片 + 新頻道

**使用者操作**:
1. 點擊「官方API導入」按鈕 → modal 開啟
2. 輸入影片 URL: `https://www.youtube.com/watch?v=dQw4w9WgXcQ`
3. 點擊「檢查是否建檔」
4. 看到提示: 「頻道和影片都未建檔！」
5. 看到預覽: 頻道名稱、影片標題、5 則留言
6. 選擇標籤: 至少 1 個（必選）
7. 點擊「確認導入」
8. 看到成功訊息: 「成功導入 145 則留言」
9. 關閉 modal → 留言列表自動更新，新留言顯示在頂部

**後端流程**:
```
POST /api/youtube-import/metadata
  → YouTubeApiImportController.getMetadata()
    → 驗證 URL 格式
    → 檢查 videos 表（不存在）
    → 檢查 channels 表（不存在）
    → 呼叫 YouTube API 取得元資料
    → 返回 status: "metadata_ready", import_mode: "new"

POST /api/youtube-import/preview
  → YouTubeApiImportController.getPreview()
    → 呼叫 YouTubeApiService.fetchPreviewComments()
    → 返回 5 則最新留言

POST /api/youtube-import/confirm-import
  → YouTubeApiImportController.confirmImport()
    → 驗證標籤（至少 1 個）
    → 呼叫 CommentImportService.executeFullImport()
      → 事務開始
      → 建立 Channel 記錄
      → 建立 Video 記錄
      → 呼叫 YouTubeApiService.fetchAllComments()（遞迴至 3 層）
      → 批量建立 Authors
      → 批量建立 Comments 及其 parent_comment_id 關係
      → 計算 comment_count = 145
      → 更新 videos.comment_count = 145
      → 建立 channel_tags 關係（多對多）
      → 更新 channels.last_import_at
      → 事務提交
    → 返回 status: "import_complete", total_imported: 145

JavaScript: AJAX 重新載入 /comments 頁面資料（無頁面重新整理）
```

### Scenario 2: 新影片 + 既有頻道

**使用者操作**:
1. 輸入新影片 URL
2. 點擊「檢查是否建檔」
3. 看到提示: 「這是已存在頻道的新影片」
4. 看到預覽 + 當前頻道標籤（灰顯，可選修改）
5. 確認或修改標籤
6. 點擊「確認導入」
7. 導入完成，顯示成功訊息

**注意**: 現有標籤顯示，但用戶可選修改。修改內容導入時生效。

### Scenario 3: 既有影片

**使用者操作**:
1. 輸入已存在的影片 URL
2. 點擊「檢查是否建檔」
3. 看到提示: 「影片已建檔，請利用更新功能導入留言」
4. Modal 自動關閉

**說明**: 已導入的影片不允許重複導入。更新功能為未來功能。

---

## Code Examples

### 在 Controller 中使用

```php
<?php

namespace App\Http\Controllers;

use App\Services\YouTubeApiService;
use App\Services\CommentImportService;
use Illuminate\Http\Request;

class YouTubeApiImportController extends Controller
{
    public function __construct(
        private YouTubeApiService $youtubeService,
        private CommentImportService $importService
    ) {}

    public function getMetadata(Request $request)
    {
        $url = $request->validate(['video_url' => 'required|string']);

        try {
            // 解析 URL 取得 video_id
            $videoId = $this->extractVideoId($url['video_url']);

            // 檢查影片是否已存在
            if (Video::where('video_id', $videoId)->exists()) {
                return response()->json([
                    'success' => true,
                    'status' => 'existing_video',
                    'data' => ['video_id' => $videoId]
                ]);
            }

            // 取得 YouTube 元資料
            $metadata = $this->youtubeService->fetchVideoMetadata($videoId);
            $channelExists = Channel::where('channel_id', $metadata['channel_id'])->exists();

            return response()->json([
                'success' => true,
                'status' => 'metadata_ready',
                'data' => [
                    ...$metadata,
                    'import_mode' => $channelExists ? 'incremental' : 'new',
                    'existing_tags' => $channelExists
                        ? Channel::find($metadata['channel_id'])->tags
                        : null
                ]
            ], 202);

        } catch (InvalidVideoIdException $e) {
            return response()->json([
                'success' => false,
                'error' => '無效的 YouTube URL 格式'
            ], 400);
        } catch (YouTubeApiException $e) {
            return response()->json([
                'success' => false,
                'error' => '無法連接 YouTube API，請稍後重試'
            ], 502);
        }
    }

    public function confirmImport(Request $request)
    {
        $data = $request->validate([
            'video_id' => 'required|string|size:11',
            'video_metadata' => 'required|array',
            'selected_tags' => 'required|array|min:1',
            'import_mode' => 'required|in:new,incremental'
        ]);

        try {
            $result = $this->importService->executeFullImport(
                $data['video_id'],
                $data['video_metadata'],
                $data['selected_tags']
            );

            return response()->json([
                'success' => true,
                'status' => 'import_complete',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            \Log::error('Import failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => '導入失敗，請稍後重試'
            ], 500);
        }
    }

    private function extractVideoId(string $url): string
    {
        // 支援多種 URL 格式
        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|^)([a-zA-Z0-9_-]{11})/', $url, $matches);
        return $matches[1] ?? throw new InvalidVideoIdException();
    }
}
```

### Service 層使用

```php
<?php

namespace App\Services;

use App\Models\{Channel, Video, Comment, Author};
use Illuminate\Support\Facades\DB;

class CommentImportService
{
    public function __construct(
        private YouTubeApiService $youtubeService
    ) {}

    public function executeFullImport(
        string $videoId,
        array $videoMetadata,
        array $selectedTagIds
    ): array {
        return DB::transaction(function () use ($videoId, $videoMetadata, $selectedTagIds) {
            // 1. 建立或更新頻道
            $channel = Channel::updateOrCreate(
                ['channel_id' => $videoMetadata['channel_id']],
                [
                    'channel_name' => $videoMetadata['channel_name'],
                    'first_import_at' => $channel->first_import_at ?? now(),
                    'last_import_at' => now()
                ]
            );

            // 2. 建立影片
            $video = Video::create([
                'video_id' => $videoId,
                'channel_id' => $videoMetadata['channel_id'],
                'title' => $videoMetadata['title'],
                'published_at' => $videoMetadata['published_at']
            ]);

            // 3. 取得所有留言（遞迴）
            $allComments = $this->youtubeService->fetchAllComments($videoId);

            // 4. 建立 Authors 並插入 Comments
            $commentCount = 0;
            $replyCount = 0;

            foreach ($allComments as $commentData) {
                // get or create author
                Author::updateOrCreate(
                    ['author_channel_id' => $commentData['author_channel_id']],
                    ['name' => $commentData['author_name'] ?? null]
                );

                // 建立評論
                Comment::create([
                    'comment_id' => $commentData['comment_id'],
                    'video_id' => $videoId,
                    'author_channel_id' => $commentData['author_channel_id'],
                    'text' => $commentData['text'],
                    'like_count' => $commentData['like_count'],
                    'published_at' => $commentData['published_at'],
                    'parent_comment_id' => $commentData['parent_comment_id']
                ]);

                if ($commentData['parent_comment_id'] === null) {
                    $commentCount++;
                } else {
                    $replyCount++;
                }
            }

            // 5. 更新影片 comment_count
            $video->update(['comment_count' => $commentCount + $replyCount]);

            // 6. 同步標籤關係（多對多）
            $channel->tags()->sync($selectedTagIds);

            return [
                'video_id' => $videoId,
                'comments_imported' => $commentCount,
                'replies_imported' => $replyCount,
                'total_imported' => $commentCount + $replyCount,
                'import_duration_seconds' => 12.5
            ];
        });
    }
}
```

### 前端 JavaScript 範例

```javascript
// modal.blade.php 中的 JavaScript

async function handleMetadataFetch() {
    const url = document.querySelector('#video-url').value;

    try {
        const response = await fetch('/api/youtube-import/metadata', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ video_url: url })
        });

        const result = await response.json();

        if (result.success) {
            if (result.status === 'existing_video') {
                alert('影片已建檔，請利用更新功能導入留言');
                closeModal();
                return;
            }

            // 顯示預覽
            showPreviewStep(result.data);
        } else {
            alert(`錯誤: ${result.error}`);
        }
    } catch (error) {
        alert('網路錯誤，請稍後重試');
    }
}

async function handleConfirmImport(metadata, selectedTags) {
    const response = await fetch('/api/youtube-import/confirm-import', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            video_id: metadata.video_id,
            video_metadata: metadata,
            selected_tags: selectedTags,
            import_mode: metadata.import_mode
        })
    });

    const result = await response.json();

    if (result.success) {
        // 顯示成功訊息
        showSuccessMessage(`成功導入 ${result.data.total_imported} 則留言`);

        // 2 秒後關閉 modal 並重新載入列表
        setTimeout(() => {
            closeModal();
            reloadCommentsList();
        }, 2000);
    } else {
        alert(`導入失敗: ${result.error}`);
    }
}

async function reloadCommentsList() {
    // AJAX 方式重新載入留言列表（無頁面刷新）
    const response = await fetch('/comments?ajax=1');
    const html = await response.text();
    document.querySelector('#comments-list').innerHTML = html;
}
```

---

## Testing the Feature

### 手動測試清單

#### Test Case 1: 新影片 + 新頻道
- [ ] 輸入新影片 URL
- [ ] 系統正確識別「新頻道」
- [ ] 強制選擇至少 1 個標籤
- [ ] 導入完成，留言列表更新
- [ ] 檢查 DB: channels, videos, comments, channel_tags 表都有新記錄

#### Test Case 2: 新影片 + 既有頻道
- [ ] 輸入現有頻道的新影片 URL
- [ ] 系統顯示「已存在頻道的新影片」
- [ ] 允許修改標籤
- [ ] 導入完成，標籤更新

#### Test Case 3: 既有影片
- [ ] 輸入已導入過的影片 URL
- [ ] 系統提示「已建檔」
- [ ] Modal 關閉，無導入

#### Test Case 4: 錯誤處理
- [ ] 無效 URL 格式 → 錯誤訊息
- [ ] YouTube API 配額用盡 → 顯示「重試」按鈕
- [ ] 影片不存在或已刪除 → 錯誤訊息

### 自動化測試

```bash
# 運行功能測試
php artisan test tests/Feature/YoutubeApiImportTest.php

# 運行單元測試
php artisan test tests/Unit/YoutubeCommentImportServiceTest.php

# 運行所有測試
php artisan test
```

---

## Common Issues & Troubleshooting

### Issue 1: YouTube API 配額用盡

**症狀**: 所有 API 呼叫返回 `quotaExceeded` 錯誤

**解決**:
1. 檢查 Google Cloud Console 的配額使用情況
2. 申請提高配額（需 Google 帳號驗證）
3. 提示用戶等待 24 小時配額重置
4. 在 `.env` 中設置 `YOUTUBE_API_TIMEOUT=60` 以增加超時時間

### Issue 2: 影片 ID 提取失敗

**症狀**: 即使 URL 有效仍提示「無效格式」

**檢查**:
- YouTube URL 格式支援: `youtube.com/watch?v=` 或 `youtu.be/`
- 影片 ID 長度必須為 11 字元
- 檢查 regex 模式是否正確

**測試 URL**:
```
✓ https://www.youtube.com/watch?v=dQw4w9WgXcQ
✓ https://youtu.be/dQw4w9WgXcQ
✓ youtu.be/dQw4w9WgXcQ
✓ dQw4w9WgXcQ (直接 ID)
✗ https://www.youtube.com/channel/... (頻道 URL，不支持)
```

### Issue 3: 留言列表不更新

**症狀**: 導入成功但留言列表未變化

**檢查**:
- 檢查瀏覽器 console 是否有 JavaScript 錯誤
- 確認 AJAX 請求成功（Network 標籤）
- 檢查 `reloadCommentsList()` 函數是否正確實裝
- 清空瀏覽器快取，重新整理頁面

### Issue 4: 時間戳格式不一致

**症狀**: 資料庫中 published_at 格式混亂

**檢查**:
- YouTube API 返回 ISO 8601 格式（自動轉換）
- Laravel 模型應使用 datetime casting
- 檢查 migration 中 timestamp 欄位定義

**修正**:
```php
// 在 Comment Model 中
protected $casts = [
    'published_at' => 'datetime',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
];
```

---

## Performance Tips

### API 呼叫最佳化

1. **快取元資料**: 頻道和影片資訊（3 小時）
2. **批量操作**: 使用 `insertOrIgnore()` 或 `updateOrCreate()` 減少查詢
3. **進度顯示**: 長時間導入時顯示進度條（使用 progress callback）

### 資料庫最佳化

1. **索引**: 確保 `video_id`, `channel_id`, `parent_comment_id` 有索引
2. **分區**: 如果 comments 表超過 1M 記錄，考慮按 video_id 分區
3. **查詢**: 使用 eager loading 避免 N+1 查詢

```php
// ❌ N+1 問題
$comments = Comment::all();
foreach ($comments as $comment) {
    echo $comment->video->title;  // 每次都查詢
}

// ✓ 正確做法
$comments = Comment::with('video')->get();
foreach ($comments as $comment) {
    echo $comment->video->title;  // 已預加載
}
```

---

## Next Steps

1. **實裝測試**: 完成 YouTubeApiImportTest.php 中所有 3 個 User Story 測試
2. **增量導入功能**: 實裝「更新留言」功能（未來任務）
3. **UI 優化**: 添加進度條、錯誤恢復機制
4. **監控告警**: 設置 API 配額告警、大型導入耗時告警

---

## Additional Resources

- [YouTube Data API v3 文檔](https://developers.google.com/youtube/v3/docs)
- [Laravel 事務文檔](https://laravel.com/docs/database#transactions)
- [Google API 客戶端庫](https://github.com/googleapis/google-api-php-client)

---

**版本**: 1.0 | **最後更新**: 2025-11-17
