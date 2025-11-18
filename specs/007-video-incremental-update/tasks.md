# Tasks: Video Incremental Update

**Input**: Design documents from `/specs/007-video-incremental-update/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Included per Constitution Principle I (Test-First Development)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3, US4)
- Include exact file paths in descriptions

## Path Conventions

- Laravel project structure: `app/`, `resources/`, `routes/`, `tests/` at repository root
- Controllers: `app/Http/Controllers/`
- Services: `app/Services/`
- Views: `resources/views/`
- Tests: `tests/Feature/`, `tests/Integration/`, `tests/Unit/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and verification

- [X] T001 Verify YouTube API key is configured in `.env` (YOUTUBE_API_KEY)
- [X] T002 Verify existing tables are accessible: `videos`, `comments`, `channels` (no migrations needed)
- [X] T003 [P] Create contracts directory: `specs/007-video-incremental-update/contracts/`
- [X] T004 [P] Verify Laravel 12.38.1 and PHP 8.2 environment

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core API infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T005 Extend YouTubeApiService: Add `fetchCommentsAfter()` method in `app/Services/YouTubeApiService.php` to support publishedAfter filtering
- [X] T006 Create API routes group in `routes/api.php` for `/api/video-update/*` endpoints
- [X] T007 [P] Create VideoIncrementalUpdateService skeleton in `app/Services/VideoIncrementalUpdateService.php` with constructor dependencies

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - View Update Button and Trigger Modal (Priority: P1) 🎯 MVP

**Goal**: Add "Update" button to Videos List and create modal UI that opens when clicked

**Independent Test**: Navigate to Videos List page, verify each video row has an "Update" button, click it and confirm modal opens with video title displayed

### Tests for User Story 1 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T008 [P] [US1] Feature test: Update button exists on Videos List page in `tests/Feature/VideoIncrementalUpdateTest.php`
- [ ] T009 [P] [US1] Feature test: Clicking Update button opens modal with correct video ID in `tests/Feature/VideoIncrementalUpdateTest.php`
- [ ] T010 [P] [US1] Feature test: Modal closes on ESC key or backdrop click in `tests/Feature/VideoIncrementalUpdateTest.php`

**Run Tests** (should FAIL): `php artisan test --filter=VideoIncrementalUpdate`

### Implementation for User Story 1

- [X] T011 [US1] Create modal Blade component: `resources/views/videos/incremental-update-modal.blade.php` with structure (header, body, footer, close button)
- [X] T012 [US1] Modify Videos List view: Add "更新" button next to each video title in `resources/views/videos/list.blade.php`
- [X] T013 [US1] Add JavaScript function `openUpdateModal(videoId, videoTitle)` to modal component to handle button clicks
- [X] T014 [US1] Implement modal open/close logic (ESC key, backdrop click, close button) in modal JavaScript
- [X] T015 [US1] Include modal component at bottom of `resources/views/videos/list.blade.php` using `@include('videos.incremental-update-modal')`
- [X] T016 [US1] Style "Update" button with Tailwind CSS (green background `bg-green-600`, hover effect)

**Run Tests** (should PASS): `php artisan test --filter=VideoIncrementalUpdate`

**Checkpoint**: Update button functional, modal opens/closes correctly

---

## Phase 4: User Story 2 - Preview New Comments (Priority: P1)

**Goal**: Fetch and display preview of new comments (first 5 chronologically + total count) when modal opens

**Independent Test**: Open update modal for a video, verify it queries database for last comment timestamp, calls YouTube API, and displays preview count ("剩下 X 則留言") with 5 comment previews

### Tests for User Story 2 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T017 [P] [US2] Contract test: Preview API endpoint returns correct JSON schema in `tests/Feature/VideoIncrementalUpdateTest.php`
- [ ] T018 [P] [US2] Integration test: Preview fetches comments after last_comment_time in `tests/Integration/IncrementalImportServiceTest.php`
- [ ] T019 [P] [US2] Unit test: YouTubeApiService.fetchCommentsAfter() with publishedAfter parameter in `tests/Unit/YouTubeApiServiceTest.php`

**Run Tests** (should FAIL): `php artisan test --filter="VideoIncrementalUpdate|IncrementalImport|YouTubeApiService"`

### Implementation for User Story 2

- [X] T020 [P] [US2] Create VideoUpdateController with `preview()` method in `app/Http/Controllers/Api/VideoUpdateController.php`
- [X] T021 [US2] Implement `getPreview(string $videoId): array` method in `app/Services/VideoIncrementalUpdateService.php`
- [X] T022 [US2] Add preview route: `POST /api/video-update/preview` in `routes/api.php`
- [X] T023 [US2] Add preview section to modal: display new comment count and preview list in `resources/views/videos/incremental-update-modal.blade.php`
- [X] T024 [US2] Implement AJAX call to `/api/video-update/preview` in modal JavaScript when modal opens
- [X] T025 [US2] Handle "No new comments" case: display message and disable "Confirm Update" button in modal
- [X] T026 [US2] Handle API errors: display error message with "Retry" button in modal
- [X] T027 [US2] Add loading indicator during preview fetch in modal

