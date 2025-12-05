# Tasks: Comments Pattern Summary

**Input**: Design documents from `/specs/009-comments-pattern-summary/`
**Prerequisites**: plan.md (tech stack), spec.md (user stories), data-model.md (entities), contracts/ (API specs)

**Tests**: Test tasks included per Constitution Principle I (Test-First Development)

**Organization**: Tasks grouped by user story to enable independent implementation and testing

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: User story label (US0=View All, US1=Repeat, US2=Night-time, US3=Aggressive, US4=Chinese)
- Include exact file paths in descriptions

## Path Conventions

- Laravel MVC structure: `app/`, `resources/`, `routes/`, `tests/`
- New files: `app/Services/CommentPatternService.php`, `app/Http/Controllers/CommentPatternController.php`
- Modified files: `app/Models/Comment.php`, `resources/views/videos/analysis.blade.php`, `routes/api.php`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization - verify prerequisites and prepare for feature development

- [ ] T001 Verify PHP 8.2 and Laravel 12.38.1 installation via `php -v` and `php artisan --version`
- [ ] T002 [P] Verify Redis is running and accessible via `php artisan tinker` → `Cache::put('test', 'ok', 60)`
- [ ] T003 [P] Verify MySQL timezone support by testing `SELECT CONVERT_TZ('2025-01-01 00:00:00', '+00:00', '+08:00')`
- [ ] T004 [P] Create feature branch `009-comments-pattern-summary` if not already created
- [ ] T005 [P] Verify existing comment modal layout in `resources/views/comments/list.blade.php` (id="commentModal")

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story implementation

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

### Contract Tests (Write FIRST - Must FAIL before implementation)

- [ ] T006 [P] Create PHPUnit test file `tests/Feature/Api/CommentPatternTest.php` with test structure
- [ ] T007 [P] Write contract test for pattern statistics endpoint structure in `tests/Feature/Api/CommentPatternTest.php::test_pattern_statistics_returns_correct_structure`
- [ ] T008 [P] Write contract test for paginated comments endpoint structure in `tests/Feature/Api/CommentPatternTest.php::test_paginated_comments_returns_correct_format`
- [ ] T009 [P] Write timezone conversion test in `tests/Feature/Api/CommentPatternTest.php::test_timestamps_display_in_gmt_plus_8`
- [ ] T010 [P] Run tests to verify they FAIL (expected - no implementation yet): `php artisan test --filter=CommentPatternTest`

### Service Layer Foundation

- [ ] T011 Create `app/Services/CommentPatternService.php` with class structure and placeholder methods: `getPatternStatistics()`, `getCommentsByPattern()`, `calculateRepeatCommenters()`, `calculateNightTimeCommenters()`
- [ ] T012 Add query scope `scopeRepeatCommenters()` to `app/Models/Comment.php` for GROUP BY author_channel_id HAVING COUNT(*) >= 2
- [ ] T013 [P] Add query scope `scopeNightTimeHighFrequencyCommenters()` to `app/Models/Comment.php` with CONVERT_TZ for 01:00-05:59 GMT+8 filtering
- [ ] T014 [P] Add query scope `scopeByPattern()` to `app/Models/Comment.php` to filter comments by pattern type (all/repeat/night_time/aggressive/simplified_chinese)

### API Controller and Routes

- [ ] T015 Create `app/Http/Controllers/CommentPatternController.php` with methods: `getPatternStatistics(string $videoId)`, `getCommentsByPattern(Request $request, string $videoId)`
- [ ] T016 Register API routes in `routes/api.php`: `GET /api/videos/{videoId}/pattern-statistics` and `GET /api/videos/{videoId}/comments`
- [ ] T017 Create `app/Http/Resources/PatternStatisticsResource.php` for API response formatting
- [ ] T018 [P] Create `app/Http/Resources/CommentListResource.php` for paginated comment response formatting

### Frontend Assets

- [ ] T019 [P] Create `resources/js/comment-pattern.js` with CommentPatternUI class structure (constructor, init, loadStatistics, renderFilterList, loadComments, appendComments, switchPattern, setupInfiniteScroll)
- [ ] T020 [P] Create `resources/views/comments/_pattern_item.blade.php` Blade partial for single comment display (following commentModal layout)

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 0 - View All Comments (Priority: P0) 🎯 MVP Foundation

**Goal**: Display all comments in default right panel view with infinite scroll, providing foundation for all filtering features

**Independent Test**: Navigate to `/videos/{video_id}/analysis`, verify right panel shows first 100 comments sorted newest to oldest, "所有留言" is highlighted in left panel, scrolling loads more comments

