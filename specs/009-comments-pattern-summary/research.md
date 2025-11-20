# Research: Comments Pattern Summary

**Feature**: 009-comments-pattern-summary
**Date**: 2025-11-20
**Purpose**: Resolve technical unknowns and document technology choices

## Research Questions Addressed

1. How to implement infinite scroll in Laravel (no built-in support)?
2. What caching strategy for expensive cross-channel queries?
3. Best practices for timezone conversion in MySQL queries?
4. Frontend infinite scroll implementation patterns?

---

## 1. Infinite Scroll Implementation in Laravel

### Decision: Custom Offset-Based Pagination with AJAX

**Rationale**:
- Laravel's built-in `paginate()` uses page numbers, not suitable for infinite scroll
- Laravel's `simplePaginate()` still uses "next page" links
- Laravel's `cursorPaginate()` is designed for large datasets but requires unique sortable column
- Custom offset/limit approach provides most control and matches "100 comments per batch" requirement

**Implementation Pattern**:
```php
// In CommentPatternService
public function getComments($videoId, $pattern, $offset = 0, $limit = 100)
{
    return Comment::where('video_id', $videoId)
        ->when($pattern !== 'all', function ($query) use ($pattern) {
            // Apply pattern-specific filtering
        })
        ->orderBy('published_at', 'desc')
        ->offset($offset)
        ->limit($limit)
        ->get();
}
```

**API Response Format**:
```json
{
  "data": [...],
  "meta": {
    "offset": 0,
    "limit": 100,
    "returned": 100,
    "has_more": true
  }
}
```

**Alternatives Considered**:
- **Laravel cursor pagination**: Rejected because requires stable cursor column (published_at may have duplicates)
- **Spatie Query Builder package**: Rejected to avoid new dependency for simple use case
- **Load More button**: Rejected per spec requirement for infinite scroll

---

## 2. Caching Strategy for Cross-Channel Queries

### Decision: Redis Cache with 5-Minute TTL for Night-Time Statistics

**Rationale**:
- Night-time frequency calculation scans ALL comments across ALL channels per author
- This is expensive for active authors with thousands of comments
- Statistics don't need real-time accuracy (5-minute staleness acceptable)
- Cache invalidation not needed (comment imports are batch operations, not real-time)

**Caching Pattern**:
```php
// In CommentPatternService
public function getNightTimeFrequencyCommenters($videoId)
{
    $cacheKey = "video:{$videoId}:night_time_commenters";

    return Cache::remember($cacheKey, 300, function () use ($videoId) {
        // Expensive cross-channel query here
        return DB::table('comments')
            ->select('author_channel_id')
            ->selectRaw('
                COUNT(*) as total_comments,
                SUM(CASE
                    WHEN HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) BETWEEN 1 AND 5
                    THEN 1 ELSE 0
                END) as night_comments
            ')
            ->groupBy('author_channel_id')
            ->havingRaw('night_comments / total_comments > 0.5')
            ->havingRaw('total_comments >= 2')
            ->pluck('author_channel_id');
    });
}
```

**Cache Configuration**:
- **Driver**: Redis (assumed available per Laravel best practices)
- **TTL**: 300 seconds (5 minutes)
- **Key Pattern**: `video:{videoId}:pattern:{patternType}`
- **Invalidation**: None (time-based expiry sufficient)

**Alternatives Considered**:
- **Database materialized views**: Rejected due to MySQL version uncertainty
- **Pre-computed nightly batch**: Rejected to avoid data staleness
- **No caching**: Rejected due to performance concerns for videos with many active commenters

---

## 3. Timezone Conversion Best Practices

### Decision: Use MySQL CONVERT_TZ() for Filtering, Carbon for Display

**Rationale**:
- Database stores UTC (verified from existing migrations)
- Filtering by hour (01:00-05:59 GMT+8) MUST happen at database layer for performance
- Display conversion happens in application layer for flexibility

**Query Pattern (Night-Time Filtering)**:
```sql
-- Correct: Convert to GMT+8 THEN extract hour
WHERE HOUR(CONVERT_TZ(published_at, '+00:00', '+08:00')) BETWEEN 1 AND 5

-- Wrong: Extract hour from UTC then compare
WHERE HOUR(published_at) BETWEEN ... -- This uses UTC hours!
```

**Display Pattern (Blade/Resources)**:
```php
// In API Resource or Blade template
$comment->published_at->setTimezone('Asia/Taipei')->format('Y-m-d H:i')
```

**Edge Cases Handled**:
- Null timestamps: Excluded from night-time calculations per spec (FR-013)
- DST transitions: Taiwan doesn't observe DST, GMT+8 is constant
- Leap seconds: MySQL handles at storage layer

