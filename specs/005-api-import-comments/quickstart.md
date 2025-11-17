# Quickstart: YouTube API 官方導入留言

**Branch**: `005-api-import-comments` | **Date**: 2025-11-17

---

## For Backend Developers

### Prerequisites

- Laravel 11.x project setup
- `.env` file with `YOUTUBE_API_KEY=<your-api-key>`
- MySQL database running

### 1. Run Database Migrations

```bash
# Create new migrations (copy from specs/005-api-import-comments/)
cp database/migrations/2025_11_17_add_comment_count_to_videos.php database/migrations/
cp database/migrations/2025_11_17_add_parent_id_to_comments.php database/migrations/

# Execute migrations
php artisan migrate
```

### 2. Install Google API Client

```bash
composer require google/apiclient
```

### 3. Create Services & Controller

**Copy these files from implementation plan**:

- `app/Services/YoutubeApiClient.php` – YouTube API wrapper with rate limiting
- `app/Services/CommentImportService.php` – Core 3-stage import logic
- `app/Services/ChannelTagManager.php` – Tag pivot management
- `app/Http/Controllers/Api/ImportCommentsController.php` – REST endpoints

### 4. Register API Routes

**In `routes/api.php`**:

```php
Route::middleware('auth:sanctum')->group(function () {
  Route::post('/comments/check', [ImportCommentsController::class, 'check']);
  Route::post('/comments/import', [ImportCommentsController::class, 'import']);
});
```

### 5. Test Endpoints with curl

**Test 1: Check existing video**

```bash
curl -X POST http://localhost:8000/api/comments/check \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"video_url": "https://youtu.be/dQw4w9WgXcQ"}'
```

Expected response (if video already in DB):
```json
{
  "status": "video_exists",
  "message": "影片已建檔，請利用更新功能導入留言"
}
```

**Test 2: Check new video + existing channel**

```bash
curl -X POST http://localhost:8000/api/comments/check \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"video_url": "https://youtu.be/SOME_NEW_VIDEO_ID"}'
```

Expected response:
```json
{
  "status": "new_video_existing_channel",
  "channel_id": "UCxxxxx",
  "channel_title": "Channel Name",
  "video_title": "Video Title",
  "comment_count_total": 1250,
  "preview_comments": [...],
  "existing_channel_tags": [...]
}
```

**Test 3: Import new video with tags**

```bash
curl -X POST http://localhost:8000/api/comments/import \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "video_url": "https://youtu.be/SOME_NEW_VIDEO_ID",
    "scenario": "new_video_existing_channel",
    "channel_tags": [1, 3],
    "import_replies": true
  }'
```

Expected response:
```json
{
  "status": "success",
  "message": "成功導入 1247 則留言",
  "imported_comment_count": 1247,
  "imported_reply_count": 523,
  "total_imported": 1770,
  "channel_id": "UCxxxxx",
  "video_id": "SOME_NEW_VIDEO_ID",
  "timestamp": "2025-11-15 10:30:45"
}
```

### 6. Run Unit Tests

```bash
# Run all import-related tests
php artisan test --filter=ImportComments

# Run with coverage report
php artisan test --filter=ImportComments --coverage

# Expected: All tests pass per Test-First Development (Principle I)
```

### 7. Check Database Results

```sql
-- Verify channel imported
SELECT * FROM channels WHERE channel_id = 'UCxxxxx';

-- Verify video + comment count
SELECT id, video_id, title, comment_count FROM videos WHERE video_id = 'SOME_NEW_VIDEO_ID';

-- Verify comments with parent_comment_id (for reply structure)
SELECT comment_id, author_channel_id, content, parent_comment_id, published_at FROM comments
  WHERE video_id = (SELECT id FROM videos WHERE video_id = 'SOME_NEW_VIDEO_ID')
  ORDER BY parent_comment_id, published_at;

-- Verify channel tags (pivot table)
SELECT c.name AS channel_name, t.name AS tag_name
  FROM channels c
  JOIN channel_tags ct ON c.id = ct.channel_id
  JOIN tags t ON ct.tag_id = t.id
  WHERE c.channel_id = 'UCxxxxx';
```

---

## For Frontend Developers

### Prerequisites

- Blade templating knowledge
- Alpine.js familiarity (for modal interactions)
- Tailwind CSS understanding

### 1. Create Modal Component

**In `resources/views/components/import-comments-modal.blade.php`**:

