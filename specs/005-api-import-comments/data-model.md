# Data Model: YouTube API 官方導入留言

**Branch**: `005-api-import-comments` | **Date**: 2025-11-17

---

## Entity Relationships

```
Channel (existing)
  ├── id (PK)
  ├── channel_id (unique, from YouTube API)
  ├── name (new field)
  ├── last_import_at (NEW: datetime, nullable)
  ├── comment_count (computed: SUM of videos.comment_count)
  └── tags (via channel_tags pivot) ← NEW RELATIONSHIP
      ├── Tag.id (PK)
      └── Tag.name (unique)

Video (existing, MODIFIED)
  ├── id (PK)
  ├── channel_id (FK to channels.id)
  ├── video_id (unique, from YouTube API)
  ├── title
  ├── published_at
  └── comment_count (NEW: nullable int, calculated after import)

Comment (existing, MODIFIED)
  ├── id (PK)
  ├── video_id (FK to videos.id)
  ├── comment_id (unique, from YouTube API)
  ├── author_channel_id (from YouTube API)
  ├── content (text)
  ├── like_count
  ├── published_at
  ├── parent_comment_id (NEW: FK to comments.id, nullable, for reply hierarchy)
  └── created_at
      └── updated_at

ChannelTag (existing pivot, NO CHANGES)
  ├── channel_id (FK to channels.id)
  └── tag_id (FK to tags.id)
```

---

## Field Specifications

### channels (Modified)

| Column | Type | Nullable | Constraint | Source | Note |
|--------|------|----------|------------|--------|------|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | - | |
| channel_id | VARCHAR(255) | NO | UNIQUE | YouTube API `snippet.channelId` | |
| name | VARCHAR(255) | NO | - | YouTube API `snippet.channelTitle` | |
| last_import_at | DATETIME | YES | - | System (NOW()) | NEW: Timestamp of last successful import |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | System | |
| updated_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP ON UPDATE | System | |

**NEW Validation Rules**:
- `name`: Required, max 255 chars, non-empty
- `last_import_at`: Must be in UTC format `YYYY-MM-DD HH:MM:SS` (e.g., "2025-11-15 10:30:45")

**Relationships**:
- `belongsToMany(Tag::class, 'channel_tags')` – via pivot table (existing)
- `hasMany(Video::class)` – one channel has many videos

---

### videos (Modified)

| Column | Type | Nullable | Constraint | Source | Note |
|--------|------|----------|------------|--------|------|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | - | |
| channel_id | BIGINT UNSIGNED | NO | FK | Videos.channel_id | |
| video_id | VARCHAR(255) | NO | UNIQUE | YouTube API `snippet.videoId` | |
| title | VARCHAR(255) | NO | - | YouTube API `snippet.title` | |
| published_at | DATETIME | NO | - | YouTube API `snippet.publishedAt` | Format: `YYYY-MM-DD HH:MM:SS` (已在之前的版本中支援) |
| comment_count | INT UNSIGNED | YES | - | System (COUNT aggregation) | Calculated after all comments imported |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | System | |
| updated_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP ON UPDATE | System | |

**NEW Validation Rules**:
- `comment_count`: Must be non-negative integer OR NULL
  - Populated AFTER comment import completes
  - Calculated as: `COUNT(comments WHERE video_id = ?) + COUNT(replies)`
  - If import interrupted, remains NULL (can be recalculated via "update" feature)

**Relationships**:
- `belongsTo(Channel::class)` – each video belongs to one channel
- `hasMany(Comment::class)` – one video has many comments

**Migration**:
```php
// database/migrations/2025_11_17_add_comment_count_to_videos.php
Schema::table('videos', function (Blueprint $table) {
  $table->unsignedInteger('comment_count')->nullable()->after('published_at');
});
```

---

### comments (Modified)

| Column | Type | Nullable | Constraint | Source | Note |
|--------|------|----------|------------|--------|------|
| id | BIGINT UNSIGNED | NO | PK, AUTO_INCREMENT | - | |
| video_id | BIGINT UNSIGNED | NO | FK | Comment.video_id | |
| comment_id | VARCHAR(255) | NO | UNIQUE | YouTube API `id` | YouTube's comment ID |
| author_channel_id | VARCHAR(255) | NO | - | YouTube API `snippet.authorChannelId.value` | Channel ID of commenter |
| content | TEXT | NO | - | YouTube API `snippet.textDisplay` | Max 5000 chars (YouTube limit) |
| like_count | INT UNSIGNED | NO | DEFAULT 0 | YouTube API `snippet.likeCount` | |
| published_at | DATETIME | NO | - | YouTube API `snippet.publishedAt` | Format: `YYYY-MM-DD HH:MM:SS` |
| parent_comment_id | BIGINT UNSIGNED | YES | FK (self-referential) | YouTube API (implicit in reply structure) | NEW: NULL if top-level comment |
| created_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP | System | |
| updated_at | TIMESTAMP | NO | DEFAULT CURRENT_TIMESTAMP ON UPDATE | System | |

