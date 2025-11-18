# Quickstart: Video Incremental Update

**Feature**: 007-video-incremental-update
**Date**: 2025-11-18
**Purpose**: Step-by-step guide to implement and test the incremental video update feature

## Prerequisites

Before starting implementation, ensure:

1. ✅ **Specification Complete**: [spec.md](./spec.md) finalized with no `[NEEDS CLARIFICATION]` markers
2. ✅ **Clarifications Resolved**: Session 2025-11-18 documented with answers for concurrent updates and timeout handling
3. ✅ **Research Complete**: [research.md](./research.md) covers all technical decisions
4. ✅ **Data Model Defined**: [data-model.md](./data-model.md) confirms no schema changes needed
5. ✅ **API Contracts Ready**: [contracts/](./contracts/) contains OpenAPI specs for preview and import endpoints
6. ✅ **Environment Setup**:
   - PHP 8.2+ installed
   - Laravel 12.38.1 running
   - MySQL/MariaDB accessible
   - YouTube Data API key configured in `.env` (`YOUTUBE_API_KEY`)
   - Existing tables: `videos`, `comments`, `channels` (from previous features)

---

## Implementation Workflow (TDD Approach)

Follow this strict TDD (Test-Driven Development) sequence as per Constitution Principle I:

### Phase 1: Contract Tests (RED)

**Goal**: Write API contract tests that fail initially

```bash
# Create test file
touch tests/Feature/VideoIncrementalUpdateTest.php
```

**Test Cases to Write** (before any implementation):

```php
// tests/Feature/VideoIncrementalUpdateTest.php

test('preview endpoint returns new comment count and first 5 comments', function() {
    // Arrange: Create video with 10 existing comments
    // Act: POST /api/video-update/preview with video_id
    // Assert: Response has 200, success=true, new_comment_count=0 (no new comments yet)
});

test('preview endpoint shows new comments after manual insert', function() {
    // Arrange: Video with comments, manually insert 3 new comments with later published_at
    // Act: POST /api/video-update/preview
    // Assert: new_comment_count=3, preview_comments has 3 items in chronological order
});

test('import endpoint persists new comments and updates video', function() {
    // Arrange: Video with 5 new comments available
    // Act: POST /api/video-update/import
    // Assert: 5 new comments inserted, video.comment_count updated, video.updated_at set
});

test('import endpoint enforces 500-comment limit', function() {
    // Arrange: Mock YouTube API to return 800 comments
    // Act: POST /api/video-update/import
    // Assert: imported_count=500, remaining=300, has_more=true
});

test('concurrent imports are idempotent', function() {
    // Arrange: Video with 10 new comments
    // Act: Call import endpoint twice simultaneously
    // Assert: No duplicate comment_id in database, final count correct
});
```

**Run Tests** (they should FAIL - RED):
```bash
php artisan test --filter=VideoIncrementalUpdate
# Expected: All tests fail (endpoints don't exist yet)
```

---

### Phase 2: Implement Backend API (GREEN)

**Goal**: Make tests pass with minimal code

#### Step 2.1: Create API Controller

```bash
# Create controller
php artisan make:controller Api/VideoUpdateController
```

**Implement** `app/Http/Controllers/Api/VideoUpdateController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VideoIncrementalUpdateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VideoUpdateController extends Controller
{
    public function __construct(
        private VideoIncrementalUpdateService $updateService
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_id' => 'required|string|max:11|exists:videos,video_id',
        ]);

        try {
            $result = $this->updateService->getPreview($validated['video_id']);
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_id' => 'required|string|max:11|exists:videos,video_id',
        ]);

        try {
            $result = $this->updateService->executeImport($validated['video_id']);
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
```

#### Step 2.2: Create Orchestration Service

```bash
touch app/Services/VideoIncrementalUpdateService.php
```

**Implement** (see [data-model.md](./data-model.md) for data flow):

```php
<?php

namespace App\Services;

use App\Models\Video;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VideoIncrementalUpdateService
{
    public function __construct(
        private YouTubeApiService $youtubeApi,
        private CommentImportService $importService
    ) {}

    public function getPreview(string $videoId): array
    {
        // 1. Get last comment timestamp
        $lastCommentTime = Comment::where('video_id', $videoId)
            ->max('published_at');

        // 2. Fetch new comments from YouTube API (with publishedAfter filter)
        $newComments = $this->youtubeApi->fetchCommentsAfter($videoId, $lastCommentTime, 500);

        // 3. Return preview (first 5 + count)
        return [
            'video_id' => $videoId,
            'video_title' => Video::find($videoId)->title,
            'last_comment_time' => $lastCommentTime,
            'new_comment_count' => count($newComments),
            'preview_comments' => array_slice($newComments, 0, 5),
            'has_more' => count($newComments) > 500,
            'import_limit' => 500,
        ];
    }

    public function executeImport(string $videoId): array
    {
        // ... implementation following 500-limit + idempotent insert pattern
    }
}
```

#### Step 2.3: Extend YouTubeApiService

**Modify** `app/Services/YouTubeApiService.php`:

