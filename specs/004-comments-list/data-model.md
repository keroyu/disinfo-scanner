# Phase 1: Data Model & Entity Relationships

**Feature**: Comments List View
**Date**: 2025-11-16
**Status**: Design Complete

## Entity Overview

### Primary Entities

#### Comment
The core entity representing a YouTube comment stored in the system.

**Table**: `comments`

**Fields**:
| Field | Type | Index | Nullable | Purpose |
|-------|------|-------|----------|---------|
| id | BIGINT | PK | NO | Primary key (YouTube comment ID) |
| video_id | VARCHAR(255) | YES | NO | Reference to Video; used for navigation links |
| channel_id | VARCHAR(255) | FK | NO | Reference to Channel |
| channel_name | VARCHAR(255) | YES | NO | Channel name (indexed for search) |
| video_title | VARCHAR(500) | YES | NO | Video title (indexed for search) |
| commenter_id | VARCHAR(255) | YES | NO | YouTube commenter ID (indexed for search) |
| content | LONGTEXT | YES | NO | Comment text (indexed for search) |
| published_at | TIMESTAMP | YES | NO | Comment publication date (indexed for sort/filter) |
| like_count | INT | YES | NO | Number of likes on comment (indexed for sort) |
| imported_at | TIMESTAMP | NO | NO | When comment was imported into system |
| updated_at | TIMESTAMP | NO | NO | Last update timestamp |

**Indexes** (for filtering/sorting performance):
- `idx_published_at`: ON published_at (for date range filtering and sorting)
- `idx_like_count`: ON like_count (for sorting by likes)
- `idx_channel_name`: ON channel_name (for keyword search)
- `idx_video_title`: ON video_title (for keyword search)
- `idx_commenter_id`: ON commenter_id (for keyword search)
- `idx_content_search`: ON content (for keyword search, using FULLTEXT or prefix)
- **Composite index**: (channel_name, video_title, commenter_id) for multi-field search

**Relationships**:
- `belongsTo(Video)`: Video associated with comment (video_id → videos.video_id)
- `belongsTo(Channel)`: Channel that published the video (channel_id → channels.channel_id)

**Constraints**:
- Immutable once created (no update operations except `updated_at` timestamp)
- Unique constraint on id (YouTube comment IDs are globally unique)
- Published_at must be in UTC timezone
- Like_count >= 0 (non-negative integer)

**Eloquent Scopes** (query helpers):
```
- filterByKeyword(string $keyword): Filter across channel_name, video_title, commenter_id, content
- filterByDateRange(Carbon $from, Carbon $to): Filter published_at between dates (inclusive)
- sortByLikes(string $direction = 'DESC'): Order by like_count
- sortByDate(string $direction = 'DESC'): Order by published_at
```

---

#### Video
Related entity representing a YouTube video.

**Table**: `videos`

**Fields**:
| Field | Type | Index | Nullable | Purpose |
|-------|------|-------|----------|---------|
| video_id | VARCHAR(255) | PK | NO | YouTube video ID |
| channel_id | VARCHAR(255) | FK | NO | Parent channel |
| title | VARCHAR(500) | NO | NO | Video title |
| description | LONGTEXT | YES | YES | Video description |
| published_at | TIMESTAMP | NO | NO | Video publication date |
| view_count | INT | NO | YES | View count |
| like_count | INT | NO | YES | Like count |
| comment_count | INT | NO | YES | Comment count |
| created_at | TIMESTAMP | NO | NO | Import timestamp |
| updated_at | TIMESTAMP | NO | NO | Last update timestamp |

**Relationships**:
- `hasMany(Comment)`: Comments on this video
- `belongsTo(Channel)`: Channel that published this video

---

#### Channel
Related entity representing a YouTube channel.

**Table**: `channels`

**Fields**:
| Field | Type | Index | Nullable | Purpose |
|-------|------|-------|----------|---------|
| channel_id | VARCHAR(255) | PK | NO | YouTube channel ID |
| channel_name | VARCHAR(255) | NO | NO | Channel name/title |
| url | VARCHAR(500) | NO | NO | YouTube channel URL (e.g., @channelname) |
| description | LONGTEXT | NO | YES | Channel description |
| subscriber_count | INT | NO | YES | Subscriber count |
| video_count | INT | NO | YES | Video count |
| created_at | TIMESTAMP | NO | NO | Import timestamp |
| updated_at | TIMESTAMP | NO | NO | Last update timestamp |

**Relationships**:
- `hasMany(Video)`: Videos published by this channel
- `hasMany(Comment)`: Comments on videos by this channel (through videos)

---

## Query Patterns

### Pattern 1: List All Comments (Paginated)

```php
Comment::query()
  ->orderBy('published_at', 'DESC')  // Default sort: newest first
  ->paginate(500);
```

**Performance**: O(log n) with indexed published_at column

---

### Pattern 2: Search + Filter + Sort + Paginate

```php
$query = Comment::query()
  ->filterByKeyword($searchTerm)           // WHERE channel_name LIKE ... OR video_title LIKE ...
  ->filterByDateRange($fromDate, $toDate)  // WHERE published_at BETWEEN ...
  ->sortByLikes('DESC')                     // ORDER BY like_count DESC
  ->paginate(500);
```

**Performance**: O(log n) with composite indexes; results determined by filter selectivity

---

### Pattern 3: Navigation Link Construction

