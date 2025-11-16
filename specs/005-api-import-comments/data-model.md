# Data Model: YouTube API Comments Import

**Feature**: YouTube API Comments Import
**Date**: 2025-11-17
**Status**: Incremental Design (no breaking changes)

---

## Clarifications Mapping

This data model directly implements all clarifications from spec.md Clarifications section:

| Clarification ID | Question | Answer | Data Model Impact |
|------------------|----------|--------|-------------------|
| 2025-11-16-001 | Preview behavior | 5 comments, display only, no DB save | fetchPreviewComments returns non-persisted array |
| 2025-11-16-002 | Preview persistence | Save only after "確認導入" | Comment insert only in confirm endpoint |
| 2025-11-16-003 | Channel updates | comment_count, last_import_at, updated_at on ALL imports; video_count/first_import_at/created_at only NEW | Update rules in section 3 |
| 2025-11-16-004 | Video updates | updated_at ALWAYS; video_id/title/published_at/created_at only NEW; channel_id only NEW channel | Update rules in section 2 |
| 2025-11-16-005 | Cancellation | Close dialog, no DB changes | No transaction isolation needed |
| 2025-11-16-006 | Reply depth | ALL levels recursively | parent_comment_id field + recursive fetch logic |
| 2025-11-16-007 | New video flow | Invoke existing "匯入" dialog | Video existence check → route to ImportController |
| 2025-11-17-001 | File separation | YouTubeApiService separate | Service isolation (documented in Architecture) |

---

## Overview

The YouTube API comment import feature operates on **existing entities** (Comment, Video, Channel) with minimal schema modifications. The primary addition is support for reply comment hierarchies via a `parent_comment_id` field.

---

## Entity Changes

### 1. Comment (Existing Entity - MODIFIED)

**Table**: `comments` (existing)

**New Field**:
```sql
ALTER TABLE comments ADD COLUMN parent_comment_id VARCHAR(255) NULLABLE AFTER comment_id;
ALTER TABLE comments ADD FOREIGN KEY (parent_comment_id) REFERENCES comments(comment_id) ON DELETE SET NULL;
```

**Updated Schema**:

| Field | Type | Key | Notes |
|-------|------|-----|-------|
| comment_id | VARCHAR(255) | PRIMARY | YouTube comment ID, immutable |
| video_id | VARCHAR(255) | FK | References videos.video_id |
| author_channel_id | VARCHAR(255) | FK | References authors.author_channel_id |
| parent_comment_id | VARCHAR(255) | FK | **NEW**: References comments.comment_id for reply hierarchies |
| text | LONGTEXT | | Comment body text |
| like_count | UNSIGNED INT | | Like count (may update on subsequent imports) |
| published_at | TIMESTAMP | | Comment timestamp from YouTube API |
| created_at | TIMESTAMP | | First import timestamp |
| updated_at | TIMESTAMP | | Last update timestamp |

**Indexes**:
- `PRIMARY KEY (comment_id)` — unique identifier
- `INDEX (video_id)` — find comments by video
- `INDEX (author_channel_id)` — find comments by author
- `INDEX (parent_comment_id)` — **NEW**: find reply comments efficiently
- `UNIQUE INDEX (video_id, comment_id)` — prevent duplicates within a video

**Validation Rules**:
- `comment_id` must be non-null and unique
- `video_id` must reference existing video
- `author_channel_id` must reference existing author
- `parent_comment_id` (if set) must reference existing comment with same `video_id` (enforced in application logic)
- `text` must not be empty
- `published_at` must be <= current time

**State Transitions**:
- **New Comment**: created_at = now, updated_at = now
- **Duplicate Detection**: If comment_id already exists, stop import (skip update)
- **Like Count Update**: On subsequent import of same comment, `like_count` and `updated_at` may change
- **Reply Hierarchy**: `parent_comment_id` set during initial import if comment is a reply; never changes

---

### 2. Video (Existing Entity - METADATA UPDATES ONLY)

**Table**: `videos` (existing)

**From Clarification 2025-11-16-004**: Video field update rules

**Update Rules During Comment Import**:
- **Always update**:
  - `updated_at` = current timestamp
- **Initialize only for NEW videos** (first time video_id seen):
  - `video_id` (YouTube video ID)
  - `title` (from existing "匯入" web scraping)
  - `published_at` (from existing "匯入" or YouTube API)
  - `created_at` (timestamp of first import)
- **Set only if NEW channel**:
  - `channel_id` = only when importing from a channel never seen before

**No schema changes required for comment import feature.**

**Important**: New video detection → Route to existing "匯入" dialog (Clarification 2025-11-16-007)
- Title, published_at, channel metadata captured there
- Comment import feature only updates `updated_at`