**Run Tests** (should PASS): `php artisan test --filter="VideoIncrementalUpdate|IncrementalImport|YouTubeApiService"`

**Checkpoint**: Preview functionality complete, displays new comment count and first 5 comments

---

## Phase 5: User Story 3 - Execute Incremental Import (Priority: P1)

**Goal**: Import all new comments (up to 500 limit) when user clicks "Confirm Update", update database, and refresh UI

**Independent Test**: From preview modal, click "Confirm Update", verify: (1) new comments inserted in `comments` table, (2) `videos.comment_count` updated, (3) `videos.updated_at` set to now(), (4) success message displayed, (5) Videos List row updated without page refresh

### Tests for User Story 3 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T028 [P] [US3] Contract test: Import API endpoint returns correct JSON schema in `tests/Feature/VideoIncrementalUpdateTest.php`
- [ ] T029 [P] [US3] Integration test: Import enforces 500-comment limit in `tests/Integration/IncrementalImportServiceTest.php`
- [ ] T030 [P] [US3] Integration test: Idempotent inserts (concurrent updates) in `tests/Integration/IncrementalImportServiceTest.php`
- [ ] T031 [P] [US3] Feature test: Video.comment_count and Video.updated_at are updated after import in `tests/Feature/VideoIncrementalUpdateTest.php`

**Run Tests** (should FAIL): `php artisan test --filter="VideoIncrementalUpdate|IncrementalImport"`

### Implementation for User Story 3

- [X] T032 [P] [US3] Add `import()` method to VideoUpdateController in `app/Http/Controllers/Api/VideoUpdateController.php`
- [X] T033 [US3] Implement `executeImport(string $videoId): array` method in `app/Services/VideoIncrementalUpdateService.php`
- [X] T034 [US3] Add `importIncrementalComments()` method to CommentImportService in `app/Services/CommentImportService.php` with idempotent insert logic (firstOrCreate)
- [X] T035 [US3] Enforce 500-comment limit in `executeImport()` method with clear user messaging
- [X] T036 [US3] Update `videos.comment_count` by counting comments after import in `executeImport()`
- [X] T037 [US3] Update `videos.updated_at` to `now()` after import in `executeImport()`
- [X] T038 [US3] Add import route: `POST /api/video-update/import` in `routes/api.php`
- [X] T039 [US3] Add "Confirm Update" button click handler in modal JavaScript
- [X] T040 [US3] Implement AJAX call to `/api/video-update/import` with loading indicator in modal
- [X] T041 [US3] Display success message in modal: "成功導入 X 則留言" or partial import message
- [X] T042 [US3] Update Videos List table row dynamically (comment count, last comment time) without page refresh
- [X] T043 [US3] Add logging for each import operation (video_id, imported_count, timestamp) in VideoIncrementalUpdateService
- [X] T044 [US3] Handle import errors: display error message with "Retry" button in modal

**Run Tests** (should PASS): `php artisan test --filter="VideoIncrementalUpdate|IncrementalImport"`

**Checkpoint**: Full incremental import workflow functional, idempotent, with 500-limit

---

## Phase 6: User Story 4 - Truncate Video Titles (Priority: P2)

**Goal**: Truncate video titles to 15 Chinese characters with ellipsis and tooltip

**Independent Test**: View Videos List page, verify titles with >15 Chinese characters show exactly 15 chars + "...", hover to see full title tooltip, click to confirm link still works

### Tests for User Story 4 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T045 [P] [US4] Feature test: Titles ≤15 chars display fully in `tests/Feature/VideoIncrementalUpdateTest.php`
- [ ] T046 [P] [US4] Feature test: Titles >15 chars truncated with "..." in `tests/Feature/VideoIncrementalUpdateTest.php`
- [ ] T047 [P] [US4] Feature test: Tooltip shows full title on hover in `tests/Feature/VideoIncrementalUpdateTest.php`

**Run Tests** (should FAIL): `php artisan test --filter=VideoIncrementalUpdate`

### Implementation for User Story 4

- [X] T048 [US4] Add PHP helper function to truncate Chinese characters using `mb_substr($title, 0, 15)` in `resources/views/videos/list.blade.php`
- [X] T049 [US4] Modify video title display: truncate to 15 chars if longer, append "..." in `resources/views/videos/list.blade.php`
- [X] T050 [US4] Add `title` attribute to title element for tooltip with full untruncated title in `resources/views/videos/list.blade.php`
- [X] T051 [US4] Verify title link still passes full title to Comments List filter (use untruncated `$video->title`)

**Run Tests** (should PASS): `php artisan test --filter=VideoIncrementalUpdate`

