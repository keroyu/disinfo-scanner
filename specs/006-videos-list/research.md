# Research: Videos List Implementation

**Feature**: Videos List
**Date**: 2025-11-18
**Phase**: Phase 0 - Technical Research

## Research Questions

### 1. Query Optimization for Comment Statistics

**Question**: What is the most efficient way to calculate comment count and last comment time for each video?

**Options Evaluated**:

| Option | Pros | Cons | Performance |
|--------|------|------|-------------|
| **A. Eager Loading with groupBy** | Simple Laravel syntax | N+1 queries for large datasets | Poor (10k videos = 10k queries) |
| **B. Subqueries in SELECT** | Single query, accurate counts | Complex SQL, harder to maintain | Good (1 query) |
| **C. Database View** | Excellent read performance | Requires migration, not dynamic | Excellent (pre-computed) |
| **D. withCount() + subselect** | Built-in Laravel feature | Limited to COUNT only, no MAX | Moderate |

**Decision**: **Option B - Subqueries in SELECT**

**Rationale**:
- Single query execution reduces database round-trips
- Laravel Query Builder supports `selectRaw()` and `leftJoin()` for subqueries
- Accurate real-time counts without caching staleness
- No schema changes required (vs. database views)

**Implementation Pattern**:
```php
Video::selectRaw('
    videos.*,
    (SELECT COUNT(*) FROM comments WHERE comments.video_id = videos.video_id) as actual_comment_count,
    (SELECT MAX(published_at) FROM comments WHERE comments.video_id = videos.video_id) as last_comment_time
')
->having('actual_comment_count', '>', 0)
->orderBy('published_at', 'desc')
->paginate(500);
```

**Alternatives Considered**:
- Database view rejected: Adds migration complexity without significant benefit for this read-heavy but not ultra-high-traffic page
- Eager loading rejected: Would generate 1 + N queries (unacceptable for 10k videos)

---

### 2. Pagination with State Preservation

**Question**: How to maintain search and sort parameters across pagination?

**Research Findings**:
- Laravel's `appends()` method automatically includes query parameters in pagination links
- Standard pattern in existing CommentsController already uses this approach

**Decision**: **Use `appends(request()->query())`**

**Rationale**:
- Built-in Laravel feature, no custom implementation needed
- Automatically handles all query parameters (search, sort, direction)
- Already proven in Comments List implementation

**Implementation Pattern**:
```php
$videos->appends(request()->query())->links();
```

**No alternatives needed** - this is the standard Laravel best practice.

---

### 3. Case-Insensitive Search

**Question**: Best approach for case-insensitive search in MySQL?

**Options Evaluated**:

| Option | SQL Pattern | Performance | Indexability |
|--------|-------------|-------------|--------------|
| **A. LOWER() function** | `WHERE LOWER(title) LIKE LOWER(?)` | Slower (function call per row) | Not index-friendly |
| **B. COLLATE clause** | `WHERE title LIKE ? COLLATE utf8mb4_general_ci` | Faster (collation-level) | Index-friendly |
| **C. Default collation** | `WHERE title LIKE ?` | Fastest (if table uses ci collation) | Fully indexed |

**Decision**: **Option C - Rely on Default Collation**

**Rationale**:
- MySQL default for `utf8mb4` is `utf8mb4_0900_ai_ci` (accent-insensitive, case-insensitive)
- Laravel migrations use `utf8mb4` charset by default
- No performance penalty (uses indexes directly)
- Simple query syntax

**Verification**:
```sql
SHOW TABLE STATUS LIKE 'videos';
-- Expected Collation: utf8mb4_0900_ai_ci or utf8mb4_unicode_ci
```

**Implementation Pattern**:
```php
->where('title', 'LIKE', "%{$keyword}%")
->orWhereHas('channel', function($q) use ($keyword) {
    $q->where('channel_name', 'LIKE', "%{$keyword}%");
})
```

**Fallback**: If table uses case-sensitive collation, add `COLLATE utf8mb4_general_ci` to WHERE clauses.

---

### 4. Date Calculation for 90-Day Range Navigation

**Question**: How to calculate and pass 90-day date range to Comments List?

**Research Findings**:
- Laravel uses Carbon library (already included)
- Carbon provides `subDays()` method for date arithmetic
- URL parameters can encode dates as `Y-m-d` format

**Decision**: **Carbon + URL Query String**

**Rationale**:
- Carbon is already available (no new dependency)
- Standard date format (`Y-m-d`) is URL-safe without encoding
- Comments List already handles `from_date` and `to_date` parameters

**Implementation Pattern**:
```php
// In Blade template (click handler for last_comment_time)
$clickedDate = $video->last_comment_time;
$fromDate = Carbon::parse($clickedDate)->subDays(90)->format('Y-m-d');
$toDate = Carbon::parse($clickedDate)->format('Y-m-d');

$url = route('comments.index', [
    'search' => $video->title,
    'from_date' => $fromDate,
    'to_date' => $toDate,
]);
```

**No special encoding needed** - dates in `Y-m-d` format are URL-safe.

---

## Technology Stack Confirmation

**Backend**:
- PHP 8.2
- Laravel 12.38.1
- MySQL (utf8mb4 collation)

**Frontend**:
- Blade templating engine
- Tailwind CSS (existing)
- Alpine.js (inferred from existing patterns, optional)

**Testing**:
- PHPUnit 11.5.3 (feature tests)
- Laravel Dusk (browser tests for visual consistency)

**No new dependencies required** - all functionality achievable with existing tech stack.

---

## Performance Validation Plan

### Query Performance Benchmarks

**Target**: < 2 seconds for 10,000 videos

**Test Scenarios**:
1. Load Videos List with no filters (default sort by published_at DESC)
2. Search by keyword (case-insensitive)
3. Sort by comment_count DESC
4. Sort by last_comment_time DESC
5. Pagination (page 1, page 10, page 20 of 20)

**Monitoring**:
- Laravel Debugbar (development)
- Query logging for slow queries (>1s)

### Expected Query Count
- **Page load**: 1 main query (with subqueries) + 1 pagination count query = **2 queries total**
- **With search**: Same as above (search is WHERE clause)
- **With sort**: Same as above (sort is ORDER BY clause)

---

## Conclusion

All research questions resolved. No blocking technical issues identified. Ready to proceed to Phase 1 (Design).
