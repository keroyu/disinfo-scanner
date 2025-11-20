# Data Model: Time-Based Comment Filtering from Chart

**Feature**: 010-time-based-comment-filter
**Date**: 2025-11-20
**Purpose**: Define data structures for time-based filtering with existing Comment model

## Overview

This feature extends the existing comment filtering system (Feature 009) with time-based filtering. No new database tables or migrations are required. All data structures are value objects and DTOs for API communication and client-side state management.

---

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
    INDEX idx_published_at (published_at)  -- ✅ Existing, used for time filtering
);
```

**Note**: No database migrations needed. This feature uses existing schema.

---

## New Domain Models

### 1. TimePoint (Value Object)

**Purpose**: Represents a single clickable point on the Comments Density chart

**Structure**:
```php
namespace App\ValueObjects;

use Carbon\Carbon;

class TimePoint
{
    public function __construct(
        public readonly Carbon $timestamp,    // Start of hour in GMT+8
        public readonly int $commentCount,     // Number of comments in this hour
        public readonly bool $isSelected       // UI selection state (client-side only)
    ) {}

    /**
     * Get the end of this hourly time range
     */
    public function getEndTime(): Carbon
    {
        return $this->timestamp->copy()->addHour();
    }

    /**
     * Get UTC range for database query
     */
    public function getUtcRange(): array
    {
        $startUtc = $this->timestamp->copy()->setTimezone('UTC');
        $endUtc = $this->getEndTime()->setTimezone('UTC');

        return [
            'start' => $startUtc,
            'end' => $endUtc
        ];
    }

    /**
     * Format for display (GMT+8)
     */
    public function getDisplayLabel(): string
    {
        return $this->timestamp->format('H:i') . '-' . $this->getEndTime()->format('H:i');
    }

    /**
     * Format for API parameter (ISO 8601 in GMT+8)
     */
    public function toIsoString(): string
    {
        return $this->timestamp->toIso8601String();
    }
}
```

**Validation Rules**:
- `timestamp`: Must be start of hour (minutes, seconds = 0)
- `commentCount`: >= 0
- `isSelected`: boolean

**State Transitions**: Immutable value object. Selection state managed separately in FilterState.

---

### 2. TimeRange (Value Object)

**Purpose**: Represents a single hourly time range for filtering (derived from TimePoint)

**Structure**:
```php
namespace App\ValueObjects;

use Carbon\Carbon;

class TimeRange
{
    public function __construct(
        public readonly Carbon $startTime,  // Inclusive start (GMT+8)
        public readonly Carbon $endTime     // Exclusive end (GMT+8)
    ) {
        if ($endTime->lte($startTime)) {
            throw new \InvalidArgumentException('End time must be after start time');
        }
    }

    /**
     * Create from ISO timestamp (1-hour range)
     */
    public static function fromIsoTimestamp(string $isoTimestamp): self
    {
        $start = Carbon::parse($isoTimestamp, 'Asia/Taipei');
        $end = $start->copy()->addHour();

        return new self($start, $end);
    }

    /**
     * Get UTC range for database WHERE clause
     */
    public function getUtcRange(): array
    {
        return [
            'start' => $this->startTime->copy()->setTimezone('UTC'),
            'end' => $this->endTime->copy()->setTimezone('UTC')
        ];
    }

    /**
     * Check if a timestamp falls within this range
     */
    public function contains(Carbon $timestamp): bool
    {
        return $timestamp->gte($this->startTime) && $timestamp->lt($this->endTime);
    }

    /**
     * Format for display
     */
    public function toDisplayString(): string
    {
        return $this->startTime->format('H:i') . '-' . $this->endTime->format('H:i');
    }
}
```

**Invariants**:
- Range is always exactly 1 hour (validated in fromIsoTimestamp)
- Start time is inclusive, end time is exclusive
- Times stored in GMT+8 for display, converted to UTC for queries

---

### 3. FilterState (Client-side State Object)

**Purpose**: Manages combined state of pattern filter and time filter selections

**Structure** (JavaScript):
```javascript
class FilterState {
    constructor() {
        this.patternType = 'all';              // Current pattern: 'all', 'repeat', etc.
        this.selectedTimePoints = new Set();   // Set of chart point indices
        this.selectedTimestamps = [];          // Array of ISO timestamps (GMT+8)
        this.observers = [];                   // UI update callbacks
    }

