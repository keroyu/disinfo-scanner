# Phase 0: Research & Analysis

**Feature**: Comments List View
**Date**: 2025-11-16
**Status**: Research Complete

## Research Summary

This document consolidates findings for technical decisions required to implement the comments list feature. All decisions are based on the existing DISINFO_SCANNER codebase analysis and Laravel best practices.

---

## 1. Database Schema & Comment Model

### Decision
Use existing `Comment` Eloquent model with additional query scopes for filtering, sorting, and pagination.

### Rationale
- Comment model already exists in the application (referenced in existing migrations)
- Eloquent ORM provides built-in support for efficient querying, sorting, and pagination
- No schema changes required; feature uses existing comment fields (id, video_id, channel_id, channel_name, video_title, commenter_id, content, published_at, like_count)
- Existing relationships to `Video` and `Channel` models support navigation links

### Alternatives Considered
- Raw SQL queries: Rejected because Eloquent provides type safety, relationship loading, and easier maintenance
- Repository pattern: Rejected for this MVP because service-based approach with scopes is sufficient and simpler

### Implementation Details
- Extend `Comment` model with query scopes: `filterByKeyword()`, `filterByDateRange()`, `sortByLikes()`, `sortByDate()`
- Create `CommentFilterService` to coordinate multiple filters and sorting
- Leverage Eloquent's `paginate()` for 500-per-page pagination

---

## 2. Search/Filter Performance Optimization

### Decision
Use database-level filtering with indexed columns and lazy-load relationships. Implement query scopes for composable, reusable filtering logic.

### Rationale
- Database-level filtering (WHERE clauses) is more efficient than application-level filtering
- Required fields (channel_name, video_title, commenter_id, content, published_at) should be indexed for fast LIKE searches
- Pagination before relationship loading (lazy loading) prevents N+1 query problems
- Success criteria require <2s response time for 10k+ comment searches; database indexes are essential

### Alternatives Considered
- Full-text search (MySQL FULLTEXT): Considered but rejected because LIKE with wildcards is simpler to implement and sufficient for this MVP
- Elasticsearch: Rejected as over-engineering for current scale; database indexes are adequate
- In-memory caching (Redis): Not needed for initial implementation; can be added if performance degrades

### Implementation Details
- Create database migration to add composite indexes on (channel_name, video_title, commenter_id, content) for LIKE searches
- Index published_at for date range filtering and sorting
- Index like_count for sorting operations
- Use Eloquent's `select()` to avoid loading unnecessary columns initially

---

## 3. Sorting & Pagination Strategy

### Decision
Implement column-header click sorting with toggle (ascending ↔ descending) and preserve sort state across filtered results.

### Rationale
- Specification requires single-click toggle behavior on column headers
- Sorting must work on filtered/searched result sets (intersection of filters)
- Pagination must apply after sorting (sorted results are then paginated)
- Default sort is by published_at descending (newest first) per specification assumptions

### Alternatives Considered
- Separate sort buttons: Rejected because spec requires clickable column headers
- Client-side sorting: Rejected because >10k records exceed practical browser sorting limits
- URL query parameters for sort state: Accepted as standard web pattern for bookmarking/sharing filtered views

### Implementation Details
- Store sort state in URL query params: `?sort=likes&direction=desc`
- Build query chain: Filter → Sort → Paginate
- Default if no sort specified: `published_at DESC` (newest first)
- Preserve existing filters when sorting

---

## 4. Date Range Filtering

### Decision
Use HTML5 date picker inputs (native browser support) with server-side inclusive date range validation.

### Rationale
- HTML5 `<input type="date">` provides native date picker UI across modern browsers
- Specification requires inclusive filtering (comments on start and end dates included)
- Server-side validation prevents client-side bypass and ensures data integrity
- Simple to implement in Blade templates

### Alternatives Considered
- JavaScript date picker library (Flatpickr, etc.): Rejected because HTML5 native picker is sufficient and reduces dependencies
- Single-date filtering: Rejected because spec requires range filtering for temporal analysis

### Implementation Details
- Use Eloquent `whereBetween()` with `Carbon::parse()` for inclusive date range
- Handle timezone considerations (assume UTC for stored dates)
- Allow clearing individual filters without affecting others

---

## 5. Navigation Links (YouTube URLs)

### Decision
Construct YouTube URLs client-side in Blade templates using stored comment fields. Channel links use `channel_name`, video links use `video_id` with comment_id parameter.

### Rationale
- Specification requires standard YouTube URL format: `https://www.youtube.com/@channelname` for channels and `https://www.youtube.com/watch?v=[VIDEO_ID]&lc=[COMMENT_ID]` for videos
- Fields are stored in database and available in Comment model
- No additional API calls needed to construct links
- YouTube's native behavior highlights comments with `lc` parameter

