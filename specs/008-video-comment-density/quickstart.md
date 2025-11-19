# Quickstart Guide: Video Comment Density Analysis

**Feature**: `008-video-comment-density`
**Date**: 2025-11-19
**Audience**: Developers implementing this feature

## Overview

This guide helps developers quickly understand the comment density analysis feature architecture, implementation requirements, and how to build and test the feature following the project's constitution principles (TDD, API-first, observability, contract testing, semantic versioning).

---

## Architecture Overview

### Component Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                       User Browser                          │
│  ┌───────────────────────────────────────────────────────┐ │
│  │  videos/list.blade.php                                 │ │
│  │  - Displays video list with "分析" button              │ │
│  └────────────────────┬──────────────────────────────────┘ │
│                       │ Click "分析"                        │
│                       ▼                                      │
│  ┌───────────────────────────────────────────────────────┐ │
│  │  videos/analysis.blade.php                             │ │
│  │  - Time range selector (preset + custom)               │ │
│  │  - Loading skeleton + spinner                          │ │
│  │  - Chart.js line chart                                 │ │
│  │  - Error display (technical details)                   │ │
│  └────────────────────┬──────────────────────────────────┘ │
└────────────────────────┼───────────────────────────────────┘
                         │ AJAX Request
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                    Laravel Backend                          │
│  ┌───────────────────────────────────────────────────────┐ │
│  │  VideoAnalysisController                               │ │
│  │  - showAnalysisPage() → renders Blade view            │ │
│  │  - getCommentDensityData() → API endpoint             │ │
│  └────────────────────┬──────────────────────────────────┘ │
│                       │ Delegates business logic            │
│                       ▼                                      │
│  ┌───────────────────────────────────────────────────────┐ │
│  │  CommentDensityAnalysisService                         │ │
│  │  - determineGranularity()                              │ │
│  │  - aggregateCommentDensity()                           │ │
│  │  - fillMissingBuckets()                                │ │
│  │  - convertToDataPoints()                               │ │
│  └────────────────────┬──────────────────────────────────┘ │
│                       │ Queries database                    │
│                       ▼                                      │
│  ┌───────────────────────────────────────────────────────┐ │
│  │  Comment Model + Video Model                           │ │
│  │  - Eloquent queries with timezone conversion           │ │
│  │  - Index: idx_video_published (video_id, published_at)│ │
│  └───────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow (Optimized)

1. **User Navigation**: Click "分析" button on video list → Navigate to `/videos/{video_id}/analysis`
2. **Page Load**: Controller renders analysis page with video context
3. **Chart Request**: JavaScript sends AJAX GET to `/api/videos/{video_id}/comment-density` (NO query parameters)
4. **Service Processing** (ONE-TIME fetch):
   - Validate video_id exists
   - Execute TWO SQL aggregation queries in parallel:
     - Query 1: Hourly data (first 14 days) with timezone conversion
     - Query 2: Daily data (publication to current date) with timezone conversion
   - Fill missing buckets for both datasets (dense time series)
   - Convert both datasets to GMT+8 timestamps
   - Build JSON response with BOTH complete datasets
5. **Frontend Caching**: Store complete response in JavaScript memory
6. **Range Selection**: User selects time range (3/7/14/30 days or custom)
7. **Client-Side Filtering**: JavaScript filters cached data (NO API call):
   - 3/7/14 days → slice hourly_data
   - 30 days → slice daily_data
   - Custom range → filter appropriate dataset by timestamp
8. **Chart Rendering**: Chart.js renders/updates line chart instantly (0ms)
9. **Error Handling**: Display technical errors if initial query fails (per FR-017)

---

## File Structure

### New Files to Create

```
app/Http/Controllers/
  └── VideoAnalysisController.php          # Controller for analysis page and API

app/Services/
  └── CommentDensityAnalysisService.php    # Business logic layer

resources/views/videos/
  └── analysis.blade.php                   # Analysis page view

tests/Feature/
  └── VideoAnalysisControllerTest.php      # Integration tests for page + API

tests/Feature/Api/
  └── CommentDensityApiTest.php            # API contract tests

tests/Unit/Services/
  └── CommentDensityAnalysisServiceTest.php # Unit tests for service
```

### Files to Modify