### Tests for User Story 0

> **NOTE: These tests extend the contract tests from Phase 2**

- [ ] T021 [P] [US0] Write test for default "所有留言" selection on page load in `tests/Feature/Api/CommentPatternTest.php::test_all_comments_selected_by_default`
- [ ] T022 [P] [US0] Write test for infinite scroll pagination in `tests/Feature/Api/CommentPatternTest.php::test_infinite_scroll_loads_next_batch`
- [ ] T023 [P] [US0] Write test for sorting (newest first) in `tests/Feature/Api/CommentPatternTest.php::test_comments_sorted_newest_first`

### Implementation for User Story 0

- [ ] T024 [US0] Implement `getPatternStatistics()` in `app/Services/CommentPatternService.php` with "all" pattern calculation (count = total unique commenters, percentage = 100)
- [ ] T025 [US0] Implement `getCommentsByPattern()` in `app/Services/CommentPatternService.php` for pattern="all" with offset/limit pagination, timezone conversion to GMT+8
- [ ] T026 [US0] Add Redis caching to `getPatternStatistics()` with 5-minute TTL using cache key `video:{videoId}:pattern:all_statistics`
- [ ] T027 [US0] Implement controller methods in `app/Http/Controllers/CommentPatternController.php` calling service methods and returning JSON responses
- [ ] T028 [US0] Add Comments Pattern block HTML to `resources/views/videos/analysis.blade.php` with left/right panel structure, filter list container, comment list container
- [ ] T029 [US0] Implement JavaScript functions in `resources/js/comment-pattern.js`: `loadStatistics()`, `renderFilterList()` (showing "所有留言" highlighted), `loadComments()` for initial 100
- [ ] T030 [US0] Implement infinite scroll in `resources/js/comment-pattern.js`: `setupInfiniteScroll()` using Intersection Observer API on sentinel element
- [ ] T031 [US0] Implement `appendComments()` in `resources/js/comment-pattern.js` to render comment items using commentModal layout style
- [ ] T032 [US0] Add validation and error handling for empty video case (0 comments) showing "0 個 (0%)" for all patterns
- [ ] T033 [US0] Add logging for pattern statistics calculation with video_id, execution_time, cache_hit status in `app/Services/CommentPatternService.php`
- [ ] T034 [US0] Build frontend assets: `npm run build` or `npm run dev`
- [ ] T035 [US0] Run tests to verify User Story 0 passes: `php artisan test --filter=CommentPatternTest`

**Checkpoint**: At this point, User Story 0 (all comments view with infinite scroll) should be fully functional

---

## Phase 4: User Story 1 - View Repeat Commenters Statistics (Priority: P1)

**Goal**: Display statistics and filtered list for commenters who posted 2+ times on the same video

**Independent Test**: Navigate to video analysis page, verify "重複留言者有 X 個 (Y%)" shows correct count/percentage, click it to see only repeat commenter comments

### Tests for User Story 1

- [ ] T036 [P] [US1] Write test for repeat commenters calculation in `tests/Feature/Api/CommentPatternTest.php::test_repeat_commenters_count_accurate`
- [ ] T037 [P] [US1] Write test for repeat commenters percentage rounding in `tests/Feature/Api/CommentPatternTest.php::test_repeat_percentage_rounded_to_integer`
- [ ] T038 [P] [US1] Write test for clicking repeat filter shows correct comments in `tests/Feature/Api/CommentPatternTest.php::test_repeat_filter_shows_only_repeat_comments`

### Implementation for User Story 1

- [ ] T039 [P] [US1] Implement `calculateRepeatCommenters()` private method in `app/Services/CommentPatternService.php` using GROUP BY author_channel_id HAVING COUNT(*) >= 2
- [ ] T040 [US1] Update `getPatternStatistics()` in `app/Services/CommentPatternService.php` to include "repeat" pattern calculation with count and percentage
- [ ] T041 [US1] Update `getCommentsByPattern()` in `app/Services/CommentPatternService.php` to handle pattern="repeat" by filtering to repeat author IDs
- [ ] T042 [US1] Update `renderFilterList()` in `resources/js/comment-pattern.js` to display repeat commenters statistic with actual count/percentage
- [ ] T043 [US1] Implement filter click handler in `resources/js/comment-pattern.js`: `switchPattern('repeat')` reloads comment list with repeat filter
- [ ] T044 [US1] Update visual highlighting in `resources/js/comment-pattern.js` to show active filter with bg-blue-100 and border-blue-500 classes
- [ ] T045 [US1] Handle edge case: video with comments but 0 repeat commenters shows "重複留言者有 0 個 (0%)"
- [ ] T046 [US1] Add logging for repeat commenter queries with video_id, count, execution_time in `app/Services/CommentPatternService.php`
- [ ] T047 [US1] Run tests to verify User Story 1 passes: `php artisan test --filter=test_repeat`

