# Data Model: YouTube API 官方導入留言

**Branch**: `005-api-import-comments` | **Date**: 2025-11-17 (Updated)

---

## Entity Relationships

```
Channel
  ├── channel_id (PK, VARCHAR, from YouTube API)
  ├── channel_name (VARCHAR, nullable)
  ├── tag_ids (VARCHAR, comma-separated tag IDs, e.g., "6,9")
  ├── first_import_at (DATETIME, nullable)
  ├── last_import_at (DATETIME, nullable)
  ├── created_at (DATETIME)
  └── updated_at (DATETIME)
  └── Relationships:
      ├── hasMany(Video, 'channel_id')
      └── Tags (via tag_ids string parsing, no pivot table)

Video
  ├── video_id (PK, VARCHAR, from YouTube API)
  ├── channel_id (FK to channels.channel_id)
  ├── title (VARCHAR, nullable)
  ├── published_at (DATETIME, nullable)
  ├── comment_count (INTEGER, nullable, calculated after import)
  ├── created_at (DATETIME)
  └── updated_at (DATETIME)
  └── Relationships:
      ├── belongsTo(Channel, 'channel_id')
      └── hasMany(Comment, 'video_id')

Comment
  ├── comment_id (PK, VARCHAR, from YouTube API)
  ├── video_id (FK to videos.video_id)
  ├── author_channel_id (FK to authors.author_channel_id)
  ├── text (TEXT, not null)
  ├── like_count (INTEGER, default 0)
  ├── published_at (DATETIME, nullable)
  ├── parent_comment_id (FK to comments.comment_id, nullable, reply hierarchy)
  ├── imported_at (DATETIME, nullable)
  ├── created_at (DATETIME)
  └── updated_at (DATETIME)
  └── Relationships:
      ├── belongsTo(Video, 'video_id')
      ├── belongsTo(Author, 'author_channel_id')
      ├── belongsTo(Comment, 'parent_comment_id') [self-referential]
      └── hasMany(Comment, 'parent_comment_id') [replies]

Tag
  ├── tag_id (PK, INTEGER AUTO_INCREMENT)
  ├── code (VARCHAR UNIQUE, not null)
  ├── name (VARCHAR, not null)
  ├── description (TEXT, nullable)
  ├── color (VARCHAR, not null)
  ├── created_at (DATETIME)
  └── updated_at (DATETIME)
  └── No direct relationship to Channel (referenced via channels.tag_ids)

Author
  ├── author_channel_id (PK, VARCHAR, from YouTube API)
  ├── name (VARCHAR, nullable)
  ├── profile_url (VARCHAR, nullable)
  ├── created_at (DATETIME)
  └── updated_at (DATETIME)
  └── Relationships:
      └── hasMany(Comment, 'author_channel_id')
```

---

## Field Specifications

### channels

| Column | Type | Nullable | Constraint | Source | Note |
|--------|------|----------|------------|--------|------|
| channel_id | VARCHAR | NO | PK | YouTube API `snippet.channelId` | YouTube's channel ID |
| channel_name | VARCHAR | YES | - | YouTube API `snippet.channelTitle` | |
| tag_ids | VARCHAR | YES | - | User selection | Comma-separated tag IDs (e.g., "6,9") |
| first_import_at | DATETIME | YES | - | System (NOW()) | First comment import timestamp |
| last_import_at | DATETIME | YES | - | System (NOW()) | Last comment import timestamp |
| created_at | DATETIME | NO | - | System | |
| updated_at | DATETIME | NO | - | System | |

**Validation Rules**:
- `channel_name`: Max 255 chars
- `tag_ids`: Comma-separated integers (validated against tags table)
- `first_import_at`, `last_import_at`: UTC format `YYYY-MM-DD HH:MM:SS`

**Relationships**:
- `hasMany(Video::class, 'channel_id')` – one channel has many videos
- **No pivot table for tags** – uses `tag_ids` string field

**Helper Methods** (in Channel model):
```php
getTagIdsArray(): array       // "6,9" → [6, 9]
setTagIdsFromArray(array)     // [6, 9] → "6,9"
tags(): Collection            // Query tags by IDs
```

---

### videos

