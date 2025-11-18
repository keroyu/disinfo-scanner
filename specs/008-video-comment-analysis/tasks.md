# Tasks: Video Comment Density Analysis

**Input**: Design documents from `/specs/008-video-comment-analysis/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: Following TDD approach as per project constitution - tests written before implementation

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `- [ ] [ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and database schema

- [ ] T001 Run database migration to add views and likes columns to videos table using `database/migrations/2025_11_19_add_views_likes_to_videos_table.php`
- [ ] T002 [P] Add Chart.js CDN script tag to `resources/views/layouts/app.blade.php` head section
- [ ] T003 [P] Configure API logging channel in `config/logging.php` for structured JSON logs

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core models and services that ALL user stories depend on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T004 Add `isCacheStale()` and `getCacheAgeAttribute()` methods to `app/Models/Video.php` for cache validation
- [ ] T005 Create `app/Services/CommentDensityService.php` with method signatures for date range calculation and granularity selection
- [ ] T006 Implement YouTube API cache refresh logic in `app/Services/CommentDensityService.php` using existing `YouTubeApiService` with `lockForUpdate()` locking
- [ ] T007 Add structured logging for API cache refresh events in `CommentDensityService` using Log::channel('api')
- [ ] T008 Configure timezone to Asia/Taipei in `config/app.php` if not already set

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Access Video Analysis from Video List (Priority: P1) 🎯 MVP

**Goal**: Enable analysts to navigate from video list to analysis page with breadcrumb

**Independent Test**: Click "分析" button on any video → verify navigation to analysis page with breadcrumb "首頁 > 影片列表 > 影片分析"

### Tests (TDD - Write First)

- [ ] T009 [US1] Write feature test in `tests/Feature/VideoAnalysisPageTest.php` for analysis page accessibility
- [ ] T010 [US1] Write feature test for breadcrumb navigation display and links
- [ ] T011 [US1] Write feature test for "分析" button presence and hover state

### Implementation

- [ ] T012 [US1] Create `app/Http/Controllers/VideoAnalysisController.php` with `show(Video $video)` method
- [ ] T013 [US1] Add route `GET /videos/{video}/analysis` to `routes/web.php` named `videos.analysis`
- [ ] T014 [US1] Create basic `resources/views/videos/analysis.blade.php` extending `layouts/app.blade.php` with breadcrumb array
- [ ] T015 [US1] Add "分析" button next to "更新" button in `resources/views/videos/list.blade.php` linking to `videos.analysis` route
- [ ] T016 [US1] Add hover styles for "分析" button in Tailwind CSS

### Verification

- [ ] T017 [US1] Run feature tests to verify navigation and breadcrumb functionality
- [ ] T018 [US1] Manual test: click "分析" button and verify page loads with correct breadcrumb

---

## Phase 4: User Story 2 - View Video Overview Statistics (Priority: P1)

**Goal**: Display video statistics (publish time, views, likes, comments) with cache management

**Independent Test**: Navigate to analysis page → verify overview shows 4 metrics with proper formatting and cache indicator

### Tests (TDD - Write First)

- [ ] T019 [US2] Write unit test in `tests/Unit/VideoCacheTest.php` for `isCacheStale()` method with various timestamps
- [ ] T020 [US2] Write unit test for cache refresh logic with YouTube API mock
- [ ] T021 [US2] Write integration test in `tests/Integration/YouTubeApiCacheIntegrationTest.php` for cache lifecycle (fresh → stale → refresh)
- [ ] T022 [US2] Write feature test for overview section displaying all 4 metrics with proper formatting

### Implementation

- [ ] T023 [US2] Implement `refreshFromYouTubeApi()` method in `app/Models/Video.php` to fetch and update views/likes
- [ ] T024 [US2] Add number formatting helper for large numbers (commas) in Video model
- [ ] T025 [US2] Update `VideoAnalysisController@show` to check cache staleness and trigger refresh if needed
- [ ] T026 [US2] Create overview section in `resources/views/videos/analysis.blade.php` displaying 4 metrics
- [ ] T027 [US2] Add cache age indicator UI component showing data freshness
- [ ] T028 [US2] Implement error handling for YouTube API failures showing cached data with warning

### Verification

- [ ] T029 [US2] Run unit tests to verify cache logic correctness
- [ ] T030 [US2] Run integration test to verify full cache lifecycle
- [ ] T031 [US2] Manual test: verify overview loads <3 seconds with proper formatting

---

## Phase 5: User Story 3 - Analyze Comment Density with Preset Time Ranges (Priority: P1)

**Goal**: Display comment density line chart with 4 preset time ranges (3/7/14/30 days)

**Independent Test**: Select each preset time range → verify chart displays with correct granularity (hourly ≤7 days, daily >7 days) and data points

### Tests (TDD - Write First)

- [ ] T032 [US3] Write unit test in `tests/Unit/CommentDensityServiceTest.php` for hourly aggregation query (3/7 days)
- [ ] T033 [US3] Write unit test for daily aggregation query (14/30 days)
- [ ] T034 [US3] Write unit test for granularity selection logic based on time range
- [ ] T035 [US3] Write feature test in `tests/Feature/CommentDensityChartTest.php` for chart data API response structure
- [ ] T036 [US3] Write feature test for chart data correctness (labels-values array equality, total comments sum)

### Implementation

- [ ] T037 [US3] Implement `getChartData(int $videoId, string $range)` in `app/Services/CommentDensityService.php`
- [ ] T038 [US3] Implement hourly aggregation SQL query with `DATE_FORMAT` and Asia/Taipei timezone conversion
- [ ] T039 [US3] Implement daily aggregation SQL query with `DATE` function
- [ ] T040 [US3] Create `app/Http/Controllers/Api/VideoChartDataController.php` with `index` method
- [ ] T041 [US3] Create `app/Http/Requests/ChartDataRequest.php` for validating range parameter
- [ ] T042 [US3] Add API route `GET /api/videos/{video}/chart-data` to `routes/web.php`
- [ ] T043 [US3] Create `resources/js/video-analysis.js` Alpine.js component with Chart.js initialization
- [ ] T044 [US3] Add chart canvas and time range selector buttons to `resources/views/videos/analysis.blade.php`
- [ ] T045 [US3] Implement chart data loading with fetch API in Alpine.js component
- [ ] T046 [US3] Add skeleton chart UI with loading spinner overlay using Tailwind CSS
- [ ] T047 [US3] Implement Chart.js update logic when time range changes
- [ ] T048 [US3] Add tooltip configuration for hover interactions showing exact values

### Verification

- [ ] T049 [US3] Run unit tests to verify aggregation query correctness
- [ ] T050 [US3] Run feature tests to verify API response format and data accuracy
- [ ] T051 [US3] Manual test: select each time range (3/7/14/30 days) and verify chart renders <5 seconds with correct granularity
- [ ] T052 [US3] Performance test: verify chart handles 100k comments without degradation

---

## Phase 6: User Story 4 - Analyze Comment Density with Custom Date Range (Priority: P2)

**Goal**: Allow custom date selection with automatic granularity (hourly ≤7 days, daily >7 days)

**Independent Test**: Select custom date range → verify chart displays with appropriate granularity and validation works

### Tests (TDD - Write First)

- [ ] T053 [US4] Write unit test for custom date range parsing and validation
- [ ] T054 [US4] Write unit test for granularity selection based on custom range length
- [ ] T055 [US4] Write feature test for custom range validation errors (end before start)
- [ ] T056 [US4] Write feature test for custom range chart data with 5-day range (hourly) and 10-day range (daily)

### Implementation

- [ ] T057 [US4] Add `start_date` and `end_date` validation rules to `app/Http/Requests/ChartDataRequest.php`
- [ ] T058 [US4] Implement custom date range handling in `CommentDensityService@getChartData`
- [ ] T059 [US4] Add date picker UI to `resources/views/videos/analysis.blade.php` for custom range selection
- [ ] T060 [US4] Implement date validation in Alpine.js component (end date after start date)
- [ ] T061 [US4] Add "自由選擇日期範圍" button and date input fields
- [ ] T062 [US4] Handle validation error display in UI with Tailwind error styles

### Verification

- [ ] T063 [US4] Run unit tests to verify custom range logic
- [ ] T064 [US4] Run feature tests to verify validation and correct granularity
- [ ] T065 [US4] Manual test: try various custom ranges (5 days, 7 days, 10 days) and verify granularity switching

---

## Phase 7: User Story 5 - Access Repeat Commenter Summary (Priority: P3)

**Goal**: Display count of repeat commenters with placeholder link for future list view

**Independent Test**: Load analysis page → verify "重複留言者有 X 個" displays with correct count and clickable "查看列表" link

### Tests (TDD - Write First)

- [ ] T066 [P] [US5] Write unit test for repeat commenter counting logic (users with 2+ comments)
- [ ] T067 [P] [US5] Write feature test for repeat commenter count display

### Implementation

- [ ] T068 [US5] Implement `countRepeatCommenters()` method in `app/Models/Video.php` or `CommentDensityService`
- [ ] T069 [US5] Add repeat commenter count to `VideoAnalysisController@show` view data
- [ ] T070 [US5] Create UI section in `resources/views/videos/analysis.blade.php` for "重複留言者有 X 個（查看列表）"
- [ ] T071 [US5] Add placeholder link/button for "查看列表" (functional but destination TBD)

### Verification

- [ ] T072 [US5] Run tests to verify count accuracy
- [ ] T073 [US5] Manual test: verify count displays correctly including zero case

---

## Phase 8: User Story 6 - Access High-Aggression Commenter Summary (Priority: P3)

**Goal**: Display count of high-aggression commenters with placeholder link

**Independent Test**: Load analysis page → verify "高攻擊性留言者有 X 個" displays with clickable "查看列表" link

### Tests (TDD - Write First)

- [ ] T074 [P] [US6] Write unit test for high-aggression commenter placeholder count (returns 0 initially)
- [ ] T075 [P] [US6] Write feature test for high-aggression count display

### Implementation

- [ ] T076 [US6] Implement `countHighAggressionCommenters()` placeholder method returning 0 in `app/Models/Video.php`
- [ ] T077 [US6] Add high-aggression count to `VideoAnalysisController@show` view data
- [ ] T078 [US6] Create UI section in `resources/views/videos/analysis.blade.php` for "高攻擊性留言者有 X 個（查看列表）"
- [ ] T079 [US6] Add placeholder link/button for "查看列表"

### Verification

- [ ] T080 [US6] Run tests to verify placeholder functionality
- [ ] T081 [US6] Manual test: verify "0 個" displays correctly with functional link

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Final improvements, optimization, and documentation

- [ ] T082 [P] Add database index `idx_video_created` on `comments(video_id, created_at)` if not exists
- [ ] T083 [P] Add database index `idx_videos_updated` on `videos(updated_at)` if not exists
- [ ] T084 [P] Create optional `app/Console/Commands/RefreshVideoStatsCommand.php` for batch cache refresh CLI
- [ ] T085 [P] Add API response caching headers (Cache-Control: private, max-age=300) in VideoChartDataController
- [ ] T086 [P] Implement rate limiting for API routes (60 requests/minute per IP)
- [ ] T087 [P] Add Chart.js decimation plugin configuration for handling 100k+ data points
- [ ] T088 [P] Create error boundary UI for chart rendering failures
- [ ] T089 [P] Add loading state improvements (progressive rendering for large datasets)
- [ ] T090 Review all files for timestamp format consistency (`Y-m-d H:i:s` format as specified)
- [ ] T091 Run full test suite (`php artisan test`) and verify all tests pass
- [ ] T092 Run Laravel Pint for code style formatting (`./vendor/bin/pint`)
- [ ] T093 Build frontend assets for production (`npm run build`)
- [ ] T094 Performance test: verify page loads and chart renders meet targets (<3s overview, <5s chart)
- [ ] T095 Update documentation in quickstart.md with any implementation notes

---

## Dependencies & Execution Order

### User Story Completion Order

```
Phase 1: Setup (T001-T003)
    ↓
Phase 2: Foundational (T004-T008) ⚠️ BLOCKING
    ↓
    ├─→ Phase 3: US1 (T009-T018) 🎯 MVP - Navigation
    │       ↓
    ├─→ Phase 4: US2 (T019-T031) - Video Overview (depends on US1)
    │       ↓
    ├─→ Phase 5: US3 (T032-T052) - Chart with Preset Ranges (depends on US1+US2)
    │       ↓
    ├─→ Phase 6: US4 (T053-T065) - Custom Date Range (depends on US3)
    │
    ├─→ Phase 7: US5 (T066-T073) - Repeat Commenters (depends on US1, independent of charts)
    │
    └─→ Phase 8: US6 (T074-T081) - High-Aggression Commenters (depends on US1, independent of others)
        ↓
    Phase 9: Polish (T082-T095) - After all user stories complete
```

### Critical Path (Minimum for MVP)
1. Setup → Foundational → US1 → US2 → US3
2. Estimated: ~20-25 tasks for basic working feature

### Parallel Opportunities

**After Foundational Phase Complete**:
- US1 (Navigation) can start immediately
- After US1 done:
  - US2 (Overview) + US5 (Repeat Count) + US6 (Aggression Count) can run in parallel
  - US3 (Charts) depends on US2
  - US4 (Custom Range) depends on US3

**Within Each Phase**:
- Tests can be written in parallel (marked with [P])
- Polish tasks (T082-T089) can run in parallel

---

## Implementation Strategy

### MVP Scope (Recommended First Delivery)
**User Stories**: US1 + US2 + US3 (Navigation + Overview + Preset Charts)
**Tasks**: T001-T052 (~52 tasks)
**Delivers**: Core analysis feature - analysts can navigate to page, see video stats, and visualize comment density with 4 preset time ranges

### Iteration 2 (Enhanced Functionality)
**User Stories**: US4 (Custom Date Range)
**Tasks**: T053-T065
**Delivers**: Flexible date selection for detailed investigation

### Iteration 3 (Attack Detection UI)
**User Stories**: US5 + US6 (Repeat/Aggression Commenters)
**Tasks**: T066-T081
**Delivers**: UI placeholders for future attack detection features

### Final Polish
**Phase**: Polish & Optimization
**Tasks**: T082-T095
**Delivers**: Production-ready performance and code quality

---

## Testing Checklist

### Unit Tests
- [ ] Video cache staleness logic
- [ ] Cache refresh with API mock
- [ ] Comment density aggregation (hourly/daily)
- [ ] Granularity selection logic
- [ ] Date range validation
- [ ] Repeat commenter counting
- Total: ~12 unit test files

### Integration Tests
- [ ] YouTube API cache lifecycle
- Total: ~1 integration test file

### Feature Tests
- [ ] Page navigation and breadcrumb
- [ ] Video overview display
- [ ] Chart data API responses
- [ ] Chart data accuracy
- [ ] Custom range validation
- [ ] Repeat/aggression count display
- Total: ~8 feature test files

### Manual Tests
- [ ] Click "分析" button → page loads
- [ ] Breadcrumb links work
- [ ] Overview displays 4 metrics <3s
- [ ] Chart renders for each time range <5s
- [ ] Chart interactions respond <1s
- [ ] Custom date picker works
- [ ] Validation errors display correctly
- [ ] Commenter counts show correctly

---

## Performance Targets

| Metric | Target | Task Reference |
|--------|--------|----------------|
| Overview load time | < 3 seconds | T031 |
| Chart render time | < 5 seconds | T051 |
| Chart interaction response | < 1 second | T051 |
| Max comments handled | 100,000 | T052 |
| API response time | < 1 second | T035 |

---

## File Summary

### New Files (15 total)
- `database/migrations/2025_11_19_add_views_likes_to_videos_table.php`
- `app/Services/CommentDensityService.php`
- `app/Http/Controllers/VideoAnalysisController.php`
- `app/Http/Controllers/Api/VideoChartDataController.php`
- `app/Http/Requests/ChartDataRequest.php`
- `resources/views/videos/analysis.blade.php`
- `resources/js/video-analysis.js`
- `app/Console/Commands/RefreshVideoStatsCommand.php` (optional)
- `tests/Unit/VideoCacheTest.php`
- `tests/Unit/CommentDensityServiceTest.php`
- `tests/Integration/YouTubeApiCacheIntegrationTest.php`
- `tests/Feature/VideoAnalysisPageTest.php`
- `tests/Feature/CommentDensityChartTest.php`

### Modified Files (4 total)
- `app/Models/Video.php`
- `routes/web.php`
- `resources/views/videos/list.blade.php`
- `config/logging.php`
- `config/app.php`

---

**Total Tasks**: 95
**MVP Tasks**: 52 (T001-T052)
**Test Tasks**: 27 (marked with test references)
**Parallel Tasks**: 15 (marked with [P])
**Estimated MVP Time**: 3-4 days for experienced Laravel developer
**Full Feature Time**: 5-7 days including all user stories and polish
