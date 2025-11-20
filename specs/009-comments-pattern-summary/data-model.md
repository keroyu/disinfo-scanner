# Data Model: Comments Pattern Summary

**Feature**: 009-comments-pattern-summary
**Date**: 2025-11-20
**Purpose**: Define data structures for pattern statistics and comment filtering

## Existing Database Schema (Reference Only)

### Comments Table
```sql
CREATE TABLE comments (
    comment_id VARCHAR(255) PRIMARY KEY,
    video_id VARCHAR(255) NOT NULL,
    author_channel_id VARCHAR(255) NOT NULL,
    text LONGTEXT NOT NULL,
    like_count INT UNSIGNED DEFAULT 0,
    published_at TIMESTAMP NULL,  -- ⚠️ Stored in UTC
    parent_comment_id VARCHAR(255) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_video_id (video_id),
    INDEX idx_author_channel_id (author_channel_id),
    INDEX idx_parent_comment_id (parent_comment_id),
    INDEX idx_video_comment (video_id, comment_id)
);
```

### Key Relationships
- Comment `belongsTo` Video (via video_id)
- Comment `belongsTo` Author (via author_channel_id)
- Comment `belongsTo` ParentComment (self-referencing via parent_comment_id)
- Comment `hasMany` Replies (self-referencing inverse)

**Note**: No database migrations needed. This feature uses existing schema.

---

## New Domain Models

### 1. CommentPattern (Value Object)

**Purpose**: Represents a single pattern statistic (count + percentage)

**Structure**:
```php
namespace App\ValueObjects;

class CommentPattern
{
    public function __construct(
        public readonly string $type,        // 'all', 'repeat', 'night_time', 'aggressive', 'simplified_chinese'
        public readonly int $count,          // Number of matching commenters
        public readonly float $percentage,   // Percentage of total unique commenters (0-100)
        public readonly int $totalCommenters // Total unique commenters on video
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'count' => $this->count,
            'percentage' => round($this->percentage, 0), // Round to whole number per spec
            'total_commenters' => $this->totalCommenters,
        ];
    }

    public static function placeholder(string $type, int $totalCommenters): self
    {
        return new self($type, 0, 0.0, $totalCommenters);
    }
}
```

**Validation Rules**:
- `type`: Must be one of: 'all', 'repeat', 'night_time', 'aggressive', 'simplified_chinese'
- `count`: >= 0
- `percentage`: 0.0 - 100.0 (inclusive)
- `totalCommenters`: >= 0

**State Transitions**: Immutable value object (no state changes after construction)

---

### 2. PatternStatistics (Aggregate)

**Purpose**: Collection of all pattern statistics for a video

**Structure**:
```php
namespace App\ValueObjects;

class PatternStatistics
{
    /** @param CommentPattern[] $patterns */
    public function __construct(
        public readonly string $videoId,
        public readonly array $patterns,     // Indexed by pattern type
        public readonly int $totalComments,
        public readonly \Carbon\Carbon $calculatedAt
    ) {}

    public function getPattern(string $type): ?CommentPattern
    {
        return $this->patterns[$type] ?? null;
    }

    public function toArray(): array
    {
        return [
            'video_id' => $this->videoId,
            'total_comments' => $this->totalComments,
            'patterns' => array_map(fn($p) => $p->toArray(), $this->patterns),
            'calculated_at' => $this->calculatedAt->toIso8601String(),
        ];
    }
}
```

**Invariants**:
- Must contain pattern for 'all' (default view)
- Total of all pattern counts <= total unique commenters
- All patterns reference same totalCommenters value

---

### 3. PaginatedCommentList (Data Transfer Object)

**Purpose**: API response structure for paginated comment list

