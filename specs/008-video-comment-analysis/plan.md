# Implementation Plan: Video Comment Density Analysis

**Branch**: `008-video-comment-analysis` | **Date**: 2025-11-19 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/008-video-comment-analysis/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Add video comment density analysis feature to identify coordinated attacks by visualizing comment patterns over time. Analysts can access analysis from video list, view video overview statistics (publish time, views, likes, comments), and examine comment density charts with multiple time ranges (3/7/14/30 days, custom). System caches YouTube API data for 24 hours to reduce API calls and uses existing YouTube API import functionality for authentication.

## Technical Context

**Language/Version**: PHP 8.2 with Laravel Framework 12.0
**Primary Dependencies**:
- Backend: Laravel 12.0, Google API Client 2.18 (existing YouTube API integration)
- Frontend: Tailwind CSS 4.1, Alpine.js 3.15, Vite 7.0
- Testing: PHPUnit 11.5

**Storage**: MySQL/MariaDB (existing database with videos and comments tables)
**Testing**: PHPUnit for backend unit/integration tests
**Target Platform**: Web application (server-side Laravel + client-side Alpine.js)
**Project Type**: Web application (MVC with Blade templates)
**Performance Goals**:
- Video overview load: <3 seconds
- Chart render: <5 seconds
- Chart interactions: <1 second response
- Handle up to 100,000 comments per video

**Constraints**:
- Must use existing YouTube API import functionality (authentication already configured)
- Database timestamps format: `Y-m-d H:i:s` (e.g., `2025-06-13 21:00:03`)
- All timestamps use Asia/Taipei timezone
- Cache validity: 24 hours for view/like data
- YouTube API rate limits handled by existing integration

**Scale/Scope**:
- Single video analysis page
- 5 preset time ranges + custom date picker
- Support concurrent access with cache locking
- Chart library: NEEDS CLARIFICATION (options: Chart.js, ApexCharts, or Laravel Charts)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Test-First Development ✅ PASS
- **Status**: Compliant
- **Plan**: Unit tests for chart data aggregation, cache logic, and controller actions. Integration tests for YouTube API caching and database queries. Feature tests for page navigation and chart rendering.

### II. API-First Design ✅ PASS
- **Status**: Compliant
- **Plan**: Define controller routes and JSON API endpoints for chart data before implementing views. Support both HTML (Blade) and JSON responses for chart data to enable future API consumers.

### III. Observable Systems ✅ PASS
- **Status**: Compliant
- **Plan**: Structured logging for API failures and cache refresh events including video ID, timestamp, and success/failure status (as per clarifications).

### IV. Contract Testing ✅ PASS
- **Status**: Compliant
- **Plan**: Contract tests for chart data API responses (hourly/daily aggregation), YouTube API integration (using existing patterns), and cache behavior contracts.

### V. Semantic Versioning ✅ PASS
- **Status**: Compliant (feature addition, no breaking changes)
- **Plan**: This is a new feature (MINOR version bump). Database schema addition (views, likes fields) with migration. No breaking changes to existing APIs.

**Overall Gate Status**: ✅ PASS - All constitutional principles satisfied

## Project Structure

### Documentation (this feature)

```text
specs/008-video-comment-analysis/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
│   ├── routes.md        # Laravel route definitions
│   └── api-responses.md # JSON response schemas for chart data
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
# Laravel MVC Web Application Structure

app/
├── Http/
│   └── Controllers/
│       ├── VideoAnalysisController.php    # NEW - Main analysis page controller
│       └── Api/
│           └── VideoChartDataController.php # NEW - Chart data API endpoint
├── Models/
│   └── Video.php                          # MODIFIED - Add views/likes cache accessors
├── Services/
│   ├── YouTubeApiService.php             # EXISTING - Reuse for cache refresh
│   └── CommentDensityService.php         # NEW - Chart data aggregation logic
└── Console/
    └── Commands/
        └── RefreshVideoStatsCommand.php  # NEW - Optional: CLI for batch cache refresh

database/
└── migrations/
    └── 2025_11_19_add_views_likes_to_videos_table.php  # NEW - Cache fields

resources/
├── views/
│   ├── videos/
│   │   ├── analysis.blade.php            # NEW - Main analysis page
│   │   └── list.blade.php                # MODIFIED - Add "分析" button
│   └── layouts/
│       └── app.blade.php                 # EXISTING - Already has breadcrumb support
└── js/
    └── video-analysis.js                 # NEW - Alpine.js chart component

routes/
└── web.php                               # MODIFIED - Add analysis routes

tests/
├── Feature/
│   ├── VideoAnalysisPageTest.php         # NEW - Page access and navigation
│   └── CommentDensityChartTest.php       # NEW - Chart data correctness
├── Unit/
│   ├── CommentDensityServiceTest.php     # NEW - Aggregation logic
│   └── VideoCacheTest.php                # NEW - Cache refresh logic
└── Integration/
    └── YouTubeApiCacheIntegrationTest.php # NEW - API + cache behavior
```

**Structure Decision**: This is a Laravel web application following MVC pattern. The feature adds a new controller for video analysis, a service layer for chart data aggregation, and Blade views with Alpine.js for interactivity. Database migration adds cache fields. Existing YouTube API integration will be reused for data fetching.

## Complexity Tracking

No constitutional violations. All principles satisfied by the proposed design.

## Files to Create (NEW)

**Backend:**
1. `app/Http/Controllers/VideoAnalysisController.php` - Analysis page controller
2. `app/Http/Controllers/Api/VideoChartDataController.php` - Chart data API
3. `app/Services/CommentDensityService.php` - Chart aggregation logic
4. `database/migrations/2025_11_19_add_views_likes_to_videos_table.php` - Database schema
5. `app/Console/Commands/RefreshVideoStatsCommand.php` - Optional CLI command

**Frontend:**
6. `resources/views/videos/analysis.blade.php` - Analysis page view
7. `resources/js/video-analysis.js` - Alpine.js chart component

**Tests:**
8. `tests/Feature/VideoAnalysisPageTest.php` - Page access tests
9. `tests/Feature/CommentDensityChartTest.php` - Chart data tests
10. `tests/Unit/CommentDensityServiceTest.php` - Service logic tests
11. `tests/Unit/VideoCacheTest.php` - Cache logic tests
12. `tests/Integration/YouTubeApiCacheIntegrationTest.php` - API integration tests

## Files to Modify (MODIFIED)

**Backend:**
1. `app/Models/Video.php` - Add views/likes accessors and cache methods
2. `routes/web.php` - Add analysis routes

**Frontend:**
3. `resources/views/videos/list.blade.php` - Add "分析" button next to "更新" button

## Phase 0: Research (will be generated)

Research tasks identified:
1. Chart library selection (Chart.js vs ApexCharts vs Laravel Charts)
2. Database locking strategy for cache refresh (Laravel's `lockForUpdate()` vs Redis locks)
3. Date aggregation query optimization for large datasets (100k comments)
4. Alpine.js chart integration patterns
5. Best practices for structured logging in Laravel

## Phase 1: Design & Contracts (will be generated)

Artifacts to generate:
1. `data-model.md` - Video cache schema, comment aggregation data structure
2. `contracts/routes.md` - Laravel route definitions
3. `contracts/api-responses.md` - Chart data JSON schemas
4. `quickstart.md` - Developer setup and testing guide