    /**
     * Toggle a time point selection
     * @param {number} index - Chart point index
     * @param {string} isoTimestamp - ISO 8601 timestamp (GMT+8)
     * @returns {boolean} - Success (false if limit reached)
     */
    toggleTimePoint(index, isoTimestamp) {
        if (this.selectedTimePoints.has(index)) {
            // Deselect
            this.selectedTimePoints.delete(index);
            this.selectedTimestamps = this.selectedTimestamps.filter(
                t => t !== isoTimestamp
            );
            this.notify('time_deselected', { index, timestamp: isoTimestamp });
            return true;
        } else {
            // Check limit
            if (this.selectedTimePoints.size >= 20) {
                this.notify('limit_reached', { limit: 20 });
                return false;
            }

            // Check warning threshold
            if (this.selectedTimePoints.size >= 15 && this.selectedTimePoints.size < 20) {
                this.notify('warning', { threshold: 15 });
            }

            // Select
            this.selectedTimePoints.add(index);
            this.selectedTimestamps.push(isoTimestamp);
            this.notify('time_selected', { index, timestamp: isoTimestamp });
            return true;
        }
    }

    /**
     * Set pattern filter (maintains time selections)
     */
    setPattern(patternType) {
        this.patternType = patternType;
        this.notify('pattern_changed', { pattern: patternType });
    }

    /**
     * Clear time selections (maintains pattern)
     */
    clearTimeFilter() {
        this.selectedTimePoints.clear();
        this.selectedTimestamps = [];
        this.notify('time_cleared');
    }

    /**
     * Clear all filters
     */
    clearAll() {
        this.patternType = 'all';
        this.selectedTimePoints.clear();
        this.selectedTimestamps = [];
        this.notify('all_cleared');
    }

    /**
     * Get current filter mode
     */
    getFilterMode() {
        const hasPattern = this.patternType !== 'all';
        const hasTime = this.selectedTimestamps.length > 0;

        if (hasPattern && hasTime) return 'combined';
        if (hasTime) return 'time_only';
        if (hasPattern) return 'pattern_only';
        return 'none';
    }

    /**
     * Build API query parameters
     */
    toQueryParams() {
        const params = {
            pattern: this.patternType,
            offset: 0,
            limit: 100
        };

        if (this.selectedTimestamps.length > 0) {
            params.time_points = this.selectedTimestamps.join(',');
        }

        return params;
    }

    /**
     * Subscribe to state changes
     */
    subscribe(callback) {
        this.observers.push(callback);
    }

    /**
     * Notify observers of state changes
     */
    notify(event, data = {}) {
        this.observers.forEach(callback => callback(event, data));
    }
}
```

**State Transitions**:
```
[none] ──select time──> [time_only]
[none] ──select pattern──> [pattern_only]
[time_only] ──select pattern──> [combined]
[pattern_only] ──select time──> [combined]
[combined] ──clear time──> [pattern_only]
[combined] ──clear pattern──> [time_only]
[any] ──clear all──> [none]
```

---

## Extended API Response Structures

### Time-Filtered Comments Response

**Endpoint**: `GET /api/videos/{videoId}/comments?pattern={type}&time_points={ISO_TIMESTAMPS}&offset={n}&limit={m}`

**Request Example**:
```
GET /api/videos/abc123/comments?pattern=repeat&time_points=2025-11-20T14:00:00+08:00,2025-11-20T16:00:00+08:00&offset=0&limit=100
```

**Response** (extends existing comments API):
```json
{
  "video_id": "abc123",
  "pattern": "repeat",
  "time_filter": {
    "ranges": [
      {
        "start": "2025-11-20 14:00 (GMT+8)",
        "end": "2025-11-20 15:00 (GMT+8)"
      },
      {
        "start": "2025-11-20 16:00 (GMT+8)",
        "end": "2025-11-20 17:00 (GMT+8)"
      }
    ],
    "count": 2
  },
  "comments": [
    {
      "comment_id": "comment123",
      "author_channel_id": "channel456",
      "author_name": "John Doe",
      "text": "Great video!",
      "like_count": 15,
      "published_at": "2025-11-20 14:30 (GMT+8)",
      "is_reply": false
    }
  ],
  "offset": 0,
  "limit": 100,
  "has_more": true,
  "total": 250
}
```

**Response Fields**:
- `time_filter`: Included only when time_points parameter present
- `time_filter.ranges`: Array of human-readable time ranges (GMT+8)
- `time_filter.count`: Number of time ranges selected
- Other fields: Same as existing comments API (backward compatible)

---

## Query Scope Extension to Comment Model

### Time-Filtered Scope

```php
// In app/Models/Comment.php

