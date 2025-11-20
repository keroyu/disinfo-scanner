# Tasks: Time-Based Comment Filtering from Chart

**Feature**: 010-time-based-comment-filter
**Input**: Design documents from `/specs/010-time-based-comment-filter/`
**Prerequisites**: Feature 009 (comments-pattern-summary) and Feature 008 (comment-density) must be completed

**Tests**: Test tasks included based on Constitution Principle I (Test-First Development)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3, US4)
- Include exact file paths in descriptions

## Path Conventions

This project uses Laravel MVC structure:
- Backend: `app/Http/Controllers/`, `app/Services/`, `app/Models/`, `app/ValueObjects/`
- Frontend: `public/js/`, `resources/views/`
- Tests: `tests/Unit/`, `tests/Feature/`, `tests/Integration/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and verify Feature 009 dependencies

- [ ] T001 Verify Feature 009 (comments-pattern-summary) is deployed and functional
- [ ] T002 Verify Chart.js 4.4.0 is loaded in resources/views/videos/analysis.blade.php
- [ ] T003 [P] Verify database index on comments.published_at column exists
- [ ] T004 [P] Create app/ValueObjects/ directory if it doesn't exist

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T005 Create TimeRange value object in app/ValueObjects/TimeRange.php with timezone conversion methods
- [ ] T006 Add byTimeRanges() query scope to app/Models/Comment.php for multiple time range filtering
- [ ] T007 Extend CommentPatternService::getCommentsByPattern() in app/Services/CommentPatternService.php to accept optional timePointsIso parameter
- [ ] T008 Extend CommentPatternController::getCommentsByPattern() in app/Http/Controllers/CommentPatternController.php to validate time_points parameter

**Tests for Foundational Components** (Test-First):

- [ ] T009 [P] Write unit tests for TimeRange timezone conversion in tests/Unit/ValueObjects/TimeRangeTest.php (expect FAIL)
- [ ] T010 [P] Write unit tests for Comment::byTimeRanges() scope in tests/Unit/Models/CommentScopeTest.php (expect FAIL)
- [ ] T011 [P] Write contract test for time_points parameter validation in tests/Feature/Api/TimeFilteredCommentsTest.php (expect FAIL)

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Single Time Point Selection (Priority: P0) 🎯 MVP

**Goal**: Enable analysts to click a single time point on the Comments Density chart and view filtered comments from that hour

**Independent Test**: Navigate to video analysis page, click any data point on chart, verify comments panel displays only comments from that hourly time range with time indicator "📍 Showing comments from HH:MM-HH:MM"

### Tests for User Story 1 (Test-First) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T012 [P] [US1] Contract test for single time range API request in tests/Feature/Api/TimeFilteredCommentsTest.php::testSingleTimeRange() (expect FAIL)
- [ ] T013 [P] [US1] Integration test for chart click → API call → comments display in tests/Integration/TimeFilterUITest.php::testSinglePointSelection() (expect FAIL)

### Implementation for User Story 1

- [ ] T014 [P] [US1] Create time-filter.js with TimeFilterState class in public/js/time-filter.js
- [ ] T015 [P] [US1] Add Chart.js onClick handler logic in public/js/time-filter.js
- [ ] T016 [US1] Integrate TimeFilterState with existing CommentPatternUI in public/js/comment-pattern.js
- [ ] T017 [US1] Add time filter indicator UI element to resources/views/videos/analysis.blade.php (above comments panel)
- [ ] T018 [US1] Implement API call with time_points parameter in public/js/time-filter.js::loadTimeFilteredComments()
- [ ] T019 [US1] Add "Clear Selection" button to time filter indicator in resources/views/videos/analysis.blade.php
- [ ] T020 [US1] Test single time point selection end-to-end (verify tests T012, T013 now PASS)

**Checkpoint**: At this point, User Story 1 should be fully functional - analysts can click one chart point and see filtered comments

---

## Phase 4: User Story 2 - Multiple Time Points Selection (Priority: P1)

**Goal**: Enable analysts to select multiple non-contiguous time periods and view combined comments

**Independent Test**: Click 3 different time points on chart (e.g., 08:00, 12:00, 18:00), verify comments panel displays combined results sorted chronologically with indicator "📍 Selected: 3 time periods"

### Tests for User Story 2 (Test-First) ⚠️

- [ ] T021 [P] [US2] Contract test for multiple time ranges API request in tests/Feature/Api/TimeFilteredCommentsTest.php::testMultipleTimeRanges() (expect FAIL)
- [ ] T022 [P] [US2] Integration test for multi-select behavior in tests/Integration/TimeFilterUITest.php::testMultiplePointSelection() (expect FAIL)
- [ ] T023 [P] [US2] Integration test for deselection behavior in tests/Integration/TimeFilterUITest.php::testPointDeselection() (expect FAIL)

### Implementation for User Story 2

- [ ] T024 [US2] Extend TimeFilterState.toggleTimePoint() to handle multi-select logic in public/js/time-filter.js
- [ ] T025 [US2] Update time filter indicator to display multiple time ranges in resources/views/videos/analysis.blade.php
- [ ] T026 [US2] Implement deselection (click selected point again) in public/js/time-filter.js
- [ ] T027 [US2] Add "Clear All" button functionality in public/js/time-filter.js
- [ ] T028 [US2] Test multiple time point selection end-to-end (verify tests T021-T023 now PASS)

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently - analysts can select multiple time ranges

---

## Phase 5: User Story 3 - Visual Highlighting of Selected Points (Priority: P2)

**Goal**: Provide visual feedback by highlighting selected chart points with darker blue color

**Independent Test**: Click 3 chart points, visually confirm all 3 have darker blue fill compared to unselected points; click one again, confirm it returns to light blue

### Tests for User Story 3 (Test-First) ⚠️

- [ ] T029 [P] [US3] Integration test for chart visual highlighting in tests/Integration/ChartHighlightingTest.php::testPointHighlighting() (expect FAIL)
- [ ] T030 [P] [US3] Integration test for highlight removal on deselect in tests/Integration/ChartHighlightingTest.php::testHighlightRemoval() (expect FAIL)

### Implementation for User Story 3

- [ ] T031 [US3] Initialize backgroundColor array for all chart points in public/js/time-filter.js::initializeChartColors()
- [ ] T032 [US3] Implement updateChartHighlighting() function to change point colors in public/js/time-filter.js
- [ ] T033 [US3] Call chartInstance.update('none') after color changes for instant feedback in public/js/time-filter.js
- [ ] T034 [US3] Integrate highlighting with TimeFilterState toggle events in public/js/time-filter.js
- [ ] T035 [US3] Test visual highlighting end-to-end (verify tests T029-T030 now PASS)

**Checkpoint**: All user stories 1-3 should now be functional with full visual feedback

---

## Phase 6: User Story 4 - Combine Time Filter with Pattern Filters (Priority: P3)

**Goal**: Enable analysts to combine time filtering with existing pattern filters (repeat commenters, night-time, etc.) using AND logic

**Independent Test**: Select 2 time points (02:00, 04:00), then click "重複留言者" filter, verify only repeat commenters from those time ranges appear

### Tests for User Story 4 (Test-First) ⚠️

- [ ] T036 [P] [US4] Contract test for combined pattern + time filtering in tests/Feature/Api/TimeFilteredCommentsTest.php::testCombinedPatternAndTimeFilter() (expect FAIL)
- [ ] T037 [P] [US4] Integration test for time + pattern filter interaction in tests/Integration/CombinedFilteringTest.php::testTimeFilterMaintainedOnPatternChange() (expect FAIL)
- [ ] T038 [P] [US4] Integration test for pattern filter maintained on time change in tests/Integration/CombinedFilteringTest.php::testPatternFilterMaintainedOnTimeChange() (expect FAIL)

### Implementation for User Story 4

- [ ] T039 [US4] Modify CommentPatternService to apply both pattern AND time filters in app/Services/CommentPatternService.php
- [ ] T040 [US4] Update FilterState to track both pattern and time selections in public/js/time-filter.js::FilterState class
- [ ] T041 [US4] Modify CommentPatternUI to include time_points in API calls when active in public/js/comment-pattern.js
- [ ] T042 [US4] Update time filter indicator to show combined mode in resources/views/videos/analysis.blade.php
- [ ] T043 [US4] Implement "Clear Time Selection" (keep pattern) functionality in public/js/time-filter.js
- [ ] T044 [US4] Implement "Clear Pattern" (keep time) functionality in public/js/comment-pattern.js
- [ ] T045 [US4] Test combined filtering end-to-end (verify tests T036-T038 now PASS)

**Checkpoint**: All user stories should now be independently functional with full integration

---

## Phase 7: Performance & Validation

**Purpose**: Enforce performance limits and validate behavior

### Tests for Performance Limits (Test-First) ⚠️

- [ ] T046 [P] Contract test for 21 time points rejection in tests/Feature/Api/TimeFilteredCommentsTest.php::testTooManyTimePoints() (expect FAIL)
- [ ] T047 [P] Integration test for warning at 15 selections in tests/Integration/PerformanceLimitsTest.php::testWarningAt15Selections() (expect FAIL)
- [ ] T048 [P] Integration test for hard limit at 20 selections in tests/Integration/PerformanceLimitsTest.php::testHardLimitAt20Selections() (expect FAIL)

### Implementation for Performance Limits

- [ ] T049 [US2] Add 20-point validation in backend CommentPatternController in app/Http/Controllers/CommentPatternController.php
- [ ] T050 [US2] Add frontend limit enforcement in TimeFilterState.toggleTimePoint() in public/js/time-filter.js
- [ ] T051 [US2] Display warning message at 15 selections in public/js/time-filter.js
- [ ] T052 [US2] Display error message and prevent selection at 20 points in public/js/time-filter.js
- [ ] T053 Test performance limits (verify tests T046-T048 now PASS)

---

## Phase 8: Edge Cases & Empty States

**Purpose**: Handle edge cases and empty states gracefully

### Tests for Edge Cases (Test-First) ⚠️

- [ ] T054 [P] Integration test for empty time range in tests/Integration/EdgeCasesTest.php::testEmptyTimeRange() (expect FAIL)
- [ ] T055 [P] Integration test for time range change clears selection in tests/Integration/EdgeCasesTest.php::testTimeRangeSwitchClearsSelection() (expect FAIL)
- [ ] T056 [P] Contract test for invalid timestamp format in tests/Feature/Api/TimeFilteredCommentsTest.php::testInvalidTimestampFormat() (expect FAIL)

### Implementation for Edge Cases

- [ ] T057 Display appropriate empty state message when selected time has no comments in public/js/time-filter.js
- [ ] T058 Clear time selection when user switches time range view (24h/3d/7d) in public/js/time-filter.js
- [ ] T059 Add backend validation for ISO timestamp format in app/Http/Controllers/CommentPatternController.php
- [ ] T060 Test edge cases (verify tests T054-T056 now PASS)

---

## Phase 9: Logging & Observability

**Purpose**: Implement structured logging per Constitution Principle III

- [ ] T061 [P] Add structured logging for time filter requests in app/Http/Controllers/CommentPatternController.php (log video_id, time_points_count, execution_time)
- [ ] T062 [P] Add logging for timezone conversions in app/ValueObjects/TimeRange.php (log input GMT+8, output UTC)
- [ ] T063 [P] Add performance metrics logging for multi-range queries in app/Services/CommentPatternService.php

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] T064 [P] Update API documentation with time_points parameter in specs/010-time-based-comment-filter/contracts/time-filtered-comments-api.md (if changes needed)
- [ ] T065 [P] Code cleanup and refactoring in public/js/time-filter.js
- [ ] T066 [P] Performance optimization: Test 20 time ranges with 10,000 comments dataset
- [ ] T067 [P] Run quickstart.md manual test scenarios (6 test cases)
- [ ] T068 [P] Verify backward compatibility (API without time_points still works)
- [ ] T069 Security review: SQL injection prevention for time range queries
- [ ] T070 Final integration test: Complete user workflow from chart click to filtered results

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phases 3-6)**: All depend on Foundational phase completion
  - US1 (P0): Can start after Foundational - No dependencies on other stories
  - US2 (P1): Can start after Foundational - Extends US1 but independently testable
  - US3 (P2): Can start after Foundational - Enhances US1/US2 but independently testable
  - US4 (P3): Can start after Foundational - Integrates with Feature 009 patterns
- **Performance (Phase 7)**: Depends on US2 (multi-select) being implemented
- **Edge Cases (Phase 8)**: Can start after US1/US2 are functional
- **Logging (Phase 9)**: Can run in parallel with any user story implementation
- **Polish (Phase 10)**: Depends on all user stories being complete

### User Story Dependencies

- **User Story 1 (US1 - P0)**: Can start after Foundational (Phase 2) - No dependencies on other stories ✅ MVP
- **User Story 2 (US2 - P1)**: Can start after Foundational (Phase 2) - Extends US1 logic but independently testable
- **User Story 3 (US3 - P2)**: Can start after Foundational (Phase 2) - Adds visual feedback to US1/US2
- **User Story 4 (US4 - P3)**: Can start after Foundational (Phase 2) - Integrates time filter with existing pattern filters from Feature 009

### Within Each User Story

- Tests MUST be written and FAIL before implementation (TDD)
- Value objects before models
- Models before services
- Services before controllers
- Core implementation before UI integration
- Story complete and tests passing before moving to next priority

### Parallel Opportunities

- **Setup Phase**: All tasks marked [P] (T003, T004) can run in parallel
- **Foundational Tests**: All test tasks (T009, T010, T011) can run in parallel
- **User Story Tests**: All tests within a story marked [P] can run in parallel
- **User Story Implementation**: T014 and T015 can run in parallel (different concerns)
- **Multiple User Stories**: Once Foundational phase completes, US1, US2, US3, US4 can be worked on in parallel by different developers
- **Logging Phase**: All logging tasks (T061, T062, T063) can run in parallel
- **Polish Phase**: Most tasks marked [P] can run in parallel

---

## Parallel Example: User Story 1 Implementation

```bash
# After Foundational phase completes:

