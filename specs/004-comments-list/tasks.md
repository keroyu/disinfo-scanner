# Task Breakdown: Comments List View Implementation

**Feature**: Comments List View
**Branch**: `004-comments-list`
**Date**: 2025-11-16
**Status**: Ready for Implementation

---

## Executive Summary

This document breaks down the Comments List feature into 37 implementation tasks organized by phase and user story. The feature enables analysts to browse, search, filter, and sort YouTube comments with 500 comments per page and clickable navigation to YouTube channels and videos.

### Implementation Strategy

**MVP Scope** (Phase 1-3): Implement P1 user stories (US1-3, US6-7) for core comment viewing and filtering functionality. This provides immediate value to analysts with basic list, search, date filter, and sorting.

**Phase 4 (P2 Features)**: Add channel and video navigation links (US4-5) after validating MVP with users.

**Total Tasks**: 37
- **Phase 1 (Setup)**: 4 tasks
- **Phase 2 (Foundational)**: 6 tasks
- **Phase 3 (US1 - View Comments List)**: 8 tasks
- **Phase 4 (US2 - Search Comments)**: 5 tasks
- **Phase 5 (US3 - Filter by Date)**: 4 tasks
- **Phase 6 (US6 - Sort by Likes)**: 3 tasks
- **Phase 7 (US7 - Sort by Date)**: 2 tasks
- **Phase 8 (US4 - Channel Navigation)**: 2 tasks
- **Phase 9 (US5 - Video Navigation)**: 2 tasks
- **Phase 10 (Polish & Testing)**: 1 task

### Parallel Opportunities

- **Phase 3**: Tests (T008-T009) can run in parallel with implementation (T010-T015)
- **Phase 4-7**: US2-7 are largely independent; filtering/sorting can be implemented in parallel after Phase 2
- **Phases 8-9**: Navigation features are independent of core filtering/sorting

### Dependencies

```
Phase 1 (Setup)
    ↓
Phase 2 (Foundational: DB indexes, Model scopes, Service, Controller, Routes)
    ├→ Phase 3 (US1: List view)
    │   ├→ Phase 4 (US2: Search)
    │   ├→ Phase 5 (US3: Date filter)
    │   ├→ Phase 6 (US6: Sort by likes)
    │   ├→ Phase 7 (US7: Sort by date)
    │   └→ Phases 8-9 (US4-5: Navigation)
    └→ Phase 10 (Polish & Testing)
```

---

## Phase 1: Project Setup

**Goal**: Initialize project structure and prepare for development

**Estimated Duration**: 30 minutes

- [ ] T001 Create database migration for comment indexes in `database/migrations/[timestamp]_add_comments_list_indexes.php`

- [ ] T002 Create CommentFilterService file at `app/Services/CommentFilterService.php` (empty class skeleton)

- [ ] T003 Create CommentController file at `app/Http/Controllers/CommentController.php` (empty controller skeleton)

- [ ] T004 Register routes for comments list in `routes/web.php` (GET /comments and GET /api/comments)

---

## Phase 2: Foundational Infrastructure

**Goal**: Implement database indexes and core filtering/querying logic that all user stories depend on

**Prerequisites**: Phase 1 complete

**Estimated Duration**: 2 hours

**Independent Test Criteria**:
- Database indexes exist and can be verified with `SHOW INDEX FROM comments;`
- Model scopes execute without errors
- CommentFilterService can chain filter operations
- Controller accepts valid query parameters without crashing

- [ ] T005 Run migration and create database indexes on: published_at, like_count, channel_name, video_title, commenter_id, content in `database/migrations/[timestamp]_add_comments_list_indexes.php`

- [ ] T006 [P] Add `filterByKeyword()` Eloquent scope to Comment model in `app/Models/Comment.php` (case-insensitive LIKE search across channel_name, video_title, commenter_id, content)

- [ ] T007 [P] Add `filterByDateRange()` Eloquent scope to Comment model in `app/Models/Comment.php` (whereBetween on published_at with Carbon parsing)

- [ ] T008 [P] Add `sortByLikes()` Eloquent scope to Comment model in `app/Models/Comment.php` (orderBy like_count with direction parameter)

- [ ] T009 [P] Add `sortByDate()` Eloquent scope to Comment model in `app/Models/Comment.php` (orderBy published_at with direction parameter)

- [ ] T010 Implement CommentFilterService methods in `app/Services/CommentFilterService.php`: `__construct()`, `searchKeyword()`, `filterByDateRange()`, `sort()`, `paginate()` with method chaining