/**
 * Scope to filter comments by multiple time ranges
 *
 * @param array $timeRanges Array of ['start' => Carbon, 'end' => Carbon] in UTC
 */
public function scopeByTimeRanges($query, array $timeRanges)
{
    if (empty($timeRanges)) {
        return $query;
    }

    return $query->where(function($q) use ($timeRanges) {
        foreach ($timeRanges as $range) {
            $q->orWhere(function($subQuery) use ($range) {
                $subQuery->where('published_at', '>=', $range['start'])
                         ->where('published_at', '<', $range['end']);
            });
        }
    });
}

/**
 * Combined pattern and time filtering
 */
public function scopeByPatternAndTime(
    $query,
    string $videoId,
    string $pattern,
    ?array $timeRanges = null,
    ?Collection $matchingAuthorIds = null
) {
    // Apply video filter
    $query->where('video_id', $videoId);

    // Apply pattern filter (reuse existing logic from Feature 009)
    $query = match($pattern) {
        'all' => $query->orderBy('published_at', 'DESC'),
        'top_liked' => $query->orderBy('like_count', 'DESC')->orderBy('published_at', 'DESC'),
        'repeat', 'night_time' => $query->whereIn('author_channel_id', $matchingAuthorIds ?? [])
                                        ->orderBy('published_at', 'DESC'),
        'aggressive', 'simplified_chinese' => $query->whereRaw('1 = 0'), // Placeholders
        default => $query->whereRaw('1 = 0')
    };

    // Apply time filter if present
    if ($timeRanges && count($timeRanges) > 0) {
        $query->byTimeRanges($timeRanges);
    }

    return $query;
}
```

---

## Service Layer Extension

### CommentPatternService Updates

```php
namespace App\Services;

use App\ValueObjects\TimeRange;
use Illuminate\Support\Collection;

