# API Contract: Import Comments

**Endpoint**: `POST /api/comments/import`
**Purpose**: Execute staged import of video, channel, and all comments/replies into database
**Access**: Authenticated (Laravel auth middleware)
**Rate Limit**: 1 concurrent import per session (queue-based for large imports)

---

## Request

### Headers
```
Content-Type: application/json
Authorization: Bearer {session_token}
```

### Body
```json
{
  "video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
  "scenario": "new_video_existing_channel",
  "channel_tags": [1, 3, 5],
  "import_replies": true
}
```

### Field Specifications

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| video_url | string | YES | - | YouTube video URL (same validation as /check endpoint) |
| scenario | enum | YES | - | `new_video_existing_channel` OR `new_video_new_channel` |
| channel_tags | array[int] | CONDITIONAL | - | Tag IDs to assign. Required if `scenario=new_video_new_channel`; optional if `scenario=new_video_existing_channel` |
| import_replies | boolean | NO | true | Whether to recursively fetch replies (up to depth 3) |

### Validation Rules

- `video_url`: Must be valid YouTube URL (same as `/check` endpoint)
- `scenario`: Must be exactly one of the two specified values
- `channel_tags`:
  - If `scenario=new_video_existing_channel`: Can be empty array (keeps existing tags) or array of tag IDs to update
  - If `scenario=new_video_new_channel`: Must be non-empty array (at least 1 tag required)
  - All tag IDs must exist in database
- `import_replies`: Boolean only

---

## Response Scenarios

### Scenario 1: Import Success

**Status Code**: `200 OK`

```json
{
  "status": "success",
  "message": "成功導入 1247 則留言",
  "imported_comment_count": 1247,
  "imported_reply_count": 523,
  "total_imported": 1770,
  "channel_id": "UCxxxxx",
  "channel_name": "Channel Name",
  "video_id": "dQw4w9WgXcQ",
  "video_title": "Video Title",
  "timestamp": "2025-11-15 10:30:45",
  "recursion_depth_reached": 3
}
```

**Data Details**:
- `imported_comment_count`: Top-level comments only
- `imported_reply_count`: Nested replies (depth 1-3)
- `total_imported`: Sum of comments + replies
- `timestamp`: Import completion time (UTC format)
- `recursion_depth_reached`: Actual maximum depth reached (1-3); indicates if replies were deeper but truncated

---

### Scenario 2: Import Partial Failure (Some Comments Imported)

**Status Code**: `207 Multi-Status` (or `200 OK` with warning)

```json
{
  "status": "partial_success",
  "message": "導入過程中發生錯誤，已導入 450 則留言",
  "imported_comment_count": 450,
  "imported_reply_count": 120,
  "total_imported": 570,
  "expected_total": 1247,
  "channel_id": "UCxxxxx",
  "video_id": "dQw4w9WgXcQ",
  "error_code": "IMPORT_TIMEOUT",
  "error_message": "導入超時，但已保存 570 則留言。請稍候後重試以導入剩餘留言。",
  "can_retry": true,
  "timestamp": "2025-11-15 10:35:42"
}
```

**Causes**:
- API timeout mid-import (large comment count)
- Database transaction exceeded timeout
- Network interruption during fetch

**Next Action**: User sees partial success message; can retry to continue importing remaining comments

---

### Error Scenario 1: Invalid URL or Scenario

**Status Code**: `400 Bad Request`

```json
{
  "status": "error",
  "code": "INVALID_REQUEST",
  "message": "請求參數有誤",
  "details": [
    "scenario 必須是 'new_video_existing_channel' 或 'new_video_new_channel'",
    "channel_tags 至少需要選擇一個標籤（新頻道情況下）"
  ]
}
```

---

### Error Scenario 2: Tag Validation Failure

**Status Code**: `422 Unprocessable Entity`

```json
{
  "status": "error",
  "code": "TAG_VALIDATION_FAILED",
  "message": "標籤驗證失敗",
  "details": {
    "missing_tags": [1, 3, 5],
    "available_tags": [1, 2, 3, 4],
    "reason": "標籤 5 不存在"
  }
}
```

---

### Error Scenario 3: Complete Import Failure (No Changes)

**Status Code**: `500 Internal Server Error`

