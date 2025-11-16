# Quickstart: YouTube API Comments Import

**Feature**: `005-api-import-comments`
**Target Audience**: Developers, QA testers, feature integrators
**Estimated Setup Time**: 15 minutes

---

## Overview

This guide walks through:
1. **Setup**: Installing dependencies and configuring YouTube API key
2. **Development**: Running tests and implementing the service
3. **Testing**: Manual testing of the import workflow
4. **Integration**: Connecting to the existing comments interface

---

## Prerequisites

- Laravel 10/11 running locally with PostgreSQL database
- PHP 8.1+ with Composer
- YouTube API key (instructions below)
- Existing `comments`, `videos`, `channels` tables in database

---

## Part 1: Setup & Configuration

### 1.1 Install YouTube API Client Library

```bash
cd /Users/yueyu/Dev/DISINFO_SCANNER
composer require google/apiclient
```

This installs the official `google/apiclient` package for YouTube API v3.

### 1.2 Create YouTube API Key

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create new project: "DISINFO_SCANNER"
3. Enable YouTube API v3:
   - Search "YouTube Data API v3" in APIs & Services
   - Click "Enable"
4. Create API Key:
   - Go to "Credentials"
   - Click "Create Credentials" → "API Key"
   - Copy the key
5. Add to `.env`:
   ```
   YOUTUBE_API_KEY=AIzaSy... (paste your key here)
   ```

**Quota Notes**:
- YouTube API v3 quotas measured in "units":
  - `videos.list`: 1 unit per call
  - `commentThreads.list`: 1 unit per call
  - Free quota: 10,000 units/day (enough for ~5,000 comment fetches)
- For production, request quota increase after testing

### 1.3 Create Blade Templates

Create three UI components in `resources/views/comments/`:

**File**: `import-modal.blade.php`
```blade
<div id="import-modal" class="modal">
  <div class="modal-content">
    <h2>官方API導入</h2>
    <form id="import-form">
      @csrf
      <label>YouTube 影片網址</label>
      <input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=..." required>

      <div class="actions">
        <button type="submit">取得影片資訊</button>
        <button type="button" onclick="closeModal()">取消</button>
      </div>
    </form>
  </div>
</div>
```

**File**: `metadata-dialog.blade.php`
```blade
<div id="metadata-dialog" style="display: none;">
  <div class="dialog-content">
    <h3>確認影片資訊</h3>
    <div id="metadata-info">
      <p><strong>標題:</strong> <span id="meta-title"></span></p>
      <p><strong>頻道:</strong> <span id="meta-channel"></span></p>
    </div>

    <label>選擇標籤 (可多選)</label>
    <div id="tags-container">
      <!-- Populated dynamically -->
    </div>

    <div class="actions">
      <button onclick="confirmMetadata()">確認</button>
      <button onclick="cancelImport()">取消</button>
    </div>
  </div>
</div>
```

**File**: `preview-dialog.blade.php`
```blade
<div id="preview-dialog" style="display: none;">
  <div class="dialog-content">
    <h3>留言預覽</h3>
    <div id="preview-comments">
      <!-- Populated dynamically -->
    </div>
    <p id="total-comments-info"></p>

    <div class="actions">
      <button onclick="confirmImport()" id="confirm-import-btn">確認導入</button>
      <button onclick="cancelImport()">取消</button>
    </div>
  </div>
</div>
```

### 1.4 Verify Database Tables

Ensure the following tables exist (or create migrations):

```bash
php artisan make:migration create_videos_table
php artisan make:migration create_channels_table
php artisan make:migration create_comments_table
```

Check that columns match `data-model.md`:
- `videos`: `id`, `video_id`, `channel_id`, `title`, `published_at`, `created_at`, `updated_at`
- `channels`: `id`, `channel_id`, `name`, `video_count`, `comment_count`, `first_import_at`, `last_import_at`, `created_at`, `updated_at`
- `comments`: `id`, `comment_id`, `video_id`, `author_channel_id`, `text`, `like_count`, `parent_comment_id`, `published_at`, `created_at`, `updated_at`

Run migrations:
```bash
php artisan migrate
```

---

## Part 2: Development Setup

### 2.1 Create Service Layer

**File**: `app/Services/YouTubeApiService.php`

Skeleton (detailed implementation in `/speckit.tasks`):