**Checkpoint**: All user stories complete, feature fully functional

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [X] T052 [P] Add structured logging to YouTubeApiService for API quota tracking
- [X] T053 [P] Verify datetime format consistency: all `updated_at` writes use `now()` (auto-formats to YYYY-MM-DD HH:MM:SS)
- [X] T054 Code review: Check for SQL injection, XSS vulnerabilities in modal inputs
- [ ] T055 [P] Performance test: Verify preview loads within 3 seconds, import within 60 seconds for 500 comments
- [ ] T056 [P] Browser compatibility test: Test modal in Chrome, Firefox, Safari
- [ ] T057 [P] Manual test: Two users update same video simultaneously (verify no duplicates)
- [ ] T058 Update quickstart.md with actual performance benchmarks from T055
- [X] T059 [P] Documentation: Add API endpoint docs to contracts/preview-api.yaml and contracts/import-api.yaml
- [X] T060 Code cleanup: Remove debug console.log statements, ensure consistent code style

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-6)**: All depend on Foundational phase completion
  - User stories can proceed in parallel (if staffed)
  - Or sequentially in priority order (US1 → US2 → US3 → US4)
- **Polish (Phase 7)**: Depends on all user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - Independent
- **User Story 2 (P1)**: Depends on User Story 1 (modal must exist to show preview) - Should be tested with US1
- **User Story 3 (P1)**: Depends on User Story 2 (preview must work before import) - Should be tested with US1+US2
- **User Story 4 (P2)**: Can start after Foundational (Phase 2) - Independent (can be implemented anytime)

### Within Each User Story

- Tests MUST be written and FAIL before implementation (RED phase)
- Implementation makes tests pass (GREEN phase)
- Tests pass → move to next task
- Story complete before moving to next priority

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel
- All Foundational tasks marked [P] can run in parallel (within Phase 2)
- All tests for a user story marked [P] can run in parallel
- User Story 4 can be implemented in parallel with US1-US3 (independent)
- All Polish tasks marked [P] can run in parallel

---

## Parallel Example: User Story 2

```bash
# Launch all tests for User Story 2 together (RED phase):
Task T017: "Contract test: Preview API endpoint in tests/Feature/VideoIncrementalUpdateTest.php"
Task T018: "Integration test: Preview fetches after last_comment_time in tests/Integration/IncrementalImportServiceTest.php"
Task T019: "Unit test: fetchCommentsAfter() in tests/Unit/YouTubeApiServiceTest.php"

# After tests fail, launch parallel implementation tasks (GREEN phase):
Task T020: "Create VideoUpdateController in app/Http/Controllers/Api/VideoUpdateController.php"
Task T022: "Add preview route in routes/api.php"
Task T027: "Add loading indicator to modal"
```

---

## Implementation Strategy

### MVP First (User Stories 1-3 Only)

1. Complete Phase 1: Setup (verify environment)
2. Complete Phase 2: Foundational (CRITICAL - YouTubeApiService extension)
3. Complete Phase 3: User Story 1 (Update button + modal)
4. Complete Phase 4: User Story 2 (Preview functionality)
5. Complete Phase 5: User Story 3 (Import functionality)
6. **STOP and VALIDATE**: Test complete incremental update workflow
7. Deploy/demo if ready

**MVP Scope**: US1+US2+US3 = Full incremental update workflow
**Optional Enhancement**: US4 = Title truncation (P2 priority)

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test modal opens → Checkpoint
3. Add User Story 2 → Test preview displays → Checkpoint
4. Add User Story 3 → Test import works → Deploy/Demo (MVP!)
5. Add User Story 4 → Test title truncation → Deploy/Demo (enhanced UX)

### TDD Workflow (Constitution Principle I)

For each user story:
1. **RED**: Write tests (T008-T010 for US1), run tests → FAIL ✗
2. **GREEN**: Implement (T011-T016 for US1), run tests → PASS ✓
3. **REFACTOR**: Clean up code, run tests → still PASS ✓
4. **CHECKPOINT**: Story independently testable

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Stories 1+2+3 (core workflow - sequential)
   - Developer B: User Story 4 (title truncation - parallel)
3. Stories integrate and all tests pass

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- **TDD Required**: Write tests FIRST (RED), then implementation (GREEN), then refactor
- Verify tests fail before implementing (critical for TDD validation)
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Database datetime format: `YYYY-MM-DD HH:MM:SS` (Laravel `now()` auto-formats)
- Idempotency: Use `firstOrCreate()` on `comment_id` to prevent duplicates
- 500-comment limit: Enforce in service layer, not controller
- Avoid: vague tasks, same file conflicts, skipping tests

---

## Task Summary

**Total Tasks**: 60
- Setup: 4 tasks
- Foundational: 3 tasks
- User Story 1 (P1): 9 tasks (3 tests + 6 implementation)
- User Story 2 (P1): 11 tasks (3 tests + 8 implementation)
- User Story 3 (P1): 17 tasks (4 tests + 13 implementation)
- User Story 4 (P2): 7 tasks (3 tests + 4 implementation)
- Polish: 9 tasks

**Parallel Tasks**: 25 tasks marked [P] can run in parallel within their phase

**Independent Test Criteria**:
- US1: Modal opens/closes correctly
- US2: Preview displays new comment count and 5 comments
- US3: Import persists comments, updates video fields, refreshes UI
- US4: Titles truncated to 15 Chinese characters with tooltip

**Suggested MVP Scope**: User Stories 1-3 (43 tasks) = Core incremental update workflow