# 1. Launch all tests for US1 together (Test-First):
Task: "Contract test for single time range in tests/Feature/Api/TimeFilteredCommentsTest.php::testSingleTimeRange()"
Task: "Integration test for chart click in tests/Integration/TimeFilterUITest.php::testSinglePointSelection()"

# 2. Launch parallel implementation tasks for US1:
Task: "Create time-filter.js with TimeFilterState in public/js/time-filter.js" (T014)
Task: "Add Chart.js onClick handler in public/js/time-filter.js" (T015)

# 3. Sequential tasks (depend on T014, T015):
Task: "Integrate TimeFilterState with CommentPatternUI" (T016)
Task: "Add time filter indicator UI" (T017)
Task: "Implement API call with time_points" (T018)
Task: "Add Clear Selection button" (T019)
Task: "Test end-to-end" (T020)
```

---

## Parallel Example: Multiple User Stories

```bash
# After Foundational phase completes, multiple developers can work in parallel:

Developer A: User Story 1 (T012-T020) - Core time filtering
Developer B: User Story 2 (T021-T028) - Multi-select extension
Developer C: User Story 3 (T029-T035) - Visual highlighting
Developer D: User Story 4 (T036-T045) - Combined filtering

# Each developer works independently and tests their story
# Stories integrate naturally through shared FilterState interface
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001-T004)
2. Complete Phase 2: Foundational (T005-T011) - CRITICAL checkpoint
3. Complete Phase 3: User Story 1 (T012-T020)
4. **STOP and VALIDATE**: Test US1 independently with quickstart.md scenarios
5. Deploy/demo if ready - Analysts can now filter by single time point

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → **Deploy/Demo (MVP!)**
3. Add User Story 2 → Test independently → Deploy/Demo (multi-select)
4. Add User Story 3 → Test independently → Deploy/Demo (visual feedback)
5. Add User Story 4 → Test independently → Deploy/Demo (combined filters)
6. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together (T001-T011)
2. Once Foundational is done:
   - Developer A: User Story 1 (T012-T020) - Core feature
   - Developer B: User Story 2 (T021-T028) - Multi-select
   - Developer C: User Story 3 (T029-T035) - Visual polish
   - Developer D: User Story 4 (T036-T045) - Integration
