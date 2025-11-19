# Implementation Plan: Video Comment Density Analysis

**Branch**: `008-video-comment-density` | **Date**: 2025-11-19 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/008-video-comment-density/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Add comment density analysis feature to visualize comment patterns over time for detecting coordinated attacks. Users can access a dedicated analysis page from video list, select preset time ranges (3/7/14/30 days) or custom ranges, and view interactive line charts showing hourly or daily comment counts. The feature helps content moderators identify abnormal comment spikes through visual pattern recognition.

## Technical Context

**Language/Version**: PHP 8.2 with Laravel Framework 12.0
**Primary Dependencies**: Chart.js (client-side visualization), Tailwind CSS (existing UI framework), Carbon (datetime handling)
**Storage**: MySQL/MariaDB (existing database with videos and comments tables)
**Testing**: PHPUnit (Laravel's default testing framework)
**Target Platform**: Web application (server-side rendering with Blade templates, client-side Chart.js)
**Project Type**: Web application (Laravel MVC pattern)
**Performance Goals**: Chart data aggregation <3 seconds for typical datasets; extended loading with feedback for large datasets (100,000+ comments)
**Constraints**: All timestamps in Asia/Taipei timezone (GMT+8); display technical errors for debugging; visual-only pattern detection (no automated thresholds)
**Scale/Scope**: Support videos with 0 to 100,000+ comments; concurrent multi-user access without performance degradation

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Test-First Development ✅
- **Status**: PASS
- **Evidence**: Plan includes PHPUnit contract tests for API endpoints (data aggregation) and integration tests for chart rendering before implementation
- **Approach**: Red-Green-Refactor cycle will be applied to:
  - API endpoint returning aggregated comment density data
  - Data transformation logic (hourly/daily bucketing)
  - Timezone conversion (UTC to Asia/Taipei)
  - Edge case handling (empty datasets, invalid ranges)

### II. API-First Design ✅
- **Status**: PASS
- **Evidence**: New API endpoint `/api/videos/{video_id}/comment-density` will be defined with OpenAPI contract before UI implementation
- **Contract**: Endpoint accepts time range parameters, returns JSON with timestamp-count pairs
- **Error Handling**: Structured error responses with technical details (per clarification decision)

### III. Observable Systems ✅
- **Status**: PASS
- **Evidence**:
  - Database query failures will display technical error details to users (per FR-017)
  - Structured logging for slow queries (>3 seconds) with trace IDs
  - Performance metrics logged: query time, record count, aggregation method
- **Traceability**: Each analysis request generates unique trace ID for debugging

### IV. Contract Testing ✅
- **Status**: PASS
- **Evidence**:
  - API contract tests verify response schema (timestamps, counts, metadata)
  - Service boundary tests validate Comment model query scopes
  - Frontend-backend contract: JSON format, timezone handling, error codes
- **Shared Schema**: Comment density data structure will have contract tests

### V. Semantic Versioning ✅
- **Status**: PASS
- **Evidence**: This is a MINOR version increment (new functionality, backward-compatible)
- **Versioning**: Existing video list API unchanged; new endpoint added
- **Migration**: No database schema changes required (uses existing videos/comments tables)

### Summary
**All constitution principles satisfied.** No violations to justify. This feature introduces a new analysis capability without breaking existing functionality, follows TDD with contract-first API design, and maintains observability standards.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Http/
│   └── Controllers/
│       └── VideoAnalysisController.php              # NEW - Handles analysis page and chart data
├── Services/
│   └── CommentDensityAnalysisService.php            # NEW - Business logic for data aggregation
└── Models/
    ├── Video.php                                    # MODIFIED - Add analysisUrl() helper method
    └── Comment.php                                  # EXISTING - Already has timestamp support

routes/
├── web.php                                          # MODIFIED - Add analysis page route
└── api.php                                          # MODIFIED - Add comment-density API endpoint

resources/
├── views/
│   └── videos/
│       ├── list.blade.php                           # MODIFIED - Add "分析" button next to "更新"
│       └── analysis.blade.php                       # NEW - Comment density analysis page
└── js/
    └── comment-density-chart.js                     # NEW - Chart.js visualization logic (optional)

tests/
├── Feature/
│   ├── VideoAnalysisControllerTest.php              # NEW - Integration tests for analysis page
│   └── Api/
│       └── CommentDensityApiTest.php                # NEW - API endpoint tests
└── Unit/
    └── Services/
        └── CommentDensityAnalysisServiceTest.php    # NEW - Unit tests for aggregation logic
```

**Structure Decision**: Laravel MVC web application structure. This feature adds a new controller (`VideoAnalysisController`) for the analysis page, a service layer (`CommentDensityAnalysisService`) for complex aggregation logic, new Blade views, and corresponding tests. The existing `Video` and `Comment` models are reused with minimal modifications. Frontend uses server-side rendering (Blade) with Chart.js loaded via CDN for client-side visualization.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No violations. All constitution principles are satisfied.

---

## Implementation Phases Status

### Phase 0: Outline & Research ✅ COMPLETED

**Generated artifacts**:
- [research.md](./research.md) - Technical decisions and best practices research

**Key decisions documented**:
1. Chart.js v4.x with time scale adapter for visualization
2. MySQL DATE_FORMAT() for efficient SQL aggregation with indexed queries
3. Laravel service pattern for business logic separation
4. Progressive loading with timeout detection for large datasets
5. UTC storage with GMT+8 presentation layer conversion

**All NEEDS CLARIFICATION items resolved**: Yes

---

### Phase 1: Design & Contracts ✅ COMPLETED

**Generated artifacts**:
- [data-model.md](./data-model.md) - Data structures, transformations, and relationships
- [contracts/comment-density-api.yaml](./contracts/comment-density-api.yaml) - OpenAPI 3.0 specification for API endpoint
- [quickstart.md](./quickstart.md) - Developer onboarding guide with implementation checklist

**Constitution re-evaluation**: All principles remain satisfied post-design

**Files identified for creation/modification**:

**New files** (11 total):
1. `app/Http/Controllers/VideoAnalysisController.php`
2. `app/Services/CommentDensityAnalysisService.php`
3. `resources/views/videos/analysis.blade.php`
4. `tests/Feature/VideoAnalysisControllerTest.php`
5. `tests/Feature/Api/CommentDensityApiTest.php`
6. `tests/Unit/Services/CommentDensityAnalysisServiceTest.php`

**Modified files** (3 total):
1. `app/Models/Video.php` - Add `analysisUrl()` helper method
2. `routes/web.php` - Add analysis page route
3. `routes/api.php` - Add comment-density API route
4. `resources/views/videos/list.blade.php` - Add "分析" button

**Total implementation scope**: 6 new files + 4 modified files = 10 file changes

---

### Phase 2: Task Generation (Next Step)

**Command**: Run `/speckit.tasks` to generate dependency-ordered implementation tasks in `tasks.md`

**Expected output**: Breakdown of implementation steps following TDD workflow (RED → GREEN → REFACTOR) with dependencies, acceptance criteria, and file-level granularity.

---

## Quick Links

- **Specification**: [spec.md](./spec.md)
- **Research**: [research.md](./research.md)
- **Data Model**: [data-model.md](./data-model.md)
- **API Contract**: [contracts/comment-density-api.yaml](./contracts/comment-density-api.yaml)
- **Quickstart Guide**: [quickstart.md](./quickstart.md)
- **Constitution**: [../../.specify/memory/constitution.md](../../.specify/memory/constitution.md)