```php
<?php

namespace App\Services;

use Google_Client;
use Google_Service_YouTube;
use Illuminate\Support\Facades\Log;
use Exception;

class YouTubeApiService
{
    protected Google_Service_YouTube $youTubeService;

    public function __construct()
    {
        $client = new Google_Client();
        $client->setApplicationName("DISINFO_SCANNER");
        $client->setDeveloperKey(env('YOUTUBE_API_KEY'));
        $this->youTubeService = new Google_Service_YouTube($client);
    }

    /**
     * Fetch video metadata from YouTube API
     *
     * @param string $videoId YouTube video ID
     * @return array Video metadata: title, channel_id, channel_name, published_at
     * @throws YouTubeApiException
     */
    public function fetchVideoMetadata(string $videoId): array
    {
        // Implementation per contract (research.md)
        // Returns: ['video_id', 'title', 'channel_id', 'channel_name', 'published_at']
    }

    /**
     * Fetch all comments (with recursive replies)
     *
     * @param string $videoId YouTube video ID
     * @param array $options Optional: max_timestamp for incremental, existing_comment_ids
     * @return array Comments with recursive replies
     * @throws YouTubeApiException
     */
    public function fetchComments(string $videoId, array $options = []): array
    {
        // Implementation per contract (research.md)
        // Returns: ['comments' => [...], 'total_count' => X, 'stopped_by' => 'timestamp'|'duplicate_id']
    }

    /**
     * Recursively fetch replies to a comment
     */
    protected function fetchRepliesRecursive(string $commentId, array $options = []): array
    {
        // Helper method for recursive reply fetching
    }
}
```

### 2.2 Create Comment Import Service

**File**: `app/Services/CommentImportService.php`

Skeleton:

```php
<?php

namespace App\Services;

use App\Models\{Video, Channel, Comment};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommentImportService
{
    protected YouTubeApiService $youtubeService;

    public function __construct(YouTubeApiService $youtubeService)
    {
        $this->youtubeService = $youtubeService;
    }

    /**
     * Execute complete import workflow with transaction
     *
     * @param string $videoId YouTube video ID
     * @param string|null $traceId Optional trace ID for logging
     * @return array Import result: ['total_comments' => X, 'new_video' => bool, ...]
     * @throws Exception
     */
    public function importComments(string $videoId, ?string $traceId = null): array
    {
        $traceId = $traceId ?? Str::uuid();

        return DB::transaction(function () use ($videoId, $traceId) {
            // 1. Check if video exists
            $isNewVideo = !Video::where('video_id', $videoId)->exists();

            // 2. If new, fetch and save metadata
            if ($isNewVideo) {
                $metadata = $this->youtubeService->fetchVideoMetadata($videoId);
                // Save video and channel (per FR-016 logic)
            }

            // 3. Fetch all comments (incremental if existing)
            $commentData = $this->youtubeService->fetchComments($videoId, [
                'max_timestamp' => $this->getMaxCommentTimestamp($videoId),
                'existing_comment_ids' => $this->getExistingCommentIds($videoId)
            ]);

            // 4. Insert comments (with parent linking)
            $this->insertComments($videoId, $commentData['comments']);

            // 5. Update video/channel counts (per FR-016)
            $this->updateCounts($videoId);

            // 6. Log success
            Log::info('import_completed', [
                'trace_id' => $traceId,
                'video_id' => $videoId,
                'total_comments' => count($commentData['comments']),
                'status' => 'success'
            ]);

            return [
                'total_comments' => count($commentData['comments']),
                'new_video' => $isNewVideo,
                'trace_id' => $traceId
            ];
        });
    }

    protected function getMaxCommentTimestamp(string $videoId): ?string
    {
        // Query existing comments, return max published_at
    }

    protected function getExistingCommentIds(string $videoId): array
    {
        // Query existing comment IDs for duplicate detection
    }

    protected function insertComments(string $videoId, array $comments): void
    {
        // Flatten recursive tree and insert with proper parent_comment_id
    }

    protected function updateCounts(string $videoId): void
    {
        // Update videos and channels tables per FR-016
    }
}
```

### 2.3 Create Controller

**File**: `app/Http/Controllers/YoutubeApiImportController.php`

Skeleton:

```php
<?php

namespace App\Http\Controllers;

use App\Services\{YouTubeApiService, CommentImportService};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class YoutubeApiImportController extends Controller
{
    protected YouTubeApiService $youtubeService;
    protected CommentImportService $importService;

    public function __construct(
        YouTubeApiService $youtubeService,
        CommentImportService $importService
    ) {
        $this->youtubeService = $youtubeService;
        $this->importService = $importService;
    }

    public function showImportForm()
    {
        return view('comments.import-modal');
    }

    public function getMetadata(Request $request): JsonResponse
    {
        // Validate video URL, extract video ID, fetch metadata
    }

    public function confirmMetadata(Request $request): JsonResponse
    {
        // Validate metadata matches, store in session
    }

    public function getPreview(Request $request): JsonResponse
    {
        // Fetch 5 sample comments (incremental if existing)
    }

    public function confirmImport(Request $request): JsonResponse
    {
        // Execute full import via CommentImportService
    }
}
```