**Alternatives Considered**:
- **Store both UTC and GMT+8**: Rejected due to data duplication and sync issues
- **Convert in application layer**: Rejected due to performance (can't use indexes)
- **Use named timezone 'Asia/Taipei'**: Avoided in SQL due to timezone table dependency

---

## 4. Frontend Infinite Scroll Implementation

### Decision: Intersection Observer API with Sentinel Element

**Rationale**:
- Modern browser API (95%+ support, includes all relevant browsers)
- More performant than scroll event listeners
- Declarative approach (observe sentinel, not calculate scroll positions)
- Handles edge cases (resize, dynamic content height) automatically

**Implementation Pattern**:
```javascript
// comment-pattern.js
class CommentPatternUI {
    constructor() {
        this.offset = 0;
        this.loading = false;
        this.hasMore = true;
        this.currentPattern = 'all';
        this.initIntersectionObserver();
    }

    initIntersectionObserver() {
        const sentinel = document.querySelector('#comment-list-sentinel');
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && this.hasMore && !this.loading) {
                this.loadMoreComments();
            }
        }, { threshold: 0.1 });

        observer.observe(sentinel);
    }

    async loadMoreComments() {
        this.loading = true;
        const response = await fetch(
            `/api/videos/${videoId}/comments?pattern=${this.currentPattern}&offset=${this.offset}&limit=100`
        );
        const data = await response.json();

        this.appendComments(data.data);
        this.offset += data.meta.returned;
        this.hasMore = data.meta.has_more;
        this.loading = false;
    }

    switchPattern(pattern) {
        this.currentPattern = pattern;
        this.offset = 0;
        this.hasMore = true;
        this.clearComments();
        this.loadMoreComments();
    }
}
```

**Sentinel Element Pattern**:
```html
<div id="comment-list">
    <!-- Comments appended here -->
</div>
<div id="comment-list-sentinel" class="h-4"></div> <!-- Observer target -->
```

**Loading States**:
- Initial load: Show skeleton loaders
- Infinite scroll: Show spinner at bottom during fetch
- No more data: Hide sentinel, show "沒有更多留言" message
- Error state: Show retry button

**Alternatives Considered**:
- **Scroll event listener**: Rejected due to performance (requires throttling/debouncing)
- **Third-party library (react-infinite-scroll)**: Rejected due to vanilla JS requirement
- **Virtual scrolling**: Rejected as overkill for 100-comment batches

---

## 5. Repeat Commenter Detection

### Decision: Simple GROUP BY with HAVING Clause

**Rationale**:
- Straightforward SQL query, no complex logic needed
- MySQL optimizes GROUP BY well with proper indexes
- Existing index on [video_id, author_channel_id] supports this query

**Query Pattern**:
```sql
SELECT author_channel_id, COUNT(*) as comment_count
FROM comments
WHERE video_id = ?
GROUP BY author_channel_id
HAVING comment_count >= 2
```

**Performance**:
- Uses existing composite index: `[video_id, author_channel_id]`
- Expected execution time: <100ms for 10k comments
- No additional indexes needed

---

## Technology Choices Summary

| Decision Area | Technology Choice | Rationale |
|---------------|------------------|-----------|
| Infinite Scroll (Backend) | Custom offset/limit pagination | Simple, predictable, matches 100-batch requirement |
| Infinite Scroll (Frontend) | Intersection Observer API | Modern, performant, handles edge cases |
| Caching | Redis with 5-min TTL | Balances performance vs freshness |
| Timezone Conversion (Query) | MySQL CONVERT_TZ() | Required for hour-based filtering |
| Timezone Conversion (Display) | Laravel Carbon setTimezone() | Flexible, framework-native |
| Repeat Detection | SQL GROUP BY ... HAVING | Simple, well-indexed |
| API Response Format | JSON with meta envelope | Standard pagination pattern |

---

## Performance Benchmarks (Expected)

Based on research and existing codebase patterns:

| Operation | Target | Basis |
|-----------|--------|-------|
| Pattern statistics (cached) | <200ms | Similar to comment density endpoint |
| Pattern statistics (uncached) | <2s | Cross-channel query complexity |
| Comment list (100 records) | <500ms | Indexed query with simple filters |
| Infinite scroll batch | <1s | Per spec requirement SC-004 |
| Night-time calculation | <3s (uncached) | Cross-channel aggregation |

---

## Risks and Mitigations

### Risk 1: Night-Time Query Performance
- **Risk**: Cross-channel query scans millions of comments
- **Mitigation**: Redis caching (5-min TTL), consider pre-computation if needed
- **Fallback**: Show loading state, async computation with job queue

### Risk 2: Infinite Scroll Memory Leaks
- **Risk**: DOM grows indefinitely if user scrolls thousands of comments
- **Mitigation**: Virtual scrolling OR warn user after 500 comments
- **Decision**: Accept risk for MVP (unlikely scenario given 100-batch UX)

### Risk 3: Timezone Table Dependency
- **Risk**: CONVERT_TZ requires MySQL timezone tables loaded
- **Mitigation**: Use offset-based syntax ('+00:00', '+08:00') not named timezones
- **Verification**: Test query before deployment

---

## References

- Laravel Pagination Docs: https://laravel.com/docs/12.x/pagination
- Intersection Observer API: https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
- MySQL CONVERT_TZ: https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html#function_convert-tz
- Redis Caching in Laravel: https://laravel.com/docs/12.x/cache
