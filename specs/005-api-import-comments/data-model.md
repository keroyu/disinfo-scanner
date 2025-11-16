# Data Model: YouTube API Comments Import

**Date**: 2025-11-17
**Feature**: `005-api-import-comments`
**Based On**: Feature spec, research.md findings

---

## Overview

The YouTube API import feature operates on three core entities: **Video**, **Channel**, and **Comment**. These entities represent data from YouTube's API with explicit field mappings. The data model emphasizes:

- **Immutability**: Comments are stored as-is from YouTube; no overwrites
- **Traceability**: Import timestamps and source tracking for audit trails
- **Atomicity**: Complete import success or complete rollback (no partial data)
- **Threading**: Parent-child relationships via `parent_comment_id` field

---

## Entity: Video

Represents a YouTube video, identified by YouTube's unique `video_id`.

### Fields

| Field Name | Type | Nullable | Source | Notes |
|------------|------|----------|--------|-------|
| `id` | BIGINT | NO | System | Auto-incrementing primary key (Laravel) |
| `video_id` | VARCHAR(255) | NO | YouTube API | YouTube's unique video identifier; must be indexed for uniqueness |
| `channel_id` | BIGINT | NO | System FK | Foreign key to channels table (resolved from channel_name via YouTube API) |
| `title` | VARCHAR(255) | NO | YouTube API | From `videos.list` snippet.title |
| `published_at` | TIMESTAMP | YES | YouTube API | From `videos.list` snippet.publishedAt; when video was originally published |
| `created_at` | TIMESTAMP | NO | System | Timestamp of first import (DISINFO_SCANNER import, not YouTube publish) |
| `updated_at` | TIMESTAMP | NO | System | Timestamp of latest import attempt/completion |

### Validation Rules

- `video_id`: Must match YouTube video ID format (11 alphanumeric characters)
- `channel_id`: Must reference existing record in channels table
- `title`: Non-empty string, max 255 chars
- `published_at`: Must be valid timestamp or NULL
- `created_at` / `updated_at`: System-managed, cannot be manually set

### State Transitions

```
NOT_EXISTS → CREATED (on first successful import)
CREATED → UPDATED (on subsequent import attempts)
CREATED/UPDATED → FAILED (if import error; reverts on retry via transaction)
```

### Relationships

- `has_many :comments` - All comments for this video
- `belongs_to :channel` - Channel that published this video

### Indexes

- `video_id` (UNIQUE) - For duplicate detection
- `channel_id` (INDEX) - For channel queries
- `created_at` (INDEX) - For time-based queries

---

## Entity: Channel

Represents a YouTube channel, identified by YouTube's unique `channel_id`.

### Fields

| Field Name | Type | Nullable | Source | Notes |
|------------|------|----------|--------|-------|
| `id` | BIGINT | NO | System | Auto-incrementing primary key |
| `channel_id` | VARCHAR(255) | NO | YouTube API | YouTube's unique channel identifier |
| `name` | VARCHAR(255) | NO | YouTube API | From `commentThreads.snippet.authorChannelDisplayName` (top-level comment snippet) |
| `video_count` | INT | NO | System | Count of videos imported from this channel; incremented on new video import |
| `comment_count` | INT | NO | System | Total count of comments from all videos of this channel; recalculated after import |
| `first_import_at` | TIMESTAMP | NO | System | Timestamp of first import (when channel first appeared in imported data) |
| `last_import_at` | TIMESTAMP | NO | System | Timestamp of most recent import (always updated on each import) |
| `created_at` | TIMESTAMP | NO | System | Timestamp when channel record created in DB |
| `updated_at` | TIMESTAMP | NO | System | Timestamp when channel record last modified |

### Validation Rules