**NEW Validation Rules**:
- `parent_comment_id`: Optional foreign key to `comments.id`
  - NULL = top-level comment
  - Non-NULL = reply to another comment (up to depth 3 per spec)
  - Supports multi-level nested replies

**Relationships**:
- `belongsTo(Video::class)` – each comment belongs to one video
- `belongsTo(Comment::class, 'parent_comment_id')` – each reply belongs to a parent comment
- `hasMany(Comment::class, 'parent_comment_id')` – each comment may have many replies

**Migration**:
```php
// database/migrations/2025_11_17_add_parent_id_to_comments.php
Schema::table('comments', function (Blueprint $table) {
  $table->unsignedBigInteger('parent_comment_id')->nullable()->after('published_at');
  $table->foreign('parent_comment_id')
    ->references('id')
    ->on('comments')
    ->onDelete('cascade');  // If parent deleted, replies deleted
});
```

---

### channel_tags (Pivot, No Changes)

| Column | Type | Constraint |
|--------|------|-----------|
| channel_id | BIGINT UNSIGNED | FK to channels.id |
| tag_id | BIGINT UNSIGNED | FK to tags.id |

**No modifications to this table** – it already supports the many-to-many relationship between channels and tags.

---

## State Transitions & Lifecycle

### Import Workflow States

```
User Inputs URL
    ↓
[Check Stage 1: Video exists?]
    ├─ YES → "Video already archived" → Modal closes ✗ (no state change)
    └─ NO → Continue
        ↓
[Check Stage 2: Channel exists?]
    ├─ YES → Show preview (User Story 2)
    └─ NO → Show preview + tag selection (User Story 3)
        ↓
[API Stage 3: Fetch all comments (depth 0-3)]
    ├─ API Error → "Retry" button (no DB write)
    └─ Success → Begin DB transaction
        ↓
[DB Stage 4: Insert Channel (first_or_create)]
    ├─ Error → Rollback all, show error ✗
    └─ Success → Continue
        ↓
[DB Stage 5: Insert Video + comment_count = NULL]
    ├─ Error → Rollback all, show error ✗
    └─ Success → Continue
        ↓
[DB Stage 6: Insert Comments recursively (depth 0-3)]
    ├─ Error → Rollback all, show error ✗
    └─ Success → Continue
        ↓
[DB Stage 7: Calculate comment_count + update videos.comment_count]
    ├─ Error → Rollback all, show error ✗
    └─ Success → COMMIT transaction
        ↓
[Update channels.last_import_at = NOW()]
    ↓
Success message: "成功導入 1247 則留言"
    ↓
User closes modal → AJAX refresh comments list
```

### Field Lifecycle

**channels.last_import_at**:
- Initial: NULL
- First import: Set to import completion timestamp (e.g., "2025-11-15 10:30:45")
- Subsequent imports: Updated to latest completion timestamp
- Never reset once set

