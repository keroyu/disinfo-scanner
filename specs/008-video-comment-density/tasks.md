# Implementation Tasks: Video Comment Density Analysis

**Feature Branch**: `008-video-comment-density`
**Generated**: 2025-11-19
**Total Tasks**: 45

---

## Task Summary

| Phase | User Story | Task Count | Can Parallelize |
|-------|------------|------------|-----------------|
| Phase 1: Setup | N/A | 3 | Yes (2/3) |
| Phase 2: Foundational | N/A | 7 | Yes (5/7) |
| Phase 3: User Story 1 (P1) | Quick Attack Detection | 15 | Yes (8/15) |
| Phase 4: User Story 2 (P2) | Custom Date Range | 8 | Yes (5/8) |
| Phase 5: User Story 3 (P3) | Chart Interaction | 6 | Yes (4/6) |
| Phase 6: Polish | N/A | 6 | Yes (4/6) |

**Parallel Execution Opportunities**: 28 tasks (62%) can run in parallel within their phase

---

## Implementation Strategy

### MVP Scope (Recommended First Delivery)
- **User Story 1 only** (Phase 1-3 完成)
- Provides core value: Preset time range analysis with hourly/daily granularity
- Independently testable and deployable
- Estimated: 15-20 hours of development

### Incremental Delivery Phases
1. **MVP** (US1): Preset time ranges with basic chart - Deploy to production
2. **Enhancement 1** (US2): Custom date range selector - Deploy after MVP validation
3. **Enhancement 2** (US3): Interactive chart tooltips - Deploy for improved UX

### Dependencies Between User Stories
- US1 → US2: Custom range depends on foundational chart rendering (US1)
- US1 → US3: Interactive tooltips depend on basic chart (US1)
- US2 ⊥ US3: Custom range and tooltips are independent of each other

---

## Phase 1: Setup (3 tasks)

**Goal**: Initialize project structure and verify database schema

- [ ] T001 [P] Verify database index exists on comments table (video_id, published_at)
  - **File**: database migration or manual verification
  - **Action**: Run `SHOW INDEX FROM comments WHERE Key_name = 'idx_video_published'`
  - **If missing**: Create migration `add_video_published_index_to_comments`
  - **Validation**: Query EXPLAIN uses index for comment density queries

- [ ] T002 [P] Install Chart.js v4.x and date adapter dependencies via CDN
  - **Files**: `resources/views/layouts/app.blade.php` or analysis page
  - **Action**: Add CDN links for Chart.js and chartjs-adapter-date-fns
  - **Validation**: Console shows no 404 errors for Chart.js resources

- [ ] T003 Review existing Video and Comment models for timezone handling
  - **Files**: `app/Models/Video.php`, `app/Models/Comment.php`
  - **Action**: Verify `published_at` casts to datetime and Carbon timezone support
  - **Validation**: Model timestamps correctly parse and convert to Asia/Taipei

---

## Phase 2: Foundational Tasks (7 tasks)

**Goal**: Build shared infrastructure needed by all user stories

**Blocking**: These MUST complete before starting any user story implementation

- [ ] T004 [P] Write service layer unit tests (RED) for CommentDensityAnalysisService
  - **File**: `tests/Unit/Services/CommentDensityAnalysisServiceTest.php`
  - **Tests**: `aggregateHourlyData()`, `aggregateDailyData()`, `fillMissingBuckets()`, `convertToDataPoints()`
  - **Expected**: All tests FAIL (method stubs not implemented)
  - **TDD**: RED phase - tests written before implementation

- [ ] T005 Create CommentDensityAnalysisService with method stubs
  - **File**: `app/Services/CommentDensityAnalysisService.php`
  - **Methods**: `getCommentDensityData()`, `aggregateHourlyData()`, `aggregateDailyData()`, `fillMissingBuckets()`, `convertToDataPoints()`
  - **Implementation**: Return empty arrays/null (stubs only)
  - **Validation**: Service class exists and is autoloadable