**Checkpoint**: User Stories 0 AND 1 should both work independently

---

## Phase 5: User Story 2 - View Night-time High-Frequency Commenters Statistics (Priority: P2)

**Goal**: Display statistics and filtered list for commenters with >50% comments during 01:00-05:59 GMT+8 across ALL channels

**Independent Test**: Navigate to video analysis page, verify "夜間高頻留言者有 X 個 (Y%)" shows correct count, click it to see night-time commenter comments

### Tests for User Story 2

- [ ] T048 [P] [US2] Write test for night-time calculation with timezone conversion in `tests/Feature/Api/CommentPatternTest.php::test_night_time_uses_gmt_plus_8_hours`
- [ ] T049 [P] [US2] Write test for cross-channel aggregation in `tests/Feature/Api/CommentPatternTest.php::test_night_time_checks_all_channels_not_just_current_video`
- [ ] T050 [P] [US2] Write test for >50% threshold (exactly 50% excluded) in `tests/Feature/Api/CommentPatternTest.php::test_night_time_requires_greater_than_fifty_percent`
- [ ] T051 [P] [US2] Write test for minimum 2 comments requirement in `tests/Feature/Api/CommentPatternTest.php::test_night_time_requires_minimum_two_comments`

### Implementation for User Story 2

- [ ] T052 [US2] Implement `calculateNightTimeCommenters()` private method in `app/Services/CommentPatternService.php` with cross-channel query using CONVERT_TZ(published_at, '+00:00', '+08:00') and HOUR() BETWEEN 1 AND 5
- [ ] T053 [US2] Add Redis caching to `calculateNightTimeCommenters()` with cache key `video:{videoId}:pattern:night_time` and 5-minute TTL
- [ ] T054 [US2] Update `getPatternStatistics()` in `app/Services/CommentPatternService.php` to include "night_time" pattern calculation
- [ ] T055 [US2] Update `getCommentsByPattern()` in `app/Services/CommentPatternService.php` to handle pattern="night_time" by filtering to night-time author IDs
- [ ] T056 [US2] Exclude comments with NULL published_at from night-time calculations per FR-013
- [ ] T057 [US2] Update `renderFilterList()` in `resources/js/comment-pattern.js` to display night-time statistic with count/percentage
- [ ] T058 [US2] Test night-time filter click handler: `switchPattern('night_time')` correctly filters comments
- [ ] T059 [US2] Add trace_id logging for night-time cross-channel queries for debugging expensive operations
- [ ] T060 [US2] Run tests to verify User Story 2 passes: `php artisan test --filter=test_night_time`

**Checkpoint**: User Stories 0, 1, AND 2 should all work independently

---

## Phase 6: User Story 3 - View Aggressive Commenters Placeholder (Priority: P3)

**Goal**: Display placeholder UI for future aggressive commenter detection (shows "X" for count)

**Independent Test**: Navigate to video analysis page, verify "高攻擊性留言者有 X 個 (0%)" displays, clicking shows placeholder message

### Tests for User Story 3

- [ ] T061 [P] [US3] Write test for aggressive placeholder shows "X" in `tests/Feature/Api/CommentPatternTest.php::test_aggressive_shows_placeholder_x`
- [ ] T062 [P] [US3] Write test for aggressive filter returns empty list in `tests/Feature/Api/CommentPatternTest.php::test_aggressive_filter_returns_empty`

### Implementation for User Story 3

- [ ] T063 [P] [US3] Implement `placeholderPattern('aggressive')` private method in `app/Services/CommentPatternService.php` returning count=0, percentage=0
- [ ] T064 [US3] Update `getPatternStatistics()` in `app/Services/CommentPatternService.php` to include "aggressive" placeholder
- [ ] T065 [US3] Update `getCommentsByPattern()` in `app/Services/CommentPatternService.php` to return empty array for pattern="aggressive"
- [ ] T066 [US3] Update `renderFilterList()` in `resources/js/comment-pattern.js` to display "X" for aggressive count instead of numeric value
- [ ] T067 [US3] Implement placeholder message display in right panel when aggressive filter clicked showing "此功能待人工審查實作"
- [ ] T068 [US3] Run tests to verify User Story 3 passes: `php artisan test --filter=test_aggressive`

**Checkpoint**: User Stories 0-3 should all work independently

---

## Phase 7: User Story 4 - View Simplified Chinese Commenters Placeholder (Priority: P3)