```
app/Models/Video.php                       # Add analysisUrl() helper method
routes/web.php                             # Add analysis page route
routes/api.php                             # Add comment-density API route
resources/views/videos/list.blade.php      # Add "分析" button next to "更新"
```

---

## Implementation Checklist

### Phase 1: Setup and Testing Infrastructure (TDD)

- [ ] **1.1** Create feature test stubs (RED tests first)
  ```bash
  php artisan make:test VideoAnalysisControllerTest
  php artisan make:test Api/CommentDensityApiTest
  php artisan make:test Unit/Services/CommentDensityAnalysisServiceTest
  ```

- [ ] **1.2** Write failing contract tests for API endpoint
  - Test: GET `/api/videos/{id}/comment-density?range_type=7days` returns 404 (video not implemented yet)
  - Test: API response schema matches OpenAPI spec (`contracts/comment-density-api.yaml`)
  - Test: Timezone conversion (UTC → GMT+8)
  - Test: Granularity determination (≤14 days = hourly, >14 days = daily)

- [ ] **1.3** Write failing unit tests for service methods
  - Test: `determineGranularity(7 days)` → "hourly"
  - Test: `determineGranularity(30 days)` → "daily"
  - Test: `fillMissingBuckets()` inserts zero-count buckets
  - Test: `convertToDataPoints()` formats timestamps correctly

- [ ] **1.4** Verify all tests fail (RED phase)
  ```bash
  php artisan test --filter=VideoAnalysis
  php artisan test --filter=CommentDensity
  ```

### Phase 2: Database Preparation

- [ ] **2.1** Verify index exists on comments table
  ```bash
  # Check existing indexes
  php artisan tinker
  >>> DB::select("SHOW INDEX FROM comments WHERE Key_name = 'idx_video_published'");
  ```

- [ ] **2.2** Create migration if index missing
  ```bash
  php artisan make:migration add_video_published_index_to_comments
  ```
  ```php
  public function up()
  {
      Schema::table('comments', function (Blueprint $table) {
          $table->index(['video_id', 'published_at'], 'idx_video_published');
      });
  }
  ```

- [ ] **2.3** Run migration
  ```bash
  php artisan migrate
  ```

### Phase 3: Service Layer Implementation (GREEN)

- [ ] **3.1** Create `CommentDensityAnalysisService`
  ```bash
  # Create service file manually at app/Services/CommentDensityAnalysisService.php
  ```

- [ ] **3.2** Implement service methods following data-model.md specifications (OPTIMIZED)
  - `getCommentDensityData(string $videoId, Carbon $videoPublishedAt): array` - Main method returning both datasets
  - `aggregateHourlyData(string $videoId, Carbon $start, Carbon $end): array` - Query hourly buckets
  - `aggregateDailyData(string $videoId, Carbon $start, Carbon $end): array` - Query daily buckets
  - `fillMissingBuckets(array $sparse, Carbon $start, Carbon $end, string $granularity): array` - Fill zeros
  - `convertToDataPoints(array $dense, string $granularity): array` - Convert to GMT+8 with display strings

- [ ] **3.3** Run unit tests to verify service logic
  ```bash
  php artisan test tests/Unit/Services/CommentDensityAnalysisServiceTest.php
  ```

- [ ] **3.4** Fix any failures until all service tests pass (GREEN phase)

### Phase 4: API Endpoint Implementation (GREEN)

- [ ] **4.1** Create `VideoAnalysisController`
  ```bash
  php artisan make:controller VideoAnalysisController
  ```

- [ ] **4.2** Implement API endpoint method (OPTIMIZED - no query parameters needed)
  ```php
  public function getCommentDensityData(string $videoId): JsonResponse
  {
      // Validate video exists
      $video = Video::findOrFail($videoId);

      // Call service to get BOTH datasets
      $densityData = $this->commentDensityService->getCommentDensityData(
          $videoId,
          $video->published_at
      );

      // Return complete response with hourly_data + daily_data
      return response()->json($densityData);

      // Handle exceptions → CommentDensityErrorResponse with technical details
  }
  ```

- [ ] **4.3** Add routes (SIMPLIFIED - no query parameters)
  ```php
  // routes/api.php
  Route::get('/videos/{videoId}/comment-density', [VideoAnalysisController::class, 'getCommentDensityData']);

  // Note: No range_type, custom_start_date, or custom_end_date parameters needed!
  ```

