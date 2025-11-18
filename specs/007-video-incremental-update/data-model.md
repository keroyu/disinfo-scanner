# Data Model: Video Incremental Update

**Feature**: 007-video-incremental-update
**Date**: 2025-11-18
**Purpose**: Define data structures, validation rules, and state transitions for incremental update feature

## Existing Entities (No Schema Changes Required)

### Video Entity

**Table**: `videos`

**Existing Fields** (all fields already present, no migrations needed):
- `video_id` (string, primary key) - YouTube video ID
- `channel_id` (string, foreign key → channels.channel_id) - Parent channel
- `title` (string) - Video title
- `published_at` (datetime) - Video publication timestamp
- `comment_count` (integer, nullable) - Total comment count (updated by this feature)
- `created_at` (datetime) - Laravel timestamp
- `updated_at` (datetime) - **Updated by incremental import to track last update time**

**Validation Rules** (enforced in controller/service):
```php
[
    'video_id' => 'required|string|max:11',
    'channel_id' => 'required|string|exists:channels,channel_id',
    'title' => 'required|string|max:500',
    'published_at' => 'required|date',
    'comment_count' => 'nullable|integer|min:0',
]
```

**State Transitions**:
1. **Initial State**: Video exists with `comment_count` (from initial import)
2. **Update Trigger**: User clicks "Update" button → system queries MAX(published_at) from comments
3. **Preview State**: Display new comment count and preview without persisting
4. **Import State**: On confirm, import new comments (max 500), update `comment_count`, set `updated_at = now()`
5. **Final State**: Video has updated `comment_count` and `updated_at` timestamp

**Computed Properties** (from Video model scopes):
- `actual_comment_count` - `SELECT COUNT(*) FROM comments WHERE video_id = ?`
- `last_comment_time` - `SELECT MAX(published_at) FROM comments WHERE video_id = ?`

---

### Comment Entity

**Table**: `comments`

**Existing Fields** (all fields already present, no migrations needed):
- `comment_id` (string, primary key) - YouTube comment ID (unique constraint for idempotency)
- `video_id` (string, foreign key → videos.video_id) - Parent video
- `author_channel_id` (string) - Commenter's channel ID
- `text` (text) - Comment content
- `like_count` (integer) - Number of likes
- `published_at` (datetime) - **Critical for incremental filtering** - Comment publication timestamp
- `parent_comment_id` (string, nullable) - Parent comment ID for replies
- `created_at` (datetime) - Laravel timestamp
- `updated_at` (datetime) - Laravel timestamp

**Validation Rules**:
```php
[
    'comment_id' => 'required|string|max:255|unique:comments,comment_id',
    'video_id' => 'required|string|exists:videos,video_id',
    'author_channel_id' => 'nullable|string|max:255',
    'text' => 'required|string',
    'like_count' => 'required|integer|min:0',
    'published_at' => 'required|date',
    'parent_comment_id' => 'nullable|string|max:255',
]
```

**Idempotency Constraint**:
- Unique index on `comment_id` ensures no duplicate imports
- `firstOrCreate()` pattern: if `comment_id` exists, skip insert silently

**Query Patterns for Incremental Update**:
```sql
-- Get last comment timestamp for video
SELECT MAX(published_at) FROM comments WHERE video_id = ? LIMIT 1;

-- Count new comments after timestamp (for preview)
SELECT COUNT(*) FROM comments_temp
WHERE video_id = ? AND published_at > ?;

-- Get actual comment count for video (after import)
SELECT COUNT(*) FROM comments WHERE video_id = ?;
```

---

## Transient Data Structures (Not Persisted)

### IncrementalUpdateRequest (API Request Payload)

**Purpose**: Data sent from frontend to backend for preview/import operations

**Structure**:
```php
[
    'video_id' => 'string',        // Required: YouTube video ID
    'video_title' => 'string',     // Optional: For display in preview
]
```

**Validation**:
```php
$request->validate([
    'video_id' => 'required|string|max:11|exists:videos,video_id',
    'video_title' => 'nullable|string|max:500',
]);
```

---

### IncrementalUpdatePreviewResponse (API Response)

**Purpose**: Preview data returned before import confirmation

**Structure**:
```json
{
    "success": true,
    "data": {
        "video_id": "abc123",
        "video_title": "示例影片標題",
        "last_comment_time": "2025-11-05 15:00:00",
        "new_comment_count": 42,
        "preview_comments": [
            {
                "comment_id": "xyz789",
                "author_channel_id": "UC...",
                "text": "留言內容示例...",
                "like_count": 5,
                "published_at": "2025-11-05 16:00:00",
                "parent_comment_id": null
            }
            // ... up to 5 comments
        ],
        "has_more": false,
        "import_limit": 500
    }
}
```

