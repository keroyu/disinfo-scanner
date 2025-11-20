# Implementation Plan: Comments Pattern Summary

**Branch**: `009-comments-pattern-summary` | **Date**: 2025-11-20 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/009-comments-pattern-summary/spec.md`

## Summary

Add comments pattern analysis feature to Video Analysis page showing:
- Default view: All comments (newest to oldest, 100 per batch, infinite scroll)
- Repeat commenters (2+ comments on same video)
- Night-time high-frequency commenters (>50% comments during 01:00-05:59 GMT+8 across all channels)
- Placeholders for aggressive/simplified Chinese commenters
- Left-side filter list with visual highlighting
- Right-side always-visible comment panel using existing commentModal layout
- All timestamps converted from UTC (database) to GMT+8 (display)

**Technical Approach**: Extend existing Laravel service layer pattern, add new API endpoints for pattern statistics and paginated comment fetching, implement infinite scroll with JavaScript, reuse existing Tailwind CSS comment layout.

## Technical Context

**Language/Version**: PHP 8.2 with Laravel 12.38.1
**Primary Dependencies**: Laravel Framework, Tailwind CSS (via CDN), Font Awesome 6.4.0, Chart.js 4.4.0
**Storage**: MySQL/MariaDB with existing comments, videos, authors tables (UTC timezone)
**Testing**: PHPUnit (Laravel default test framework)
**Target Platform**: Web application (server-side rendering with Blade templates + AJAX)
**Project Type**: Web (existing Laravel MVC structure)
**Performance Goals**:
- Initial pattern statistics load: <2s
- Comment list load (100 records): <2s
- Infinite scroll batch (100 records): <1s
- 100% accuracy for statistics calculations

**Constraints**:
- Database timestamps in UTC, must convert to GMT+8 for all display/filtering
- Night-time calculation requires cross-channel query (potentially expensive)
- Infinite scroll requires custom implementation (Laravel has standard pagination only)
- Right panel layout must match existing commentModal styling
- Always-visible right panel (no close mechanism)

**Scale/Scope**:
- Expected: Thousands of comments per video
- Night-time calculation: Millions of comments across all channels
- Pattern filters: 5 total (all comments + 4 patterns)
- UI components: 1 left panel (filter list) + 1 right panel (comment display)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Test-First Development ✅
- **Status**: PASS
- **Action**: Write PHPUnit tests for:
  - Pattern statistics calculation (repeat commenters, night-time frequency)
  - API endpoints (pattern stats, paginated comments)
  - Timezone conversion utilities
  - Edge cases (zero comments, no matches)

### II. API-First Design ✅
- **Status**: PASS
- **Action**: Define contracts before implementation:
  - GET `/api/videos/{videoId}/pattern-statistics` - returns all pattern counts/percentages
  - GET `/api/videos/{videoId}/comments?pattern={type}&offset={n}&limit={m}` - paginated comments
  - Both endpoints return JSON with structured error responses

### III. Observable Systems ✅
- **Status**: PASS
- **Action**: Add structured logging:
  - Log pattern calculation queries with video_id, pattern_type, execution_time
  - Log pagination requests with offset, limit, returned_count
  - Include trace_id for debugging cross-channel queries
  - Log timezone conversion operations for audit

### IV. Contract Testing ✅
- **Status**: PASS
- **Action**: Create contract tests:
  - API response schemas (statistics structure, comment list structure)
  - Service layer contracts (CommentPatternService interface)
  - Database query contracts (expected columns, timezone handling)

### V. Semantic Versioning ✅
- **Status**: PASS
- **Action**: This is a MINOR version (new feature, backward-compatible)
  - No breaking changes to existing APIs
  - Adds new endpoints without modifying existing ones
  - Version bump: 1.X.0 → 1.(X+1).0

**Overall Gate Status**: ✅ PASS - Proceed to Phase 0

## Project Structure

### Documentation (this feature)

```text
specs/009-comments-pattern-summary/
├── plan.md              # This file (/speckit.plan command output)
├── spec.md              # Feature specification (already exists)
├── research.md          # Phase 0 output (infinite scroll, caching strategy)
├── data-model.md        # Phase 1 output (pattern statistics, comment filters)
├── quickstart.md        # Phase 1 output (developer setup guide)
├── contracts/           # Phase 1 output (API endpoint specs)
│   ├── pattern-statistics-api.md
│   └── paginated-comments-api.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created yet)
```

### Source Code (repository root)

**Existing Laravel MVC Structure** (modified files marked with ⚠️, new files marked with ✨):

```text
app/
├── Http/
│   └── Controllers/
│       ├── VideoAnalysisController.php      # ⚠️ MODIFY: Add pattern statistics/comments endpoints
│       └── CommentPatternController.php     # ✨ NEW: Dedicated controller for pattern operations
├── Models/
│   └── Comment.php                          # ⚠️ MODIFY: Add pattern-related query scopes
├── Services/
│   ├── CommentDensityAnalysisService.php    # (existing, reference only)
│   └── CommentPatternService.php            # ✨ NEW: Pattern calculation logic
└── Http/
    └── Resources/
        ├── PatternStatisticsResource.php    # ✨ NEW: API response transformer
        └── CommentListResource.php          # ✨ NEW: Paginated comment response