---

### 3. Channel (Existing Entity - METADATA UPDATES ONLY)

**Table**: `channels` (existing)

**From Clarification 2025-11-16-003**: Channel field update rules

**Update Rules During Comment Import**:
- **Always update (all imports)**:
  - `comment_count` = recalculate from DB (sum of comments for all videos in channel)
  - `last_import_at` = current timestamp
  - `updated_at` = current timestamp
- **Initialize only for NEW channels** (first time channel_id seen):
  - `video_count` = count of videos from this channel
  - `first_import_at` = timestamp of first import
  - `created_at` = timestamp of first import

**Implementation Note**:
- `comment_count` should be recalculated as `COUNT(*)` from comments table filtered by videos with this channel_id
- This approach ensures accuracy even if comments were partially imported or deleted

**No schema changes required for comment import feature.**

---

### 4. Author (Existing Entity - UNCHANGED)

**Table**: `authors` (existing)

No changes. Used only as foreign key reference.

---

## Data Consistency Rules

### Incremental Import Logic

**Implemented from Clarifications 2025-11-16-001, 2025-11-16-002, 2025-11-16-005, 2025-11-16-006, 2025-11-16-007**

**Entry Point**: User enters video URL

1. **Check if video exists** (Clarification 2025-11-16-007):
   - Yes → **Incremental mode**: Find max(published_at) from comments for this video
   - No → **New video mode**: Invoke existing "匯入" dialog → capture metadata → proceed to import

2. **Fetch Preview** (5 comments) (Clarifications 2025-11-16-001, 2025-11-16-002):
   - Query: `commentThreads.list` with `order=time` (newest first)
   - Limit: 5 results
   - **Do NOT persist** to database (preview only)
   - Return preview array to controller without DB insert

3. **User Action**: Click "確認導入"

4. **Fetch All Comments**:
   - **Incremental**: Fetch comments newer than max(published_at) (newest first via API order)
   - **Full**: Fetch all comments (newest first via API order)
   - **Recursively fetch all reply levels** (Clarification 2025-11-16-006):
     - For each top-level comment with `totalReplyCount > 0`
     - Call YouTube API `comments.list` with `parentId` filter
     - Process all replies recursively (reply-to-reply, etc.)

5. **Duplicate Detection**:
   - Stop immediately if `comment_id` already in database (incremental safety)
   - Stop if published_at <= previous max(published_at) (chronological safety)

6. **Storage** (Clarification 2025-11-16-002 - only on confirm):
   - Check if comment_id exists
   - If exists: skip (do not update like_count or other fields; append-only model)
   - If new: insert with all required fields
   - Set `parent_comment_id` if reply to another comment (Clarification 2025-11-16-006)

7. **Update Related Records** (Clarifications 2025-11-16-003, 2025-11-16-004):
   - **Channels**:
     - `comment_count` = `SELECT COUNT(*) FROM comments WHERE video_id IN (SELECT video_id FROM videos WHERE channel_id = X)`
     - `last_import_at` = now()
     - `updated_at` = now()
     - If new channel: also set `video_count`, `first_import_at`, `created_at`
   - **Videos**:
     - `updated_at` = now()
     - If new video: also set `video_id`, `title`, `published_at`, `created_at`
     - If new channel: also set `channel_id`

### Failure Handling

**From Clarification 2025-11-16-005** (Cancellation):
- **User cancels at preview stage**: Close dialog, no DB changes, return to URL input
- **User cancels during full import**: Return without DB transaction, import stops immediately

**Other Failures**:
- **Preview fetch fails**: Show error, return to URL input
- **Full import fails mid-way**: Partial comments already in DB; next import attempt treats as incremental (duplicate detection stops at previous max)
- **YouTube API quota exceeded**: Log error, show user actionable message, allow retry later

---

## API Response Mapping

### YouTube API v3: commentThreads.list Response Structure

```json
{
  "items": [
    {
      "kind": "youtube#commentThread",
      "etag": "...",
      "id": "...",
      "snippet": {
        "videoId": "abc123",
        "topLevelComment": {
          "kind": "youtube#comment",
          "etag": "...",
          "id": "comment_id_1",
          "snippet": {
            "textDisplay": "Comment text",
            "authorDisplayName": "Username",
            "authorProfileImageUrl": "...",
            "authorChannelUrl": "http://www.youtube.com/channel/UC_channel_id",
            "authorChannelId": {
              "value": "UC_channel_id"
            },
            "videoId": "abc123",
            "canLike": true,
            "likeCount": 5,
            "publishedAt": "2025-11-10T12:00:00Z",
            "updatedAt": "2025-11-10T12:00:00Z"
          }
        },
        "canReply": true,
        "totalReplyCount": 2,
        "isPublic": true
      },
      "replies": {
        "comments": [
          {
            "kind": "youtube#comment",
            "id": "reply_comment_id_1",
            "snippet": {
              "textDisplay": "Reply text",
              "authorChannelId": { "value": "UC_reply_author_id" },
              "videoId": "abc123",
              "likeCount": 0,
              "publishedAt": "2025-11-10T13:00:00Z",
              "parentId": "comment_id_1"
            }
          }
        ]
      }
    }
  ]
}
```