---

## Phase 3: User Story 1 - View Comments List (P1)

**User Story**: Analysts need to browse a comprehensive list of all comments collected from YouTube videos.

**Acceptance Criteria**:
1. Comments are displayed with all required metadata (channel name, video title, commenter ID, content, date, like count)
2. Empty state message shown when no comments exist
3. Pagination shows exactly 500 comments per page with navigation controls
4. Default sort is newest first (published_at DESC)

**Independent Test Criteria**:
- Load /comments page and verify HTML contains comment table
- Verify pagination controls exist and are functional
- Verify all required comment fields are rendered
- Verify empty state message displays when no comments exist

**Estimated Duration**: 2.5 hours

- [ ] T011 [US1] Create main comments list Blade template at `resources/views/comments/index.blade.php` with table layout (columns: Date, Channel, Video, Commenter, Content, Likes)

- [ ] T012 [US1] Create search bar component at `resources/views/comments/components/search-bar.blade.php` with keyword input field and submit button

- [ ] T013 [US1] Create date filter component at `resources/views/comments/components/date-filter.blade.php` with from/to date picker inputs

- [ ] T014 [US1] Create pagination component at `resources/views/comments/components/pagination.blade.php` with previous, next, page numbers navigation

- [ ] T015 [US1] Implement `index()` method in CommentController at `app/Http/Controllers/CommentController.php` to fetch comments, validate query params, pass data to view

- [ ] T016 [US1] Add Comments List navigation link to main layout at `resources/layouts/app.blade.php` alongside existing Channel List button

- [ ] T017 [US1] Create Feature test file at `tests/Feature/CommentListTest.php` with tests for: page loads, comments rendered, pagination displays correctly, empty state message

- [ ] T018 [US1] Create Unit test file at `tests/Unit/CommentFilterServiceTest.php` with test for basic pagination (no filters)

---

## Phase 4: User Story 2 - Search Comments by Content (P1)

**User Story**: Analysts need to quickly locate comments matching specific keywords.

**Acceptance Criteria**:
1. Keyword search filters across channel name, video title, commenter ID, and content
2. Search is case-insensitive
3. "No results" message displays when no matches found
4. Search can be cleared to display all comments again

**Independent Test Criteria**:
- Enter keyword and verify filtered results only contain that keyword
- Verify search works on each field (channel, video, commenter, content) independently
- Verify "no results" message displays for non-matching searches
- Verify clearing search returns all comments

**Estimated Duration**: 1.5 hours

- [ ] T019 [P] [US2] Add keyword validation to CommentController `index()` method: max 255 chars, sanitize input

- [ ] T020 [P] [US2] Update CommentFilterService to call `searchKeyword()` scope in `index()` method when keyword provided

- [ ] T021 [US2] Add "clear search" button to search bar component at `resources/views/comments/components/search-bar.blade.php`

- [ ] T022 [US2] Create Feature test file at `tests/Feature/CommentSearchTest.php` with tests for: keyword filtering on each field, case-insensitive matching, no-results message, clear search

- [ ] T023 [US2] Create Unit test at `tests/Unit/CommentFilterServiceTest.php` for `searchKeyword()` scope with multiple test cases

---

## Phase 5: User Story 3 - Filter Comments by Date Range (P1)

**User Story**: Analysts need to focus on comments within specific time periods.

**Acceptance Criteria**:
1. Date range picker allows selection of from/to dates (inclusive)
2. "No results" message when no comments in date range
3. Date filter can be cleared independently of search
4. Keyword search and date filter work together (intersection)

**Independent Test Criteria**:
- Select date range and verify only comments within range display
- Verify inclusive filtering (comments on start/end dates included)
- Verify "no results" message for empty date ranges
- Verify filters combine correctly (keyword AND date range)

**Estimated Duration**: 1.5 hours

- [ ] T024 [P] [US3] Add date range validation to CommentController: `date_from` must be <= `date_to`, must be valid ISO dates

- [ ] T025 [P] [US3] Update CommentFilterService to call `filterByDateRange()` scope when both dates provided

- [ ] T026 [US3] Add "clear date filter" button to date filter component at `resources/views/comments/components/date-filter.blade.php`

- [ ] T027 [US3] Create Feature test file at `tests/Feature/CommentFilterTest.php` with tests for: date range filtering, inclusive boundaries, no-results message, combined keyword+date filters