```blade
<div x-data="importCommentsModal()" x-show="open" @keydown.escape="open = false" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
  <!-- Modal container -->
  <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-auto p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6 border-b pb-4">
      <h2 class="text-xl font-bold text-gray-800">官方API導入留言</h2>
      <button @click="open = false" class="text-gray-400 hover:text-gray-600">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
      </button>
    </div>

    <!-- Step 1: URL Input -->
    <div x-show="step === 1" class="mb-6">
      <label class="block text-sm font-medium text-gray-700 mb-2">YouTube 影片網址</label>
      <input type="text" x-model="videoUrl" placeholder="https://youtu.be/dQw4w9WgXcQ"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />

      <button @click="checkVideo()" :disabled="!videoUrl" class="mt-4 w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50">
        <span x-show="!checking">檢查是否建檔</span>
        <span x-show="checking">檢查中...</span>
      </button>

      <div x-show="error" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
        <p x-text="error"></p>
        <button @click="error = ''" class="mt-2 text-red-600 hover:text-red-800 underline">關閉</button>
      </div>
    </div>

    <!-- Step 2: Preview + Tags (Existing Channel) -->
    <div x-show="step === 2 && scenario === 'new_video_existing_channel'" class="mb-6">
      <h3 class="font-semibold text-lg mb-4">新影片 • 已存在頻道</h3>

      <!-- Channel & Video Info -->
      <div class="bg-gray-50 p-4 rounded-lg mb-4">
        <p><strong>頻道：</strong> <span x-text="previewData.channel_title"></span></p>
        <p><strong>影片：</strong> <span x-text="previewData.video_title"></span></p>
        <p><strong>留言數：</strong> <span x-text="previewData.comment_count_total"></span></p>
      </div>

      <!-- Preview Comments -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">預覽最新 5 則留言</label>
        <div class="space-y-3 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3 bg-white">
          <template x-for="comment in previewData.preview_comments" :key="comment.comment_id">
            <div class="border-b pb-2 last:border-b-0">
              <p class="text-sm text-gray-600"><strong x-text="comment.author_name"></strong></p>
              <p class="text-sm" x-text="comment.content"></p>
              <p class="text-xs text-gray-400 mt-1" x-text="comment.published_at"></p>
            </div>
          </template>
        </div>
      </div>

      <!-- Tag Selection -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">頻道標籤（可修改）</label>
        <div class="space-y-2">
          <template x-for="tag in previewData.existing_channel_tags" :key="tag.id">
            <label class="flex items-center">
              <input type="checkbox" :value="tag.id" x-model="selectedTags" class="mr-2" />
              <span class="inline-block w-3 h-3 rounded-full mr-2" :style="`background-color: ${tag.color}`"></span>
              <span x-text="tag.name"></span>
            </label>
          </template>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3">
        <button @click="step = 1" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">返回</button>
        <button @click="importComments()" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">確認導入</button>
      </div>
    </div>

    <!-- Step 2: Preview + Tags (New Channel) -->
    <div x-show="step === 2 && scenario === 'new_video_new_channel'" class="mb-6">
      <h3 class="font-semibold text-lg mb-4">新影片 • 新頻道</h3>

      <!-- Channel & Video Info -->
      <div class="bg-gray-50 p-4 rounded-lg mb-4">
        <p><strong>頻道：</strong> <span x-text="previewData.channel_title"></span></p>
        <p><strong>影片：</strong> <span x-text="previewData.video_title"></span></p>
        <p><strong>留言數：</strong> <span x-text="previewData.comment_count_total"></span></p>
      </div>

      <!-- Preview Comments -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">預覽最新 5 則留言</label>
        <div class="space-y-3 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3 bg-white">
          <template x-for="comment in previewData.preview_comments" :key="comment.comment_id">
            <div class="border-b pb-2 last:border-b-0">
              <p class="text-sm text-gray-600"><strong x-text="comment.author_name"></strong></p>
              <p class="text-sm" x-text="comment.content"></p>
              <p class="text-xs text-gray-400 mt-1" x-text="comment.published_at"></p>
            </div>
          </template>
        </div>
      </div>

      <!-- Tag Selection (Required) -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">選擇頻道標籤（必須至少選 1 個）</label>
        <div class="space-y-2">
          <template x-for="tag in previewData.available_tags" :key="tag.id">
            <label class="flex items-center">
              <input type="checkbox" :value="tag.id" x-model="selectedTags" class="mr-2" />
              <span class="inline-block w-3 h-3 rounded-full mr-2" :style="`background-color: ${tag.color}`"></span>
              <span x-text="tag.name"></span>
            </label>
          </template>
        </div>
        <p x-show="selectedTags.length === 0" class="text-red-600 text-sm mt-2">請至少選擇一個標籤</p>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3">
        <button @click="step = 1" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">返回</button>
        <button @click="importComments()" :disabled="selectedTags.length === 0" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">確認導入</button>
      </div>
    </div>

    <!-- Step 3: Success Message -->
    <div x-show="step === 3" class="mb-6 text-center">
      <div class="mb-6">
        <svg class="w-16 h-16 text-green-600 mx-auto" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
      </div>
      <h3 class="text-xl font-bold text-gray-800 mb-2" x-text="successMessage"></h3>
      <p class="text-gray-600 mb-6">導入已完成，列表將自動更新</p>
      <button @click="closeModal()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">完成</button>
    </div>

    <!-- Video Already Exists Message -->
    <div x-show="step === 4" class="mb-6 text-center">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
        <p class="text-blue-800">影片已建檔，請利用更新功能導入留言</p>
      </div>
      <button @click="open = false" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">關閉</button>
    </div>

    <!-- Importing Spinner -->
    <div x-show="importing" class="text-center py-12">
      <div class="inline-block animate-spin">
        <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a8 8 0 100 16 8 8 0 000-16zm0 14v2m-4.243-1.757l1.414 1.414m2.829-2.829l1.414 1.414m4.243 1.757l-1.414-1.414m-2.829 2.829l-1.414-1.414m1.757 4.243h2m-2-14h-2" />
        </svg>
      </div>
      <p class="mt-4 text-gray-600">正在導入留言...</p>
    </div>
  </div>
</div>

<script>
function importCommentsModal() {
  return {
    open: false,
    step: 1,  // 1: URL input, 2: Preview, 3: Success, 4: Video exists
    videoUrl: '',
    scenario: null,  // 'new_video_existing_channel' or 'new_video_new_channel'
    previewData: {},
    selectedTags: [],
    checking: false,
    importing: false,
    error: '',
    successMessage: '',

    async checkVideo() {
      this.checking = true;
      this.error = '';
      try {
        const response = await fetch('/api/comments/check', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ video_url: this.videoUrl })
        });

        const data = await response.json();

        if (data.status === 'video_exists') {
          this.step = 4;  // Video exists message
        } else if (data.status === 'new_video_existing_channel' || data.status === 'new_video_new_channel') {
          this.scenario = data.status;
          this.previewData = data;
          this.selectedTags = data.existing_channel_tags?.map(t => t.id) || [];
          this.step = 2;  // Show preview
        } else {
          this.error = data.message || '發生錯誤';
        }
      } catch (e) {
        this.error = '無法連接到伺服器';
      } finally {
        this.checking = false;
      }
    },

    async importComments() {
      this.importing = true;
      this.error = '';
      try {
        const response = await fetch('/api/comments/import', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            video_url: this.videoUrl,
            scenario: this.scenario,
            channel_tags: this.selectedTags,
            import_replies: true
          })
        });

        const data = await response.json();

        if (data.status === 'success') {
          this.successMessage = data.message;
          this.step = 3;  // Success message
        } else {
          this.error = data.message || '導入失敗';
        }
      } catch (e) {
        this.error = '無法連接到伺服器';
      } finally {
        this.importing = false;
      }
    },

    closeModal() {
      this.open = false;
      // Trigger AJAX refresh of comments list
      window.location.reload();  // Or use a smarter AJAX reload
    }
  };
}
</script>
```

