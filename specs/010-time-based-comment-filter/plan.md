# Implementation Plan: Time-Based Comment Filtering from Chart

**Branch**: `010-time-based-comment-filter` | **Date**: 2025-11-20 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/010-time-based-comment-filter/spec.md`

## Summary

Enable analysts to click time points on the Comments Density chart to filter comments by hourly time periods. Supports multi-select (up to 20 points) for non-contiguous time ranges, with visual highlighting and combined pattern filtering. All selected time ranges are merged and displayed in the existing Commenter Pattern Summary panel with infinite scroll.

**Technical Approach**: Extend Chart.js onClick handler to capture selected timestamps, pass comma-separated ISO timestamps to backend API, backend converts UTC to GMT+8 and filters comments using multiple time range conditions (OR clauses), return paginated results. Frontend maintains selection state and highlights selected chart points.

## Technical Context

**Language/Version**: PHP 8.2 with Laravel Framework 12.0
**Primary Dependencies**: Laravel Framework, Chart.js 4.4.0 (existing), Tailwind CSS (via CDN)
**Storage**: MySQL/MariaDB with existing comments table (published_at in UTC)
**Testing**: PHPUnit (Laravel default test framework)
**Target Platform**: Web application (server-side rendering with Blade templates + AJAX)
**Project Type**: Web (existing Laravel MVC structure)
**Performance Goals**:
- Single time point selection: <2s response time
- Multiple selections (up to 20 points): <3s response time
- Infinite scroll batches: <1s per 100 comments
- Chart visual feedback: <200ms for highlighting

**Constraints**:
- Maximum 20 time point selections (hard limit to prevent query performance degradation)
- Hourly granularity (1-hour buckets fixed to match chart data points)
- UTC → GMT+8 conversion must happen in backend (Constitution VI)
- Query performance with multiple OR conditions (20 time ranges = 20 OR clauses)
- Selection state is client-side only (cleared on page refresh)

**Scale/Scope**:
- Expected: 100-10,000 comments per video
- Time range queries: Up to 20 non-contiguous hourly ranges per request
- UI components: 1 chart interaction handler + 1 filter state manager
- API endpoints: 1 endpoint extension (existing comments API)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Test-First Development ✅
- **Status**: PASS
- **Action**: Write PHPUnit tests for:
  - Time range filtering logic (single and multiple ranges)
  - API endpoint with time_points parameter
  - UTC to GMT+8 conversion in API responses
  - Infinite scroll with time filters
  - Edge cases (empty results, 20-point limit)

### II. API-First Design ✅
- **Status**: PASS
- **Action**: Define contract before implementation:
  - Extend GET `/api/videos/{videoId}/comments?time_points={ISO_TIMESTAMPS}&pattern={type}&offset={n}&limit={m}`
  - Accept comma-separated ISO 8601 timestamps (GMT+8 format from frontend)
  - Return JSON with same structure as existing comments API
  - Backend converts timestamps to UTC for database query

### III. Observable Systems ✅
- **Status**: PASS
- **Action**: Add structured logging:
  - Log time filter requests with video_id, time_points array, execution_time
  - Log timezone conversion operations (GMT+8 → UTC for query)
  - Include trace_id for debugging multi-range queries
  - Log query performance metrics (number of OR clauses, result count)

### IV. Contract Testing ✅
- **Status**: PASS
- **Action**: Create contract tests:
  - API request/response schema (time_points parameter format)
  - Backward compatibility with existing comments API (no breaking changes)
  - Timezone conversion contract (input GMT+8, query UTC, return GMT+8)
  - Integration with pattern filters (combined filtering)

### V. Semantic Versioning ✅
- **Status**: PASS
- **Action**: This is a MINOR version (new feature, backward-compatible)
  - Extends existing API with optional time_points parameter
  - No breaking changes to existing functionality
  - Version bump: 1.X.0 → 1.(X+1).0

### VI. Timezone Consistency ✅
- **Status**: PASS
- **Action**: Enforce timezone handling:
  - Database stores published_at in UTC (already confirmed)
  - Backend accepts time_points in GMT+8 (ISO format from frontend)
  - Backend converts GMT+8 → UTC for database WHERE clauses
  - Backend converts UTC → GMT+8 for API response timestamps
  - Frontend displays all times with explicit "(GMT+8)" indicators

**Overall Gate Status**: ✅ PASS - Proceed to Phase 0

## Project Structure

### Documentation (this feature)

```text
specs/010-time-based-comment-filter/
├── plan.md              # This file (✅ completed)
├── spec.md              # Feature specification (✅ existing)
├── research.md          # Phase 0 output (✅ completed)
├── data-model.md        # Phase 1 output (✅ completed)
├── quickstart.md        # Phase 1 output (✅ completed)
├── contracts/           # Phase 1 output (✅ completed)
│   └── time-filtered-comments-api.md
└── tasks.md             # Phase 2 output (⏳ NOT created yet - use /speckit.tasks)
```

### Source Code (repository root)

**Existing Laravel MVC Structure** (modified files marked with ⚠️, new files marked with ✨):

```text
app/
├── Http/
│   └── Controllers/
│       └── CommentPatternController.php      # ⚠️ MODIFY: Add time_points parameter validation
├── Models/
│   └── Comment.php                           # ⚠️ MODIFY: Add byTimeRanges() query scope
├── Services/
│   └── CommentPatternService.php             # ⚠️ MODIFY: Add time range filtering logic
└── ValueObjects/
    └── TimeRange.php                         # ✨ NEW: Time range value object