- [ ] T006 Implement aggregateHourlyData() method with SQL query
  - **File**: `app/Services/CommentDensityAnalysisService.php`
  - **Query**: `DATE_FORMAT(CONVERT_TZ(published_at, '+00:00', '+08:00'), '%Y-%m-%d %H:00:00')` GROUP BY
  - **Range**: video_published_at to (video_published_at + 14 days) OR current_time
  - **Validation**: Unit test passes for hourly aggregation
  - **TDD**: GREEN phase - make test pass

- [ ] T007 Implement aggregateDailyData() method with SQL query
  - **File**: `app/Services/CommentDensityAnalysisService.php`
  - **Query**: `DATE(CONVERT_TZ(published_at, '+00:00', '+08:00'))` GROUP BY
  - **Range**: video_published_at to current_date
  - **Validation**: Unit test passes for daily aggregation
  - **TDD**: GREEN phase - make test pass

- [ ] T008 [P] Implement fillMissingBuckets() method for dense time series
  - **File**: `app/Services/CommentDensityAnalysisService.php`
  - **Logic**: Generate all time buckets from start to end, fill sparse data with zeros
  - **Validation**: Unit test passes - sparse input (3 buckets) → dense output (336 buckets for hourly)
  - **TDD**: GREEN phase - make test pass

- [ ] T009 [P] Implement convertToDataPoints() method for GMT+8 conversion
  - **File**: `app/Services/CommentDensityAnalysisService.php`
  - **Logic**: Convert UTC buckets to Asia/Taipei timezone, format with "(GMT+8)" label
  - **Validation**: Unit test passes - output has `timestamp`, `display_time`, `count`, `bucket_size`
  - **TDD**: GREEN phase - make test pass

- [ ] T010 Implement getCommentDensityData() orchestration method
  - **File**: `app/Services/CommentDensityAnalysisService.php`
  - **Logic**: Call aggregateHourlyData() + aggregateDailyData(), fill missing buckets, convert to data points
  - **Return**: `{ hourly_data: {...}, daily_data: {...}, metadata: {...} }`
  - **Validation**: Unit test passes - returns complete dual-dataset structure
  - **TDD**: GREEN phase - make test pass

---

## Phase 3: User Story 1 - Quick Attack Detection via Preset Time Ranges (P1) (15 tasks)

**Story Goal**: Content moderators can select preset time ranges (3/7/14/30 days) and view hourly/daily comment density charts to identify attack patterns

**Independent Test Criteria**:
- ✅ User clicks "分析" button → navigates to analysis page with breadcrumb
- ✅ User selects "3 days" → chart displays 72 hourly data points
- ✅ User selects "7 days" → chart displays 168 hourly data points
- ✅ User selects "14 days" → chart displays 336 hourly data points (all hourly data)
- ✅ User selects "30 days" → chart displays 30 daily data points
- ✅ Chart renders with GMT+8 timestamps and comment counts

### Backend API Tests

- [ ] T011 [P] [US1] Write API contract test (RED) for GET /api/videos/{id}/comment-density
  - **File**: `tests/Feature/Api/CommentDensityApiTest.php`
  - **Tests**: Response schema matches OpenAPI spec (hourly_data + daily_data structure)
  - **Expected**: Test FAILS (endpoint not implemented)
  - **TDD**: RED phase

- [ ] T012 [P] [US1] Write API test (RED) for 200 OK response with valid video_id
  - **File**: `tests/Feature/Api/CommentDensityApiTest.php`
  - **Test**: GET /api/videos/{valid_id}/comment-density returns 200 with hourly_data and daily_data
  - **Expected**: Test FAILS (endpoint returns 404)
  - **TDD**: RED phase