### 2. Add Button to Comments Page

**In `resources/views/pages/comments.blade.php` (or wherever comments are listed)**:

```blade
<div class="flex justify-between items-center mb-6">
  <h1 class="text-3xl font-bold">留言列表</h1>

  <!-- Official API Import Button -->
  <button @click="open = true" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
    官方API導入
  </button>
</div>

<!-- Import Modal -->
<x-import-comments-modal />

<!-- Comments list below -->
<div id="comments-list">
  @foreach($comments as $comment)
    <div class="border-b py-4">
      <p class="font-semibold">{{ $comment->author_name }}</p>
      <p>{{ $comment->content }}</p>
      <p class="text-gray-500 text-sm">{{ $comment->published_at }}</p>
    </div>
  @endforeach
</div>
```

### 3. Test Modal Interactions

1. Click "官方API導入" button → Modal opens
2. Input a YouTube URL → Click "檢查是否建檔"
3. See preview data → Optionally modify tags
4. Click "確認導入" → Success message appears
5. Click "完成" → Modal closes + list refreshes

---

## Summary

| Component | Location | Status |
|-----------|----------|--------|
| Migrations | `database/migrations/` | NEW (2 files) |
| Services | `app/Services/` | NEW (3 files) |
| Controller | `app/Http/Controllers/Api/` | NEW |
| Modal Component | `resources/views/components/` | NEW |
| API Routes | `routes/api.php` | MODIFY (add 2 endpoints) |
| Comments Page | `resources/views/pages/` | MODIFY (add button + modal) |

All new code is **independent of urtubeapi** and uses standard Laravel patterns for API-First Design (Principle II).
