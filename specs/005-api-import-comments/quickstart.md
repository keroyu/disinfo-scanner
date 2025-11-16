# Quickstart: YouTube API Comments Import

**Feature**: YouTube API Comments Import
**Branch**: `005-api-import-comments`
**Date**: 2025-11-17

---

## Feature Overview

The YouTube API Comments Import feature enables users to import YouTube comments directly via the YouTube Data API v3 (not the existing urtubeapi service). It supports:

- **New Videos** (Clarification 2025-11-16-007): Capture metadata via existing "匯入" dialog, then import comments
- **Existing Videos** (Clarification 2025-11-16-001): Incremental updates fetching only new comments
- **Reply Comments** (Clarification 2025-11-16-006): Recursive import of all reply levels (all nesting depths)
- **Preview Mode** (Clarifications 2025-11-16-001, 2025-11-16-002): Review up to 5 sample comments WITHOUT database persistence
- **Smart Metadata Updates** (Clarifications 2025-11-16-003, 2025-11-16-004): Update only changed fields on channel/video records

---

## Clarifications Checklist

**All clarifications from spec.md incorporated**:

- ✅ **2025-11-16-001**: Preview shows 5 comments (no limit, but show 5 samples)
- ✅ **2025-11-16-002**: Preview comments NOT saved to DB until confirm
- ✅ **2025-11-16-003**: Channel fields updated: `comment_count` (all), `last_import_at` (all), `updated_at` (all); Initialize `video_count`, `first_import_at`, `created_at` for NEW channels only
- ✅ **2025-11-16-004**: Video fields updated: `updated_at` (all); Initialize `video_id`, `title`, `published_at`, `created_at` for NEW videos; Set `channel_id` for NEW channels
- ✅ **2025-11-16-005**: Cancellation: Close dialog, no DB changes; If import interrupted, next import uses incremental logic to handle partial state
- ✅ **2025-11-16-006**: Reply comments: Import ALL levels recursively (comment → reply → reply-to-reply → ...)
- ✅ **2025-11-16-007**: New video workflow: Input URL → Check DB → If new, invoke "匯入" dialog → After complete, auto-start comment preview
- ✅ **2025-11-17-001**: File separation: YouTubeApiService separate from UrtubeapiService

---

## Key Architectural Decisions

### 1. Service Isolation
- **New Service**: `YouTubeApiService.php` (YouTube API only)
- **Existing Service**: `UrtubeapiService.php` (untouched)
- **Rationale**: Complete separation prevents cross-contamination, enables independent testing

### 2. Data Model Changes
- **Add Column**: `parent_comment_id` to `comments` table
- **Purpose**: Support reply hierarchies (top-level → reply → reply-to-reply, etc.)
- **Migration**: One new migration required

### 3. UI Integration
- **New Button**: "API 導入" alongside existing "匯入" button
- **Workflow**:
  - For new videos: Route to existing "匯入" dialog for metadata → auto-proceed to comment import
  - For existing videos: Direct to comment preview

