# API Contract: Check Video & Channel Existence

**Endpoint**: `POST /api/comments/check`
**Purpose**: Determine if video exists in DB, and if not, check channel existence + fetch preview data
**Access**: Authenticated (Laravel auth middleware)
**Rate Limit**: None (but YouTube API quota will limit behind-the-scenes)

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
  "video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
}
```

### Validation Rules
- `video_url` (required): Must match patterns:
  - `https://www.youtube.com/watch?v={video_id}`
  - `https://youtu.be/{video_id}`
  - `{video_id}` must be 11-character alphanumeric string

---

## Response Scenarios

### Scenario 1: Video Already Exists (User Story 1, Acceptance 2)

**Status Code**: `200 OK`

```json
{
  "status": "video_exists",
  "message": "影片已建檔，請利用更新功能導入留言"
}
```

**Next Action**: Modal closes, no further action possible (awaiting future "update" feature)

---

### Scenario 2: New Video + Existing Channel (User Story 2)

**Status Code**: `200 OK`

```json
{
  "status": "new_video_existing_channel",
  "channel_id": "UCxxxxx",
  "channel_title": "Channel Name",
  "video_title": "Video Title",
  "comment_count_total": 1250,
  "preview_comments": [
    {
      "comment_id": "Ugx7t5NxVcEP_VFP...",
      "author_channel_id": "UCyyyyy",
      "author_name": "Comment Author",
      "content": "Great video! Very informative.",
      "like_count": 42,
      "published_at": "2025-11-15 10:30:45"
    },
    {
      "comment_id": "Ugx2t5NxVcEP_VFZ...",
      "author_channel_id": "UCzzzzz",
      "author_name": "Another User",
      "content": "Thanks for sharing!",
      "like_count": 18,
      "published_at": "2025-11-14 14:22:10"
    },
    {
      "comment_id": "Ugx9t5NxVcEP_VFA...",
      "author_channel_id": "UCwwwww",
      "author_name": "Viewer",
      "content": "Subscribed!",
      "like_count": 7,
      "published_at": "2025-11-14 09:15:33"
    },
    {
      "comment_id": "Ugx3t5NxVcEP_VFB...",
      "author_channel_id": "UCvvvvv",
      "author_name": "Fan",
      "content": "Looking forward to more.",
      "like_count": 12,
      "published_at": "2025-11-13 16:05:21"
    },
    {
      "comment_id": "Ugx4t5NxVcEP_VFC...",
      "author_channel_id": "UCuuuuu",
      "author_name": "Member",
      "content": "This is amazing work!",
      "like_count": 89,
      "published_at": "2025-11-13 11:44:55"
    }
  ],
  "existing_channel_tags": [
    {
      "id": 1,
      "name": "政治",
      "color": "#FF5733"
    },
    {
      "id": 3,
      "name": "社會",
      "color": "#33FF57"
    }
  ]
}
```

**Data Details**:
- `comment_count_total`: Total comments/replies on video (from YouTube API `statistics.commentCount`)
- `preview_comments`: Latest 5 comments sorted by `publishedAt` DESC (descending = newest first)
- `existing_channel_tags`: Current tags assigned to this channel (user can modify before import)
- All timestamps: UTC format `YYYY-MM-DD HH:MM:SS`

**Next Action**: Modal shows preview + tag selector (editable). User can modify tags, then click "確認導入"

---

### Scenario 3: New Video + New Channel (User Story 3)

**Status Code**: `200 OK`