---

## Phase 6: User Story 6 - Sort Comments by Likes (P1)

**User Story**: Analysts need to prioritize reviewing comments by engagement level.

**Acceptance Criteria**:
1. Like count column header is clickable
2. First click sorts ascending (lowest to highest)
3. Second click toggles to descending (highest to lowest)
4. Sort order preserved when filters applied

**Independent Test Criteria**:
- Click like count header and verify ascending sort
- Click again and verify descending sort
- Verify sort persists across pagination
- Verify sort works with filters applied

**Estimated Duration**: 1 hour

- [ ] T028 [P] [US6] Create sort header component at `resources/views/comments/components/sort-header.blade.php` with clickable column headers for likes and date

- [ ] T029 [US6] Update CommentController to handle `sort` and `direction` query params, validate against allowed columns

- [ ] T030 [US6] Update CommentFilterService to call appropriate `sortByLikes()` or `sortByDate()` scope based on sort param

- [ ] T031 [US6] Create Feature test file at `tests/Feature/CommentSortTest.php` with tests for: sort by likes ascending/descending, sort toggle, sort with filters

---

## Phase 7: User Story 7 - Sort Comments by Date (P1)

**User Story**: Analysts need to review comments in chronological order.

**Acceptance Criteria**:
1. Comment date column header is clickable
2. First click sorts ascending (oldest first)
3. Second click toggles to descending (newest first)
4. Default sort is descending (newest first)

**Independent Test Criteria**:
- Click date header and verify chronological sort
- Verify toggle to reverse chronological
- Verify default is newest first on initial load

**Estimated Duration**: 0.5 hours

- [ ] T032 [US7] Update sort header component to include clickable date column at `resources/views/comments/components/sort-header.blade.php`

- [ ] T033 [US7] Add Feature test to `tests/Feature/CommentSortTest.php` for: sort by date ascending/descending, default sort order, sort toggle

---

## Phase 8: User Story 4 - Navigate to Channel Details (P2)

**User Story**: Analysts need quick access to channel information when investigating.

