# Research: Video Comment Density Analysis

**Feature**: 008-video-comment-analysis
**Date**: 2025-11-19
**Status**: Complete

## Research Task 1: Chart Library Selection

**Decision**: Chart.js

**Rationale**:
- **Lightweight and performant**: Chart.js is ideal for rendering up to 100k data points with proper optimization
- **Alpine.js compatible**: Works seamlessly with Alpine.js reactive data binding
- **CDN availability**: Can be loaded via CDN without npm install (simpler for Laravel + Vite setup)
- **Time-series support**: Built-in time scale support perfect for hourly/daily comment density
- **Active maintenance**: Large community, regular updates, excellent documentation

**Alternatives Considered**:
- **ApexCharts**: More feature-rich but heavier bundle size. Overkill for our use case
- **Laravel Charts**: Wrapper around Chart.js but adds unnecessary abstraction layer. Direct Chart.js gives more control

**Implementation Notes**:
- Use Chart.js 4.x (latest stable)
- Load via CDN in Blade template: `<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>`
- Alpine.js will manage chart instance and data updates
- Use `decimation` plugin for large datasets (100k comments)

## Research Task 2: Database Locking Strategy for Cache Refresh

**Decision**: Laravel's database `lockForUpdate()` with timeout

**Rationale**:
- **Native Laravel support**: No additional Redis dependency required
- **Transaction safety**: Ensures atomic cache refresh operations
- **Timeout handling**: Prevents indefinite locks if refresh fails
- **Simple implementation**: Single database query with `->lockForUpdate()`

**Alternatives Considered**:
- **Redis locks**: Requires Redis setup; overkill for single-server deployment
- **Optimistic locking (versioning)**: More complex; risk of race conditions
- **No locking**: Multiple API calls waste quota and cause unnecessary load

**Implementation Pattern**:
```php
DB::transaction(function () use ($videoId) {
    $video = Video::where('id', $videoId)
        ->lockForUpdate()
        ->first();

    if ($video->isCacheStale()) {
        $video->refreshFromYouTubeApi();
    }
}, 5); // 5 attempt retries
```

**Stale Cache Handling**:
- If lock timeout (5 seconds), return cached data with age indicator
- Log lock timeout events for monitoring

## Research Task 3: Date Aggregation Query Optimization

**Decision**: MySQL date functions with indexed `created_at` on comments table

**Rationale**:
- **Indexed queries**: Existing `created_at` index enables fast range queries
- **Database-level aggregation**: More efficient than PHP-level grouping for large datasets
- **Native timezone support**: MySQL `CONVERT_TZ()` handles Asia/Taipei conversion

**Query Strategy**:

**Hourly Aggregation** (≤7 days):
```sql
SELECT
    DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '+08:00'), '%Y-%m-%d %H:00:00') as time_bucket,
    COUNT(*) as comment_count
FROM comments
WHERE video_id = ?
    AND created_at >= ?
    AND created_at < ?
GROUP BY time_bucket
ORDER BY time_bucket
```

**Daily Aggregation** (>7 days):
```sql
SELECT
    DATE(CONVERT_TZ(created_at, '+00:00', '+08:00')) as time_bucket,
    COUNT(*) as comment_count
FROM comments
WHERE video_id = ?
    AND created_at >= ?
    AND created_at < ?
GROUP BY time_bucket
ORDER BY time_bucket
```

**Alternatives Considered**:
- **Laravel Eloquent groupBy**: Slower for 100k records; raw queries are faster
- **Caching aggregated results**: Adds complexity; not needed for 5-second target
- **Materialized views**: Overkill for read-mostly workload

**Performance Notes**:
- Expected query time: <1 second for 100k comments with index
- Add composite index if needed: `INDEX idx_video_created (video_id, created_at)`

## Research Task 4: Alpine.js Chart Integration Patterns

**Decision**: Alpine.js `x-data` component with Chart.js instance management

**Rationale**:
- **Reactive updates**: Alpine.js `$watch` triggers chart updates on time range change
- **Lifecycle hooks**: `x-init` creates chart, `Alpine.effect()` handles cleanup
- **No build step**: Works with Laravel Blade templates without complex bundling

**Implementation Pattern**:
```javascript
Alpine.data('videoAnalysisChart', (videoId) => ({
    chart: null,
    timeRange: '3days',
    loading: false,

    init() {
        const ctx = this.$refs.canvas.getContext('2d');
        this.chart = new Chart(ctx, {
            type: 'line',
            data: { labels: [], datasets: [] },
            options: { /* ... */ }
        });

        this.$watch('timeRange', () => this.loadChartData());
        this.loadChartData();
    },

    async loadChartData() {
        this.loading = true;
        const response = await fetch(`/api/videos/${videoId}/chart-data?range=${this.timeRange}`);
        const data = await response.json();

        this.chart.data.labels = data.labels;
        this.chart.data.datasets[0].data = data.values;
        this.chart.update();
        this.loading = false;
    }
}));
```

**Alternatives Considered**:
- **Vue.js**: Too heavy; requires npm build process
- **Vanilla JS**: Less maintainable; Alpine.js already in stack
- **Livewire**: Server-side rendering adds latency for chart updates

**Loading State**:
- Show skeleton chart SVG with loading spinner overlay
- Use Tailwind CSS for skeleton UI

## Research Task 5: Structured Logging in Laravel

**Decision**: Laravel's native `Log` facade with JSON context

**Rationale**:
- **Built-in support**: Laravel logging is PSR-3 compliant
- **Structured context**: `Log::info($message, $context)` supports arrays
- **Multiple channels**: Can configure separate log file for API events

**Logging Pattern**:
```php
Log::channel('api')->info('YouTube API cache refresh', [
    'video_id' => $video->id,
    'success' => true,
    'views' => $stats['viewCount'],
    'likes' => $stats['likeCount'],
    'cached_at' => now()->toDateTimeString(),
    'execution_time_ms' => $executionTime
]);

Log::channel('api')->error('YouTube API failure', [
    'video_id' => $video->id,
    'error' => $exception->getMessage(),
    'status_code' => $response->status(),
    'timestamp' => now()->toDateTimeString()
]);
```

**Configuration** (`config/logging.php`):
```php
'channels' => [
    'api' => [
        'driver' => 'single',
        'path' => storage_path('logs/api.log'),
        'level' => 'info',
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
    ],
],
```

**Alternatives Considered**:
- **External logging service (Sentry, Papertrail)**: Not needed for MVP
- **Custom logging class**: Laravel's built-in logging is sufficient
- **Database logging**: Slows down requests; file-based is faster

**Monitoring Requirements** (from spec):
- Log all API failures with video ID and timestamp
- Log cache refresh events (success/failure)
- Include execution time for performance monitoring

## Summary of Decisions

| Research Area | Decision | Key Benefit |
|---------------|----------|-------------|
| Chart Library | Chart.js 4.x via CDN | Lightweight, Alpine.js compatible |
| Cache Locking | Laravel `lockForUpdate()` | Native support, no Redis needed |
| Query Optimization | Indexed MySQL date functions | Sub-second queries for 100k records |
| Frontend Framework | Alpine.js with Chart.js | Already in stack, no build complexity |
| Logging Strategy | Laravel Log with JSON formatter | Built-in, structured, monitorable |

All NEEDS CLARIFICATION items from Technical Context are now resolved.