| Column | Type | Nullable | Constraint | Source | Note |
|--------|------|----------|------------|--------|------|
| video_id | VARCHAR | NO | PK | YouTube API `id` | YouTube's video ID |
| channel_id | VARCHAR | NO | FK | YouTube API `snippet.channelId` | Foreign key to channels |
| title | VARCHAR | YES | - | YouTube API `snippet.title` | |
| published_at | DATETIME | YES | - | YouTube API `snippet.publishedAt` | Format: `YYYY-MM-DD HH:MM:SS` |
| comment_count | INTEGER | YES | - | System (COUNT aggregation) | Calculated after import |
| created_at | DATETIME | NO | - | System | |
| updated_at | DATETIME | NO | - | System | |

**Indexes**:
- `channel_id` (for JOIN queries)

**Validation Rules**:
- `comment_count`: Non-negative integer OR NULL
  - Calculated as: `COUNT(comments WHERE video_id = ?)`
  - Updated after successful comment import

**Relationships**:
- `belongsTo(Channel::class, 'channel_id')`
- `hasMany(Comment::class, 'video_id')`

---

### comments

| Column | Type | Nullable | Constraint | Source | Note |
|--------|------|----------|------------|--------|------|
| comment_id | VARCHAR | NO | PK | YouTube API `id` | YouTube's comment ID |
| video_id | VARCHAR | NO | FK | YouTube API `snippet.videoId` | Foreign key to videos |
| author_channel_id | VARCHAR | NO | FK | YouTube API `snippet.authorChannelId.value` | Foreign key to authors |
| text | TEXT | NO | - | YouTube API `snippet.textDisplay` | Max 5000 chars |
| like_count | INTEGER | NO | DEFAULT 0 | YouTube API `snippet.likeCount` | |
| published_at | DATETIME | YES | - | YouTube API `snippet.publishedAt` | Format: `YYYY-MM-DD HH:MM:SS` |
| parent_comment_id | VARCHAR | YES | FK (self) | YouTube API (reply structure) | NULL = top-level comment |
| imported_at | DATETIME | YES | - | System (NOW()) | Import timestamp |
| created_at | DATETIME | NO | - | System | |
| updated_at | DATETIME | NO | - | System | |

**Indexes**:
- `video_id` (primary lookup)
- `author_channel_id` (for author queries)
- `parent_comment_id` (for reply hierarchy)
- `like_count` (for sorting)
- `published_at` (for time-based queries)
- Composite: `(video_id, comment_id)` for efficient duplicate detection

**Validation Rules**:
- `parent_comment_id`: Optional foreign key to `comments.comment_id`
  - NULL = top-level comment
  - Non-NULL = reply (max depth 3 per spec)
  - Must reference existing comment if set

**Relationships**:
- `belongsTo(Video::class, 'video_id')`
- `belongsTo(Author::class, 'author_channel_id')`
- `belongsTo(Comment::class, 'parent_comment_id')` – parent comment
- `hasMany(Comment::class, 'parent_comment_id')` – replies

---

### tags

| Column | Type | Nullable | Constraint | Source | Note |
|--------|------|----------|------------|--------|------|
| tag_id | INTEGER | NO | PK, AUTO_INCREMENT | - | |
| code | VARCHAR | NO | UNIQUE | System | Unique code for tag |
| name | VARCHAR | NO | - | System | Display name |
| description | TEXT | YES | - | System | Optional description |
| color | VARCHAR | NO | - | System | Hex color code |
| created_at | DATETIME | NO | - | System | |
| updated_at | DATETIME | NO | - | System | |

**Indexes**:
- `code` (UNIQUE)

**No direct relationship to Channel** – referenced via `channels.tag_ids` string field

---

### authors

| Column | Type | Nullable | Constraint | Source | Note |
|--------|------|----------|------------|--------|------|
| author_channel_id | VARCHAR | NO | PK | YouTube API `snippet.authorChannelId.value` | YouTube channel ID |
| name | VARCHAR | YES | - | YouTube API `snippet.authorDisplayName` | Display name |
| profile_url | VARCHAR | YES | - | Constructed from author_channel_id | YouTube channel URL |
| created_at | DATETIME | NO | - | System | |
| updated_at | DATETIME | NO | - | System | |

**Indexes**:
- `author_channel_id` (PK)

**Relationships**:
- `hasMany(Comment::class, 'author_channel_id')`

---

## State Transitions & Lifecycle

### Import Workflow States