- [ ] **4.4** Run API contract tests
  ```bash
  php artisan test tests/Feature/Api/CommentDensityApiTest.php
  ```

- [ ] **4.5** Fix failures until API tests pass (GREEN phase)

### Phase 5: Web Page Implementation

- [ ] **5.1** Add `analysisUrl()` helper to Video model
  ```php
  // app/Models/Video.php
  public function analysisUrl(): string
  {
      return route('videos.analysis', ['video' => $this->video_id]);
  }
  ```

- [ ] **5.2** Create analysis page controller method
  ```php
  public function showAnalysisPage(string $videoId): View
  {
      $video = Video::findOrFail($videoId);
      return view('videos.analysis', compact('video'));
  }
  ```

- [ ] **5.3** Add web route
  ```php
  // routes/web.php
  Route::get('/videos/{video}/analysis', [VideoAnalysisController::class, 'showAnalysisPage'])->name('videos.analysis');
  ```

- [ ] **5.4** Create `analysis.blade.php` view
  - Breadcrumb navigation: "首頁 > 影片列表 > 影片分析"
  - Time range selector (5 preset options + custom date picker)
  - Loading skeleton + spinner
  - Chart.js canvas element
  - Error display area (technical details per FR-017)

- [ ] **5.5** Modify `list.blade.php` to add "分析" button
  ```blade
  <!-- Add next to existing "更新" button -->
  <a href="{{ $video->analysisUrl() }}"
     class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
    分析
  </a>
  ```

- [ ] **5.6** Add Chart.js integration script (OPTIMIZED for client-side filtering)
  - Include Chart.js v4 CDN in layout or analysis page
  - Include chartjs-adapter-date-fns for time scale
  - Implement ONE-TIME AJAX call to API endpoint (no parameters)
  - Cache response in JavaScript variable
  - Implement range selector change handler (filters cached data)
  - Implement 3-second timeout detection for initial load (update message + show cancel button)
  - Implement Chart.js rendering with GMT+8 timezone
  - **Key optimization**: Range switching filters cached data, NO additional API calls

  ```javascript
  // Example implementation
  let cachedDensityData = null;
  let chartInstance = null;

  async function loadInitialData(videoId) {
      showSkeleton();

      const response = await fetch(`/api/videos/${videoId}/comment-density`);
      cachedDensityData = await response.json();

      // Initialize with 7-day view by default
      renderChart(filterDataByRange('7days'));
  }

  function filterDataByRange(rangeType, customStart, customEnd) {
      if (!cachedDensityData) return [];

      switch (rangeType) {
          case '3days':   return cachedDensityData.hourly_data.data.slice(0, 72);
          case '7days':   return cachedDensityData.hourly_data.data.slice(0, 168);
          case '14days':  return cachedDensityData.hourly_data.data;
          case '30days':  return cachedDensityData.daily_data.data.slice(0, 30);
          case 'custom':
              const daysDiff = (customEnd - customStart) / (1000*60*60*24);
              const dataset = daysDiff <= 14
                  ? cachedDensityData.hourly_data.data
                  : cachedDensityData.daily_data.data;
              return dataset.filter(d => {
                  const t = new Date(d.timestamp);
                  return t >= customStart && t <= customEnd;
              });
      }
  }

  function renderChart(filteredData) {
      const chartData = {
          datasets: [{
              label: 'Comment Count',
              data: filteredData.map(d => ({ x: d.timestamp, y: d.count })),
              borderColor: 'rgb(59, 130, 246)',
              tension: 0.1
          }]
      };

      if (chartInstance) {
          chartInstance.data = chartData;
          chartInstance.update(); // Instant update, no API call
      } else {
          chartInstance = new Chart(ctx, {
              type: 'line',
              data: chartData,
              options: {
                  scales: {
                      x: { type: 'time', time: { timezone: 'Asia/Taipei' } },
                      y: { beginAtZero: true }
                  }
              }
          });
      }
  }

  // Range selector change handler
  document.getElementById('rangeSelector').addEventListener('change', (e) => {
      const filteredData = filterDataByRange(e.target.value);
      renderChart(filteredData); // Instant, 0ms
  });
  ```

### Phase 6: Testing and Validation

- [ ] **6.1** Run all tests
  ```bash
  php artisan test
  ```

