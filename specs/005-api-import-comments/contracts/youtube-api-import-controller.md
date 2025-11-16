# API Contract: YouTubeApiImportController

**Controller**: `App\Http\Controllers\YouTubeApiImportController`
**Type**: Laravel HTTP Controller
**Purpose**: Handle HTTP requests for YouTube API comment import workflows
**Feature**: YouTube API Comments Import

---

## Overview

The `YouTubeApiImportController` handles two main request flows:

1. **Preview Comments** (`POST /api/youtube-import/preview`)
   - User enters video URL → extract video ID → check existence → fetch 5 sample comments
   - Return to user without persisting

2. **Confirm & Full Import** (`POST /api/youtube-import/confirm`)
   - User clicks "確認導入" → fetch all comments → persist to database → update metadata
   - Return success/error status with count

**Isolation**: Uses only `YouTubeApiService` (not UrtubeapiService). Integration with existing "匯入" happens at view/route level, not in this controller.

---

## Public Methods

### 1. preview

```php
public function preview(Request $request): JsonResponse
```

**Route**: `POST /api/youtube-import/preview`

**Purpose**: Fetch preview comments (5 samples) for user review.

**Request Body**:
```json
{
    "video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "trace_id": "trace-abc123"  // Optional: for logging
}
```

**Validation**:
- `video_url` (required, string): Valid YouTube URL or video ID
  - Accept formats: `https://youtube.com/watch?v=ID`, `youtube.com/watch?v=ID`, `youtu.be/ID`, or bare video ID
  - Extract video ID from URL

- `trace_id` (optional, string, max 255): Request trace ID for logging

**Response (Success 200)**:
```json
{
    "success": true,
    "status": "preview_ready",
    "data": {
        "video_id": "dQw4w9WgXcQ",
        "video_exists_in_db": true,
        "import_mode": "incremental",
        "preview_comments": [
            {
                "comment_id": "Ug_abc123...",
                "text": "Great video!",
                "author_channel_id": "UCxyz...",
                "like_count": 5,
                "published_at": "2025-11-10T12:00:00Z",
                "is_reply": false,
                "reply_count": 0
            },
            // ... up to 5 comments
        ],
        "total_comments": 1250,
        "has_more": true,
        "last_comment_timestamp": "2025-11-09T18:00:00Z"  // Only in incremental mode
    },
    "message": "5 sample comments loaded for preview"
}
```

**Response (Video Not Exists 200 with action)**:
```json
{
    "success": true,
    "status": "new_video_detected",
    "data": {
        "video_id": "dQw4w9WgXcQ",
        "import_mode": "full",
        "action_required": "invoke_import_dialog",
        "message": "This video is new. Please complete the import dialog first to capture metadata."
    }
}
```

**Response (Error 400/422)**:
```json
{
    "success": false,
    "error": "invalid_video_id",
    "message": "Invalid video ID format",
    "trace_id": "trace-abc123"
}
```

**Response (Error 404)**:
```json
{
    "success": false,
    "error": "video_not_found",
    "message": "Video not found on YouTube. Please check the URL and try again.",
    "trace_id": "trace-abc123"
}
```

**Response (Error 403)**:
```json
{
    "success": false,
    "error": "quota_exceeded",
    "message": "YouTube API quota exceeded. Please try again later.",
    "trace_id": "trace-abc123"
}
```

**Behavior**:
1. Validate request input
2. Extract video ID from URL (use `UrlParsingService`)
3. Check if video exists in database
4. If **not exists**: Return `new_video_detected` status (do not fetch preview)
5. If **exists**: Call `YouTubeApiService::fetchPreviewComments`
6. Return preview data to frontend
7. Log operation with trace ID

**Throws**:
- `ValidationException`: Invalid URL format
- `YouTubeApiException`: API errors handled gracefully

**Side Effects**: None (read-only)

---

### 2. confirm

```php
public function confirm(Request $request): JsonResponse
```

**Route**: `POST /api/youtube-import/confirm`

**Purpose**: Perform full import after user confirms preview.

**Request Body**:
```json
{
    "video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "trace_id": "trace-abc123",  // Optional
    "should_update_metadata": true  // Only if new video (user completed "匯入" dialog)
}
```

