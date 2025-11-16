# Contract: YoutubeApiImportController

**Module**: `app/Http/Controllers/YoutubeApiImportController.php`
**Responsibility**: Handle HTTP requests for YouTube API comment import workflow (URL input, metadata confirmation, preview, full import)
**Testing**: Integration tests in `tests/Integration/CommentImportWorkflowTest.php` and feature tests for controller actions

---

## Controller Contract

The `YoutubeApiImportController` implements the complete import workflow with distinct HTTP endpoints/actions.

### Constructor

```php
public function __construct(
    YouTubeApiService $youtubeApiService,
    CommentImportService $importService,
    LoggerInterface $logger
)
```

---

## HTTP Endpoints

### 1. Show Import Form

**Route**: `GET /comments/import`
**Action Method**: `showImportForm()`
**Response**: HTML view with YouTube URL input form

#### Response Body (HTML)

Returns Blade view `import-modal.blade.php` with:
- Text input for YouTube URL (id: `youtube_url`)
- Submit button labeled "取得影片資訊" (Get video info)
- Cancel button

#### Status Codes

- `200 OK` - Form rendered successfully

---

### 2. Submit Video URL & Get Metadata

**Route**: `POST /comments/import/metadata`
**Action Method**: `getMetadata()`
**Request**: FormRequest `ImportVideoRequest`

#### Request Body

```
Content-Type: application/x-www-form-urlencoded
youtube_url: https://www.youtube.com/watch?v=dQw4w9WgXcQ
```

#### Request Validation

`ImportVideoRequest` validates:
- `youtube_url` is required
- `youtube_url` matches YouTube video URL pattern (extract video ID)
- Video ID is extracted and validated (11 alphanumeric chars)

#### Response (Success - New Video)

**Status**: `200 OK`
**Content-Type**: `application/json`

```json
{
  "status": "success",
  "data": {
    "video_id": "dQw4w9WgXcQ",
    "is_new": true,
    "metadata": {
      "title": "Rick Astley - Never Gonna Give You Up (Official Video)",
      "channel_id": "UCuAXFkgsw1L7xaCfnd5J JzQ",
      "channel_name": "Rick Astley",
      "published_at": "2009-10-25T06:57:33Z"
    },
    "action": "show_metadata_dialog"
  }
}
```

#### Response (Success - Existing Video)

**Status**: `200 OK`

```json
{
  "status": "success",
  "data": {
    "video_id": "dQw4w9WgXcQ",
    "is_new": false,
    "action": "show_preview"
  }
}
```

#### Response (Error - Invalid URL)

**Status**: `422 Unprocessable Entity`

```json
{
  "status": "error",
  "message": "Invalid YouTube URL format",
  "error_code": "INVALID_URL"
}
```

#### Response (Error - Video Not Found)

**Status**: `404 Not Found`

```json
{
  "status": "error",
  "message": "Video not found on YouTube API",
  "error_code": "VIDEO_NOT_FOUND"
}
```

#### Response (Error - API Error)

**Status**: `503 Service Unavailable`

```json
{
  "status": "error",
  "message": "YouTube API error: quota exceeded",
  "error_code": "API_QUOTA_EXCEEDED"
}
```

#### Side Effects

- Logs metadata fetch attempt with trace ID
- No data persisted to database

---

### 3. Confirm Metadata & Get Tags (New Videos Only)

**Route**: `POST /comments/import/confirm-metadata`
**Action Method**: `confirmMetadata()`

#### Request Body

```json
{
  "video_id": "dQw4w9WgXcQ",
  "tags": ["politics", "election"],
  "title": "Rick Astley - Never Gonna Give You Up (Official Video)",
  "channel_name": "Rick Astley"
}
```

#### Validation

- `video_id`: Must match previously fetched video
- `tags`: Array of tag strings (optional, can be empty)
- `title`: Must match fetched metadata (prevents tampering)
- `channel_name`: Must match fetched metadata

#### Response (Success)

**Status**: `200 OK`

```json
{
  "status": "success",
  "data": {
    "action": "show_preview",
    "video_id": "dQw4w9WgXcQ"
  }
}
```

#### Response (Error - Metadata Mismatch)

**Status**: `409 Conflict`

```json
{
  "status": "error",
  "message": "Metadata mismatch - please refetch video info",
  "error_code": "METADATA_MISMATCH"
}
```

#### Side Effects

- Metadata NOT yet persisted to database (stored only in request session/temporary storage)
- Logs confirmation action

---

### 4. Fetch Comment Preview (5 Sample Comments)

**Route**: `POST /comments/import/preview`
**Action Method**: `getPreview()`

#### Request Body

```json
{
  "video_id": "dQw4w9WgXcQ"
}
```

#### Response (Success - New Video)

**Status**: `200 OK`

```json
{
  "status": "success",
  "data": {
    "video_id": "dQw4w9WgXcQ",
    "is_new": true,
    "preview_comments": [
      {
        "comment_id": "UgfABC123...",
        "author_name": "John Doe",
        "author_channel_id": "UCxyz...",
        "text": "This is a great video! [truncated if >200 chars]",
        "like_count": 42,
        "published_at": "2023-01-16T08:15:00Z",
        "reply_count": 3
      }
    ],
    "total_available_comments": 156,
    "estimated_fetch_time_seconds": 15
  }
}
```

#### Response (Success - Existing Video, New Comments)

**Status**: `200 OK`