**Goal**: Display placeholder UI for future simplified Chinese detection (shows "X" for count)

**Independent Test**: Navigate to video analysis page, verify "簡體中文留言者有 X 個 (0%)" displays, clicking shows placeholder message

### Tests for User Story 4

- [ ] T069 [P] [US4] Write test for Chinese placeholder shows "X" in `tests/Feature/Api/CommentPatternTest.php::test_simplified_chinese_shows_placeholder_x`
- [ ] T070 [P] [US4] Write test for Chinese filter returns empty list in `tests/Feature/Api/CommentPatternTest.php::test_simplified_chinese_filter_returns_empty`

### Implementation for User Story 4

- [ ] T071 [P] [US4] Implement `placeholderPattern('simplified_chinese')` private method in `app/Services/CommentPatternService.php` returning count=0, percentage=0
- [ ] T072 [US4] Update `getPatternStatistics()` in `app/Services/CommentPatternService.php` to include "simplified_chinese" placeholder
- [ ] T073 [US4] Update `getCommentsByPattern()` in `app/Services/CommentPatternService.php` to return empty array for pattern="simplified_chinese"
- [ ] T074 [US4] Update `renderFilterList()` in `resources/js/comment-pattern.js` to display "X" for simplified Chinese count
- [ ] T075 [US4] Implement placeholder message display in right panel when Chinese filter clicked showing "此功能待語言偵測實作"
- [ ] T076 [US4] Run tests to verify User Story 4 passes: `php artisan test --filter=test_simplified_chinese`

**Checkpoint**: All user stories (0-4) should now be independently functional

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Improvements affecting multiple user stories and final validation

- [ ] T077 [P] Add comprehensive error handling for 404 (video not found) and 500 (server error) responses in `app/Http/Controllers/CommentPatternController.php`
- [ ] T078 [P] Add request validation in `app/Http/Controllers/CommentPatternController.php::getCommentsByPattern()` for offset >= 0, limit 1-100, pattern in allowed values
- [ ] T079 [P] Optimize database queries: verify existing indexes on (video_id, author_channel_id) support pattern queries efficiently
- [ ] T080 [P] Add performance monitoring: log execution times for all API endpoints exceeding 2s threshold
- [ ] T081 Add loading states to `resources/js/comment-pattern.js`: skeleton loaders during initial load, spinner during infinite scroll
- [ ] T082 Add error state handling in `resources/js/comment-pattern.js`: retry button on fetch failure, user-friendly error messages
- [ ] T083 [P] Verify timezone conversion consistency across all display points: comment list timestamps, filter interactions
- [ ] T084 [P] Code cleanup and refactoring: extract common patterns, remove debug code, add inline documentation
- [ ] T085 Run full test suite: `php artisan test` and verify all tests pass
- [ ] T086 [P] Performance testing: test with video having 10k+ comments, verify <2s statistics load, <1s pagination
- [ ] T087 [P] Manual testing following `specs/009-comments-pattern-summary/quickstart.md` validation checklist
- [ ] T088 Security review: verify no SQL injection vulnerabilities in pattern filtering, sanitize user inputs
- [ ] T089 Accessibility review: verify keyboard navigation works for filter selection, screen reader support for statistics
- [ ] T090 Browser compatibility testing: verify Intersection Observer API works in target browsers (Chrome, Firefox, Safari)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phases 3-7)**: All depend on Foundational phase completion
  - User stories CAN proceed in parallel (if staffed) after Phase 2
  - Or sequentially in priority order: US0 → US1 → US2 → US3 → US4
- **Polish (Phase 8)**: Depends on all desired user stories being complete

### User Story Dependencies

- **US0 (P0) - View All Comments**: Can start after Foundational - No dependencies on other stories
- **US1 (P1) - Repeat Commenters**: Can start after Foundational - No dependencies (extends US0 pattern)
- **US2 (P2) - Night-time Commenters**: Can start after Foundational - No dependencies (extends US0 pattern)
- **US3 (P3) - Aggressive Placeholder**: Can start after Foundational - No dependencies (extends US0 pattern)
- **US4 (P3) - Chinese Placeholder**: Can start after Foundational - No dependencies (extends US0 pattern)

**All user stories are independently testable and can be implemented in parallel**

### Within Each User Story

- Tests MUST be written and FAIL before implementation (TDD)
- Service layer before controller
- Backend implementation before frontend integration
- Core functionality before edge case handling
- Story complete and independently tested before moving to next priority

### Parallel Opportunities