**Error Response**:
```json
{
    "success": false,
    "error": "No new comments found",
    "data": {
        "video_id": "abc123",
        "last_comment_time": "2025-11-05 15:00:00",
        "new_comment_count": 0
    }
}
```

---

### IncrementalUpdateImportResponse (API Response)

**Purpose**: Import result returned after confirmation

**Structure (Success - All comments imported)**:
```json
{
    "success": true,
    "data": {
        "video_id": "abc123",
        "imported_count": 42,
        "total_available": 42,
        "remaining": 0,
        "has_more": false,
        "updated_comment_count": 542,
        "message": "成功導入 42 則留言"
    }
}
```

**Structure (Success - Partial import due to 500 limit)**:
```json
{
    "success": true,
    "data": {
        "video_id": "abc123",
        "imported_count": 500,
        "total_available": 1200,
        "remaining": 700,
        "has_more": true,
        "updated_comment_count": 1500,
        "message": "成功導入 500 則留言。還有 700 則新留言可用，請再次點擊更新按鈕繼續導入。"
    }
}
```

**Error Response**:
```json
{
    "success": false,
    "error": "YouTube API quota exceeded",
    "data": {
        "video_id": "abc123",
        "retry_after": "2025-11-19 00:00:00"
    }
}
```

---

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. User clicks "Update" button on Videos List page             │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. Frontend: Open modal, AJAX POST /api/video-update/preview   │
│    Payload: { video_id: "abc123" }                             │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. Backend: VideoUpdateController::preview()                    │
│    - Query: SELECT MAX(published_at) FROM comments WHERE ...   │
│    - Call YouTube API with publishedAfter filter               │
│    - Return preview (first 5 comments + total count)           │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. Frontend: Display preview modal                             │
│    - Show: "剩下 42 則留言需要導入"                            │
│    - Preview 5 comments (oldest to newest)                     │
│    - User clicks "確認更新"                                    │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. Frontend: AJAX POST /api/video-update/import                │
│    Payload: { video_id: "abc123" }                             │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. Backend: VideoUpdateController::import()                     │
│    - Fetch ALL new comments (publishedAfter filter)            │
│    - Enforce 500-comment limit                                 │
│    - Insert with firstOrCreate() (idempotent)                  │
│    - Update video: comment_count, updated_at                   │
│    - Return: imported_count, remaining, has_more               │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│ 7. Frontend: Display success message, update Videos List       │
│    - Show: "成功導入 42 則留言" or partial import message      │
│    - Refresh table row (comment count, last comment time)      │
└─────────────────────────────────────────────────────────────────┘
```

---

## Indexing Strategy

**Existing Indexes** (already present, no changes needed):
- `comments.comment_id` - Primary key (unique index)
- `comments.video_id` - Foreign key index (for joins)
- `comments.published_at` - Index for efficient `MAX(published_at)` queries

**Query Performance**:
- Last comment query: `SELECT MAX(published_at)` uses index on `published_at` - O(log n)
- Comment count: `SELECT COUNT(*)` uses index on `video_id` - O(log n)
- Duplicate check: `firstOrCreate()` uses primary key `comment_id` - O(1)

---

## Datetime Format Standard

All datetime fields MUST follow Laravel convention:

**Database Storage**: `YYYY-MM-DD HH:MM:SS`
- Example: `2025-06-13 21:00:03`

**Implementation**:
```php
// Laravel automatically handles conversion
$video->updated_at = now(); // Stored as: 2025-11-18 14:30:00

// For YouTube API RFC 3339 → MySQL datetime conversion
$comment->published_at = $youtubeData['snippet']['publishedAt'];
// Input: "2025-11-05T16:00:00Z" (RFC 3339)
// Eloquent auto-converts to: "2025-11-05 16:00:00" (MySQL datetime)
```

**Timezone Handling**:
- Application timezone: Set in `config/app.php` (default: UTC)
- Laravel Carbon automatically converts to application timezone
- Database stores in application timezone (UTC recommended)

---

## Summary

**Schema Changes**: ✅ None required - all fields already exist

**New Tables**: ❌ None

**Modified Tables**: ❌ None (only data updates to existing records)

**Idempotency**: ✅ Enforced via unique constraint on `comments.comment_id` + `firstOrCreate()` pattern

**Validation**: ✅ Enforced at controller layer with Laravel validation rules

**Performance**: ✅ Optimized with existing indexes on `video_id`, `comment_id`, `published_at`