### 4. Technology Stack
- **API Client**: `google/apiclient` (Google's official SDK)
- **Framework**: Laravel 11 (existing)
- **Testing**: Pest (existing test framework)
- **Storage**: MySQL/SQLite (existing)

---

## Implementation Scope

### Files to Create

```
app/Services/YouTubeApiService.php
app/Http/Controllers/YouTubeApiImportController.php
database/migrations/YYYY_MM_DD_HHMMSS_add_parent_comment_id_to_comments_table.php
tests/Unit/Services/YouTubeApiServiceTest.php
tests/Feature/YouTubeApiImportTest.php
```

### Files to Modify

```
app/Models/Comment.php                  [Add parent_comment_id field + relation]
routes/api.php                          [Add new routes for API endpoints]
resources/views/comments/list.blade.php [Add "API 導入" button]
```

### Files to Leave Unchanged

```
app/Services/UrtubeapiService.php      [NO CHANGES]
app/Http/Controllers/ImportController.php  [NO CHANGES]
database/migrations/*_create_comments_table.php  [NO CHANGES]
```

---

## Dependencies

### New Composer Package

```bash
composer require google/apiclient:^2.15
```

### Environment Configuration

Add to `.env`:
```env
YOUTUBE_API_KEY=AIzaSyD...  # Get from Google Cloud Console
```

### No Other Dependencies
- Uses existing Laravel, Guzzle, Pest installations

---

## High-Level Workflow

### User Story 1: Import Comments for New Video

1. User clicks "API 導入" button on comments page
2. User enters YouTube video URL
3. System checks if video exists in DB
   - **If NO**: Routes to existing "匯入" dialog (web scraping for metadata)
   - User completes "匯入" workflow
   - After successful import, system auto-proceeds to comment import
4. System fetches 5 preview comments from YouTube API
5. User reviews preview and clicks "確認導入"
6. System fetches all comments + all reply levels recursively
7. System stores comments in DB, updates channel/video metadata
8. Success message shown with comment count

### User Story 2: Update Existing Video

1. User clicks "API 導入" button
2. User enters YouTube video URL for video already in DB
3. System fetches 5 **new** comments (since last import)
4. User reviews preview and clicks "確認導入"
5. System fetches all comments newer than last import
6. System stops at first duplicate (incremental safety)
7. Stores new comments, updates metadata
8. Success message shown with count of new comments

### User Story 3: Reply Comment Handling

1. System fetches top-level comments from API
2. For each comment with replies:
   - Call YouTube API `comments.list` with `parentId`
   - Recursively fetch all reply levels
3. Flatten all comments (top-level + replies) into storage array
4. Each reply has `parent_comment_id` pointing to its parent
5. Store all with correct hierarchy preserved

---

## Database Schema Changes

### Migration: Add parent_comment_id

```sql
ALTER TABLE comments ADD COLUMN parent_comment_id VARCHAR(255) NULLABLE AFTER comment_id;
ALTER TABLE comments ADD FOREIGN KEY (parent_comment_id) REFERENCES comments(comment_id) ON DELETE SET NULL;
ALTER TABLE comments ADD INDEX idx_parent_comment_id (parent_comment_id);
```

**Why nullable**: Top-level comments have `parent_comment_id = NULL`

**Why cascade on delete SET NULL**: If parent reply is deleted, child replies remain but lose parent reference

---

## API Endpoints (New)

### POST /api/youtube-import/preview

**Purpose**: Fetch preview comments without persisting

**Request**:
```json
{
    "video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
}
```

**Response (Existing Video)**:
```json
{
    "success": true,
    "status": "preview_ready",
    "data": {
        "video_id": "dQw4w9WgXcQ",
        "import_mode": "incremental",
        "preview_comments": [
            {
                "comment_id": "Ug_abc123",
                "text": "Great video!",
                "author_channel_id": "UCxyz",
                "like_count": 5,
                "published_at": "2025-11-10T12:00:00Z",
                "is_reply": false
            }
            // ... up to 5 comments
        ],
        "total_comments": 1250
    }
}
```

**Response (New Video)**:
```json
{
    "success": true,
    "status": "new_video_detected",
    "data": {
        "import_mode": "full",
        "action_required": "invoke_import_dialog"
    }
}
```

### POST /api/youtube-import/confirm

**Purpose**: Perform full import after user confirms

**Request**:
```json
{
    "video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
}
```

**Response (Success)**:
```json
{
    "success": true,
    "status": "import_complete",
    "data": {
        "video_id": "dQw4w9WgXcQ",
        "comments_imported": 1250,
        "replies_imported": 340,
        "total_imported": 1590,
        "import_mode": "full",
        "import_duration_seconds": 23.5
    }
}
```

---

## Service Architecture

### YouTubeApiService (New)

```php
class YouTubeApiService {
    public function fetchPreviewComments(string $videoId): array
    public function fetchAllComments(string $videoId, ?string $afterDate = null): array
    public function validateVideoId(string $videoId): bool
}
```

**Responsibilities**:
- Authenticate with YouTube API
- Fetch comments (preview and full)
- Handle API errors
- Log operations

**What it does NOT do**:
- Store data to database (controller's job)
- Handle HTTP requests (controller's job)
- Format API responses for frontend (controller's job)

### YouTubeApiImportController (New)

```php
class YouTubeApiImportController {
    public function preview(Request $request): JsonResponse
    public function confirm(Request $request): JsonResponse
}
```

**Responsibilities**:
- Validate user input (video URL)
- Check if video exists in DB
- Call YouTubeApiService
- Persist comments to database
- Update channel/video metadata
- Return JSON responses

---

## Error Handling

### YouTube API Errors

| Scenario | HTTP Code | Action |
|----------|-----------|--------|
| Video not found | 404 | Show: "Video not found. Check URL and try again." |
| Invalid API key | 401 | Show: "System configuration error. Contact admin." |
| Quota exceeded | 403 | Show: "YouTube API quota exceeded. Try again later." |
| Network error | 500 | Show: "Network error. Check connection and retry." |

### Validation Errors

| Scenario | HTTP Code | Action |
|----------|-----------|--------|
| Invalid URL format | 422 | Show: "Invalid YouTube URL format" |
| Video not in DB (for incremental) | 400 | Show: "Video not found. Use new video workflow." |
| Partial import with quota | 206 | Show: "Imported N comments before quota hit" |

### Database Errors

- **Duplicate comment**: Skip (don't re-insert)
- **Transaction failure**: Rollback, show error, allow retry
- **Foreign key violation**: Log error, show: "System error storing comment"

---

## Testing Strategy

### Unit Tests (YouTubeApiServiceTest)

```php
// Contract tests for service methods
test('fetchPreviewComments returns 5 comments')
test('fetchAllComments with afterDate filters results')
test('validateVideoId accepts valid formats')
test('fetchAllComments recursively fetches replies')
test('API error handling returns proper exceptions')
```

### Feature Tests (YouTubeApiImportTest)

```php
// End-to-end integration tests
test('preview endpoint returns 5 comments for existing video')
test('preview endpoint returns new_video_detected for new video')
test('confirm endpoint imports all comments with replies')
test('incremental import stops at first duplicate')
test('invalid URL returns 422 validation error')
test('quota exceeded returns 403 with partial counts')
test('parent_comment_id correctly set for replies')
```

### Manual Testing Checklist

- [ ] Test with new video (no metadata in DB)
- [ ] Test with existing video (incremental update)
- [ ] Test with video having multi-level replies
- [ ] Test with video having no comments
- [ ] Test with video having fewer than 5 comments
- [ ] Test with invalid video URL format
- [ ] Test with invalid video ID (doesn't exist)
- [ ] Test canceling preview (return to input)
- [ ] Test importing same video twice (duplicate detection)
- [ ] Verify parent_comment_id correctly set for all replies
- [ ] Verify channel.comment_count updated correctly
- [ ] Verify video.updated_at timestamp changed
- [ ] Verify logs contain trace ID and operation details

---

## Success Criteria (from spec)

| Criteria | Target | Testing |
|----------|--------|---------|
| Preview fetch | <3 seconds | Load test with 5 comments |
| Full import | <30 seconds | Measure with 1000-comment video |
| Incremental | <10 seconds | Import only new comments, measure time |
| 100% accuracy | No data loss | Verify all comments + replies stored |
| 99% duplicate handling | No duplicates | Test incremental updates 10+ times |
| Reply accuracy | 100% correct | Verify parent_comment_id on 50+ replies |
| Error messages | Clear/actionable | Manual test all error paths |

---

## Future Enhancements (Out of Scope)

- Background job for periodic re-imports
- Bulk import of multiple videos
- Comment sentiment analysis
- Rate limiting policy enforcement
- Webhook to notify on new comments
- Comment export functionality
- Parent reply highlighting in UI

---

## Rollback Plan

If feature needs to be disabled:

1. **Remove routes**: Comment out lines in `routes/api.php`
2. **Hide button**: Set `<button style="display:none;">` in view
3. **Revert migration**: Run `php artisan migrate:rollback`
4. **Delete files**: Remove `YouTubeApiService.php`, `YouTubeApiImportController.php`, test files
5. **Revert Model**: Remove `parent_comment_id` relation from Comment model

---

## Resources

- **YouTube API Documentation**: https://developers.google.com/youtube/v3
- **Google API PHP Client**: https://github.com/googleapis/google-api-php-client
- **Feature Spec**: `/specs/005-api-import-comments/spec.md`
- **Data Model**: `/specs/005-api-import-comments/data-model.md`
- **Service Contract**: `/specs/005-api-import-comments/contracts/youtube-api-service.md`
- **Controller Contract**: `/specs/005-api-import-comments/contracts/youtube-api-import-controller.md`

---

**Status**: ✅ Quickstart ready for implementation