- **Phase 1 (Setup)**: All T002-T005 can run in parallel
- **Phase 2 (Foundational)**:
  - All contract tests (T007-T009) can be written in parallel
  - All query scopes (T012-T014) can be added in parallel
  - API resources (T017-T018) can be created in parallel
  - Frontend assets (T019-T020) can be created in parallel
- **User Stories**: After Phase 2, ALL user stories (US0-US4) can be worked on in parallel by different developers
- **Within User Story Tests**: Tests marked [P] within same story can run in parallel
- **Phase 8 (Polish)**: Most tasks (T077-T080, T083-T084, T086-T087) can run in parallel

---

## Parallel Example: After Foundational Phase

```bash
# Once Phase 2 is complete, launch all user stories in parallel:

# Developer A: User Story 0 (Foundation)
Task: "Implement getPatternStatistics() for 'all' pattern"
Task: "Implement infinite scroll JavaScript"

# Developer B: User Story 1 (Repeat)
Task: "Implement calculateRepeatCommenters()"
Task: "Add repeat filter click handler"

# Developer C: User Story 2 (Night-time)
Task: "Implement calculateNightTimeCommenters() with timezone conversion"
Task: "Add night-time filter click handler"

# Developer D: User Stories 3 & 4 (Placeholders - simpler)
Task: "Implement aggressive placeholder"
Task: "Implement simplified Chinese placeholder"
```

---

## Implementation Strategy

### MVP First (User Story 0 + User Story 1)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 0 (All comments view)
4. Complete Phase 4: User Story 1 (Repeat commenters)
5. **STOP and VALIDATE**: Test US0 and US1 independently
6. Deploy/demo if ready

**Rationale**: US0 provides foundation, US1 adds first real pattern detection (highest business value)

### Incremental Delivery

1. Phase 1 + 2 → Foundation ready
2. Add US0 → Test → Deploy (Basic comment viewing)
3. Add US1 → Test → Deploy (Repeat detection added)
4. Add US2 → Test → Deploy (Night-time detection added)
5. Add US3 + US4 → Test → Deploy (Placeholders for future features)
6. Add Phase 8 → Test → Deploy (Polish and optimization)

Each increment adds value without breaking previous features.

### Parallel Team Strategy

With 3 developers:

1. All complete Phase 1 + 2 together
2. Once Foundational done:
   - Dev A: US0 (foundation for all)
3. Once US0 done:
   - Dev A: US1 (repeat)
   - Dev B: US2 (night-time)
   - Dev C: US3 + US4 (placeholders)
4. All: Phase 8 (polish)

---

## Critical Timezone Handling Notes

**⚠️ MUST DO FOR ALL TASKS INVOLVING TIMESTAMPS**:

### Database Query Filtering (Night-time)
```sql
-- Correct: Convert to GMT+8 FIRST, then extract hour
WHERE HOUR(CONVERT_TZ(published_at, '+00:00', '+08:00')) BETWEEN 1 AND 5

-- Wrong: Extract hour from UTC
WHERE HOUR(published_at) BETWEEN 1 AND 5  -- ❌ This uses UTC!
```

### Display Formatting
```php
// Correct: Convert to Asia/Taipei timezone
$comment->published_at->setTimezone('Asia/Taipei')->format('Y/m/d H:i') . ' (GMT+8)'
```

### Testing Timezone
- Verify test data includes comments at different UTC hours
- Confirm GMT+8 conversion shows correct local times
- Test night-time filtering correctly identifies 01:00-05:59 GMT+8 (not UTC)

---

## Task Count Summary

- **Total Tasks**: 90
- **Setup (Phase 1)**: 5 tasks
- **Foundational (Phase 2)**: 15 tasks
- **User Story 0 (P0)**: 15 tasks
- **User Story 1 (P1)**: 12 tasks
- **User Story 2 (P2)**: 13 tasks
- **User Story 3 (P3)**: 8 tasks
- **User Story 4 (P3)**: 8 tasks
- **Polish (Phase 8)**: 14 tasks

**Parallel Opportunities**: 31 tasks marked [P] can run in parallel within their phase

**Independent Test Criteria Met**: Each user story has clear acceptance tests and can be validated independently

**MVP Recommendation**: Complete through User Story 1 (Phases 1-4) for initial deployment

---

## Notes

- [P] tasks = different files, no dependencies within phase
- [Story] label maps task to specific user story for traceability
- Each user story independently testable and deployable
- Timezone conversion critical for night-time filtering - always use CONVERT_TZ in SQL
- Redis caching essential for cross-channel queries (5-min TTL)
- Follow TDD: Write tests first, watch them fail, implement, watch them pass
- Commit after each task or logical task group
- Stop at any checkpoint to validate story independently