class CommentPatternService
{
    /**
     * Get comments by pattern with optional time filtering
     *
     * @param string $videoId
     * @param string $pattern
     * @param array|null $timePointsIso Array of ISO timestamps (GMT+8)
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getCommentsByPattern(
        string $videoId,
        string $pattern,
        ?array $timePointsIso = null,
        int $offset = 0,
        int $limit = 100
    ): array {
        // Build pattern-specific author IDs if needed
        $matchingAuthorIds = $this->getMatchingAuthorsForPattern($videoId, $pattern);

        // Convert ISO timestamps to UTC ranges for query
        $utcRanges = null;
        if ($timePointsIso) {
            $utcRanges = array_map(function($iso) {
                $timeRange = TimeRange::fromIsoTimestamp($iso);
                return $timeRange->getUtcRange();
            }, $timePointsIso);
        }

        // Build query with combined filters
        $query = Comment::byPatternAndTime(
            $videoId,
            $pattern,
            $utcRanges,
            $matchingAuthorIds
        );

        // Execute with pagination
        $total = $query->count();
        $comments = $query->skip($offset)->take($limit)->get();

        // Convert timestamps to GMT+8 for response
        $formattedComments = $comments->map(function($comment) {
            return [
                'comment_id' => $comment->comment_id,
                'author_channel_id' => $comment->author_channel_id,
                'author_name' => $comment->author?->channel_name ?? 'Unknown',
                'text' => $comment->text,
                'like_count' => $comment->like_count,
                'published_at' => $comment->published_at
                    ->setTimezone('Asia/Taipei')
                    ->format('Y-m-d H:i') . ' (GMT+8)',
                'is_reply' => !is_null($comment->parent_comment_id),
            ];
        });

        return [
            'comments' => $formattedComments->toArray(),
            'has_more' => ($offset + $limit) < $total,
            'total' => $total
        ];
    }

    // ... existing methods from Feature 009 ...
}
```

---

## Controller Extension

### CommentPatternController Updates

```php
// In app/Http/Controllers/CommentPatternController.php

/**
 * Get comments by pattern with optional time filtering
 *
 * GET /api/videos/{videoId}/comments?pattern=repeat&time_points=ISO1,ISO2&offset=0&limit=100
 */
public function getCommentsByPattern(Request $request, string $videoId): JsonResponse
{
    try {
        // Verify video exists
        $video = Video::find($videoId);
        if (!$video) {
            return response()->json([
                'error' => [
                    'type' => 'VideoNotFound',
                    'message' => 'Video not found',
                    'details' => ['video_id' => $videoId]
                ]
            ], 404);
        }

        // Validate request parameters
        $validated = $request->validate([
            'pattern' => ['required', Rule::in(['all', 'top_liked', 'repeat', 'night_time', 'aggressive', 'simplified_chinese'])],
            'time_points' => 'nullable|string|max:1000', // Comma-separated ISO timestamps
            'offset' => 'integer|min:0',
            'limit' => 'integer|min:1|max:100'
        ]);

        $pattern = $validated['pattern'];
        $offset = $validated['offset'] ?? 0;
        $limit = $validated['limit'] ?? 100;

        // Parse and validate time points
        $timePointsIso = null;
        if (!empty($validated['time_points'])) {
            $timePointsIso = explode(',', $validated['time_points']);

            // Validate count
            if (count($timePointsIso) > 20) {
                return response()->json([
                    'error' => [
                        'type' => 'ValidationError',
                        'message' => 'Maximum 20 time points allowed',
                        'details' => ['count' => count($timePointsIso), 'limit' => 20]
                    ]
                ], 422);
            }

            // Validate format (basic check)
            foreach ($timePointsIso as $iso) {
                try {
                    Carbon::parse($iso);
                } catch (\Exception $e) {
                    return response()->json([
                        'error' => [
                            'type' => 'ValidationError',
                            'message' => 'Invalid timestamp format',
                            'details' => ['timestamp' => $iso]
                        ]
                    ], 422);
                }
            }
        }

        $startTime = microtime(true);

        $result = $this->commentPatternService->getCommentsByPattern(
            $videoId,
            $pattern,
            $timePointsIso,
            $offset,
            $limit
        );

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        // Log request
        Log::info('Time-filtered comments retrieved', [
            'video_id' => $videoId,
            'pattern' => $pattern,
            'time_points_count' => $timePointsIso ? count($timePointsIso) : 0,
            'offset' => $offset,
            'limit' => $limit,
            'returned' => count($result['comments']),
            'execution_time_ms' => $executionTime
        ]);

        $response = [
            'video_id' => $videoId,
            'pattern' => $pattern,
            'offset' => $offset,
            'limit' => $limit,
            'comments' => $result['comments'],
            'has_more' => $result['has_more'],
            'total' => $result['total']
        ];

        // Add time filter info if present
        if ($timePointsIso) {
            $response['time_filter'] = [
                'ranges' => array_map(function($iso) {
                    $range = TimeRange::fromIsoTimestamp($iso);
                    return [
                        'start' => $range->startTime->format('Y-m-d H:i') . ' (GMT+8)',
                        'end' => $range->endTime->format('Y-m-d H:i') . ' (GMT+8)'
                    ];
                }, $timePointsIso),
                'count' => count($timePointsIso)
            ];
        }

        return response()->json($response);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'error' => [
                'type' => 'ValidationError',
                'message' => 'Invalid request parameters',
                'details' => $e->errors()
            ]
        ], 422);

    } catch (\Exception $e) {
        Log::error('Error getting time-filtered comments', [
            'video_id' => $videoId,
            'pattern' => $request->input('pattern'),
            'time_points' => $request->input('time_points'),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'error' => [
                'type' => 'ServerError',
                'message' => 'Failed to retrieve comments',
                'details' => ['error' => $e->getMessage()]
            ]
        ], 500);
    }
}
```

---

## Data Flow Diagram

```
┌──────────────────────────────────────────────────────────────────────┐
│ Frontend (GMT+8)                                                     │
├──────────────────────────────────────────────────────────────────────┤
│ 1. User clicks chart point at 14:00                                 │
│    → FilterState.toggleTimePoint(index, "2025-11-20T14:00:00+08:00")│
│    → Chart backgroundColor updated                                   │
│                                                                      │
│ 2. Build API request                                                │
│    → /api/videos/abc123/comments?                                    │
│      pattern=repeat&                                                 │
│      time_points=2025-11-20T14:00:00+08:00&                         │
│      offset=0&limit=100                                              │
└──────────────────────────────────────────────────────────────────────┘
                                  ↓