```php
public function fetchCommentsAfter(string $videoId, string $publishedAfter, int $maxResults = 500): array
{
    $this->validateVideoId($videoId);

    try {
        $response = $this->youtube->commentThreads->listCommentThreads('snippet,replies', [
            'videoId' => $videoId,
            'publishedAfter' => $publishedAfter, // RFC 3339 format
            'maxResults' => $maxResults,
            'order' => 'time',
            'textFormat' => 'plainText',
        ]);

        return $this->flattenCommentThreads($response->getItems());
    } catch (\Exception $e) {
        Log::error('YouTube API error in fetchCommentsAfter', [
            'video_id' => $videoId,
            'published_after' => $publishedAfter,
            'error' => $e->getMessage(),
        ]);
        throw new YouTubeApiException('Failed to fetch comments: ' . $e->getMessage());
    }
}
```

#### Step 2.4: Add Routes

**Modify** `routes/api.php`:

```php
use App\Http\Controllers\Api\VideoUpdateController;

Route::prefix('video-update')->group(function () {
    Route::post('/preview', [VideoUpdateController::class, 'preview']);
    Route::post('/import', [VideoUpdateController::class, 'import']);
});
```

**Run Tests** (should PASS - GREEN):
```bash
php artisan test --filter=VideoIncrementalUpdate
# Expected: All tests pass
```

---

### Phase 3: Implement Frontend Modal (GREEN)

#### Step 3.1: Create Modal Component

```bash
touch resources/views/videos/incremental-update-modal.blade.php
```

**Implement** (follow pattern from `resources/views/comments/import-modal.blade.php`):

```html
<!-- Modal structure with Tailwind CSS -->
<div id="incremental-update-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
    <!-- Modal content: header, preview section, footer buttons -->
</div>

<script>
// Modal interaction logic:
// 1. Open modal → AJAX POST /api/video-update/preview
// 2. Display preview (count + 5 comments)
// 3. Confirm button → AJAX POST /api/video-update/import
// 4. Display success, update Videos List table
</script>
```

#### Step 3.2: Modify Videos List Page

**Modify** `resources/views/videos/list.blade.php`:

1. **Truncate titles** to 15 Chinese characters:
```blade
@php
    $truncatedTitle = mb_strlen($video->title) > 15
        ? mb_substr($video->title, 0, 15) . '...'
        : $video->title;
@endphp
<span title="{{ $video->title }}">{{ $truncatedTitle }}</span>
```

2. **Add Update button**:
```blade
<button onclick="openUpdateModal('{{ $video->video_id }}', '{{ $video->title }}')"
        class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700">
    更新
</button>
```

3. **Include modal component**:
```blade
@include('videos.incremental-update-modal')
```

---

### Phase 4: Manual Testing

**Test Scenario 1: Preview with no new comments**
1. Navigate to Videos List page
2. Click "Update" button for any video
3. Verify modal shows: "No new comments to import", confirm button disabled

**Test Scenario 2: Preview with new comments**
1. Manually insert 3 new comments in database with `published_at > last_comment_time`
2. Click "Update" button
3. Verify modal shows: "剩下 3 則留言需要導入", 3 preview comments displayed

**Test Scenario 3: Import all new comments**
1. From preview modal (3 new comments), click "確認更新"
2. Verify success message: "成功導入 3 則留言"
3. Close modal, verify Videos List row updated (comment count +3)

**Test Scenario 4: Partial import (500 limit)**
1. Mock YouTube API to return 800 comments
2. Import → verify message: "成功導入 500 則留言。還有 300 則新留言可用..."
3. Click Update again → import next 300

---

## Deployment Checklist

Before merging to main branch:

- [ ] All unit tests pass (`php artisan test --filter=VideoIncrementalUpdate`)
- [ ] All integration tests pass (500-limit, idempotency)
- [ ] Manual testing completed (all 4 scenarios above)
- [ ] YouTube API quota impact assessed (incremental update uses fewer requests)
- [ ] UI tested in Chrome, Firefox, Safari
- [ ] Chinese character truncation tested with various title lengths
- [ ] Concurrent update test: two users update same video simultaneously
- [ ] Error handling tested: API quota exceeded, network failure, video deleted
- [ ] Database datetime format verified: all timestamps in `YYYY-MM-DD HH:MM:SS`
- [ ] Code review completed (check for SQL injection, XSS vulnerabilities)

---

## Rollback Plan

If issues arise post-deployment:

1. **Disable feature**: Comment out routes in `routes/api.php`
2. **Hide UI**: Remove "Update" button from `list.blade.php`
3. **Monitor logs**: Check Laravel logs for errors related to `VideoUpdateController`
4. **Rollback code**: `git revert <commit-hash>` if database corruption occurs

**Recovery**: No database migrations were created, so no schema rollback needed. Simply redeploy previous version.

---

## Performance Benchmarks

Expected performance (measured in dev environment):

| Operation | Target | Measured |
|-----------|--------|----------|
| Preview API call | < 3 seconds | TBD |
| Import 100 comments | < 20 seconds | TBD |
| Import 500 comments | < 60 seconds | TBD |
| Concurrent updates (2 users) | No errors, 0 duplicates | TBD |

**Monitoring**: Track YouTube API quota usage via Google Cloud Console after deployment.

---

## Next Steps

1. Run `/speckit.tasks` to generate detailed task breakdown
2. Implement tests (Phase 1)
3. Implement backend (Phase 2)
4. Implement frontend (Phase 3)
5. Manual testing (Phase 4)
6. Code review
7. Merge to main