**Mapping to Comment Table**:

| YouTube Field | Database Field | Handling |
|---------------|----------------|----------|
| `items[].snippet.topLevelComment.id` | `comment_id` | Top-level comment ID |
| `items[].snippet.topLevelComment.snippet.textDisplay` | `text` | Comment text (HTML-decoded if needed) |
| `items[].snippet.topLevelComment.snippet.authorChannelId.value` | `author_channel_id` | Author's YouTube channel ID |
| `items[].snippet.topLevelComment.snippet.videoId` | `video_id` | YouTube video ID |
| `items[].snippet.topLevelComment.snippet.likeCount` | `like_count` | Number of likes |
| `items[].snippet.topLevelComment.snippet.publishedAt` | `published_at` | Comment timestamp |
| (none) | `parent_comment_id` | NULL for top-level, set for replies |
| (system) | `created_at` | Set to current timestamp on first insert |
| (system) | `updated_at` | Set to current timestamp on insert or update |

**For Reply Comments** (`items[].replies.comments[*]`):
- Same mapping as above
- `parent_comment_id` = `items[].snippet.topLevelComment.id` (parent of first-level reply)
- For multi-level replies: `parent_comment_id` = `items[].replies.comments[*].snippet.parentId` (from YouTube API)

**Recursive Fetching**:
- YouTube API returns up to 20 replies per thread in the initial response
- For comments with `totalReplyCount > 20`, must call `comments.list` with `parentId` filter
- Recursively process all nested replies at any depth

---

## Validation & Constraints

### Comment Field Validation (Before Insert)

```
✓ comment_id: non-null, string, format like "Ug_..." or "UgxE...", unique per video
✓ video_id: non-null, must exist in videos table
✓ author_channel_id: non-null, must exist in authors table
✓ parent_comment_id: nullable, if set must exist in comments table and have same video_id
✓ text: non-null, string, length > 0
✓ like_count: non-negative integer
✓ published_at: non-null, timestamp <= now()
```

### Import Operation Constraints

```
✓ Cannot import if YouTube API key not configured
✓ Cannot import if video not found by YouTube API
✓ Cannot import if user cancelled "匯入" dialog
✓ Cannot update existing comments (like_count, text) — append-only model
```

### Data Integrity

- **Immutability**: Once a comment is inserted, its `text`, `published_at` never change
- **No overwrites**: Duplicate comment_id detected → skip (do not update)
- **Parent integrity**: All replies must have parent_comment_id pointing to existing comment
- **Referential integrity**: All foreign keys enforced at database level

---

## Change Summary

| Entity | Change Type | Impact |
|--------|------------|--------|
| Comment | Schema Add | Add `parent_comment_id` column + FK index |
| Video | Behavior | Update `updated_at` on import (no schema change) |
| Channel | Behavior | Recalculate `comment_count`, update `last_import_at` (no schema change) |
| Author | None | No changes |

**Migration Required**: Yes, one new migration to add `parent_comment_id` column.

---

## Testing Data Scenarios

### Scenario 1: New Video with Top-Level Comments
- Input: URL of video with 50+ comments, no replies
- Expected: All comments imported, `parent_comment_id = NULL`, video and channel updated
- Assertion: `comments.count() == 50`, all have `parent_comment_id IS NULL`

### Scenario 2: Existing Video with New Comments + Replies
- Input: URL of video imported previously; now has 10 new comments since last import, 5 have replies
- Expected: Only new comments imported, replies correctly linked
- Assertion: `comments.count() for video increased by 15`, new replies have correct `parent_comment_id`

### Scenario 3: Multi-Level Reply Chain
- Input: URL with comment having reply to reply to reply (3 levels deep)
- Expected: All levels imported with correct hierarchy
- Assertion: Parent → reply1 → reply2 → reply3 chain intact

### Scenario 4: Duplicate Prevention
- Input: User re-imports same video without new comments
- Expected: Preview shows 0 new comments, full import stopped at first duplicate
- Assertion: No new records inserted

---

**Status**: ✅ Data model finalized, ready for contract design