```
User Inputs YouTube URL
    ↓
[Check Video Exists in DB?]
    ├─ YES → Show "Video already exists" message
    └─ NO → Continue
        ↓
[Check Channel Exists in DB?]
    ├─ YES → Fetch preview (User Story 2: new_video_existing_channel)
    └─ NO → Fetch preview + show tag selection (User Story 3: new_video_new_channel)
        ↓
[YouTube API: Fetch video metadata + preview comments (max 5)]
    ├─ API Error → Show "Retry" button (no DB write)
    └─ Success → Show preview to user
        ↓
[User Confirms Import]
    ↓
[YouTube API: Fetch ALL comments recursively (depth 0-3)]
    ├─ API Error → Show error, can retry ✗
    └─ Success → Begin DB transaction
        ↓
[DB Transaction Stage 1: Insert/Update Channel]
    ├─ New Channel: Create with tag_ids, first_import_at, last_import_at
    ├─ Existing Channel: Update last_import_at only
    ├─ Error → Rollback all, show error ✗
    └─ Success → Continue
        ↓
[DB Transaction Stage 2: Insert Video with comment_count = NULL]
    ├─ Error → Rollback all, show error ✗
    └─ Success → Continue
        ↓
[DB Transaction Stage 3: Insert ALL Comments recursively]
    ├─ Top-level comments: parent_comment_id = NULL
    ├─ Replies: parent_comment_id = parent's comment_id
    ├─ Error → Rollback all, show error ✗
    └─ Success → Continue
        ↓
[DB Transaction Stage 4: Calculate & Update videos.comment_count]
    ├─ Count = COUNT(comments WHERE video_id = ?)
    ├─ Error → Rollback all, show error ✗
    └─ Success → COMMIT transaction
        ↓
Success message: "成功導入 {count} 則留言"
    ↓
Modal closes → AJAX refresh comment list (no page reload)
```

### Field Lifecycle

**channels.first_import_at**:
- Initial: NULL
- First import: Set to NOW() when first comment imported
- Never updated again (immutable after first set)

**channels.last_import_at**:
- Initial: NULL
- Every import: Updated to NOW() on successful completion
- Always reflects most recent import timestamp

**channels.tag_ids**:
- Initial: NULL (for existing channels)
- New channel: Set during first import with user-selected tags
- Can be updated later via channel management UI

**videos.comment_count**:
- Initial: NULL (before import)
- After import: Set to `COUNT(comments WHERE video_id = X)`
- Updated on incremental imports (count increases)
- Never decreases (imports only add, never delete)

**comments.parent_comment_id**:
- Top-level comment: NULL
- Reply (depth 1): Set to parent's `comment_id`
- Nested reply (depth 2-3): Set to immediate parent's `comment_id`
- Max depth: 3 levels (per YouTube API + spec)

---

## Data Consistency Constraints

### Uniqueness

| Column | Constraint | Reason |
|--------|-----------|--------|
| channels.channel_id | PK | YouTube channel IDs are globally unique |
| videos.video_id | PK | YouTube video IDs are globally unique |
| comments.comment_id | PK | YouTube comment IDs are globally unique |
| tags.code | UNIQUE | Tag codes must be unique |

### Referential Integrity

| FK | References | Delete Behavior | Reason |
|----|-----------|-----------------|--------|
| videos.channel_id | channels.channel_id | CASCADE | If channel deleted, videos should be deleted |
| comments.video_id | videos.video_id | CASCADE | If video deleted, comments should be deleted |
| comments.author_channel_id | authors.author_channel_id | CASCADE | If author deleted, comments cascade |
| comments.parent_comment_id | comments.comment_id | CASCADE | If parent deleted, replies cascade |

### Temporal Consistency

- `published_at` (comments, videos): **Immutable** (never updated after import)
- `first_import_at` (channels): **Immutable** (set once, never updated)
- `last_import_at` (channels): Updated on every successful import
- `created_at`, `updated_at`: Managed by Laravel (auto-set, auto-update)

---

## Timestamp Format Specification

**ALL datetime fields stored in UTC**:
- Format: `YYYY-MM-DD HH:MM:SS` (e.g., "2025-11-15 10:30:45")
- No timezone suffixes (NOT "2025-11-15 10:30:45 UTC")
- MySQL/SQLite DATETIME type

**Conversion from YouTube API** (ISO 8601):
```php
use Carbon\Carbon;

$youtubeDate = "2025-11-15T10:30:45Z";  // YouTube API ISO 8601 format
$dbDate = Carbon::parse($youtubeDate)
    ->setTimezone('UTC')
    ->format('Y-m-d H:i:s');  // "2025-11-15 10:30:45"

Comment::create(['published_at' => $dbDate]);
```