**Acceptance Criteria**:
1. Channel name is rendered as clickable link
2. Link navigates to YouTube channel main page (format: https://www.youtube.com/@channelname)
3. Correct channel identifier from comment data is used

**Independent Test Criteria**:
- Click channel name link and verify correct YouTube URL is opened
- Verify link href attribute contains correct channel name

**Estimated Duration**: 1 hour

- [ ] T034 [P] [US4] Create helper function `youtubeChannelUrl()` in helpers file or Blade component at `app/Helpers/YouTubeUrlHelper.php` or in controller

- [ ] T035 [US4] Update comments list template at `resources/views/comments/index.blade.php` to render channel names as `<a>` tags with href to YouTube channel

- [ ] T036 [US4] Create Feature test file at `tests/Feature/CommentNavigationTest.php` with test for: channel link exists, href is correct YouTube URL format, target="_blank"

---

## Phase 9: User Story 5 - Navigate to Video with Comment Anchor (P2)

**User Story**: Analysts need to view a comment within its video context.

**Acceptance Criteria**:
1. Video title is rendered as clickable link
2. Link navigates to YouTube video with comment_id parameter (format: https://www.youtube.com/watch?v=[VIDEO_ID]&lc=[COMMENT_ID])
3. Video ID and comment ID correctly extracted from comment record

**Independent Test Criteria**:
- Click video title link and verify correct YouTube URL with comment anchor is opened
- Verify URL format includes both video_id and comment_id (lc parameter)

**Estimated Duration**: 1 hour

- [ ] T037 [P] [US5] Create helper function `youtubeVideoWithCommentUrl()` in helpers file at `app/Helpers/YouTubeUrlHelper.php`

- [ ] T038 [US5] Update comments list template at `resources/views/comments/index.blade.php` to render video titles as `<a>` tags with href to YouTube video with comment anchor

- [ ] T039 [US5] Add Feature test to `tests/Feature/CommentNavigationTest.php` for: video link exists, href includes video_id and comment_id (lc parameter), target="_blank"

---

## Phase 10: Polish & Testing

**Goal**: Verify performance, cross-cutting concerns, and overall feature quality

**Prerequisites**: All phases 1-9 complete

**Estimated Duration**: 2 hours

- [ ] T040 Run all feature and unit tests: `php artisan test` and ensure 100% pass rate

- [ ] T041 Verify database indexes with `SHOW INDEX FROM comments;` and confirm all 7 indexes exist

- [ ] T042 Performance test: Load /comments page with 10,000+ test comments and verify <3s page load time

- [ ] T043 Performance test: Search for keyword with 10,000+ records and verify <2s response time

- [ ] T044 Performance test: Sort by likes and date with 10,000+ records and verify <1s response time

- [ ] T045 Verify pagination: Test pages 1, 5, 25 with 10,000+ comments and confirm exactly 500 per page

- [ ] T046 Test edge cases: Empty result sets, very long comments (exceed display width), search with no matches, date range with no matches

- [ ] T047 Verify no N+1 queries: Use Laravel Debugbar to check query count when rendering list with relationships

---

## Task Status Summary

| Phase | User Story | Tasks | Status |
|-------|-----------|-------|--------|
| 1 | Setup | T001-T004 | Ready |
| 2 | Foundational | T005-T010 | Ready |
| 3 | US1 (View List) | T011-T018 | Ready |
| 4 | US2 (Search) | T019-T023 | Ready |
| 5 | US3 (Date Filter) | T024-T027 | Ready |
| 6 | US6 (Sort Likes) | T028-T031 | Ready |
| 7 | US7 (Sort Date) | T032-T033 | Ready |
| 8 | US4 (Channel Nav) | T034-T036 | Ready |
| 9 | US5 (Video Nav) | T037-T039 | Ready |
| 10 | Polish & Test | T040-T047 | Ready |

**Total Tasks**: 47

---

## Parallel Execution Examples

### Scenario 1: Start with MVP (P1 Features)

**Recommended Team Size**: 3-4 developers

**Week 1**:
- Developer A: Phases 1-2 (Setup, Foundational)
- Developer B: Waits for Phase 2, then Phase 3 (View List)
- Developer C: Waits for Phase 2, then Phase 4 (Search)

**Week 2** (after Phase 2 complete):
- Developer A: Phase 5 (Date Filter)
- Developer B: Phase 6 (Sort by Likes)
- Developer C: Phase 7 (Sort by Date)
- Testers: Run tests from phases 3-4

**Week 3**:
- All: Phase 10 (Polish & Testing)
- Reviewers: Code review

### Scenario 2: Aggressive Parallel (Full Feature)

**Recommended Team Size**: 6+ developers

**Week 1**:
- Dev 1-2: Phases 1-2 (Setup & Foundational)

**Week 2** (all after Phase 2):
- Dev 1: Phase 3 (View List) + Phase 8 (Channel Nav)
- Dev 2: Phase 4 (Search) + Phase 9 (Video Nav)
- Dev 3: Phase 5 (Date Filter)
- Dev 4: Phase 6 (Sort by Likes)
- Dev 5: Phase 7 (Sort by Date)
- Dev 6: Write comprehensive tests from all phases

**Week 3**:
- All: Phase 10 (Polish & Testing), code review, documentation

---

## File Checklist

**Models** (extend existing):
- [ ] `app/Models/Comment.php` - Add scopes (filterByKeyword, filterByDateRange, sortByLikes, sortByDate)

**Services** (new):
- [ ] `app/Services/CommentFilterService.php` - Filtering/sorting orchestration

**Controllers** (new):
- [ ] `app/Http/Controllers/CommentController.php` - HTTP request handling

**Views** (new):
- [ ] `resources/views/comments/index.blade.php` - Main list view
- [ ] `resources/views/comments/components/search-bar.blade.php` - Search UI
- [ ] `resources/views/comments/components/date-filter.blade.php` - Date picker UI
- [ ] `resources/views/comments/components/sort-header.blade.php` - Sortable headers
- [ ] `resources/views/comments/components/pagination.blade.php` - Pagination UI

**Helpers** (new):
- [ ] `app/Helpers/YouTubeUrlHelper.php` - URL construction helpers

**Tests** (new):
- [ ] `tests/Feature/CommentListTest.php` - View list tests
- [ ] `tests/Feature/CommentSearchTest.php` - Search tests
- [ ] `tests/Feature/CommentFilterTest.php` - Date filter tests
- [ ] `tests/Feature/CommentSortTest.php` - Sort tests
- [ ] `tests/Feature/CommentNavigationTest.php` - Navigation link tests
- [ ] `tests/Unit/CommentFilterServiceTest.php` - Service unit tests

**Migrations** (new):
- [ ] `database/migrations/[timestamp]_add_comments_list_indexes.php` - Database indexes

**Routes** (update existing):
- [ ] `routes/web.php` - Add /comments and /api/comments routes

**Layout** (update existing):
- [ ] `resources/layouts/app.blade.php` - Add Comments List navigation link

---

## Testing Strategy

### Test-First Approach (per Constitution Principle I)

1. Write Feature test for each user story before implementing UI
2. Write Unit test for service methods before implementing service
3. Write contract test for API endpoint before implementing controller

### Test Coverage Goals

- **Phase 3 (US1)**: 8 tests (list view, pagination, empty state)
- **Phase 4 (US2)**: 5 tests (search on each field, case-insensitive, clear search)
- **Phase 5 (US3)**: 4 tests (date range, inclusive, combined filters)
- **Phase 6 (US6)**: 3 tests (sort toggle, sort with filters)
- **Phase 7 (US7)**: 2 tests (sort direction, default sort)
- **Phase 8 (US4)**: 1 test (channel link navigation)
- **Phase 9 (US5)**: 1 test (video link with comment anchor)

**Total Test Cases**: 24 feature tests + 5 unit tests = 29 tests

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/CommentListTest.php

# Run with coverage report
php artisan test --coverage
```

---

## Acceptance Criteria by Phase

### Phase 3 (US1) Acceptance
- ✅ Comments displayed with all required metadata
- ✅ Pagination shows exactly 500 per page
- ✅ Navigation controls (prev, next, page numbers) functional
- ✅ Empty state message displays when no comments
- ✅ Default sort is newest first

### Phase 4 (US2) Acceptance
- ✅ Keyword search filters across all 4 fields
- ✅ Search is case-insensitive
- ✅ "No results" message displays for non-matches
- ✅ Search can be cleared
- ✅ All search tests pass

### Phase 5 (US3) Acceptance
- ✅ Date range picker selects from/to dates
- ✅ Filtering is inclusive (comments on end dates included)
- ✅ Date filter clears independently
- ✅ Keyword + date filters work together
- ✅ All filter tests pass

### Phase 6 (US6) Acceptance
- ✅ Like count column header is clickable
- ✅ First click = ascending sort
- ✅ Second click = descending sort
- ✅ Sort persists across filters and pagination
- ✅ All sort tests pass

### Phase 7 (US7) Acceptance
- ✅ Date column header is clickable
- ✅ Chronological sorting works (ascending/descending)
- ✅ Default sort is newest first
- ✅ All sort tests pass

### Phase 8 (US4) Acceptance
- ✅ Channel names are clickable links
- ✅ Links navigate to correct YouTube channel URL
- ✅ Links open in new tab (target="_blank")

### Phase 9 (US5) Acceptance
- ✅ Video titles are clickable links
- ✅ Links include video_id and comment_id (lc parameter)
- ✅ Links navigate to correct YouTube video with comment anchor
- ✅ Links open in new tab (target="_blank")

### Phase 10 (Polish) Acceptance
- ✅ All 29 tests pass (100% pass rate)
- ✅ All 7 database indexes created
- ✅ Page load <3s (10k+ records)
- ✅ Search <2s (10k+ records)
- ✅ Sort <1s (10k+ records)
- ✅ No N+1 queries
- ✅ Edge cases handled gracefully

---

## Success Metrics

**Code Quality**:
- 100% test pass rate (29/29 tests)
- Zero code review comments on security issues
- All tasks completed per specification

**Performance**:
- Page load: < 3s (initial list, 10k+ records)
- Search: < 2s (keyword search, 10k+ records)
- Filter: < 1s (date range, 10k+ records)
- Sort: < 1s (any sort, 10k+ records)

**User Experience**:
- Comments displayed with complete metadata
- Responsive design on mobile/tablet/desktop
- Clear "no results" messaging
- Intuitive filter/sort UI with column headers

**Maintainability**:
- Feature completed with no modifications to existing models/controllers
- All changes additive and backward-compatible
- Well-documented tests and code comments

---

## References

- **Specification**: `specs/004-comments-list/spec.md`
- **Implementation Plan**: `specs/004-comments-list/plan.md`
- **Data Model**: `specs/004-comments-list/data-model.md`
- **API Contracts**: `specs/004-comments-list/contracts/CONTRACTS.md`
- **Quickstart**: `specs/004-comments-list/quickstart.md`
- **Research**: `specs/004-comments-list/research.md`

---

**Generated by**: /speckit.tasks command
**Last Updated**: 2025-11-16