- `channel_id`: Must match YouTube channel ID format
- `name`: Non-empty string, max 255 chars
- `video_count`: Non-negative integer
- `comment_count`: Non-negative integer (sum of all comments from this channel's videos)
- Timestamps: `first_import_at` immutable after creation, `last_import_at` always updated on import

### State Transitions

```
NOT_EXISTS → CREATED (when channel first appears in imported comments)
CREATED → UPDATED (on subsequent imports, video_count/comment_count recalculated)
```

### Relationships

- `has_many :videos` - All videos from this channel
- `has_many :comments` - All comments (through videos)

### Indexes

- `channel_id` (UNIQUE) - For duplicate detection
- `created_at` (INDEX) - For time-based analytics

---

## Entity: Comment

Represents a YouTube comment or reply, identified by YouTube's unique `comment_id`.

### Fields

| Field Name | Type | Nullable | Source | Notes |
|------------|------|----------|--------|-------|
| `id` | BIGINT | NO | System | Auto-incrementing primary key |
| `comment_id` | VARCHAR(255) | NO | YouTube API | YouTube's unique comment identifier |
| `video_id` | BIGINT | NO | System FK | Foreign key to videos table |
| `author_channel_id` | VARCHAR(255) | YES | YouTube API | From `snippet.authorChannelId.value` or snippet.authorChannelUrl; identifies comment author |
| `text` | LONGTEXT | NO | YouTube API | From `snippet.textDisplay`; full comment text (plaintext, not HTML) |
| `like_count` | INT | NO | YouTube API | From `snippet.likeCount`; number of likes at import time |
| `parent_comment_id` | VARCHAR(255) | YES | YouTube API | For replies: the parent comment's YouTube comment_id; NULL for top-level comments |
| `published_at` | TIMESTAMP | NO | YouTube API | From `snippet.publishedAt`; when comment was originally published |
| `created_at` | TIMESTAMP | NO | System | Timestamp of import (DISINFO_SCANNER import time, not YouTube publish time) |
| `updated_at` | TIMESTAMP | NO | System | Timestamp of last import attempt (typically same as created_at for immutable comments) |

### Validation Rules

- `comment_id`: Must be non-empty string (YouTube format)
- `video_id`: Must reference existing record in videos table
- `author_channel_id`: Can be NULL if YouTube API doesn't provide (private accounts)
- `text`: Non-empty string
- `like_count`: Non-negative integer
- `parent_comment_id`: NULL for top-level comments, valid YouTube comment_id for replies
- `published_at`: Must be valid timestamp
- `created_at` / `updated_at`: System-managed

### Data Integrity Rules

- **No Duplicates**: Uniqueness constraint on (`video_id`, `comment_id`) pair - prevents re-importing same comment
- **Parent-Child Integrity**: If `parent_comment_id` is not NULL, referenced comment must exist in same video
- **Tree Structure**: Replies must have `parent_comment_id` set; top-level comments must have it NULL
- **No Overwrites**: Comments are immutable; if duplicate detected, skip insertion (no UPDATE)

### State Transitions

```
NOT_EXISTS → CREATED (on first import)
CREATED → DUPLICATE_SKIPPED (if re-imported, no update performed)
```

### Relationships

- `belongs_to :video` - The video this comment belongs to
- `has_many :replies` - All replies to this comment (self-join via parent_comment_id)
- `belongs_to :parent_comment` - Parent comment (if this is a reply)

### Indexes

- `comment_id` (INDEX) - For duplicate detection
- `video_id` (INDEX) - For video queries
- `parent_comment_id` (INDEX) - For reply thread traversal
- `author_channel_id` (INDEX) - For author analytics
- `published_at` (INDEX) - For incremental import (max timestamp query)
- Composite: `(video_id, comment_id)` (UNIQUE) - Prevent duplicates per video

---

## Entity: Import Session (Audit)

Optional: Structured logging entity for observable systems (Constitution Principle III).

### Rationale

Each import generates a structured log with trace ID. While this can be stored in application logs, keeping a DB record enables future analytics on import patterns, failures, and campaign detection.

### Fields

| Field Name | Type | Nullable | Source | Notes |
|------------|------|----------|--------|-------|
| `id` | BIGINT | NO | System | Primary key |
| `trace_id` | VARCHAR(255) | NO | System | UUID unique per import session (set at start) |
| `video_id` | VARCHAR(255) | NO | YouTube API | The video being imported |
| `import_type` | ENUM | NO | System | "new" or "incremental" |
| `status` | ENUM | NO | System | "started", "metadata_fetched", "preview_fetched", "fetch_completed", "committed", "failed" |
| `comments_fetched` | INT | YES | System | Total comment count fetched (after all recursive replies) |
| `error_message` | TEXT | YES | System | Error message if status = failed |
| `started_at` | TIMESTAMP | NO | System | When import started |
| `completed_at` | TIMESTAMP | YES | System | When import completed (NULL if still in progress) |
| `created_at` | TIMESTAMP | NO | System | Record creation timestamp |

### Relationships

- `belongs_to :video` - The video being imported

### Indexes

- `trace_id` (UNIQUE) - For tracing specific import sessions
- `video_id` (INDEX) - For video import history
- `started_at` (INDEX) - For time-range audit queries

---

## Database Schema Notes

### Transaction Behavior

All import operations execute within a single `DB::transaction()`:

1. Check if channel exists; INSERT or UPDATE accordingly
2. Check if video exists; INSERT or UPDATE accordingly
3. INSERT log record with trace_id (optional, for audit)
4. Fetch all comments from YouTube API
5. INSERT comments (batch insert for performance)
6. INSERT replies (batch insert, respecting parent_comment_id)
7. UPDATE video/channel counts and timestamps
8. COMMIT (auto-commit on success, auto-ROLLBACK on any exception)

### Handling Incremental Imports

For existing videos, the import identifies the max `published_at` from existing comments:

```sql
SELECT MAX(published_at) FROM comments WHERE video_id = ?
```

Then fetches only comments newer than this timestamp using primary/secondary stopping conditions (see research.md).

### Migration Strategy

If tables don't exist, Laravel migrations create them:

```
app/database/migrations/YYYY_MM_DD_HHMMSS_create_videos_table.php
app/database/migrations/YYYY_MM_DD_HHMMSS_create_channels_table.php
app/database/migrations/YYYY_MM_DD_HHMMSS_create_comments_table.php
app/database/migrations/YYYY_MM_DD_HHMMSS_create_import_sessions_table.php (optional)
```

---

## Conformance with Feature Spec

This data model directly addresses requirements:

- **FR-009**: Comments stored with correct field mapping (comment_id, video_id, author_channel_id, text, like_count, published_at, parent_comment_id, created_at, updated_at)
- **FR-010**: Replies linked via `parent_comment_id` with recursive tree structure
- **FR-015a**: Channel existence checked before INSERT/UPDATE
- **FR-016**: Channels and videos tables updated ONCE per import with correct counts and timestamps
- **SC-003**: 100% of comments stored with correct field mappings
- **SC-005**: Reply comments linked to parents with 100% accuracy

---

## Conformance with Constitution

- **Test-First**: Data validation rules testable via unit tests on model methods
- **API-First**: Service methods return data in structured format (arrays/DTOs), not raw models
- **Observable**: Import session entity tracks source, timestamp, record count
- **Contract Testing**: Comment/video/channel contracts defined here, testable against schema
- **Semantic Versioning**: Schema changes documented and versioned in migrations