```json
{
  "status": "error",
  "code": "IMPORT_FAILED",
  "message": "導入失敗，未做任何資料庫變更",
  "details": "資料庫事務回滾成功。請檢查網路連線或稍候後重試。",
  "can_retry": true,
  "timestamp": "2025-11-15 10:30:45"
}
```

**Cause**: Database transaction rolled back at any stage (channels, videos, or comments)
**Guarantee**: No partial data in database (ACID compliance via transaction)

---

### Error Scenario 4: YouTube API Failure During Import

**Status Code**: `502 Bad Gateway`

```json
{
  "status": "error",
  "code": "API_ERROR",
  "message": "YouTube API 請求失敗",
  "details": "無法取得完整留言清單。已導入 120 則留言。",
  "imported_so_far": 120,
  "can_retry": true,
  "retry_after_seconds": 60
}
```

**Cause**: API quota exhausted, timeout, or network error during full-comment fetch
**Guarantee**: DB transaction rolled back; no partial data persisted

---

### Error Scenario 5: Channel Tags Update Failure (Existing Channel)

**Status Code**: `422 Unprocessable Entity`

```json
{
  "status": "error",
  "code": "TAG_UPDATE_FAILED",
  "message": "無法更新頻道標籤",
  "details": "該頻道的標籤更新失敗。請稍候後重試。",
  "can_retry": true
}
```

---

## Database Transaction Guarantees

**Import follows strict 3-stage transaction** (per plan.md research findings):

```
BEGIN TRANSACTION
  Stage 1: INSERT/UPDATE Channel
  Stage 2: INSERT Video
  Stage 3: INSERT Comments recursively
  Stage 3b: Calculate & UPDATE comment_count
  Stage 4: UPDATE channels.last_import_at
COMMIT or ROLLBACK (atomic)
```

**Outcome**:
- **Success** → All 4 stages committed
- **Failure at ANY stage** → Entire transaction rolled back (zero data written)
- **User sees**:
  - Success message (SC-009): "成功導入 XXX 則留言"
  - OR error message with "can_retry: true" option

---

## Performance Requirements

- **Complete import time**: < 30 seconds (per SC-004)
  - Includes YouTube API fetch for all comments (variable based on count)
  - Includes database inserts (typically <2 seconds for 1000-comment video)
- **Timeout**: Set to 35 seconds (5s buffer above SC-004 target)

---

## Implementation Notes

### For New Channels (scenario=new_video_new_channel)

1. **Validate channel_tags** – at least 1 tag required
2. **Create Channel** – INSERT with name from YouTube API
3. **Assign Tags** – sync() via channel_tags pivot table
4. **Update last_import_at** – set to import completion time

### For Existing Channels (scenario=new_video_existing_channel)

1. **Get Channel** – by channel_id
2. **Update Tags** (optional) – if channel_tags provided, sync() with new selection
3. **Preserve existing tags** if channel_tags array is empty
4. **Update last_import_at** – set to import completion time

### Comment Recursion

- Start with video's top-level comments
- For each comment, check if it has replies (parent_comment_id = null)
- Fetch replies recursively up to depth 3
- If depth >= 3, stop recursion (ignore deeper replies)

---

## Async Job Pattern (Optional Enhancement)

For large imports (>5000 comments), queue job instead of synchronous execution:

```php
// Controller dispatches job instead of direct service call
ImportCommentsJob::dispatch($videoUrl, $scenario, $channelTags);

// Response (202 Accepted)
{
  "status": "queued",
  "job_id": "uuid-here",
  "message": "導入已排隊，將在背景執行。預計 2-5 分鐘完成。",
  "check_status_at": "GET /api/comments/import/{job_id}/status"
}
```

---

## Testing

### Contract Tests
```php
public function test_import_new_video_new_channel_success() { ... }
public function test_import_new_video_existing_channel_success() { ... }
public function test_import_with_tag_update_existing_channel() { ... }
public function test_import_validates_channel_tags_required() { ... }
public function test_import_transaction_rollback_on_failure() { ... }
public function test_import_calculates_comment_count() { ... }
public function test_import_stores_replies_with_parent_id() { ... }
public function test_import_respects_recursion_depth_limit() { ... }
public function test_import_updates_last_import_at() { ... }
```

---

## Related Endpoints

- `POST /api/comments/check` – Preview endpoint (call this first)
- `GET /api/comments` – List comments (for post-import refresh via AJAX)
