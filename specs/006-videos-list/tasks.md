# Tasks: Videos List

**Input**: Design documents from `/specs/006-videos-list/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/videos-api.md

**Tests**: Tests are included per plan.md (Test-First Development principle)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3, US4)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic route structure

- [X] T001 Add route `GET /videos` to routes/web.php with VideoController@index

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core Video model enhancements that ALL user stories depend on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T002 [P] Add query scope `scopeWithCommentStats` to app/Models/Video.php (computes actual_comment_count and last_comment_time using subqueries)
- [X] T003 [P] Add query scope `scopeHasComments` to app/Models/Video.php (filters videos with comment count > 0)
- [X] T004 [P] Add query scope `scopeSearchByKeyword` to app/Models/Video.php (case-insensitive search in title and channel name)
- [X] T005 [P] Add query scope `scopeSortByColumn` to app/Models/Video.php (dynamic sorting with whitelist validation)

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - View All Videos with Comment Activity (Priority: P1) 🎯 MVP

**Goal**: Display a comprehensive list of all videos that have comments, showing channel name, title, comment count, and last comment time with default sorting by publication date.

**Independent Test**: Navigate to /videos and verify all videos with comments are displayed with accurate data in all columns, sorted by publication date (newest first) by default.

### Tests for User Story 1

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [X] T006 [P] [US1] Feature test: Videos list page displays correctly with default sort in tests/Feature/VideosListTest.php
- [X] T007 [P] [US1] Feature test: Only videos with comments are shown in tests/Feature/VideosListTest.php
- [X] T008 [P] [US1] Feature test: Comment counts are accurate in tests/Feature/VideosListTest.php
- [X] T009 [P] [US1] Feature test: Last comment time displays correctly in YYYY-MM-DD HH:MM format in tests/Feature/VideosListTest.php
- [X] T010 [P] [US1] Feature test: Sort by clicking column headers works (comment count, last comment time) in tests/Feature/VideosListTest.php
- [X] T011 [P] [US1] Feature test: Sort direction toggles between asc/desc on repeated clicks in tests/Feature/VideosListTest.php

### Implementation for User Story 1

- [X] T012 [US1] Create VideoController with index method in app/Http/Controllers/VideoController.php (implements query building with withCommentStats, hasComments, sortByColumn, and pagination with 500 per page)
- [X] T013 [US1] Add request validation in VideoController@index for search, sort, direction, and page parameters
- [X] T014 [US1] Create Blade view resources/views/videos/list.blade.php with table structure matching Comments List styling
- [X] T015 [US1] Implement table columns in list.blade.php: Channel Name, Video Title, Comment Count, Last Comment Time
- [X] T016 [US1] Add sortable column headers with visual indicators (arrows) in list.blade.php
- [X] T017 [US1] Add pagination controls with appends(request()->query()) in list.blade.php
- [X] T018 [US1] Add empty state handling ("No videos found") in list.blade.php
- [X] T019 [US1] Add missing channel fallback ("Unknown Channel") in list.blade.php

**Checkpoint**: At this point, User Story 1 should be fully functional - users can view and sort videos list with accurate data

---

## Phase 4: User Story 2 - Navigate from Video to Related Comments (Priority: P1)

**Goal**: Enable users to click on channel name, video title, or last comment time to navigate to the Comments List with appropriate filters pre-filled.

**Independent Test**: Click each clickable element (channel name, video title, last comment time) and verify the Comments List opens with correct search filters applied.

### Tests for User Story 2

- [X] T020 [P] [US2] Feature test: Clicking channel name redirects to Comments List with channel filter in tests/Feature/VideosListTest.php
- [X] T021 [P] [US2] Feature test: Clicking video title redirects to Comments List with title search in tests/Feature/VideosListTest.php
- [X] T022 [P] [US2] Feature test: Clicking last comment time redirects with 90-day date range in tests/Feature/VideosListTest.php
- [X] T023 [P] [US2] Feature test: Navigation URLs are correctly URL-encoded for special characters in tests/Feature/VideosListTest.php

### Implementation for User Story 2

- [X] T024 [US2] Add clickable channel name links in list.blade.php (route to /comments?search_channel={channel_name})
- [X] T025 [US2] Add clickable video title links in list.blade.php (route to /comments?search={video_title})
- [X] T026 [US2] Add clickable last comment time links in list.blade.php with 90-day date range calculation using Carbon (route to /comments?search={title}&from_date={date-90d}&to_date={date})
- [X] T027 [US2] Add URL encoding for search parameters in all navigation links

**Checkpoint**: At this point, User Stories 1 AND 2 should both work - users can view videos and navigate to related comments

---

## Phase 5: User Story 3 - Search and Filter Videos (Priority: P2)

**Goal**: Enable users to search videos by keywords (matching title or channel name) and filter results with Apply/Clear buttons.

**Independent Test**: Enter search terms, click Apply Filters, and verify results match search criteria. Click Clear Filters and verify all videos are shown again.

### Tests for User Story 3

- [X] T028 [P] [US3] Feature test: Search by keyword filters videos correctly (case-insensitive) in tests/Feature/VideosListTest.php
- [X] T029 [P] [US3] Feature test: Search matches both video title and channel name in tests/Feature/VideosListTest.php
- [X] T030 [P] [US3] Feature test: Clear Filters button resets search and shows all videos in tests/Feature/VideosListTest.php
- [X] T031 [P] [US3] Feature test: Pagination preserves search parameters in tests/Feature/VideosListTest.php

### Implementation for User Story 3

- [X] T032 [US3] Add search form UI in list.blade.php (keyword input field matching Comments List styling)
- [X] T033 [US3] Add "Apply Filters" button in list.blade.php (submits form with GET method)
- [X] T034 [US3] Add "Clear Filters" button in list.blade.php (links to /videos without query parameters)
- [X] T035 [US3] Integrate searchByKeyword scope in VideoController@index when search parameter present
- [X] T036 [US3] Ensure pagination links preserve search parameters using appends()

**Checkpoint**: At this point, User Stories 1, 2, AND 3 should all work independently - full search and filtering functionality

---

## Phase 6: User Story 4 - Access Videos List from Navigation (Priority: P2)

**Goal**: Add "Videos List" link to main navigation menu, positioned to the right of "Channels List" link, with active state highlighting.

**Independent Test**: View navigation menu and verify "Videos List" link appears in correct position, clicking it opens the Videos List page, and the link is highlighted when on that page.

### Tests for User Story 4

- [ ] T037 [P] [US4] Browser test: Navigation link exists to the right of Channels List in tests/Browser/VideosListBrowserTest.php
- [ ] T038 [P] [US4] Browser test: Clicking Videos List link navigates to /videos in tests/Browser/VideosListBrowserTest.php
- [ ] T039 [P] [US4] Browser test: Videos List link is visually highlighted when on /videos page in tests/Browser/VideosListBrowserTest.php

### Implementation for User Story 4

- [X] T040 [US4] Add "Videos List" link to navigation in resources/views/layouts/app.blade.php (positioned after Channels List link)
- [X] T041 [US4] Add active state highlighting logic for Videos List link using request()->is('videos*')

**Checkpoint**: All user stories should now be independently functional - complete navigation integration

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Visual consistency, performance validation, and final quality checks

- [ ] T042 [P] Browser test: Visual consistency with Comments List (matching card styles, table layout, colors, spacing) in tests/Browser/VideosListBrowserTest.php
- [ ] T043 [P] Feature test: Pagination displays 500 videos per page in tests/Feature/VideosListLayoutTest.php
- [ ] T044 [P] Feature test: Page handles missing channel data gracefully in tests/Feature/VideosListLayoutTest.php
- [ ] T045 Performance validation: Verify page load < 2s for 10,000 videos using Laravel Debugbar
- [ ] T046 Code review: Ensure all Blade templates use {{ }} for XSS protection
- [ ] T047 Run quickstart.md validation: Test all user workflows described in quickstart.md

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-6)**: All depend on Foundational phase completion
  - User stories can then proceed in parallel (if staffed)
  - Or sequentially in priority order (US1 → US2 → US3 → US4)
- **Polish (Phase 7)**: Depends on all user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P1)**: Can start after Foundational (Phase 2) - Modifies same Blade template as US1, should run after US1 or coordinate carefully
- **User Story 3 (P2)**: Can start after Foundational (Phase 2) - Modifies same controller and view as US1/US2, best to run after US1/US2
- **User Story 4 (P2)**: Can start independently after Foundational - Works on different file (layouts/app.blade.php)

### Within Each User Story

- Tests MUST be written and FAIL before implementation
- Scopes (Phase 2) before controller logic
- Controller before views
- Basic view structure before interactive elements (links, forms)
- Story complete before moving to next priority

### Parallel Opportunities

**Phase 2 (Foundational)**: All 4 scope additions (T002-T005) can run in parallel

**Phase 3 (US1 Tests)**: All 6 tests (T006-T011) can run in parallel

**Phase 4 (US2 Tests)**: All 4 tests (T020-T023) can run in parallel

**Phase 5 (US3 Tests)**: All 4 tests (T028-T031) can run in parallel

**Phase 6 (US4 Tests)**: All 3 tests (T037-T039) can run in parallel

**Phase 7 (Polish)**: Tests T042-T044 can run in parallel

---

## Parallel Example: Foundational Phase

```bash
# Launch all foundational scope additions together:
Task: "Add query scope scopeWithCommentStats to app/Models/Video.php"
Task: "Add query scope scopeHasComments to app/Models/Video.php"
Task: "Add query scope scopeSearchByKeyword to app/Models/Video.php"
Task: "Add query scope scopeSortByColumn to app/Models/Video.php"
```

## Parallel Example: User Story 1 Tests

```bash
# Launch all tests for User Story 1 together:
Task: "Feature test: Videos list page displays correctly with default sort"
Task: "Feature test: Only videos with comments are shown"
Task: "Feature test: Comment counts are accurate"
Task: "Feature test: Last comment time displays correctly"
Task: "Feature test: Sort by clicking column headers works"
Task: "Feature test: Sort direction toggles between asc/desc"
```

---

## Implementation Strategy

### MVP First (User Stories 1 & 2 Only)

1. Complete Phase 1: Setup (T001)
2. Complete Phase 2: Foundational (T002-T005) - CRITICAL - blocks all stories
3. Complete Phase 3: User Story 1 (T006-T019)
4. Complete Phase 4: User Story 2 (T020-T027)
5. **STOP and VALIDATE**: Test basic videos list viewing and navigation
6. Deploy/demo if ready

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → Core functionality working (view and sort videos)
3. Add User Story 2 → Test independently → Navigation integration complete (MVP!)
4. Add User Story 3 → Test independently → Search functionality added
5. Add User Story 4 → Test independently → Full navigation integration
6. Polish Phase → Final quality checks → Production ready

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together (T001-T005)
2. Once Foundational is done:
   - Developer A: User Story 1 (T006-T019)
   - Developer B: User Story 4 (T037-T041) - Different file, no conflicts
3. After US1 complete:
   - Developer A: User Story 2 (T020-T027) - Builds on US1 template
   - Developer B: User Story 3 (T028-T036) - Can coordinate with A
4. Polish tasks can be distributed

---

## Notes

- [P] tasks = different files or test cases, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Verify tests fail before implementing
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- **CRITICAL**: Phase 2 scopes must all be complete before any controller/view work begins
- Follow Test-First Development: Write failing tests before implementation
- Match Comments List visual design throughout implementation