---

## Validation Rules

### At Import Time (User Input)

| Field | Rule | Error Message |
|-------|------|---------------|
| video_url | Must be valid YouTube URL | "無法解析的 YouTube URL" |
| channel_tags | At least 1 tag (for new channels) | "請至少選擇一個標籤" |

### At DB Insert Time (System)

| Field | Rule | Enforcement |
|-------|------|-------------|
| videos.video_id | UNIQUE (PK) | Skip if duplicate (use FirstOrCreate) |
| channels.channel_id | UNIQUE (PK) | Update if exists (use FirstOrCreate) |
| comments.comment_id | UNIQUE (PK) | Skip if duplicate (use FirstOrCreate) |
| comments.parent_comment_id | Must reference existing comment OR NULL | FK constraint |
| comments.text | Non-empty, max 5000 chars | Application validation |

---

## Example Data

```sql
-- Channel (new, with tags)
INSERT INTO channels (channel_id, channel_name, tag_ids, first_import_at, last_import_at, created_at, updated_at)
VALUES ('UCxxxxx', 'Channel Name', '6,9', '2025-11-15 10:30:45', '2025-11-15 10:30:45', NOW(), NOW());

-- Video (new, comment_count will be set after import)
INSERT INTO videos (video_id, channel_id, title, published_at, comment_count, created_at, updated_at)
VALUES ('dQw4w9WgXcQ', 'UCxxxxx', 'Video Title', '2025-11-10 15:22:00', NULL, NOW(), NOW());

-- Comment (top-level)
INSERT INTO comments (comment_id, video_id, author_channel_id, text, like_count, published_at, parent_comment_id, created_at, updated_at)
VALUES ('Ugx1234abc', 'dQw4w9WgXcQ', 'UCyyyyy', 'Great video!', 42, '2025-11-11 08:15:30', NULL, NOW(), NOW());

-- Comment (reply, depth 1)
INSERT INTO comments (comment_id, video_id, author_channel_id, text, like_count, published_at, parent_comment_id, created_at, updated_at)
VALUES ('Ugx5678def', 'dQw4w9WgXcQ', 'UCzzzzz', 'Thanks!', 5, '2025-11-11 09:20:10', 'Ugx1234abc', NOW(), NOW());

-- Update video comment count (after all comments imported)
UPDATE videos SET comment_count = 1247 WHERE video_id = 'dQw4w9WgXcQ';
```

---

## Key Changes from Original Design

### ✅ Implemented Changes

1. **Removed `channel_tags` pivot table**
   - Replaced with `channels.tag_ids` VARCHAR field
   - Format: comma-separated tag IDs (e.g., "6,9")
   - Simpler schema, easier to query and update

2. **Removed `channels.video_count` and `channels.comment_count`**
   - Use aggregation instead: `withCount('videos')`, `withSum('videos', 'comment_count')`
   - Eliminates redundant data storage
   - Ensures data consistency

3. **Added `channels.first_import_at`**
   - Tracks first ever import timestamp (immutable)
   - Distinct from `last_import_at` (updated each import)

4. **Updated primary keys to use YouTube IDs**
   - `channels.channel_id` (VARCHAR, from YouTube)
   - `videos.video_id` (VARCHAR, from YouTube)
   - `comments.comment_id` (VARCHAR, from YouTube)
   - Foreign keys reference these natural keys

5. **Updated `comments.parent_comment_id` to VARCHAR**
   - References `comments.comment_id` (VARCHAR) not internal ID
   - Consistent with other foreign keys

---

## Implementation Notes

- **No channel_tags pivot table** – tags stored as comma-separated string in `channels.tag_ids`
- **Migrations are additive** – added `tag_ids`, removed old count fields via separate migrations
- **Backward compatibility** – new nullable fields don't break existing queries
- **Natural keys** – uses YouTube's IDs as primary keys for channels, videos, comments
- **Audit trail** – `created_at`/`updated_at` auto-managed by Laravel

---

## Next Steps

1. Verify migrations match current database schema
2. Ensure Eloquent models have correct relationships
3. Update service layer to use `tag_ids` string field
4. Create API contract tests validating response formats
5. Test import workflow end-to-end with real YouTube data