### Alternatives Considered
- Server-side URL generation with helper functions: Accepted as complementary approach for consistency

### Implementation Details
- Store channel_name and video_id in Comment model (already exist from import feature)
- Create Blade helper functions for URL construction: `youtubeChannelUrl()`, `youtubeVideoWithCommentUrl()`
- Render as `<a>` tags with target="_blank" for external links

---

## 6. UI Framework & Templating

### Decision
Use Blade templates (server-side rendering) with optional Livewire components for reactive filtering. CSS framework: Tailwind CSS (already in use).

### Rationale
- Existing application uses Blade templating; consistent with codebase
- Livewire enables reactive filtering without page reload (optional enhancement)
- Tailwind CSS already integrated in project; no new dependencies needed
- Server-side rendering reduces initial page load time for SEO and performance

### Alternatives Considered
- Single-page app (Vue/React): Rejected because overkill for this feature; Blade + Livewire sufficient
- Datatable library (jQuery DataTables): Rejected to avoid jQuery dependency and keep implementation simple

### Implementation Details
- Create `resources/views/comments/index.blade.php` as main list view
- Use Blade components for reusable UI elements (search bar, date picker, pagination)
- Optional: Implement Livewire component for reactive filtering (phase 1.5 enhancement)
- Responsive design: Mobile-first with Tailwind breakpoints

---

## 7. Testing Strategy

### Decision
Implement three-tier testing: Unit tests (filters), Feature tests (HTTP endpoints), and Contract tests (API response schema).

### Rationale
- Aligns with DISINFO_SCANNER Constitution principles (Test-First Development, Contract Testing)
- Unit tests validate filtering logic independently (fast, isolated)
- Feature tests validate full HTTP request/response cycle (realistic)
- Contract tests ensure API contracts are stable for future clients (mobile app, etc.)

### Alternatives Considered
- Integration tests only: Rejected because slower and less focused
- No automated tests: Rejected; violates Constitution

### Implementation Details
- Unit tests for `CommentFilterService` methods (test each filter in isolation)
- Feature tests for HTTP endpoints (`GET /comments`, with query params)
- Contract tests for API response schema (verify presence of required fields)
- Use factories for test data generation (>500 comments for pagination tests)
- Mock external dependencies (YouTube API, if any)

---

## 8. Performance Requirements & Monitoring

### Decision
Implement query performance monitoring with structured logging and optional database query profiling during development.

### Rationale
- Success criteria require <3s page load, <2s search, <1s sort operations
- Observable systems principle requires structured logging
- Performance regression detection is critical for large comment sets (10k+)

### Alternatives Considered
- Manual performance testing: Rejected because requires discipline; automation is better
- APM tools (New Relic, etc.): Deferred to post-MVP if performance issues arise

### Implementation Details
- Log all comment queries with execution time (using Laravel's query builder logging)
- Include trace IDs for request tracking
- Use Laravel Horizon (if async jobs needed) for background filtering/export operations
- Test with database seeded with 10k+ comments before release

---

## 9. No-Breaking-Changes Guarantee

### Decision
All new code is additive; no modifications to existing models, controllers, or migrations beyond adding indexes.

### Rationale
- Specification explicitly requires no modification of existing functionality
- Feature incrementally adds comments list interface without touching channel/video import logic
- Preserves stability of existing deployed features

### Implementation Details
- Create new `CommentController` (don't modify existing controllers)
- Create new views under `resources/views/comments/` (separate directory)
- Extend `Comment` model via scopes only (no property changes)
- Create new tests in separate test files (don't modify existing tests)
- Add navigation link in layout without breaking existing layout structure

---

## Summary of Key Decisions

| Decision | Chosen Approach | Key Reason |
|----------|-----------------|-----------|
| Database filtering | Eloquent scopes + service | Efficient, aligned with existing codebase |
| Search/filter performance | Indexed columns + LIKE clauses | Fast enough for MVP; DB-level filtering is efficient |
| Sorting | Query scopes with URL param state | Supports toggle behavior and preserves sort state |
| Date filtering | HTML5 date picker + whereBetween | Simple, native, supports range filtering |
| Navigation links | Constructed from stored fields | No extra API calls; uses existing data |
| UI framework | Blade + Tailwind (optional Livewire) | Consistent with existing app |
| Testing | Unit + Feature + Contract tests | Comprehensive coverage, Constitution-aligned |
| Performance monitoring | Structured logging + query profiling | Observable systems principle |
| Backward compatibility | Additive changes only | Preserves existing functionality |

---

## Next Steps (Phase 1)

1. Generate data model documentation (entity relationships, validation rules)
2. Define API contracts (endpoints, request/response schemas)
3. Create Blade template structure and components
4. Implement CommentFilterService and scopes
5. Write comprehensive tests (unit, feature, contract)