```json
{
  "status": "success",
  "data": {
    "video_id": "dQw4w9WgXcQ",
    "is_new": false,
    "preview_comments": [
      {
        "comment_id": "UgfNEW123...",
        "text": "New comment since last import",
        "like_count": 5,
        "published_at": "2023-11-15T10:30:00Z",
        "reply_count": 0
      }
    ],
    "new_comments_available": 12,
    "last_import_at": "2023-11-10T..."
  }
}
```

#### Response (Success - Existing Video, No New Comments)

**Status**: `200 OK`

```json
{
  "status": "success",
  "data": {
    "video_id": "dQw4w9WgXcQ",
    "is_new": false,
    "preview_comments": [],
    "new_comments_available": 0,
    "message": "No new comments since last import (2023-11-10)"
  }
}
```

#### Response (Error)

**Status**: `503 Service Unavailable`

```json
{
  "status": "error",
  "message": "Failed to fetch comment preview",
  "error_code": "PREVIEW_FETCH_FAILED"
}
```

#### Side Effects

- NO data persisted to database
- Logs preview fetch attempt

---

### 5. Execute Full Comment Import

**Route**: `POST /comments/import/confirm-import`
**Action Method**: `confirmImport()`

#### Request Body

```json
{
  "video_id": "dQw4w9WgXcQ",
  "tags": ["politics"],
  "title": "Video Title (for new videos)",
  "channel_name": "Channel Name (for new videos)"
}
```

#### Response (Success)

**Status**: `200 OK`

```json
{
  "status": "success",
  "data": {
    "video_id": "dQw4w9WgXcQ",
    "message": "Import completed successfully",
    "import_result": {
      "total_comments_imported": 156,
      "total_replies_imported": 89,
      "new_video_created": true,
      "new_channel_created": false,
      "import_duration_seconds": 18
    }
  }
}
```

#### Response (Error - API Failure, No Data Persisted)

**Status**: `503 Service Unavailable`

```json
{
  "status": "error",
  "message": "Failed to fetch comments from YouTube API",
  "error_code": "IMPORT_FETCH_FAILED",
  "details": "Comment fetch failed at page 3 of 5"
}
```

#### Response (Error - Database Transaction Failure)

**Status**: `500 Internal Server Error`

```json
{
  "status": "error",
  "message": "Database transaction failed",
  "error_code": "DB_TRANSACTION_FAILED"
}
```

#### Side Effects

- **On Success**:
  - Inserts/updates video, channel, all comments and replies to database (within transaction)
  - Logs import completion with trace ID and comment count
  - Returns counts to user

- **On Failure**:
  - Rolls back entire transaction (NO partial data)
  - Logs error with trace ID
  - User can retry from same step

---

## Workflow State Diagram

```
START
  ↓
[Show Form] --POST--> [Get Metadata]
  ↓
[Check if New/Existing]
  ├─ NEW VIDEO: [Show Metadata Dialog] --confirm--> [Get Preview]
  └─ EXISTING: [Get Preview]
  ↓
[Show Preview Dialog]
  ├─ Accept: [Confirm Import] --execute--> [Fetch & Save]
  └─ Cancel: [Form]
  ↓
[Success or Error with rollback]
```

---

## Error Handling Strategy

| Error Type | HTTP Status | Message | User Action |
|-----------|------------|---------|-------------|
| Invalid URL | 422 | "Invalid YouTube URL format" | Re-enter URL |
| Video not found | 404 | "Video not found on YouTube API" | Verify URL, check video exists |
| Comments disabled | 403 | "This video has comments disabled" | Choose different video |
| API quota exceeded | 503 | "YouTube API quota exceeded" | Retry later (provide ETA if possible) |
| API rate limit | 429 | "API rate limited, please retry in X seconds" | Automatic retry or manual retry |
| Network timeout | 504 | "Fetch timeout (>30s), please retry" | Automatic retry |
| DB transaction failed | 500 | "Database error, please contact admin" | Retry entire flow |

---

## Session Management

For multi-step workflow, maintain state across requests:

- **Session Storage**: Store `video_id`, `metadata`, `tags` in Laravel session
- **Session Timeout**: 30 minutes (user must complete workflow within this time)
- **On Session Timeout**: Return 419 error, user must start over
- **On Cancel**: Clear session, return to form

---

## Request/Response Format

- **Request Content-Type**: `application/x-www-form-urlencoded` (forms) or `application/json` (AJAX)
- **Response Content-Type**: `application/json`
- **Charset**: UTF-8
- **CSRF Protection**: Laravel middleware (enable for POST/PUT/DELETE routes)

---

## Testing Strategy

Integration tests verify:

1. **Happy Path**: New video → metadata → preview → import → success
2. **Happy Path**: Existing video → preview → import → success
3. **Error Handling**: Each error scenario returns correct status and message
4. **Session State**: Data persists across steps, cleared on cancel
5. **Transaction Rollback**: DB unchanged if any step fails
6. **Idempotency**: Retry same request after failure doesn't create duplicates
7. **Input Validation**: Invalid URLs/metadata rejected before API calls
8. **Concurrency**: Two users importing same video simultaneously handled correctly (last write wins on counts)

---

## Accessibility & UX Considerations

- All responses include `status` and `message` fields for clarity
- Error codes (like `VIDEO_NOT_FOUND`) are machine-parseable
- `estimated_fetch_time_seconds` helps manage user expectations
- Progress indicators recommended for long-running import (>5 seconds)
- Buttons labeled in Chinese per user context (取得影片資訊, 確認導入, 取消)