**videos.comment_count**:
- Initial: NULL (before import)
- After import: Set to `COUNT(comments WHERE video_id = X) + COUNT(parent_comment_id)`
- Never decreases (new imports only add, don't delete)
- If import interrupted: Remains NULL (can be recalculated)

**comments.parent_comment_id**:
- Top-level: NULL
- Replies: Set to parent comment's `comments.id`
- Max depth: 3 levels (YouTube API recursion limit per spec)

---

## Data Consistency Constraints

### Uniqueness

| Column | Constraint | Reason |
|--------|-----------|--------|
| channels.channel_id | UNIQUE | YouTube channel IDs are globally unique |
| videos.video_id | UNIQUE | YouTube video IDs are globally unique |
| comments.comment_id | UNIQUE | YouTube comment IDs are globally unique |

### Referential Integrity

| FK | References | Delete Behavior | Reason |
|----|-----------|-----------------|--------|
| videos.channel_id | channels.id | CASCADE | If channel deleted, related videos should be deleted |
| comments.video_id | videos.id | CASCADE | If video deleted, comments should be deleted |
| comments.parent_comment_id | comments.id | CASCADE | If parent comment deleted, replies should be deleted |
| channel_tags.channel_id | channels.id | CASCADE | If channel deleted, tags association removed |
| channel_tags.tag_id | tags.id | CASCADE | If tag deleted (unlikely), associations removed |

### Temporal Consistency

- `published_at` (comments, videos): Immutable (never updated after import)
- `last_import_at` (channels): Only updated on successful import completion
- `created_at`, `updated_at`: Managed by Laravel (auto-set, auto-update)

---

## Timestamp Format Specification

**ALL datetime fields stored in UTC**:
- Format: `YYYY-MM-DD HH:MM:SS` (e.g., "2025-11-15 10:30:45")
- No timezone abbreviations (e.g., NOT "2025-11-15 10:30:45 UTC")
- MySQL DATETIME type (not TIMESTAMP, which has implicit timezone conversion)

**Conversion from YouTube API** (ISO 8601):
```php
$youtubeDate = "2025-11-15T10:30:45Z";  // YouTube API format
$dbDate = Carbon::createFromIso8601String($youtubeDate)
  ->setTimezone('UTC')
  ->format('Y-m-d H:i:s');  // "2025-11-15 10:30:45"

Comment::create(['published_at' => $dbDate]);
```

---

## Validation Rules

### At Import Time

| Field | Rule | Error Message |
|-------|------|---------------|
| video_url | Must be valid YouTube URL | "無法解析的 YouTube URL" |
| channel_tags | At least 1 (for new channels) | "請至少選擇一個標籤" |
| content (comment) | Non-empty, max 5000 chars | "評論內容無效" |
| author_channel_id | Must not be null | "評論作者資訊遺失" |

### At DB Insert Time

| Field | Rule | Enforcement |
|-------|------|-------------|
| videos.video_id | UNIQUE | Constraint violation → skip if already exists |
| channels.channel_id | UNIQUE | Constraint violation → update if exists (FirstOrCreate) |
| comments.comment_id | UNIQUE | Constraint violation → skip if duplicate |
| comments.parent_comment_id | Must reference existing comment OR be NULL | FK constraint |

---

## Example Data

```sql
-- Channel (new)
INSERT INTO channels (channel_id, name, last_import_at, created_at, updated_at)
VALUES ('UCxxxxx', 'Channel Name', '2025-11-15 10:30:45', NOW(), NOW());

-- Video (new)
INSERT INTO videos (channel_id, video_id, title, published_at, comment_count, created_at, updated_at)
VALUES (1, 'dQw4w9WgXcQ', 'Video Title', '2025-11-10 15:22:00', NULL, NOW(), NOW());

-- Comment (top-level, after import)
INSERT INTO comments (video_id, comment_id, author_channel_id, content, like_count, published_at, parent_comment_id, created_at, updated_at)
VALUES (1, 'Ugx1234...', 'UCyyyyy', 'Great video!', 42, '2025-11-11 08:15:30', NULL, NOW(), NOW());

-- Comment (reply, depth 1)
INSERT INTO comments (video_id, comment_id, author_channel_id, content, like_count, published_at, parent_comment_id, created_at, updated_at)
VALUES (1, 'Ugx5678...', 'UCzzzzz', 'Thanks!', 5, '2025-11-11 09:20:10', 1, NOW(), NOW());

-- Channel tag association
INSERT INTO channel_tags (channel_id, tag_id)
VALUES (1, 1), (1, 3);  -- Tag IDs 1 and 3 assigned to channel 1

-- Video comment count (updated after import)
UPDATE videos SET comment_count = 1247 WHERE id = 1;
```

---

## Implementation Notes

- **No changes to tags table** – existing structure sufficient
- **No changes to channel_tags pivot** – existing structure sufficient
- **Migrations are additive only** – no column deletions or renames
- **Backward compatibility** – new nullable fields don't break existing queries
- **Audit trail** – created_at/updated_at auto-managed by Laravel, enables revision history if needed

---

## Next Steps

1. Run migrations (specified in Migration sections above)
2. Generate Eloquent models with relationships (see quickstart.md for usage examples)
3. Create API contract tests (validate response format matches /contracts/)
4. Implement services + controller per plan.md Phase 1b