┌──────────────────────────────────────────────────────────────────────┐
│ Backend (UTC)                                                        │
├──────────────────────────────────────────────────────────────────────┤
│ 3. CommentPatternController receives request                        │
│    → Validate parameters (pattern, time_points count ≤ 20)          │
│    → Parse time_points: "2025-11-20T14:00:00+08:00"                 │
│                                                                      │
│ 4. CommentPatternService processes                                  │
│    → Convert GMT+8 → UTC: 14:00+08:00 → 06:00 UTC                   │
│    → Build time range: [06:00 UTC, 07:00 UTC)                       │
│    → Get pattern author IDs (if pattern = repeat/night_time)        │
│                                                                      │
│ 5. Database query (MySQL)                                           │
│    SELECT * FROM comments                                            │
│    WHERE video_id = 'abc123'                                         │
│      AND author_channel_id IN (...)  -- if pattern filter           │
│      AND (                                                           │
│        (published_at >= '2025-11-20 06:00:00' AND                   │
│         published_at < '2025-11-20 07:00:00')                       │
│      )                                                               │
│    ORDER BY published_at DESC                                        │
│    LIMIT 100 OFFSET 0;                                               │
│                                                                      │
│ 6. Convert results UTC → GMT+8                                       │
│    → published_at: 06:30 UTC → 14:30 GMT+8                          │
│    → Format: "2025-11-20 14:30 (GMT+8)"                             │
└──────────────────────────────────────────────────────────────────────┘
                                  ↓
┌──────────────────────────────────────────────────────────────────────┐
│ Frontend (GMT+8)                                                     │
├──────────────────────────────────────────────────────────────────────┤
│ 7. Receive JSON response                                            │
│    → Display comments in right panel                                │
│    → Show time filter indicator: "📍 Selected: 1 time period"       │
│    → Setup infinite scroll for next batch                           │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Index Requirements

**Existing Indexes** (already in database):
- ✅ `idx_video_id` on comments.video_id
- ✅ `idx_published_at` on comments.published_at
- ✅ `idx_author_channel_id` on comments.author_channel_id

**Composite Index** (optional, evaluate after performance testing):
```sql
-- If combined time + pattern queries are slow:
CREATE INDEX idx_video_time_author ON comments(video_id, published_at, author_channel_id);
```

**Decision**: Monitor query performance before adding new indexes. Current indexes should handle time range queries efficiently.

---

## Summary

This data model:
- **Reuses existing database schema** (no migrations)
- **Introduces 3 value objects**: TimePoint, TimeRange (backend), FilterState (frontend)
- **Extends existing API** with optional `time_points` parameter (backward compatible)
- **Maintains timezone consistency**: UTC storage, GMT+8 display per Constitution VI
- **Supports combined filtering**: Pattern AND Time filters work together
- **Enforces performance limits**: Maximum 20 time ranges validated at multiple layers

All entities follow functional programming principles with immutable value objects and clear separation between storage (UTC) and display (GMT+8) timezones.