### 2.4 Add Routes

**File**: `routes/web.php`

```php
Route::middleware(['auth'])->group(function () {
    Route::prefix('comments/import')->group(function () {
        Route::get('/', [YoutubeApiImportController::class, 'showImportForm'])->name('import.form');
        Route::post('/metadata', [YoutubeApiImportController::class, 'getMetadata'])->name('import.metadata');
        Route::post('/confirm-metadata', [YoutubeApiImportController::class, 'confirmMetadata']);
        Route::post('/preview', [YoutubeApiImportController::class, 'getPreview'])->name('import.preview');
        Route::post('/confirm-import', [YoutubeApiImportController::class, 'confirmImport'])->name('import.execute');
    });
});
```

---

## Part 3: Testing

### 3.1 Run Test Suite

```bash
# Create test files (see tests/ directory layout in plan.md)
php artisan make:test Unit/YouTubeApiServiceTest
php artisan make:test Contract/YouTubeApiContractTest
php artisan make:test Integration/CommentImportWorkflowTest

# Run all tests
php artisan test

# Run specific test
php artisan test tests/Integration/CommentImportWorkflowTest.php
```

### 3.2 Manual Testing

1. **Access Import Form**:
   - Navigate to `/comments/import`
   - Verify form displays

2. **Test New Video Import**:
   - Enter valid YouTube URL: https://www.youtube.com/watch?v=dQw4w9WgXcQ
   - Verify metadata dialog shows title/channel
   - Select tags, confirm
   - Verify preview shows sample comments
   - Confirm import
   - Verify comments saved in database

3. **Test Existing Video Update**:
   - After first import, re-import same video
   - Verify only new comments fetched
   - Verify no duplicates created

4. **Test Error Handling**:
   - Enter invalid URL: `https://invalid-url.com` → expect error
   - Enter deleted video ID → expect "Video not found"
   - Simulate API failure (disable key) → expect quota error

---

## Part 4: Integration with Comments Interface

### 4.1 Add Import Button to Comments List

**File**: `resources/views/comments/index.blade.php`

Add button near comment controls:

```blade
<div class="comment-actions">
  <button class="btn btn-primary" onclick="openImportModal()">
    官方API導入
  </button>
</div>
```

### 4.2 Add JavaScript Event Handler

```javascript
function openImportModal() {
  // Load import form via AJAX into modal
  fetch('/comments/import')
    .then(resp => resp.text())
    .then(html => {
      document.getElementById('modal').innerHTML = html;
      showModal();
    });
}

// Form submission handler
document.getElementById('import-form').addEventListener('submit', async (e) => {
  e.preventDefault();

  const url = document.querySelector('[name="youtube_url"]').value;
  const resp = await fetch('/comments/import/metadata', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('[name="_token"]').value
    },
    body: JSON.stringify({ youtube_url: url })
  });

  const data = await resp.json();

  if (data.status === 'success' && data.data.action === 'show_metadata_dialog') {
    // Show metadata dialog
    showMetadataDialog(data.data.metadata);
  }
});
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| `YOUTUBE_API_KEY not set` | Add key to `.env`, restart server |
| `API quota exceeded` | Request quota increase at Google Cloud Console |
| `Video not found` | Verify video is public and URL is valid |
| `Comments disabled` | Video owner disabled comments, choose different video |
| `Database lock timeout` | Transaction taking too long, check for queries holding locks |
| `Duplicate key violation` | Retry, transaction should rollback and allow retry |

---

## Next Steps

After completing this setup:

1. **Run `/speckit.tasks`** to generate detailed task list for implementation
2. **Start with contract tests** (TDD per Constitution)
3. **Implement YouTube API service** using research.md patterns
4. **Test incrementally** as each method completes
5. **Integrate UI** only after services proven via tests

---

## Reference Documentation

- **Feature Spec**: `specs/005-api-import-comments/spec.md`
- **Research**: `specs/005-api-import-comments/research.md`
- **Data Model**: `specs/005-api-import-comments/data-model.md`
- **API Contracts**: `specs/005-api-import-comments/contracts/`
- **Implementation Tasks**: `specs/005-api-import-comments/tasks.md` (generated by `/speckit.tasks`)

---

## Support

For questions or issues:
1. Check feature spec for requirements clarification
2. Review research.md for architecture decisions
3. Consult data-model.md for schema details
4. Check existing test cases for examples