public/
└── js/
    └── time-filter.js                        # ✨ NEW: Chart interaction & state management

resources/
└── views/
    └── videos/
        └── analysis.blade.php                # ⚠️ MODIFY: Add time filter UI elements

routes/
└── api.php                                   # (No changes - uses existing route)

tests/
├── Unit/
│   ├── ValueObjects/
│   │   └── TimeRangeTest.php                 # ✨ NEW: TimeRange conversion tests
│   └── Services/
│       └── CommentPatternServiceTest.php     # ⚠️ MODIFY: Add time filter test cases
├── Feature/
│   └── Api/
│       └── TimeFilteredCommentsTest.php      # ✨ NEW: API contract tests
└── Integration/
    └── CombinedFilteringTest.php             # ✨ NEW: Pattern + time filter integration tests
```

**Structure Decision**: Extending existing Laravel MVC architecture from Feature 009. This feature adds minimal new code:
- 1 new value object (TimeRange.php)
- 1 new JavaScript file (time-filter.js)
- 3 new test files
- 4 modified existing files (controller, service, model, view)

All changes follow established patterns from Feature 009 (comments-pattern-summary) for consistency and maintainability.

---

## Files to Create/Modify Summary

### New Files (5 total):
1. `app/ValueObjects/TimeRange.php` - Time range value object with timezone conversion
2. `public/js/time-filter.js` - Chart click handler and FilterState class
3. `tests/Unit/ValueObjects/TimeRangeTest.php` - TimeRange unit tests
4. `tests/Feature/Api/TimeFilteredCommentsTest.php` - API contract tests
5. `tests/Integration/CombinedFilteringTest.php` - Combined filter integration tests

### Modified Files (4 total):
1. `app/Http/Controllers/CommentPatternController.php` - Add time_points validation
2. `app/Services/CommentPatternService.php` - Add time range filtering logic
3. `app/Models/Comment.php` - Add byTimeRanges() scope
4. `resources/views/videos/analysis.blade.php` - Integrate time filter UI

---

## Timezone Handling Architecture

⚠️ **CRITICAL - Constitution Principle VI Compliance**:

### Data Flow:
```
Frontend (GMT+8) → Backend (converts to UTC) → Database (UTC) → Backend (converts to GMT+8) → Frontend (displays)
```

### Implementation Points:
1. **Frontend sends**: `2025-11-20T14:00:00+08:00` (ISO 8601 with GMT+8 offset)
2. **Backend receives**: Parse with `Carbon::parse($iso, 'Asia/Taipei')`
3. **Backend queries**: Convert to UTC: `->setTimezone('UTC')` before WHERE clause
4. **Backend returns**: Convert back: `->setTimezone('Asia/Taipei')` for API response
5. **Frontend displays**: Pre-converted timestamps with "(GMT+8)" indicator

### Key Classes:
- `TimeRange::fromIsoTimestamp()` - Parses GMT+8 input
- `TimeRange::getUtcRange()` - Returns UTC range for database query
- `CommentPatternService` - Handles all timezone conversions

---

## Complexity Tracking

**No constitutional violations detected.**

This feature:
- ✅ Follows Test-First Development (tests written before implementation)
- ✅ Follows API-First Design (contract defined before code)
- ✅ Implements Observable Systems (structured logging for time filters)
- ✅ Implements Contract Testing (API contract tests required)
- ✅ Follows Semantic Versioning (MINOR version - backward compatible extension)
- ✅ Enforces Timezone Consistency (all conversions in backend per Constitution VI)

No complexity justification required.