resources/
├── views/
│   ├── videos/
│   │   └── analysis.blade.php               # ⚠️ MODIFY: Add pattern block UI
│   └── comments/
│       ├── list.blade.php                   # (existing commentModal reference)
│       └── _pattern_item.blade.php          # ✨ NEW: Single comment item partial
└── js/
    └── comment-pattern.js                   # ✨ NEW: Infinite scroll, filter interaction

routes/
└── api.php                                  # ⚠️ MODIFY: Add pattern endpoints

database/
└── migrations/
    └── (no new migrations needed - uses existing tables)

tests/
├── Unit/
│   ├── Services/
│   │   └── CommentPatternServiceTest.php    # ✨ NEW: Pattern logic tests
│   └── Models/
│       └── CommentScopeTest.php             # ✨ NEW: Query scope tests
├── Feature/
│   └── Api/
│       ├── PatternStatisticsTest.php        # ✨ NEW: API contract test
│       └── PaginatedCommentsTest.php        # ✨ NEW: Pagination test
└── Integration/
    └── TimezoneConversionTest.php           # ✨ NEW: UTC to GMT+8 conversion test
```

**Structure Decision**: Using existing Laravel MVC structure. This feature extends current Video Analysis functionality with minimal new files (1 controller, 1 service, 2 resources, 1 JS file, 1 Blade partial). Follows established patterns from CommentDensityAnalysisService for consistency.

## Files to Create/Modify Summary

### New Files (7 total):
1. `app/Http/Controllers/CommentPatternController.php` - Pattern endpoints
2. `app/Services/CommentPatternService.php` - Business logic for pattern calculations
3. `app/Http/Resources/PatternStatisticsResource.php` - API response formatting
4. `app/Http/Resources/CommentListResource.php` - Paginated comment formatting
5. `resources/views/comments/_pattern_item.blade.php` - Comment item template
6. `resources/js/comment-pattern.js` - Frontend infinite scroll logic
7. `tests/Feature/Api/CommentPatternTest.php` - API tests

### Modified Files (4 total):
1. `app/Http/Controllers/VideoAnalysisController.php` - Integrate pattern data
2. `app/Models/Comment.php` - Add pattern query scopes
3. `resources/views/videos/analysis.blade.php` - Add pattern UI block
4. `routes/api.php` - Register pattern endpoints

## Timezone Handling Requirements

⚠️ **CRITICAL**: Database stores all timestamps in UTC. Must convert to GMT+8 for:

### Display Conversion:
```php
// In controllers/resources:
$comment->published_at->setTimezone('Asia/Taipei')->format('Y-m-d H:i')
```

### Query Filtering (Night-time 01:00-05:59 GMT+8):
```sql
-- Convert UTC to GMT+8 before filtering:
WHERE HOUR(CONVERT_TZ(published_at, '+00:00', '+08:00')) BETWEEN 1 AND 5
```

### Key Patterns:
- **Frontend display**: Always show "YYYY/MM/DD HH:MM (GMT+8)" format
- **Backend queries**: Use `CONVERT_TZ(published_at, '+00:00', '+08:00')` in SQL
- **Laravel Carbon**: Use `->setTimezone('Asia/Taipei')` for display conversion
- **Night-time definition**: 01:00-05:59 in GMT+8 timezone (not UTC!)

## Complexity Tracking

No constitutional violations. This feature follows established patterns:
- Uses existing service layer architecture
- Implements standard API-first design
- Leverages existing Comment model and relationships
- No new architectural patterns introduced
- Complexity justified by cross-channel night-time calculation (business requirement)