- [ ] **6.2** Manual testing checklist
  - [ ] Navigate to video list → Click "分析" button → Analysis page loads
  - [ ] Breadcrumb shows "首頁 > 影片列表 > 影片分析"
  - [ ] Select "3 days after publication" → Chart displays hourly data
  - [ ] Select "30 days after publication" → Chart displays daily data
  - [ ] Select custom range ≤14 days → Hourly granularity
  - [ ] Select custom range >14 days → Daily granularity
  - [ ] Hover over chart point → Tooltip shows timestamp with "(GMT+8)" label
  - [ ] Select video with 0 comments → Display "No comments found in this period"
  - [ ] Test loading states (use Chrome DevTools to throttle network)
  - [ ] Trigger database error (disconnect MySQL) → Technical error details displayed

- [ ] **6.3** Performance validation
  - [ ] Test with video containing 10k comments → Response <1 second
  - [ ] Test with video containing 100k comments → Response <3 seconds or extended loading UI
  - [ ] Verify MySQL query uses index (EXPLAIN query in tinker)

### Phase 7: Observability Implementation

- [ ] **7.1** Add structured logging to service
  ```php
  Log::info('Comment density aggregation started', [
      'trace_id' => $traceId,
      'video_id' => $videoId,
      'range' => [$start, $end],
      'granularity' => $granularity
  ]);
  ```

- [ ] **7.2** Log performance metrics
  ```php
  Log::info('Comment density aggregation completed', [
      'trace_id' => $traceId,
      'query_time_ms' => $queryTimeMs,
      'record_count' => count($results)
  ]);
  ```

- [ ] **7.3** Log errors with full context
  ```php
  Log::error('Comment density query failed', [
      'trace_id' => $traceId,
      'error' => $exception->getMessage(),
      'sql' => $exception->getSql(),
      'parameters' => $params
  ]);
  ```

### Phase 8: Documentation and Commit

- [ ] **8.1** Update CLAUDE.md if new technologies added (already done in planning phase)

- [ ] **8.2** Run final test suite
  ```bash
  php artisan test
  ```

- [ ] **8.3** Commit changes following project conventions
  ```bash
  git add .
  git commit -m "feat: Implement video comment density analysis (v0.34)

  - Add VideoAnalysisController with analysis page and API endpoint
  - Add CommentDensityAnalysisService for data aggregation
  - Add analysis.blade.php with Chart.js visualization
  - Add 分析 button to video list
  - Implement TDD with contract tests and unit tests
  - Display technical errors for debugging (FR-017)
  - Support hourly/daily granularity based on range
  - All timestamps in Asia/Taipei timezone (GMT+8)

  🤖 Generated with [Claude Code](https://claude.com/claude-code)

  Co-Authored-By: Claude <noreply@anthropic.com>"
  ```

---

## Key Implementation Notes

### TDD Workflow (Constitution Principle I)

1. **Write tests FIRST** (RED phase)
   - Contract tests for API response schema
   - Unit tests for service methods
   - Integration tests for full flow

2. **Implement minimal code** (GREEN phase)
   - Service logic to pass unit tests
   - Controller logic to pass contract tests
   - View to pass integration tests

3. **Refactor** (REFACTOR phase)
   - Extract reusable methods
   - Optimize SQL queries
   - Improve code readability

### API-First Design (Constitution Principle II)

- OpenAPI contract already defined in `contracts/comment-density-api.yaml`
- Write contract tests validating response schema BEFORE implementing endpoint
- Ensure error responses include technical details (per FR-017 clarification)

### Observable Systems (Constitution Principle III)

- Every API request generates unique `trace_id` (UUID)
- Log query start, completion, and errors with trace_id
- Include trace_id in error responses to link frontend errors to backend logs
- Log performance metrics: query_time_ms, record_count

### Contract Testing (Constitution Principle IV)

- API contract tests verify response schema matches OpenAPI spec
- Service boundary tests validate Comment model queries return expected data
- Frontend-backend contract: JSON format, timezone handling (GMT+8), error codes

### Semantic Versioning (Constitution Principle V)

- This is a **MINOR** version increment (new feature, backward-compatible)
- No breaking changes to existing APIs
- No database schema changes (reuses existing tables)

---

## Common Pitfalls and Solutions

