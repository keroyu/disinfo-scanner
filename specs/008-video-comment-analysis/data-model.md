# Data Model: Video Comment Density Analysis

**Feature**: 008-video-comment-analysis
**Date**: 2025-11-19

## Entity: Video (MODIFIED)

**Purpose**: Represents a YouTube video with cached statistics

### Schema Changes

**New Fields**:
```sql
ALTER TABLE videos ADD COLUMN views INT NULL COMMENT 'Cached view count from YouTube API';
ALTER TABLE videos ADD COLUMN likes INT NULL COMMENT 'Cached like count from YouTube API';

-- Note: updated_at column assumed to already exist (Laravel standard timestamps)
```

**Full Schema (relevant fields)**:
```sql
CREATE TABLE videos (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    youtube_id VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(500),
    published_at DATETIME NOT NULL,
    views INT NULL,                 -- NEW: Cached view count
    likes INT NULL,                 -- NEW: Cached like count
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Field Specifications

| Field | Type | Nullable | Default | Validation |
|-------|------|----------|---------|------------|
| views | INT | YES | NULL | >= 0 when set |
| likes | INT | YES | NULL | >= 0 when set |
| updated_at | DATETIME | NO | CURRENT_TIMESTAMP | Format: `Y-m-d H:i:s` |

### Business Rules

1. **Cache Staleness**: Cache is considered stale when `updated_at < NOW() - INTERVAL 24 HOUR`
2. **NULL Handling**: NULL values indicate video has never been fetched from YouTube API
3. **Cache Refresh**: Only occurs when cache is stale AND user requests analysis page
4. **Timezone**: All datetime fields stored in Asia/Taipei timezone (`2025-06-13 21:00:03`)

### Model Accessors (Laravel)

```php
// app/Models/Video.php

public function isCacheStale(): bool
{
    if (is_null($this->views) || is_null($this->likes)) {
        return true; // Never fetched
    }

    return $this->updated_at->lt(now()->subHours(24));
}

public function getCacheAgeAttribute(): ?int
{
    if (is_null($this->updated_at)) {
        return null;
    }

    return now()->diffInHours($this->updated_at);
}
```

## Entity: Comment (EXISTING)

**Purpose**: Individual comments on videos (no schema changes)

### Relevant Fields

```sql
CREATE TABLE comments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    video_id BIGINT UNSIGNED NOT NULL,
    author_id VARCHAR(255) NOT NULL,
    author_name VARCHAR(255),
    content TEXT,
    created_at DATETIME NOT NULL,  -- Comment timestamp (Asia/Taipei)
    FOREIGN KEY (video_id) REFERENCES videos(id),
    INDEX idx_video_created (video_id, created_at)
);
```

### Aggregation Requirements

Comments must support:
- **Hourly aggregation**: Group by `DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')`
- **Daily aggregation**: Group by `DATE(created_at)`
- **Range filtering**: `WHERE created_at >= ? AND created_at < ?`

**Performance Note**: Composite index `idx_video_created (video_id, created_at)` recommended for queries on 100k+ comments.

## Value Object: CommentDensityDataPoint

**Purpose**: Aggregated comment count for a specific time bucket

### Structure

```php
readonly class CommentDensityDataPoint
{
    public function __construct(
        public string $timeBucket,    // '2025-06-13 14:00:00' or '2025-06-13'
        public int $commentCount,     // Number of comments in this bucket
        public string $granularity    // 'hourly' or 'daily'
    ) {}

    public function toArray(): array
    {
        return [
            'time_bucket' => $this->timeBucket,
            'comment_count' => $this->commentCount,
            'granularity' => $this->granularity,
        ];
    }
}
```

### Validation Rules

- `timeBucket`: Valid datetime string matching granularity format
- `commentCount`: Non-negative integer
- `granularity`: Must be 'hourly' or 'daily'

## Value Object: ChartDataResponse

**Purpose**: API response structure for chart data

### Structure

```php
readonly class ChartDataResponse
{
    public function __construct(
        public string $videoId,
        public string $timeRange,        // '3days', '7days', etc.
        public string $granularity,      // 'hourly' or 'daily'
        public array $labels,            // ['2025-06-13 14:00', '2025-06-13 15:00', ...]
        public array $values,            // [23, 45, 67, ...]
        public int $totalComments,       // Sum of all values
        public string $generatedAt       // '2025-06-13 21:00:03'
    ) {}

    public function toArray(): array
    {
        return [
            'video_id' => $this->videoId,
            'time_range' => $this->timeRange,
            'granularity' => $this->granularity,
            'labels' => $this->labels,
            'values' => $this->values,
            'total_comments' => $this->totalComments,
            'generated_at' => $this->generatedAt,
        ];
    }
}
```

## Entity Relationships

```
┌─────────────────┐
│     Videos      │
│                 │
│ - id            │
│ - youtube_id    │
│ - published_at  │
│ - views [NEW]   │
│ - likes [NEW]   │
│ - updated_at    │
└────────┬────────┘
         │
         │ 1:N
         │
         ▼
┌─────────────────┐
│    Comments     │
│                 │
│ - id            │
│ - video_id      │
│ - author_id     │
│ - content       │
│ - created_at    │
└─────────────────┘
```

## State Transitions: Video Cache

```
┌─────────────┐
│   Initial   │  (views = NULL, likes = NULL)
│  (No Cache) │
└──────┬──────┘
       │
       │ First API fetch
       ▼
┌─────────────┐
│   Fresh     │  (updated_at < 24 hours ago)
│   Cache     │
└──────┬──────┘
       │
       │ Time passes (>24 hours)
       ▼
┌─────────────┐
│   Stale     │  (updated_at >= 24 hours ago)
│   Cache     │
└──────┬──────┘
       │
       │ User visits analysis page + lock acquired
       ▼
┌─────────────┐
│ Refreshing  │  (Transaction in progress)
│   Cache     │
└──────┬──────┘
       │
       │ API success
       ▼
┌─────────────┐
│   Fresh     │  (updated_at = NOW())
│   Cache     │
└─────────────┘
```

**Error Handling**:
- If API fails during refresh: Keep stale cache, show age indicator
- If lock timeout: Serve stale cache, log event
- If never fetched (NULL): Show error message "Statistics unavailable"

## Database Migration

**File**: `database/migrations/2025_11_19_add_views_likes_to_videos_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->integer('views')->nullable()->comment('Cached view count from YouTube API');
            $table->integer('likes')->nullable()->comment('Cached like count from YouTube API');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['views', 'likes']);
        });
    }
};
```

## Indexes

**Existing** (assumed):
- `videos.id` (PRIMARY KEY)
- `videos.youtube_id` (UNIQUE)
- `comments.video_id` (FOREIGN KEY)

**Recommended** (if not exists):
```sql
CREATE INDEX idx_video_created ON comments(video_id, created_at);
CREATE INDEX idx_videos_updated ON videos(updated_at);  -- For finding stale caches
```

## Data Validation Summary

| Entity | Field | Validation |
|--------|-------|------------|
| Video | views | NULL or >= 0 |
| Video | likes | NULL or >= 0 |
| Video | updated_at | Valid datetime in `Y-m-d H:i:s` format |
| Comment | created_at | Valid datetime, must be after video.published_at |
| CommentDensityDataPoint | commentCount | >= 0 |
| ChartDataResponse | labels | Non-empty array of datetime strings |
| ChartDataResponse | values | Array of non-negative integers, same length as labels |