```php
// Channel link
$channelUrl = "https://www.youtube.com/@" . $comment->channel_name;

// Video with comment anchor
$videoUrl = "https://www.youtube.com/watch?v=" . $comment->video_id . "&lc=" . $comment->id;
```

**Data Flow**: Comment model → View → HTML <a> tags

---

## Validation Rules

### Comment Field Validation (for display, immutable):

| Field | Rule | Reason |
|-------|------|--------|
| id | Required, string, max 255 | YouTube IDs can be long strings |
| video_id | Required, string, max 255 | Must reference existing video |
| channel_id | Required, string, max 255 | Must reference existing channel |
| channel_name | Required, string, max 255 | Display requirement |
| video_title | Required, string, max 500 | Display requirement |
| commenter_id | Required, string, max 255 | Display requirement |
| content | Required, string | Comment text required |
| published_at | Required, date-time | For filtering/sorting |
| like_count | Required, integer, >= 0 | Non-negative count |

**Note**: Validation is for data integrity verification only; comments are created via import feature, not user input.

---

## State Transitions

Comments follow a simple state model:

```
IMPORTED → ACTIVE → [DELETED FROM DB]
```

- **IMPORTED**: Comment is imported from YouTube
- **ACTIVE**: Comment is available for querying (default state)
- **DELETED**: (Edge case) Comment is removed from database (not soft-deleted; physically deleted)

No explicit state field is needed; deletion is handled by physical record deletion.

---

## Data Integrity Constraints

1. **Referential Integrity**: Every Comment must reference an existing Video and Channel
   - Enforce via foreign keys in migrations
   - Cascade behavior: If Video/Channel is deleted, related Comments should be cascaded (or soft-deleted)

2. **Unique Constraint**: YouTube comment IDs are globally unique
   - Enforce unique constraint on `id` column
   - Prevents duplicate comment records in database

3. **Immutability**: Comments are immutable after import
   - No UPDATE operations on content, published_at, like_count
   - Only updated_at and imported_at are allowed to change (timestamp of last sync)

4. **Non-negative Counts**: Like counts and view counts must be >= 0
   - Database constraint: CHECK like_count >= 0
   - Application validation if manual sync updates like counts

---

## Indexing Strategy

**Primary Indexes** (Required for performance):

1. `published_at` (B-tree)
   - Used for: Date range filtering, chronological sorting
   - Selectivity: Medium (many comments per day)
   - Query: `WHERE published_at BETWEEN ? AND ?`

2. `like_count` (B-tree)
   - Used for: Sorting by engagement
   - Selectivity: Medium (varying engagement)
   - Query: `ORDER BY like_count DESC LIMIT 500`

3. `channel_name` (B-tree prefix or FULLTEXT)
   - Used for: Keyword search on channel names
   - Selectivity: Low (few channels, many comments per channel)
   - Query: `WHERE channel_name LIKE '%keyword%'`

4. `video_title` (B-tree prefix or FULLTEXT)
   - Used for: Keyword search on video titles
   - Selectivity: Medium (many videos, few comments per video)
   - Query: `WHERE video_title LIKE '%keyword%'`

5. `commenter_id` (B-tree prefix)
   - Used for: Keyword search on commenter IDs
   - Selectivity: High (many unique commenters)
   - Query: `WHERE commenter_id LIKE '%keyword%'`

6. `content` (FULLTEXT, optional for MVP)
   - Used for: Full-text search on comment content
   - Selectivity: Varies by keyword
   - Query: `WHERE MATCH(content) AGAINST('+keyword' IN BOOLEAN MODE)`

**Composite Index** (Optional for multi-field search):
```sql
INDEX idx_search_fields (channel_name, video_title, commenter_id)
```
Improves queries that filter on multiple fields.

---

## Migration Notes

**New Indexes to Add**:

```php
// In migration for comments list feature
Schema::table('comments', function (Blueprint $table) {
    // Add if not already indexed
    $table->index('published_at');
    $table->index('like_count');
    $table->index('channel_name');
    $table->index('video_title');
    $table->index('commenter_id');
    // Optional: FULLTEXT index on content
    // $table->fullText('content');
});
```

**Assumptions**:
- Comments table already exists (created by comment-import feature)
- Fields channel_name, video_id, commenter_id already exist
- published_at and like_count fields already exist

---

## API Response Schema

**Comment List Response** (200 OK):

```json
{
  "data": [
    {
      "id": "UgzKp...",
      "channel_name": "Example Channel",
      "video_title": "Example Video Title",
      "commenter_id": "UCuser123",
      "content": "This is a comment",
      "published_at": "2025-11-15T10:30:00Z",
      "like_count": 42,
      "video_id": "dQw4w9WgXcQ",
      "channel_id": "UCExampleChannel"
    }
  ],
  "pagination": {
    "total": 12500,
    "per_page": 500,
    "current_page": 1,
    "last_page": 25,
    "from": 1,
    "to": 500
  },
  "filters": {
    "keyword": null,
    "date_from": null,
    "date_to": null,
    "sort": "published_at",
    "direction": "DESC"
  }
}
```

---

## Summary

The comments list feature operates on the existing Comment model with added Eloquent scopes for filtering and sorting. Performance is optimized through strategic indexing on frequently-filtered columns. The data model is read-only for this feature (immutable comments), and all complexity is handled at the query layer using Eloquent's query builder.