**Structure**:
```php
namespace App\DataTransferObjects;

class PaginatedCommentList
{
    public function __construct(
        public readonly array $comments,      // Array of Comment models
        public readonly int $offset,
        public readonly int $limit,
        public readonly int $returned,
        public readonly bool $hasMore
    ) {}

    public function toArray(): array
    {
        return [
            'data' => array_map(fn($c) => [
                'comment_id' => $c->comment_id,
                'author_channel_id' => $c->author_channel_id,
                'author_name' => $c->author?->channel_name ?? 'Unknown',
                'text' => $c->text,
                'like_count' => $c->like_count,
                'published_at' => $c->published_at
                    ->setTimezone('Asia/Taipei')
                    ->format('Y/m/d H:i') . ' (GMT+8)',
                'is_reply' => !is_null($c->parent_comment_id),
            ], $this->comments),
            'meta' => [
                'offset' => $this->offset,
                'limit' => $this->limit,
                'returned' => $this->returned,
                'has_more' => $this->hasMore,
            ],
        ];
    }
}
```

**Validation Rules**:
- `offset`: >= 0
- `limit`: 1-100 (enforce maximum batch size)
- `returned`: 0-100 (actual number returned, may be less than limit)
- `hasMore`: true if more comments exist beyond current offset

---

## Query Scope Additions to Comment Model

### Repeat Commenters Scope

```php
// In app/Models/Comment.php

/**
 * Scope to get commenters who posted 2+ times on same video
 */
public function scopeRepeatCommenters($query, string $videoId)
{
    return $query
        ->where('video_id', $videoId)
        ->select('author_channel_id')
        ->groupBy('author_channel_id')
        ->havingRaw('COUNT(*) >= 2')
        ->pluck('author_channel_id');
}
```

### Night-Time High-Frequency Commenters Scope

```php
/**
 * Scope to get commenters with >50% comments during 01:00-05:59 GMT+8
 * across ALL channels (not just current video)
 */
public function scopeNightTimeHighFrequencyCommenters($query)
{
    return $query
        ->select('author_channel_id')
        ->selectRaw('
            COUNT(*) as total_comments,
            SUM(CASE
                WHEN HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) BETWEEN 1 AND 5
                THEN 1
                ELSE 0
            END) as night_comments
        ')
        ->whereNotNull('published_at')  // Exclude null timestamps per FR-013
        ->groupBy('author_channel_id')
        ->havingRaw('total_comments >= 2')  // Minimum 2 comments per A-007
        ->havingRaw('night_comments / total_comments > 0.5')  // >50% night-time
        ->pluck('author_channel_id');
}
```

### Comments by Pattern Scope

```php
/**
 * Scope to filter comments by pattern type for a specific video
 */
public function scopeByPattern($query, string $videoId, string $pattern, ?Collection $matchingAuthorIds = null)
{
    $query->where('video_id', $videoId);

    return match($pattern) {
        'all' => $query,  // No additional filtering
        'repeat', 'night_time' => $query->whereIn('author_channel_id', $matchingAuthorIds ?? []),
        'aggressive', 'simplified_chinese' => $query->whereRaw('1 = 0'),  // Return empty (placeholders)
        default => $query->whereRaw('1 = 0'),  // Invalid pattern returns empty
    };
}
```

---

## API Response Schemas

### Pattern Statistics Response

**Endpoint**: `GET /api/videos/{videoId}/pattern-statistics`

**Response**:
```json
{
  "video_id": "abc123",
  "total_comments": 450,
  "patterns": {
    "all": {
      "type": "all",
      "count": 320,
      "percentage": 100,
      "total_commenters": 320
    },
    "repeat": {
      "type": "repeat",
      "count": 25,
      "percentage": 8,
      "total_commenters": 320
    },
    "night_time": {
      "type": "night_time",
      "count": 15,
      "percentage": 5,
      "total_commenters": 320
    },
    "aggressive": {
      "type": "aggressive",
      "count": 0,
      "percentage": 0,
      "total_commenters": 320
    },
    "simplified_chinese": {
      "type": "simplified_chinese",
      "count": 0,
      "percentage": 0,
      "total_commenters": 320
    }
  },
  "calculated_at": "2025-11-20T14:30:00+08:00"
}
```

### Paginated Comments Response

**Endpoint**: `GET /api/videos/{videoId}/comments?pattern={type}&offset={n}&limit={m}`