**Validation**:
- `video_url` (required): Valid YouTube URL
- `trace_id` (optional): Request trace ID
- `should_update_metadata` (optional, default false): True if metadata already captured via "匯入" dialog

**Response (Success 200)**:
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
        "new_comments": 150,
        "duplicate_skipped": 0,
        "import_duration_seconds": 23.5,
        "last_comment_timestamp": "2025-11-01T10:00:00Z",
        "channel_updated": {
            "comment_count": 5420,
            "last_import_at": "2025-11-17T10:30:00Z"
        },
        "video_updated": {
            "updated_at": "2025-11-17T10:30:00Z"
        }
    },
    "message": "Successfully imported 1590 comments"
}
```

**Response (Partial Success 206)**:
```json
{
    "success": true,
    "status": "import_partial",
    "data": {
        "video_id": "dQw4w9WgXcQ",
        "comments_imported": 450,
        "replies_imported": 120,
        "total_imported": 570,
        "stopped_at": "duplicate_detected",
        "duplicate_comment_id": "UgxE_abc...",
        "message": "Import stopped at first duplicate (incremental update)"
    }
}
```

**Response (Failure 400/422)**:
```json
{
    "success": false,
    "error": "import_failed",
    "message": "Failed to import comments. Please try again.",
    "details": {
        "failed_at": "comments_store",
        "error_code": "database_error",
        "partially_imported": 250
    },
    "trace_id": "trace-abc123"
}
```

**Response (Failure 403)**:
```json
{
    "success": false,
    "error": "quota_exceeded",
    "message": "YouTube API quota exceeded during import. Partially imported comments may have been saved.",
    "data": {
        "comments_imported": 200,
        "trace_id": "trace-abc123"
    }
}
```

**Behavior**:
1. Validate request input
2. Extract video ID from URL
3. Determine import mode (incremental vs full):
   - If video exists in DB → incremental
   - If video not exists → error (user must complete "匯入" dialog first)
4. Call `YouTubeApiService::fetchAllComments` with optional afterDate
5. For each fetched comment:
   a. Check if comment_id exists in database
   b. If exists → skip (duplicate detection)
   c. If new → insert with all fields including parent_comment_id
6. After all comments stored:
   a. Recalculate channel.comment_count
   b. Update channel.last_import_at, channel.updated_at
   c. Update video.updated_at
7. Return success with counts and metadata
8. Log operation with counts

**Transaction Management**:
- Wrap comment insertion in database transaction
- On error: Rollback transaction (or allow partial import to persist depending on error)
- On quota exceeded: Allow partial import (comments already stored before quota hit)

**Error Handling**:
- If API returns 403 (quota exceeded):
  - Return 403 with partially_imported count
  - Don't fail if some comments already persisted
- If database insert fails:
  - Return 400 with failure details
  - Rollback transaction if possible
- If video not found:
  - Return 400 with helpful message

**Side Effects**:
- Inserts comments to database
- Updates channel and video records
- Creates structured logs with trace ID

---

### 3. getImportStatus (Optional)

```php
public function getImportStatus(string $videoId): JsonResponse
```

**Route**: `GET /api/youtube-import/status/{videoId}`

**Purpose**: Check import status for a specific video (useful for tracking).

**Response (Success 200)**:
```json
{
    "success": true,
    "data": {
        "video_id": "dQw4w9WgXcQ",
        "exists_in_db": true,
        "total_comments": 1250,
        "comment_count": 1250,
        "reply_count": 340,
        "last_import_at": "2025-11-17T10:30:00Z",
        "last_comment_timestamp": "2025-11-10T12:00:00Z",
        "channel": {
            "channel_id": "UCxyz...",
            "channel_name": "Example Channel",
            "comment_count": 5420
        }
    }
}
```

---

## Exception Handling

All exceptions caught and converted to appropriate HTTP responses:

| Exception | HTTP Code | Error Code |
|-----------|-----------|-----------|
| ValidationException | 422 | `validation_error` |
| InvalidVideoIdException | 400 | `invalid_video_id` |
| VideoNotFoundException | 404 | `video_not_found` |
| AuthenticationException | 401 | `auth_failed` |
| YouTubeApiException (quota) | 403 | `quota_exceeded` |
| YouTubeApiException (other) | 500 | `api_error` |
| DatabaseException | 500 | `database_error` |
| Exception (unknown) | 500 | `unknown_error` |

---

## Integration Points

### Services
- **YouTubeApiService**: Fetch comments from YouTube API
- **UrlParsingService**: Extract video ID from URL (existing service)
- **DuplicateDetectionService**: Optional (if needed for faster duplicate checks)

### Models
- **Comment**: Store fetched comments
- **Video**: Check existence, update metadata
- **Channel**: Update comment count and timestamps
- **Author**: Verify/create author records

### Existing Controllers
- **ImportController** ("匯入"): New video flow routes back to this for metadata capture
- Routes and views NOT modified by this feature

---

## Request Flow Diagrams

### Flow 1: New Video (Full Import)

```
User Input (URL) → preview()
    ↓