### Pitfall 1: Timezone Confusion

**Problem**: Mixing UTC and GMT+8 timestamps causes incorrect chart display

**Solution**:
- Store ALL timestamps in database as UTC (existing behavior)
- Convert to GMT+8 ONLY in service layer (`convertToDataPoints()`)
- Display "(GMT+8)" label in UI to clarify timezone

### Pitfall 2: Missing Time Buckets

**Problem**: Chart has gaps when no comments exist for certain hours/days

**Solution**:
- Use `fillMissingBuckets()` to insert zero-count buckets
- Ensures continuous time series for Chart.js

### Pitfall 3: Slow Queries on Large Datasets

**Problem**: Aggregation query times out on 100k+ comments

**Solution**:
- Verify index exists: `idx_video_published (video_id, published_at)`
- Use EXPLAIN to verify index is used
- Set query timeout to 30 seconds
- Implement extended loading UI (per FR-019, FR-020)

### Pitfall 4: Chart.js Not Displaying Time Axis

**Problem**: X-axis shows incorrect labels or NaN values

**Solution**:
- Include `chartjs-adapter-date-fns` CDN for time scale support
- Ensure data format is `[{x: timestamp, y: count}, ...]`
- Set `scales.x.type: 'time'` in Chart.js config
- Specify timezone: `scales.x.time.timezone: 'Asia/Taipei'`

---

## Testing Strategies

### Unit Tests (Service Layer)

**File**: `tests/Unit/Services/CommentDensityAnalysisServiceTest.php`

**Test Cases**:
- Granularity determination (7 days → hourly, 30 days → daily)
- Missing bucket filling (sparse → dense array)
- Timezone conversion (UTC → GMT+8)
- Edge cases (0 comments, start date before video publication)

### Contract Tests (API Layer)

**File**: `tests/Feature/Api/CommentDensityApiTest.php`

**Test Cases**:
- Response schema matches OpenAPI spec
- 200 OK with valid preset range (3days, 7days, 14days, 30days)
- 200 OK with valid custom range
- 400 Bad Request with invalid range_type
- 400 Bad Request with missing custom_start_date when range_type=custom
- 404 Not Found with nonexistent video_id
- 500 Internal Server Error simulation (mock database failure)

### Integration Tests (Full Stack)

**File**: `tests/Feature/VideoAnalysisControllerTest.php`

**Test Cases**:
- GET `/videos/{id}/analysis` renders analysis page
- Breadcrumb navigation displays correctly
- "分析" button exists on video list page
- AJAX request to API returns chart data
- Error display shows technical details on failure

---

## Performance Benchmarks

| Dataset Size | Expected Query Time | Notes |
|--------------|---------------------|-------|
| 1k comments  | <100ms             | Instant response |
| 10k comments | <500ms             | Fast response |
| 50k comments | <2 seconds         | Acceptable |
| 100k comments| <3 seconds         | Target performance |
| 500k+ comments| >3 seconds        | Extended loading UI triggered |

**Optimization Checklist**:
- [ ] Index `idx_video_published` exists
- [ ] Query uses index (verify with EXPLAIN)
- [ ] Service caches granularity calculation result
- [ ] Frontend implements request debouncing (prevent duplicate AJAX calls)

---

## Next Steps After Implementation

1. **Run `/speckit.tasks`** to generate `tasks.md` with dependency-ordered implementation tasks
2. **Execute tasks** following TDD workflow (RED → GREEN → REFACTOR)
3. **Manual QA** following testing checklist above
4. **Commit** with semantic version message
5. **Create Pull Request** referencing this specification and plan

---

## Quick Reference Links

- **Specification**: [spec.md](./spec.md)
- **Implementation Plan**: [plan.md](./plan.md)
- **Research**: [research.md](./research.md)
- **Data Model**: [data-model.md](./data-model.md)
- **API Contract**: [contracts/comment-density-api.yaml](./contracts/comment-density-api.yaml)
- **Constitution**: [../../.specify/memory/constitution.md](../../.specify/memory/constitution.md)

---

## Support

For questions or clarifications during implementation, refer to:
1. This quickstart guide
2. Constitution principles in `.specify/memory/constitution.md`
3. Feature specification in `spec.md`
4. OpenAPI contract in `contracts/comment-density-api.yaml`