- [ ] T013 [P] [US1] Write API test (RED) for 404 response with invalid video_id
  - **File**: `tests/Feature/Api/CommentDensityApiTest.php`
  - **Test**: GET /api/videos/invalid_id/comment-density returns 404
  - **Expected**: Test FAILS (endpoint doesn't exist)
  - **TDD**: RED phase

### Backend Implementation

- [ ] T014 [US1] Create VideoAnalysisController with getCommentDensityData method
  - **File**: `app/Http/Controllers/VideoAnalysisController.php`
  - **Method**: `getCommentDensityData(string $videoId): JsonResponse`
  - **Logic**: Validate video exists, call service, return JSON
  - **Validation**: API contract tests pass
  - **TDD**: GREEN phase

- [ ] T015 [US1] Add API route for comment density endpoint
  - **File**: `routes/api.php`
  - **Route**: `Route::get('/videos/{videoId}/comment-density', [VideoAnalysisController::class, 'getCommentDensityData'])`
  - **Validation**: Route exists in `php artisan route:list`
  - **TDD**: GREEN phase

- [ ] T016 [P] [US1] Implement error handling with technical details (FR-017)
  - **File**: `app/Http/Controllers/VideoAnalysisController.php`
  - **Logic**: Catch exceptions, return JSON with trace_id, error type, SQL (if DB error)
  - **Validation**: API test for 500 error passes
  - **TDD**: GREEN phase

- [ ] T017 [P] [US1] Add structured logging with trace IDs to service layer
  - **File**: `app/Services/CommentDensityAnalysisService.php`
  - **Logic**: Log start, completion, errors with unique trace_id (UUID)
  - **Validation**: Logs contain trace_id, query_time_ms, record_count
  - **Constitution**: Observable Systems principle

### Frontend - Analysis Page

- [ ] T018 [P] [US1] Write integration test (RED) for analysis page rendering
  - **File**: `tests/Feature/VideoAnalysisControllerTest.php`
  - **Test**: GET /videos/{id}/analysis renders page with breadcrumb
  - **Expected**: Test FAILS (page doesn't exist)
  - **TDD**: RED phase

- [ ] T019 [US1] Create analysis page controller method
  - **File**: `app/Http/Controllers/VideoAnalysisController.php`
  - **Method**: `showAnalysisPage(string $videoId): View`
  - **Logic**: Load video, pass to view
  - **Validation**: Integration test passes
  - **TDD**: GREEN phase

- [ ] T020 [US1] Add web route for analysis page
  - **File**: `routes/web.php`
  - **Route**: `Route::get('/videos/{video}/analysis', [VideoAnalysisController::class, 'showAnalysisPage'])->name('videos.analysis')`
  - **Validation**: Route accessible in browser
  - **TDD**: GREEN phase

- [ ] T021 [US1] Create analysis.blade.php with breadcrumb and skeleton
  - **File**: `resources/views/videos/analysis.blade.php`
  - **Elements**: Breadcrumb (首頁 > 影片列表 > 影片分析), skeleton chart, loading spinner
  - **Validation**: Page renders with "Loading chart data..." message
  - **Requirement**: FR-003, FR-018

- [ ] T022 [US1] Implement preset time range selector (4 radio buttons)
  - **File**: `resources/views/videos/analysis.blade.php`
  - **Options**: 3 days, 7 days, 14 days, 30 days
  - **Validation**: Radio buttons render and are selectable
  - **Requirement**: FR-004

- [ ] T023 [US1] Implement Chart.js initialization with optimized data fetching
  - **File**: `resources/views/videos/analysis.blade.php` (inline script or separate JS file)
  - **Logic**: Fetch `/api/videos/{id}/comment-density`, cache response, filter by range
  - **Validation**: Network tab shows 1 API call on page load
  - **Optimization**: Dual-dataset approach (Research Topic 0)

- [ ] T024 [US1] Implement client-side filtering for preset ranges
  - **File**: `resources/views/videos/analysis.blade.php` (JavaScript)
  - **Logic**: `filterDataByRange('3days')` → hourly_data.slice(0, 72), etc.
  - **Validation**: Switching ranges updates chart instantly (0ms, no API call)
  - **Optimization**: Client-side filtering

- [ ] T025 [US1] Configure Chart.js with Asia/Taipei timezone and GMT+8 labels
  - **File**: `resources/views/videos/analysis.blade.php` (JavaScript)
  - **Config**: `scales.x.time.timezone: 'Asia/Taipei'`, tooltip with "(GMT+8)"
  - **Validation**: Chart displays times with "(GMT+8)" label
  - **Requirement**: FR-016

### Frontend - Video List Button

- [ ] T026 [US1] Add analysisUrl() helper method to Video model
  - **File**: `app/Models/Video.php`
  - **Method**: `public function analysisUrl(): string { return route('videos.analysis', ['video' => $this->video_id]); }`
  - **Validation**: Method returns correct URL

- [ ] T027 [US1] Add "分析" button to video list page
  - **File**: `resources/views/videos/list.blade.php`
  - **Location**: Next to "更新" button in Actions column
  - **Link**: `<a href="{{ $video->analysisUrl() }}">分析</a>`
  - **Validation**: Button appears and navigates to analysis page
  - **Requirement**: FR-001, FR-002

---

## Phase 4: User Story 2 - Custom Date Range Analysis (P2) (8 tasks)

**Story Goal**: Analysts can select custom start/end dates to investigate specific time periods with automatic granularity determination

**Independent Test Criteria**:
- ✅ User selects "Custom date range" option → date pickers appear
- ✅ User enters range ≤14 days → chart displays hourly data (filtered from cached hourly_data)
- ✅ User enters range >14 days → chart displays daily data (filtered from cached daily_data)
- ✅ User enters invalid range (start > end) → validation error displays
- ✅ User enters start before video publication → system clamps to publication date with notification

**Dependencies**: Requires Phase 3 (US1) complete - uses same cached data and chart rendering

### Tests

- [ ] T028 [P] [US2] Write integration test (RED) for custom date range validation
  - **File**: `tests/Feature/VideoAnalysisControllerTest.php`
  - **Tests**: Start date > end date → error message, start before publication → clamped
  - **Expected**: Test FAILS (validation not implemented)
  - **TDD**: RED phase

- [ ] T029 [P] [US2] Write integration test (RED) for granularity determination
  - **File**: `tests/Feature/VideoAnalysisControllerTest.php`
  - **Tests**: Range ≤14 days uses hourly data, range >14 days uses daily data
  - **Expected**: Test FAILS (frontend filtering not implemented)
  - **TDD**: RED phase

### Implementation

- [ ] T030 [US2] Add custom date range UI components to analysis page
  - **File**: `resources/views/videos/analysis.blade.php`
  - **Elements**: "Custom date range" radio option, start date picker, end date picker
  - **Validation**: Date pickers render and accept input
  - **Requirement**: FR-010

- [ ] T031 [P] [US2] Implement client-side date range validation
  - **File**: `resources/views/videos/analysis.blade.php` (JavaScript)
  - **Logic**: Validate start < end, clamp to video publication date, clamp end to current date
  - **Validation**: Invalid inputs show error messages
  - **Requirement**: FR-014

- [ ] T032 [P] [US2] Implement custom range filtering logic with granularity determination
  - **File**: `resources/views/videos/analysis.blade.php` (JavaScript)
  - **Logic**: If daysDiff ≤14 → filter hourly_data, else → filter daily_data
  - **Validation**: Custom range charts render with correct granularity
  - **Requirement**: FR-011

- [ ] T033 [P] [US2] Display notification for clamped date ranges
  - **File**: `resources/views/videos/analysis.blade.php`
  - **Logic**: Show toast/alert when start/end dates are adjusted
  - **Message**: "Start date adjusted to video publication time" or "End date adjusted to current time"
  - **Validation**: Notification appears when dates are clamped
  - **Acceptance**: AS3 scenario 3, AS4 scenario 4

- [ ] T034 [US2] Update chart rendering to support custom filtered data
  - **File**: `resources/views/videos/analysis.blade.php` (JavaScript)
  - **Logic**: Extend `renderChart()` to handle custom timestamp-filtered datasets
  - **Validation**: Custom range charts display correctly filtered data
  - **Integration**: Reuses existing Chart.js instance from US1

- [ ] T035 [US2] Add total comment count display for selected range
  - **File**: `resources/views/videos/analysis.blade.php`
  - **Logic**: Sum `count` values from filtered data, display total
  - **Validation**: Total updates when range changes
  - **Requirement**: FR-012

---

## Phase 5: User Story 3 - Chart Interaction and Data Interpretation (P3) (6 tasks)

**Story Goal**: Users can hover over chart points to see exact timestamps and comment counts for detailed analysis

**Independent Test Criteria**:
- ✅ User hovers over data point → tooltip displays with exact timestamp (GMT+8) and count
- ✅ Chart X-axis displays time labels clearly
- ✅ Chart Y-axis scales automatically to show all data points
- ✅ Multiple data points are distinguishable on the chart

**Dependencies**: Requires Phase 3 (US1) complete - enhances existing chart

### Tests

- [ ] T036 [P] [US3] Write integration test (RED) for chart tooltip rendering
  - **File**: `tests/Feature/VideoAnalysisControllerTest.php`
  - **Test**: Simulate hover event → verify tooltip data structure
  - **Expected**: Test FAILS (tooltip config not implemented)
  - **TDD**: RED phase (browser automation test)

### Implementation

- [ ] T037 [P] [US3] Configure Chart.js tooltip plugin
  - **File**: `resources/views/videos/analysis.blade.php` (JavaScript)
  - **Config**: `plugins.tooltip.callbacks.title`, `callbacks.label` with timestamp + count
  - **Format**: "2025-11-19 15:00 (GMT+8) - 127 comments"
  - **Validation**: Tooltip shows formatted data
  - **Requirement**: FR-015, AS7 scenario 1

- [ ] T038 [P] [US3] Configure Chart.js axis labels
  - **File**: `resources/views/videos/analysis.blade.php` (JavaScript)
  - **Config**: `scales.x.title.text = 'Time (GMT+8)'`, `scales.y.title.text = 'Comment Count'`
  - **Validation**: Axis labels display correctly
  - **Requirement**: AS8 scenario 2

- [ ] T039 [P] [US3] Enable automatic Y-axis scaling
  - **File**: `resources/views/videos/analysis.blade.php` (JavaScript)
  - **Config**: `scales.y.beginAtZero: true`, `scales.y.ticks.autoSkip: true`
  - **Validation**: Y-axis scales to data range (0 to max comment count)
  - **Requirement**: AS9 scenario 3

- [ ] T040 [US3] Test chart rendering with extreme data ranges
  - **Test**: Video with 0 comments, video with 100k comments in one hour
  - **Validation**: Both render correctly, Y-axis scales appropriately
  - **Requirement**: SC-005, Edge case

- [ ] T041 [US3] Refactor Chart.js code for readability (REFACTOR)
  - **File**: Extract JavaScript to `resources/js/comment-density-chart.js` (optional)
  - **Action**: Extract chart logic from blade template to separate file
  - **Validation**: Chart functionality unchanged
  - **TDD**: REFACTOR phase - improve code quality

---

## Phase 6: Polish & Cross-Cutting Concerns (6 tasks)

**Goal**: Handle edge cases, loading states, and ensure production readiness

- [ ] T042 [P] Implement 3-second timeout detection with extended loading UI
  - **File**: `resources/views/videos/analysis.blade.php` (JavaScript)
  - **Logic**: `setTimeout(3000)` → update message to "Large dataset detected...", show cancel button
  - **Validation**: Loading message updates after 3 seconds
  - **Requirement**: FR-019, FR-020

- [ ] T043 [P] Implement cancel button functionality for long queries
  - **File**: `resources/views/videos/analysis.blade.php` (JavaScript)
  - **Logic**: `AbortController` to cancel fetch request, navigate back to video list
  - **Validation**: Cancel button aborts request and returns to video list
  - **Requirement**: FR-020

- [ ] T044 [P] Display "No comments found" message for empty datasets
  - **File**: `resources/views/videos/analysis.blade.php`
  - **Logic**: If total_comments === 0 → show message instead of empty chart
  - **Validation**: Message displays for videos with zero comments
  - **Edge case**: Scenario 1

- [ ] T045 [P] Add performance monitoring for slow queries
  - **File**: `app/Services/CommentDensityAnalysisService.php`
  - **Logic**: Log warning if query_time_ms > 3000
  - **Validation**: Slow query logs appear in Laravel logs
  - **Constitution**: Observable Systems principle

- [ ] T046 Run full test suite and verify all tests pass
  - **Command**: `php artisan test`
  - **Validation**: 0 failures, all contract tests + unit tests + integration tests pass
  - **Constitution**: Test-First Development principle

- [ ] T047 Manual QA following quickstart.md testing checklist
  - **Actions**: Test all user stories, edge cases, loading states, error states
  - **Reference**: `/specs/008-video-comment-density/quickstart.md` Phase 6 checklist
  - **Validation**: All manual test scenarios pass

---

## Parallel Execution Examples

### Phase 2: Foundational (Max Parallelism)
```bash
# Parallel execution group 1 (tests + service creation)
- T004 (unit tests) + T005 (service stub) can run in parallel

# Sequential: T006-T010 (service methods) - dependencies on each other
# But T008 (fillMissingBuckets) and T009 (convertToDataPoints) can run in parallel
# after T006 and T007 complete
```

### Phase 3: User Story 1 (Max Parallelism)
```bash
# Parallel execution group 1 (all RED phase tests)
- T011 + T012 + T013 (API tests) + T018 (page test) can run in parallel

# Parallel execution group 2 (backend + frontend foundation)
- T014-T016 (backend API) can run while T019-T021 (frontend page) are in progress

# Parallel execution group 3 (independent frontend features)
- T022 (range selector) + T023 (Chart.js init) + T026 (model method) can run in parallel

# Sequential: T024 depends on T023, T025 depends on T024, T027 depends on T026
```

### Phase 4: User Story 2 (Max Parallelism)
```bash
# Parallel execution group 1 (tests)
- T028 + T029 can run in parallel

# Parallel execution group 2 (UI components)
- T030 + T031 + T032 + T033 can run in parallel (different concerns)

# Sequential: T034 depends on T032, T035 depends on T034
```

### Phase 5: User Story 3 (Max Parallelism)
```bash
# Parallel execution group 1 (configuration tasks)
- T037 + T038 + T039 can run in parallel (different Chart.js configs)

# Sequential: T040 depends on T037-T039
```

---

## Dependencies Graph

```
Phase 1 (Setup)
  ├── T001 (DB index) ──┐
  ├── T002 (Chart.js) ──┼─→ Phase 2 (can start after any setup task)
  └── T003 (Models)   ──┘

Phase 2 (Foundational) [BLOCKING - must complete before US1]
  ├── T004 (service tests) → T005 (service stub) → T006 (hourly) ──┐
  │                                              → T007 (daily)   ──┼→ T010 (orchestration)
  │                                              → T008 (fill) ────┤
  │                                              → T009 (convert) ─┘
  └── T010 complete → Phase 3 can start

Phase 3 (US1) [Core MVP]
  ├── Backend Track: T011-T013 (tests) → T014-T016 (API) ──┐
  ├── Frontend Page: T018 (test) → T019-T021 (page) ───────┼─→ T023 (Chart init)
  │                                                         │     ↓
  │                                                         │   T024 (filtering)
  │                                                         │     ↓
  │                                                         │   T025 (timezone)
  └── Video List: T026 (model) → T027 (button) ────────────┘

  US1 Complete → US2 and US3 can start in parallel

Phase 4 (US2) [Depends on US1]
  ├── T028-T029 (tests) → T030 (UI) → T031 (validation)
  │                                 → T032 (filtering) → T034 (chart) → T035 (total)
  └──                               → T033 (notification)

Phase 5 (US3) [Depends on US1, independent of US2]
  ├── T036 (test) → T037-T039 (config) → T040 (test extremes) → T041 (refactor)

Phase 6 (Polish) [Depends on US1-US3]
  └── T042-T045 (parallel) → T046 (test suite) → T047 (manual QA)
```

---

## Success Metrics

- **Test Coverage**: 100% of service methods, 100% of API endpoints, 90% of frontend interactions
- **Performance**: API response <1.5 seconds for dual-dataset query (336 hourly + variable daily)
- **User Experience**: Range switching 0ms (instant client-side filtering)
- **Database Load**: 2 queries per video analysis (vs 5+ in traditional design)

---

## Notes

- **TDD Workflow**: All tasks follow RED → GREEN → REFACTOR cycle
- **Optimization**: Dual-dataset approach (Research Topic 0) enables instant range switching
- **Constitution Compliance**: Test-First Development, API-First Design, Observable Systems, Contract Testing, Semantic Versioning all satisfied
- **Incremental Delivery**: Each phase (US1, US2, US3) is independently deployable
- **Recommended MVP**: Complete through Phase 3 (US1 only) for initial production deployment