Check if video exists
    ↓ (No) → Return "new_video_detected" status
    ↓
Route to ImportController (existing "匯入" dialog)
    ↓
User completes metadata capture
    ↓
confirm() called with should_update_metadata=true
    ↓
Fetch all comments + Recursively fetch replies
    ↓
Store all to database
    ↓
Update channel/video metadata
    ↓
Return success with counts
```

### Flow 2: Existing Video (Incremental Update)

```
User Input (URL) → preview()
    ↓
Check if video exists
    ↓ (Yes) → Find max(published_at)
    ↓
Fetch 5 preview comments (newer than max)
    ↓
User clicks "確認導入"
    ↓
confirm() called with video_url
    ↓
Fetch all comments (order=time, stop at duplicate)
    ↓
Recursively fetch all replies
    ↓
Store new comments to database
    ↓
Update channel/video metadata
    ↓
Return success with import_mode="incremental"
```

---

## Input Validation Rules

### URL Validation

Accept multiple formats:
```
- https://www.youtube.com/watch?v=dQw4w9WgXcQ
- https://youtube.com/watch?v=dQw4w9WgXcQ
- https://youtu.be/dQw4w9WgXcQ
- dQw4w9WgXcQ (bare video ID)
```

Extract 11-character video ID; validate format.

**Validation Error**: Return 422 with message: "Invalid YouTube URL. Please use a valid video link or ID."

---

## Observability (Logging)

Every request logs to structured JSON format:

```json
{
    "timestamp": "2025-11-17T10:30:00Z",
    "trace_id": "trace-abc123",
    "operation": "youtube_import_preview",
    "video_id": "dQw4w9WgXcQ",
    "status": "success",
    "comments_count": 5,
    "duration_ms": 2300,
    "http_code": 200
}
```

Logging includes:
- Request trace ID (for audit trails)
- Video ID processed
- Operation success/failure
- Comment counts
- Duration
- HTTP response code

---

## Testing Scenarios

### Test 1: Valid Existing Video Preview
- Given: video_url = valid YouTube URL of existing video
- When: POST /api/youtube-import/preview
- Then: HTTP 200, status="preview_ready", returns 5 comments

### Test 2: New Video Preview
- Given: video_url = valid YouTube URL of new video
- When: POST /api/youtube-import/preview
- Then: HTTP 200, status="new_video_detected", no preview comments

### Test 3: Invalid URL Format
- Given: video_url = "not a url"
- When: POST /api/youtube-import/preview
- Then: HTTP 422, error="invalid_video_id"

### Test 4: Full Import Success
- Given: video_id exists in DB, has new comments
- When: POST /api/youtube-import/confirm
- Then: HTTP 200, returns comment counts, metadata updated

### Test 5: Incremental Import Stops at Duplicate
- Given: video exists, no new comments since last import
- When: POST /api/youtube-import/confirm
- Then: HTTP 206, status="import_partial", stopped_at="duplicate_detected"

### Test 6: API Quota Exceeded
- Given: YouTube API quota exceeded
- When: POST /api/youtube-import/confirm
- Then: HTTP 403, error="quota_exceeded", partially_imported count returned

---

**Status**: ✅ API contract finalized, ready for implementation
