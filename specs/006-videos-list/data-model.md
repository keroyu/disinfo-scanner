# Data Model: Videos List

**Feature**: Videos List
**Date**: 2025-11-18
**Phase**: Phase 1 - Data Design

## Entity Overview

This feature uses **existing database tables** with no schema changes required. The data model leverages Laravel Eloquent relationships and query scopes to compute derived attributes.

---

## Core Entities

### Video

**Table**: `videos` (already exists)

**Primary Key**: `video_id` (string)

**Attributes**:
| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `video_id` | string | No | YouTube video ID (primary key) |
| `channel_id` | string | No | Foreign key to channels table |
| `title` | string | Yes | Video title |
| `published_at` | timestamp | Yes | Video publication date/time |
| `comment_count` | unsigned int | Yes | Cached comment count (may be stale) |
| `created_at` | timestamp | No | Record creation timestamp |
| `updated_at` | timestamp | No | Record update timestamp |

**Indexes**:
- Primary: `video_id`
- Foreign key: `channel_id` → `channels.channel_id`
- Additional: `channel_id` (for join optimization)

**Relationships**:
- `belongsTo(Channel::class, 'channel_id', 'channel_id')` - Video belongs to one Channel
- `hasMany(Comment::class, 'video_id', 'video_id')` - Video has many Comments

**Computed Attributes** (via query scopes):
| Attribute | Computation | Usage |
|-----------|-------------|-------|
| `actual_comment_count` | `COUNT(*)` from comments table | Display accurate comment count (not cached value) |
| `last_comment_time` | `MAX(published_at)` from comments table | Display most recent comment timestamp |

---

### Channel

**Table**: `channels` (already exists, no changes)

**Primary Key**: `channel_id` (string)

**Attributes** (relevant to Videos List):
| Column | Type | Description |
|--------|------|-------------|
| `channel_id` | string | YouTube channel ID (primary key) |
| `channel_name` | string | Channel display name |

**Relationship to Video**:
- `hasMany(Video::class, 'channel_id', 'channel_id')` - Channel has many Videos

---

### Comment

**Table**: `comments` (already exists, no changes)

**Primary Key**: `comment_id` (string)

**Attributes** (relevant to Videos List):
| Column | Type | Description |
|--------|------|-------------|
| `comment_id` | string | YouTube comment ID (primary key) |
| `video_id` | string | Foreign key to videos table |
| `published_at` | timestamp | Comment publication date/time |

**Relationship to Video**:
- `belongsTo(Video::class, 'video_id', 'video_id')` - Comment belongs to one Video

---

## Query Scopes (Video Model Enhancements)

These scopes will be added to `app/Models/Video.php`:

### 1. `scopeWithCommentStats(Builder $query)`

**Purpose**: Add computed comment count and last comment time to SELECT

**SQL Pattern**:
```sql
SELECT videos.*,
    (SELECT COUNT(*) FROM comments WHERE comments.video_id = videos.video_id) as actual_comment_count,
    (SELECT MAX(published_at) FROM comments WHERE comments.video_id = videos.video_id) as last_comment_time
FROM videos
```

**Usage**:
```php
Video::withCommentStats()->get();
```

---

### 2. `scopeHasComments(Builder $query)`

**Purpose**: Filter to only videos with at least one comment

**SQL Pattern**:
```sql
HAVING actual_comment_count > 0
```

**Usage**:
```php
Video::withCommentStats()->hasComments()->get();
```

**Note**: Must be called AFTER `withCommentStats()` because it uses the computed `actual_comment_count` column.

---

### 3. `scopeSearchByKeyword(Builder $query, string $keyword)`

**Purpose**: Case-insensitive search in video title and channel name

**SQL Pattern**:
```sql
WHERE title LIKE '%keyword%'
OR EXISTS (
    SELECT 1 FROM channels
    WHERE channels.channel_id = videos.channel_id
    AND channels.channel_name LIKE '%keyword%'
)
```

**Usage**:
```php
Video::searchByKeyword('news')->get();
```

---

### 4. `scopeSortByColumn(Builder $query, string $column, string $direction = 'desc')`

**Purpose**: Dynamic sorting by specified column

**Allowed Columns**:
- `published_at` (default)
- `actual_comment_count` (computed)
- `last_comment_time` (computed)

**SQL Pattern**:
```sql
ORDER BY {column} {direction}
```

**Validation**:
- Column must be in whitelist (prevent SQL injection)
- Direction must be 'asc' or 'desc'

**Usage**:
```php
Video::withCommentStats()->sortByColumn('actual_comment_count', 'desc')->get();
```

---

## Data Flow Diagram

```text
User Request
    ↓
VideoController@index
    ↓
Video::withCommentStats()         ← Add computed columns
    →hasComments()                 ← Filter videos with comments
    →searchByKeyword($search)      ← Apply search filter (if provided)
    →sortByColumn($sort, $dir)     ← Apply sorting
    →paginate(500)                 ← Paginate results
    ↓
Blade View (videos/list.blade.php)
    ↓
Display Table:
  - Channel Name (from relationship)
  - Video Title
  - Comment Count (from actual_comment_count)
  - Last Comment Time (from last_comment_time)
```

---

## Edge Cases & Data Handling

### Missing Channel Data

**Scenario**: Video has `channel_id` but channel record doesn't exist

**Handling**:
- Check `$video->channel` in Blade template
- If null, display "Unknown Channel"
- No database changes required

**Blade Pattern**:
```blade
{{ $video->channel?->channel_name ?? 'Unknown Channel' }}
```

---

### Videos with No Comments

**Scenario**: Video exists but has zero comments

**Handling**:
- Filtered out by `hasComments()` scope
- Not displayed in Videos List
- No special handling needed

---

### Null Last Comment Time

**Scenario**: Video has comments but `last_comment_time` is NULL

**Handling**:
- Display "N/A" in Blade template
- This should not occur if data integrity is maintained
- Defensive programming: `$video->last_comment_time?->format('Y-m-d H:i') ?? 'N/A'`

---

## Performance Considerations

### Index Usage

**Existing Indexes** (no new indexes needed):
- `videos.video_id` (primary key) - Used for pagination
- `videos.channel_id` - Used for channel relationship joins
- `videos.published_at` - Used for default sorting
- `comments.video_id` - Used for subquery performance

**Query Optimization**:
- Subqueries for comment stats use `comments.video_id` index
- Pagination uses `videos.video_id` index
- Search uses `title` and `channel_name` (full-text search not needed for expected dataset size)

### Expected Query Count Per Page Load

1. **Main query with subqueries**: 1 query
2. **Pagination count query**: 1 query
3. **Total**: 2 queries

**No N+1 problem** - comment stats computed in subqueries, not separate queries per video.

---

## Data Validation

**Controller-Level Validation** (VideoController):
- `search`: optional, string, max 255 characters
- `sort`: optional, enum (published_at, actual_comment_count, last_comment_time)
- `direction`: optional, enum (asc, desc)
- `page`: optional, integer, min 1

**Model-Level Validation**: None required (read-only operation)

---

## Migration Requirements

**None** - This feature uses existing tables without schema changes.

---

## Summary

- **Tables Used**: `videos`, `comments`, `channels` (all existing)
- **Schema Changes**: None
- **New Scopes**: 4 query scopes in `Video` model
- **Relationships**: Existing relationships only
- **Performance**: 2 queries per page load (acceptable for requirements)
- **Data Integrity**: Handled via defensive Blade template checks