**Response**:
```json
{
  "data": [
    {
      "comment_id": "comment123",
      "author_channel_id": "channel456",
      "author_name": "John Doe",
      "text": "Great video!",
      "like_count": 15,
      "published_at": "2025/11/20 14:30 (GMT+8)",
      "is_reply": false
    }
  ],
  "meta": {
    "offset": 0,
    "limit": 100,
    "returned": 100,
    "has_more": true
  }
}
```

---

## Service Layer Interface

### CommentPatternService Contract

```php
namespace App\Services;

interface CommentPatternServiceInterface
{
    /**
     * Get all pattern statistics for a video
     */
    public function getPatternStatistics(string $videoId): PatternStatistics;

    /**
     * Get paginated comments filtered by pattern
     */
    public function getCommentsByPattern(
        string $videoId,
        string $pattern,
        int $offset = 0,
        int $limit = 100
    ): PaginatedCommentList;

    /**
     * Calculate repeat commenters for a video
     */
    public function calculateRepeatCommenters(string $videoId): CommentPattern;

    /**
     * Calculate night-time high-frequency commenters for a video
     * (cross-channel calculation, uses caching)
     */
    public function calculateNightTimeCommenters(string $videoId): CommentPattern;
}
```

---

## Caching Schema

### Cache Keys

```
video:{videoId}:pattern:repeat           TTL: 300s (5 min)
video:{videoId}:pattern:night_time       TTL: 300s (5 min)
video:{videoId}:pattern:all_statistics   TTL: 300s (5 min)
```

### Cache Value Format

```json
{
  "count": 25,
  "percentage": 7.8125,
  "total_commenters": 320,
  "calculated_at": 1700481234
}
```

---

## Data Flow Diagram

```
[Video Analysis Page]
        |
        | 1. Load pattern statistics
        v
[GET /api/videos/{id}/pattern-statistics]
        |
        | 2. Check cache
        v
[CommentPatternService]
        |
        +---> [Redis Cache] (if hit, return)
        |
        +---> [Database Query] (if miss)
                |
                +---> Repeat: GROUP BY author_channel_id
                +---> Night-time: Cross-channel aggregation
                |
                v
        [Store in cache, return PatternStatistics]
        |
        v
[PatternStatisticsResource::toArray()]
        |
        v
[JSON Response to Frontend]
        |
        | 3. User clicks pattern filter
        v
[GET /api/videos/{id}/comments?pattern=repeat&offset=0]
        |
        v
[CommentPatternService::getCommentsByPattern()]
        |
        +---> Get matching author IDs (from cache or fresh query)
        +---> Query comments WHERE author_channel_id IN (...)
        +---> Apply offset/limit
        |
        v
[CommentListResource::collection()]
        |
        v
[JSON Response with PaginatedCommentList]
        |
        | 4. User scrolls to bottom
        v
[Intersection Observer triggers]
        |
        v
[GET /api/videos/{id}/comments?pattern=repeat&offset=100]
        |
        v
[Append to existing list]
```

---

## Index Recommendations

**Existing Indexes** (already in database):
- `idx_video_id` on comments.video_id ✅
- `idx_author_channel_id` on comments.author_channel_id ✅
- `idx_video_comment` composite on (video_id, comment_id) ✅

**New Indexes** (optional, evaluate after performance testing):
```sql
-- If night-time queries are still slow with caching:
CREATE INDEX idx_published_at ON comments(published_at) WHERE published_at IS NOT NULL;

-- If repeat commenter queries are slow:
-- (Likely not needed - composite index on video_id + author_channel_id already exists)
```

**Decision**: Monitor query performance before adding indexes. Current indexes should be sufficient.

---

## Summary

This data model:
- Reuses existing database schema (no migrations)
- Introduces 3 value objects/DTOs for clean API responses
- Adds 3 query scopes to Comment model
- Defines service layer interface for pattern calculations
- Implements caching strategy for expensive queries
- Handles timezone conversion (UTC → GMT+8) consistently
- Supports infinite scroll pagination via offset/limit

All entities are immutable after construction, promoting functional programming style and reducing state-related bugs.