3. Stories complete and integrate independently
4. Final integration testing and polish (Phase 10)

---

## Backward Compatibility Validation

**CRITICAL**: This feature extends Feature 009 API. Must verify:

- [ ] Existing API calls without `time_points` parameter still work (T068)
- [ ] Response structure unchanged when `time_points` not provided
- [ ] Pattern filters function identically without time filtering
- [ ] All Feature 009 tests still pass after Feature 010 deployment

---

## Timezone Handling Checklist

Per Constitution Principle VI, verify for each task touching timestamps:

- [ ] Database queries use UTC (T006, T007, T039)
- [ ] Frontend sends GMT+8 ISO timestamps (T018, T041)
- [ ] Backend converts GMT+8 → UTC for queries (T005, T007)
- [ ] Backend converts UTC → GMT+8 for responses (T007, T039)
- [ ] Frontend displays times with "(GMT+8)" indicator (T017, T025)
- [ ] Tests validate timezone conversions (T009, T012, T021)

---

## Notes

- [P] tasks = different files or independent concerns, no dependencies
- [Story] label (US1, US2, US3, US4) maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Verify tests fail (RED) before implementing (GREEN)
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence
- All file paths are absolute from repository root
- Follow Laravel MVC patterns from Feature 009 for consistency

---

## Task Count Summary

- **Total Tasks**: 70
- **Setup Phase**: 4 tasks
- **Foundational Phase**: 7 tasks (3 tests + 4 implementation)
- **User Story 1 (P0 - MVP)**: 9 tasks (2 tests + 7 implementation)
- **User Story 2 (P1)**: 8 tasks (3 tests + 5 implementation)
- **User Story 3 (P2)**: 7 tasks (2 tests + 5 implementation)
- **User Story 4 (P3)**: 9 tasks (3 tests + 6 implementation)
- **Performance Phase**: 8 tasks (3 tests + 5 implementation)
- **Edge Cases Phase**: 7 tasks (3 tests + 4 implementation)
- **Logging Phase**: 3 tasks
- **Polish Phase**: 7 tasks

**Parallel Opportunities**: 23 tasks marked [P] can run in parallel within their phases

**MVP Scope**: Phases 1-3 only (T001-T020) = 20 tasks for core single time point filtering
