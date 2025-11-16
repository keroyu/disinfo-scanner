# Implementation Plan: Comments List View

**Branch**: `004-comments-list` | **Date**: 2025-11-16 | **Spec**: `specs/004-comments-list/spec.md`
**Input**: Feature specification from `/specs/004-comments-list/spec.md`

**Note**: This plan focuses on implementing the comments list feature with search, filtering, sorting, and navigation capabilities. The system extends the existing Laravel application with a new comments management interface.

## Summary

Build a comprehensive comments list view that allows analysts to browse, search, filter, and sort YouTube comments imported into the DISINFO_SCANNER system. The feature displays 500 comments per page with support for keyword search across multiple fields, date range filtering, sorting by likes and comment date, and clickable navigation to YouTube channels and videos. Success requires performance optimization for large datasets (10,000+ records) and intuitive UX for complex filtering scenarios.

## Technical Context

**Language/Version**: PHP 8.2 (Laravel 11.x framework)
**Primary Dependencies**: Laravel (Eloquent ORM, Blade templating), Vue 3 (frontend), Livewire for reactive filtering
**Storage**: MySQL/SQLite (existing database with comments table)
**Testing**: PHPUnit (unit/feature tests), Pest PHP (feature testing)
**Target Platform**: Web browser (responsive design)
**Project Type**: Web application (Laravel + Blade template backend, Vue.js/Livewire frontend)
**Performance Goals**: <3s page load for initial list, <2s for keyword search (10k+ records), <1s for sorting/filtering
**Constraints**: 500 comments per page (fixed), sort/filter operations <1s, no data modification, preserve existing functionality
**Scale/Scope**: 10,000+ comments, 7 UI components (list view, search, date picker, sort headers, pagination, navigation links)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

✅ **Principle I - Test-First Development**: Feature will include tests for all filtering, sorting, and pagination logic before implementation.

✅ **Principle II - API-First Design**: Comments list will expose API endpoints for search, filter, and sort operations with clear contracts (REST endpoints returning JSON).

✅ **Principle III - Observable Systems**: All query operations will include structured logging and trace IDs for performance monitoring.

✅ **Principle IV - Contract Testing**: Comment search and filter endpoints will have contract tests validating response schema before UI implementation.

✅ **Principle V - Semantic Versioning**: Feature increments MINOR version; no breaking changes to existing APIs.

**GATE RESULT**: ✅ **PASS** - No constitutional violations. Feature aligns with all core principles.

## Project Structure

### Documentation (this feature)

```text
specs/004-comments-list/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root - Web application)

```text
# Existing Laravel structure (extended)
app/
├── Http/Controllers/
│   └── CommentController.php         # NEW: List, search, filter endpoints
├── Models/
│   ├── Comment.php                   # EXISTING: Extend with scopes for filtering
│   ├── Video.php                     # EXISTING
│   └── Channel.php                   # EXISTING
└── Services/
    └── CommentFilterService.php      # NEW: Centralized filtering/sorting logic

resources/views/
├── comments/
│   ├── index.blade.php               # NEW: Comments list view
│   └── components/
│       ├── search-bar.blade.php      # NEW: Search component
│       ├── date-filter.blade.php     # NEW: Date range picker
│       ├── sort-header.blade.php     # NEW: Sortable column headers
│       └── pagination.blade.php      # NEW: Custom pagination
└── layouts/
    └── app.blade.php                 # EXISTING: Navigation update (add Comments List link)

tests/
├── Feature/
│   ├── CommentListTest.php           # NEW: List view tests
│   ├── CommentSearchTest.php         # NEW: Search functionality tests
│   ├── CommentFilterTest.php         # NEW: Date range filtering tests
│   ├── CommentSortTest.php           # NEW: Sorting functionality tests
│   └── CommentNavigationTest.php     # NEW: Link navigation tests
└── Unit/
    └── CommentFilterServiceTest.php  # NEW: Service logic tests

database/
└── seeders/
    └── CommentSeeder.php             # EXISTING: Use for test data (>500 records)
```

**Structure Decision**: Extended Laravel web application structure. Follows existing MVC pattern with:
- **CommentController**: HTTP request handling
- **CommentFilterService**: Business logic for filtering/sorting (testable, reusable)
- **Blade templates**: Server-rendered views with Livewire for reactive filtering (optional enhancement)
- **Feature/Unit tests**: Comprehensive test coverage for all filtering scenarios

## Complexity Tracking

No constitutional violations requiring justification. Feature design aligns with all DISINFO_SCANNER core principles.