```json
{
  "status": "new_video_new_channel",
  "channel_id": "UCxxxxx",
  "channel_title": "New Channel Name",
  "video_title": "New Video Title",
  "comment_count_total": 2847,
  "preview_comments": [
    {
      "comment_id": "Ugx7t5NxVcEP_VFP...",
      "author_channel_id": "UCyyyyy",
      "author_name": "Comment Author",
      "content": "Great video! Very informative.",
      "like_count": 42,
      "published_at": "2025-11-15 10:30:45"
    },
    {
      "comment_id": "Ugx2t5NxVcEP_VFZ...",
      "author_channel_id": "UCzzzzz",
      "author_name": "Another User",
      "content": "Thanks for sharing!",
      "like_count": 18,
      "published_at": "2025-11-14 14:22:10"
    },
    {
      "comment_id": "Ugx9t5NxVcEP_VFA...",
      "author_channel_id": "UCwwwww",
      "author_name": "Viewer",
      "content": "Subscribed!",
      "like_count": 7,
      "published_at": "2025-11-14 09:15:33"
    },
    {
      "comment_id": "Ugx3t5NxVcEP_VFB...",
      "author_channel_id": "UCvvvvv",
      "author_name": "Fan",
      "content": "Looking forward to more.",
      "like_count": 12,
      "published_at": "2025-11-13 16:05:21"
    },
    {
      "comment_id": "Ugx4t5NxVcEP_VFC...",
      "author_channel_id": "UCuuuuu",
      "author_name": "Member",
      "content": "This is amazing work!",
      "like_count": 89,
      "published_at": "2025-11-13 11:44:55"
    }
  ],
  "available_tags": [
    {
      "id": 1,
      "name": "政治",
      "color": "#FF5733"
    },
    {
      "id": 2,
      "name": "社會",
      "color": "#33FF57"
    },
    {
      "id": 3,
      "name": "媒體",
      "color": "#3357FF"
    },
    {
      "id": 4,
      "name": "科技",
      "color": "#FF33F5"
    },
    {
      "id": 5,
      "name": "經濟",
      "color": "#F5FF33"
    }
  ]
}
```

**Data Details**:
- Same as Scenario 2, except:
- `available_tags`: ALL tags in system (user must select at least 1)
- No `existing_channel_tags` field (channel doesn't exist yet)

**Next Action**: Modal shows preview + tag multi-selector (required: at least 1). User selects tags, then clicks "確認導入"

---

### Error Scenario 1: Invalid YouTube URL

**Status Code**: `400 Bad Request`

```json
{
  "status": "error",
  "code": "INVALID_URL",
  "message": "無法解析的 YouTube URL",
  "details": "支援格式：https://youtu.be/{video_id} 或 https://www.youtube.com/watch?v={video_id}"
}
```

**Cause**: URL doesn't match YouTube patterns, or video_id isn't 11 characters

---

### Error Scenario 2: YouTube API Failure

**Status Code**: `502 Bad Gateway` or `503 Service Unavailable`

```json
{
  "status": "error",
  "code": "API_ERROR",
  "message": "YouTube API 請求失敗",
  "details": "配額已用盡或網路超時。請稍候後重試。",
  "can_retry": true,
  "retry_after_seconds": 60
}
```

**Causes**:
- YouTube API quota exhausted (429 Too Many Requests)
- Network timeout
- YouTube service unavailable

**Next Action**: Show "重試" button to user; they can click to retry after delay

---

### Error Scenario 3: Video Deleted or Private

**Status Code**: `404 Not Found`

```json
{
  "status": "error",
  "code": "VIDEO_NOT_FOUND",
  "message": "找不到該影片",
  "details": "該影片可能已被刪除、設為私密，或不允許評論。"
}
```

**Causes**:
- Video deleted from YouTube
- Video set to private
- Comments disabled on video

---

### Error Scenario 4: Channel Deleted

**Status Code**: `404 Not Found`

```json
{
  "status": "error",
  "code": "CHANNEL_NOT_FOUND",
  "message": "找不到該頻道",
  "details": "該頻道可能已被刪除或禁用。"
}
```

---

## Performance Requirements

- **Response Time**: < 5 seconds (per SC-003)
  - Includes YouTube API preview fetch (1 commentThreads call)
  - Does NOT include full import (that's a separate endpoint)

---

## Implementation Notes

- **No DB writes in this endpoint** – purely read/preview
- **Idempotent** – safe to call multiple times
- **Stateless** – preview data discarded if user closes modal without importing

---

## Testing

### Contract Tests
```php
public function test_check_video_exists() { ... }
public function test_check_new_video_existing_channel() { ... }
public function test_check_new_video_new_channel() { ... }
public function test_invalid_url_returns_error() { ... }
public function test_api_failure_returns_retry_response() { ... }
```

---

## Related Endpoints

- `POST /api/comments/import` – Performs actual database import (separate contract)
