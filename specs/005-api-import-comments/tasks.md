# Tasks: YouTube API 官方導入留言

**Branch**: `005-api-import-comments` | **Date**: 2025-11-17
**Input**: Design documents from `/specs/005-api-import-comments/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Tests are REQUIRED per Constitutional Principle I (Test-First Development) and Principle IV (Contract Testing). All tests must be written BEFORE implementation.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3, US4)
- Include exact file paths in descriptions

## Path Conventions

This is a Laravel 11.x web application with the following structure:
- **Backend**: `app/` (Controllers, Services, Models, Jobs)
- **Frontend**: `resources/views/` (Blade templates, components)
- **Routes**: `routes/api.php`, `routes/web.php`
- **Migrations**: `database/migrations/`
- **Tests**: `tests/` (Feature, Unit, Contract)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization, dependencies, and database schema

- [ ] T001 Install Google API PHP Client via composer: `composer require google/apiclient`
- [ ] T002 Configure YouTube API key in `.env` file (add `YOUTUBE_API_KEY=` entry)
- [ ] T003 Create migration for `videos.comment_count` field: `database/migrations/2025_11_17_add_comment_count_to_videos.php`
- [ ] T004 Create migration for `comments.parent_comment_id` field: `database/migrations/2025_11_17_add_parent_id_to_comments.php`
- [ ] T005 Run database migrations: `php artisan migrate`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T006 Create base YouTube API client service skeleton in `app/Services/YoutubeApiClient.php`
- [ ] T007 Implement YouTube API authentication and connection in `app/Services/YoutubeApiClient.php`
- [ ] T008 Create comment import service skeleton in `app/Services/CommentImportService.php`
- [ ] T009 Create channel tag manager service skeleton in `app/Services/ChannelTagManager.php`
- [ ] T010 Create import comments controller skeleton in `app/Http/Controllers/Api/ImportCommentsController.php`
- [ ] T011 [P] Update Video model with `comment_count` field accessor in `app/Models/Video.php`
- [ ] T012 [P] Update Channel model with `last_import_at` field and tag relationship in `app/Models/Channel.php`
- [ ] T013 [P] Update Comment model with `parent_comment_id` relationship in `app/Models/Comment.php`
- [ ] T014 Add API routes for check and import endpoints in `routes/api.php`

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - 檢查影片是否已建檔 (Priority: P1) 🎯 MVP

**Goal**: User inputs YouTube video URL and system checks if video exists in database, determining the correct workflow path

**Independent Test**: Input various video URLs and verify system correctly returns "video exists", "new video + existing channel", or "new video + new channel" responses

### Tests for User Story 1 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T015 [P] [US1] Create contract test for `/api/comments/check` endpoint in `tests/Contract/CheckVideoContractTest.php`
- [ ] T016 [P] [US1] Create feature test for video existence check in `tests/Feature/CheckVideoExistenceTest.php`
- [ ] T017 [P] [US1] Create unit test for YouTube URL parsing in `tests/Unit/YoutubeUrlParserTest.php`

### Implementation for User Story 1

- [ ] T018 [US1] Implement URL parsing logic in `app/Services/YoutubeApiClient.php` (extractVideoId method)
- [ ] T019 [US1] Implement video existence check in database in `app/Services/CommentImportService.php` (checkVideoExists method)
- [ ] T020 [US1] Implement channel existence check in database in `app/Services/CommentImportService.php` (checkChannelExists method)
- [ ] T021 [US1] Implement `check()` method in `app/Http/Controllers/Api/ImportCommentsController.php` per contract/check-video.md
- [ ] T022 [US1] Add validation for YouTube URL formats (youtu.be and youtube.com/watch) in controller
- [ ] T023 [US1] Add error handling for invalid URLs (return 400 Bad Request per contract)
- [ ] T024 [US1] Add error handling for video already exists case (return "video_exists" status per contract)
- [ ] T025 [US1] Add logging for check operations using Laravel Log facade

**Checkpoint**: At this point, User Story 1 should be fully functional - can check if video exists and return appropriate status

---

## Phase 4: User Story 2 - 新影片+已存在頻道的導入流程 (Priority: P1)

**Goal**: Import new video with comments when channel already exists in database, allowing user to modify channel tags

**Independent Test**: Input URL of new video from existing channel, verify preview displays correctly with editable tags, confirm import completes successfully

### Tests for User Story 2 ⚠️

- [ ] T026 [P] [US2] Create contract test for preview data format (existing channel scenario) in `tests/Contract/PreviewExistingChannelTest.php`
- [ ] T027 [P] [US2] Create feature test for existing channel import workflow in `tests/Feature/ImportExistingChannelVideoTest.php`
- [ ] T028 [P] [US2] Create unit test for channel tag retrieval in `tests/Unit/ChannelTagManagerTest.php`

### Implementation for User Story 2

- [ ] T029 [P] [US2] Implement `getVideoMetadata()` method in `app/Services/YoutubeApiClient.php` (fetch video title, published_at)
- [ ] T030 [P] [US2] Implement `getChannelMetadata()` method in `app/Services/YoutubeApiClient.php` (fetch channel title)
- [ ] T031 [P] [US2] Implement `getPreviewComments()` method in `app/Services/YoutubeApiClient.php` (fetch latest 5 comments)
- [ ] T032 [US2] Enhance `check()` method to fetch preview data when video doesn't exist (integrate T029-T031)
- [ ] T033 [US2] Implement `getChannelTags()` method in `app/Services/ChannelTagManager.php` (retrieve existing tags for channel)
- [ ] T034 [US2] Add existing channel tag data to check response per contract/check-video.md scenario 2
- [ ] T035 [US2] Add timestamp formatting (ISO 8601 → 'Y-m-d H:i:s' format) in API client
- [ ] T036 [US2] Add comment count retrieval from YouTube API statistics
- [ ] T037 [US2] Add error handling for YouTube API failures (429 quota, timeout, network errors)
- [ ] T038 [US2] Add retry mechanism with exponential backoff for API errors

**Checkpoint**: At this point, User Story 2 preview functionality works - can display preview data for existing channel scenario

---

## Phase 5: User Story 3 - 新影片+新頻道的導入流程 (Priority: P1)

**Goal**: Import new video with comments when both video and channel are new to database, requiring user to select channel tags

**Independent Test**: Input URL of video from completely new channel, verify preview displays with tag selector, confirm all data imports correctly

### Tests for User Story 3 ⚠️

- [ ] T039 [P] [US3] Create contract test for preview data format (new channel scenario) in `tests/Contract/PreviewNewChannelTest.php`
- [ ] T040 [P] [US3] Create feature test for new channel import workflow in `tests/Feature/ImportNewChannelVideoTest.php`
- [ ] T041 [P] [US3] Create unit test for tag validation in `tests/Unit/TagValidationTest.php`

### Implementation for User Story 3

- [ ] T042 [US3] Implement `getAllTags()` method in `app/Services/ChannelTagManager.php` (fetch all available tags with colors)
- [ ] T043 [US3] Enhance `check()` method to include available tags when channel doesn't exist
- [ ] T044 [US3] Add available tags data to check response per contract/check-video.md scenario 3
- [ ] T045 [US3] Add validation for "at least 1 tag required" for new channels

**Checkpoint**: At this point, User Story 3 preview functionality works - can display preview data for new channel scenario

---

## Phase 6: Import Implementation (Core Logic for US2 & US3)

**Purpose**: Implement the actual database import logic used by both User Stories 2 and 3

**Goal**: Complete the full import workflow including API fetching, transaction management, and database persistence

### Tests for Import Logic ⚠️

- [ ] T046 [P] Create contract test for `/api/comments/import` endpoint in `tests/Contract/ImportCommentsContractTest.php`
- [ ] T047 [P] Create feature test for full import transaction in `tests/Feature/ImportTransactionTest.php`
- [ ] T048 [P] Create unit test for recursive comment fetching in `tests/Unit/RecursiveCommentFetchTest.php`
- [ ] T049 [P] Create unit test for comment count calculation in `tests/Unit/CommentCountCalculationTest.php`
- [ ] T050 [P] Create feature test for transaction rollback on failure in `tests/Feature/TransactionRollbackTest.php`

### Implementation for Import

- [ ] T051 [P] Implement `getAllComments()` method in `app/Services/YoutubeApiClient.php` (fetch all comments, paginated)
- [ ] T052 [P] Implement `getCommentReplies()` method in `app/Services/YoutubeApiClient.php` (fetch replies for a comment)
- [ ] T053 Implement recursive reply fetching with depth limit (MAX_DEPTH = 3) in `app/Services/CommentImportService.php`
- [ ] T054 Implement `importChannel()` method in `app/Services/CommentImportService.php` (firstOrCreate logic)
- [ ] T055 Implement `importVideo()` method in `app/Services/CommentImportService.php` (create with null comment_count)
- [ ] T056 Implement `importComments()` method in `app/Services/CommentImportService.php` (insert all comments/replies)
- [ ] T057 Implement comment_count calculation and update in `app/Services/CommentImportService.php`
- [ ] T058 Implement `syncChannelTags()` method in `app/Services/ChannelTagManager.php` (update channel_tags pivot table)
- [ ] T059 Implement database transaction wrapper for 3-stage import in `app/Services/CommentImportService.php`
- [ ] T060 Implement `import()` method in `app/Http/Controllers/Api/ImportCommentsController.php` per contract/import-comments.md
- [ ] T061 Add validation for import request (scenario, channel_tags, import_replies) in controller
- [ ] T062 Add tag validation (ensure all tag IDs exist in database) in controller
- [ ] T063 Add validation for "at least 1 tag" when scenario is new_video_new_channel
- [ ] T064 Implement channels.last_import_at timestamp update on successful import
- [ ] T065 Add error handling for partial import failures (return partial_success status per contract)
- [ ] T066 Add error handling for complete import failures with transaction rollback
- [ ] T067 Add logging for all import stages (channel, video, comments, comment_count)
- [ ] T068 Add API quota exhaustion handling (catch 429 errors, return retry message)

**Checkpoint**: At this point, full import functionality works - can import complete video data including recursive comments

---

## Phase 7: User Story 4 - UI 整合與入口 (Priority: P1)

**Goal**: Provide UI entry point with modal for users to input video URL and manage import workflow

**Independent Test**: Click "官方API導入" button on comments page, verify modal opens with URL input, test full workflow from URL input to success message

### Tests for User Story 4 ⚠️

- [ ] T069 [P] [US4] Create browser test for modal open/close in `tests/Browser/ImportModalTest.php` (if using Laravel Dusk)
- [ ] T070 [P] [US4] Create feature test for modal integration in `tests/Feature/ModalIntegrationTest.php`

### Implementation for User Story 4

- [ ] T071 [US4] Create modal Blade component in `resources/views/components/import-comments-modal.blade.php` per quickstart.md
- [ ] T072 [US4] Add Alpine.js data structure for modal state management (open, step, videoUrl, etc.)
- [ ] T073 [US4] Implement URL input form with validation in modal component
- [ ] T074 [US4] Implement "檢查是否建檔" button with AJAX call to `/api/comments/check`
- [ ] T075 [US4] Implement preview display for existing channel scenario (show channel/video info, tags, 5 comments)
- [ ] T076 [US4] Implement preview display for new channel scenario (show channel/video info, available tags selector, 5 comments)
- [ ] T077 [US4] Implement editable tag checkboxes for existing channel (pre-populate with current tags)
- [ ] T078 [US4] Implement required tag checkboxes for new channel (at least 1 required validation)
- [ ] T079 [US4] Implement "確認導入" button with AJAX call to `/api/comments/import`
- [ ] T080 [US4] Implement success message display with import count
- [ ] T081 [US4] Implement error message display with retry button
- [ ] T082 [US4] Implement loading/importing spinner during API operations
- [ ] T083 [US4] Implement ESC key handler to close modal
- [ ] T084 [US4] Add "官方API導入" button to comments list page header in `resources/views/pages/comments.blade.php`
- [ ] T085 [US4] Include modal component in comments page template
- [ ] T086 [US4] Implement AJAX list refresh after successful import (dynamic update without page reload)
- [ ] T087 [US4] Add Tailwind CSS styling to modal (consistent with existing UI)
- [ ] T088 [US4] Add responsive design for modal (mobile/tablet/desktop)

**Checkpoint**: At this point, User Story 4 UI is complete - users can access full import workflow through modal interface

---

## Phase 8: Edge Cases & Error Handling

**Purpose**: Handle all edge cases defined in spec.md

- [ ] T089 [P] Add validation error for invalid/malformed YouTube URLs (return INVALID_URL error per contract)
- [ ] T090 [P] Add error handling for video deleted/private (return VIDEO_NOT_FOUND error per contract)
- [ ] T091 [P] Add error handling for channel deleted (return CHANNEL_NOT_FOUND error per contract)
- [ ] T092 [P] Add handling for videos with comments disabled (return appropriate error message)
- [ ] T093 [P] Add handling for API timeout during preview fetch (display retry button)
- [ ] T094 [P] Add handling for API timeout during full import (return partial_success if some data imported)
- [ ] T095 Add data consistency checks (verify channel exists before importing video)
- [ ] T096 Add duplicate comment handling (skip if comment_id already exists)
- [ ] T097 Add performance optimization for large comment counts (>1000 comments)
- [ ] T098 Add browser refresh handling (ensure modal state doesn't break navigation)

---

## Phase 9: Integration Testing & Validation

**Purpose**: End-to-end validation of all user stories working together

- [ ] T099 Run all contract tests and verify they pass: `php artisan test --filter=Contract`
- [ ] T100 Run all feature tests and verify they pass: `php artisan test --filter=Feature`
- [ ] T101 Run all unit tests and verify they pass: `php artisan test --filter=Unit`
- [ ] T102 Test User Story 1 independently (check video existence for all scenarios)
- [ ] T103 Test User Story 2 independently (import new video from existing channel with tag modification)
- [ ] T104 Test User Story 3 independently (import new video from new channel with tag selection)
- [ ] T105 Test User Story 4 independently (full UI workflow from button click to success)
- [ ] T106 Validate all success criteria from spec.md (SC-001 through SC-010)
- [ ] T107 Validate performance requirements (check: <1s, preview: <5s, import: <30s)
- [ ] T108 Test with real YouTube API (not mocks) to verify API integration
- [ ] T109 Verify database schema matches data-model.md specifications
- [ ] T110 Verify API responses match contract specifications exactly
- [ ] T111 Test error scenarios (quota exhaustion, network timeout, invalid URLs)
- [ ] T112 Verify transaction rollback works correctly on failures
- [ ] T113 Verify comment_count calculation is accurate
- [ ] T114 Verify recursive replies work correctly (depth 0, 1, 2, 3)
- [ ] T115 Verify timestamp format is correct (YYYY-MM-DD HH:MM:SS UTC)

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Final improvements, documentation, and deployment preparation

- [ ] T116 [P] Add inline code documentation (PHPDoc) for all services and controllers
- [ ] T117 [P] Add API endpoint documentation for developers
- [ ] T118 [P] Update `.env.example` with YOUTUBE_API_KEY entry
- [ ] T119 Code cleanup and refactoring (remove dead code, improve readability)
- [ ] T120 Security review (check for XSS, SQL injection, CSRF vulnerabilities)
- [ ] T121 Add rate limiting for import endpoints (prevent abuse)
- [ ] T122 Add request logging for debugging (structured logs with trace IDs)
- [ ] T123 Optimize database queries (add indexes if needed for channel_id, video_id, comment_id)
- [ ] T124 [P] Create deployment checklist (migrations, .env setup, composer install)
- [ ] T125 Run quickstart.md validation (verify all examples work)
- [ ] T126 Final manual testing of complete workflow
- [ ] T127 Performance profiling and optimization

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational (Phase 2)
- **User Story 2 (Phase 4)**: Depends on Foundational (Phase 2) AND User Story 1
- **User Story 3 (Phase 5)**: Depends on Foundational (Phase 2) AND User Story 1
- **Import Implementation (Phase 6)**: Depends on User Stories 2 and 3 preview implementations
- **User Story 4 (Phase 7)**: Depends on Import Implementation (Phase 6)
- **Edge Cases (Phase 8)**: Depends on all user stories being implemented
- **Integration Testing (Phase 9)**: Depends on all phases 1-8
- **Polish (Phase 10)**: Depends on successful integration testing

### User Story Dependencies

```
Phase 1 (Setup)
    ↓
Phase 2 (Foundational) ← CRITICAL BLOCKING PHASE
    ↓
    ├→ Phase 3: User Story 1 (Check video) ← MVP Core
    │       ↓
    │       ├→ Phase 4: User Story 2 (Import existing channel) ← Requires US1
    │       │
    │       └→ Phase 5: User Story 3 (Import new channel) ← Requires US1
    │               ↓
    │               └→ Phase 6: Import Implementation ← Requires US2 & US3
    │                       ↓
    │                       └→ Phase 7: User Story 4 (UI) ← Requires Phase 6
    │                               ↓
    │                               └→ Phase 8: Edge Cases
    │                                       ↓
    │                                       └→ Phase 9: Integration Testing
    │                                               ↓
    │                                               └→ Phase 10: Polish
```

### Within Each User Story

1. Tests FIRST (write tests, ensure they FAIL)
2. Models and data layer
3. Services and business logic
4. Controllers and API endpoints
5. UI components (for US4)
6. Error handling and logging
7. Verify tests now PASS
8. Story checkpoint validation

### Parallel Opportunities

**Phase 1 (Setup)**: All tasks can run sequentially (migrations depend on composer install)

**Phase 2 (Foundational)**: Tasks T011, T012, T013 can run in parallel (different model files)

**Phase 3 (US1 Tests)**: Tasks T015, T016, T017 can run in parallel (different test files)

**Phase 4 (US2 Tests)**: Tasks T026, T027, T028 can run in parallel (different test files)

**Phase 4 (US2 API Methods)**: Tasks T029, T030, T031 can run in parallel (different methods in same service)

**Phase 5 (US3 Tests)**: Tasks T039, T040, T041 can run in parallel (different test files)

**Phase 6 (Import Tests)**: Tasks T046-T050 can run in parallel (different test files)

**Phase 6 (Import API Methods)**: Tasks T051, T052 can run in parallel (different methods)

**Phase 7 (US4 Tests)**: Tasks T069, T070 can run in parallel (different test types)

**Phase 8 (Edge Cases)**: Tasks T089-T094 can run in parallel (different error types)

**Phase 10 (Polish)**: Tasks T116, T117, T118, T124 can run in parallel (documentation work)

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together:
Task T015: "Contract test for /api/comments/check endpoint"
Task T016: "Feature test for video existence check"
Task T017: "Unit test for YouTube URL parsing"

# All three can be written in parallel since they test different aspects
# Wait for all to be written and FAILING, then implement
```

---

## Parallel Example: User Story 2 API Methods

```bash
# Launch all API metadata methods together:
Task T029: "Implement getVideoMetadata() method"
Task T030: "Implement getChannelMetadata() method"
Task T031: "Implement getPreviewComments() method"

# All three can be implemented in parallel since they're independent methods
```

---

## Implementation Strategy

### MVP First (Minimum Viable Product)

**Goal**: Get a working prototype as quickly as possible

1. Complete Phase 1: Setup (T001-T005)
2. Complete Phase 2: Foundational (T006-T014) - CRITICAL
3. Complete Phase 3: User Story 1 (T015-T025)
4. **STOP and VALIDATE**: Test US1 independently
   - Can check video existence?
   - Returns correct responses for all scenarios?
   - Error handling works?
5. If US1 works, proceed to Phase 4

### Incremental Delivery

**Goal**: Add value with each user story completion

1. **Foundation Ready** (Phases 1-2)
   - Database migrated
   - Base services created
   - Routes configured

2. **MVP: Check Feature** (Phase 3 - US1)
   - Can check if video exists
   - Returns appropriate workflow path
   - Deploy/Demo checkpoint ✓

3. **Preview for Existing Channels** (Phase 4 - US2)
   - Can fetch and display preview data
   - Shows existing channel tags
   - Deploy/Demo checkpoint ✓

4. **Preview for New Channels** (Phase 5 - US3)
   - Can fetch and display preview data
   - Shows available tag selector
   - Deploy/Demo checkpoint ✓

5. **Full Import Logic** (Phase 6)
   - Complete database import
   - Transaction management
   - Recursive comment fetching
   - Deploy/Demo checkpoint ✓

6. **Complete UI Workflow** (Phase 7 - US4)
   - Modal interface
   - Full user workflow
   - AJAX updates
   - Deploy/Demo checkpoint ✓

7. **Production Ready** (Phases 8-10)
   - All edge cases handled
   - Full test coverage
   - Performance optimized
   - Production deployment ✓

### Parallel Team Strategy

With multiple developers:

**Stage 1: Foundation (All Together)**
- Team completes Phases 1-2 together
- Ensures everyone understands architecture

**Stage 2: User Stories (Parallel After Foundation)**
Once Phase 2 complete:
- Developer A: US1 (Phase 3) - Check functionality
- Developer B: Can start on test infrastructure
- Wait for US1 to complete before others start

**Stage 3: Preview Features (Sequential)**
- Developer A: US2 (Phase 4) - Existing channel preview
- After US2 done, Developer B: US3 (Phase 5) - New channel preview
- Or both can work in parallel if team is large

**Stage 4: Import & UI (Sequential)**
- Developer A: Import implementation (Phase 6)
- Developer B: UI components (Phase 7) - can start skeleton while import is being built
- Integration at end

**Stage 5: Quality (Parallel)**
- Developer A: Edge cases (Phase 8)
- Developer B: Integration tests (Phase 9)
- Developer C: Documentation (Phase 10)

---

## Test-First Development Checklist

Per Constitutional Principle I, all tests must be written BEFORE implementation:

### For Each User Story:

1. ✅ Write contract tests FIRST (validate API contracts)
2. ✅ Write feature tests FIRST (validate user journeys)
3. ✅ Write unit tests FIRST (validate business logic)
4. ✅ Run all tests → Confirm they FAIL (red)
5. ✅ Implement minimum code to make tests pass (green)
6. ✅ Refactor while keeping tests green
7. ✅ Story checkpoint - all tests pass

### Test Coverage Targets:

- Contract tests: 100% of API endpoints
- Feature tests: 100% of user stories and acceptance scenarios
- Unit tests: 100% of business logic (services)
- Integration tests: All multi-component workflows

---

## Notes

- **[P]** tasks = different files, no dependencies within phase
- **[Story]** label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- **CRITICAL**: Write tests FIRST, ensure they FAIL before implementing
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Follow Laravel best practices (service layer, models, controllers)
- Use Laravel's transaction management for import operations
- Log all operations using Laravel Log facade
- Handle all YouTube API errors gracefully
- Validate all inputs at controller layer
- Keep modal component self-contained (Alpine.js state management)
- Ensure AJAX updates work without page reload
- Verify timestamp format consistency (YYYY-MM-DD HH:MM:SS UTC)
- Test with real YouTube API before final deployment

---

## Success Criteria Mapping

Each success criterion from spec.md maps to specific tasks:

- **SC-001** (Modal open <3s): T071-T088 (US4 UI implementation)
- **SC-002** (URL check <1s): T018-T024 (US1 check implementation)
- **SC-003** (API preview <5s): T029-T038 (US2/US3 preview implementation)
- **SC-004** (Full import <30s): T051-T068 (Import implementation)
- **SC-005** (All comments imported): T053, T056 (Recursive fetching)
- **SC-006** (All fields correct): T035, T051-T056 (Data mapping)
- **SC-007** (Channel/video data accurate): T029, T030, T054, T055
- **SC-008** (No data conflicts): T059, T096 (Transaction + duplicate handling)
- **SC-009** (Success message): T080 (Success display)
- **SC-010** (Auto list refresh <2s): T086 (AJAX refresh)

---

## Total Task Count: 127 tasks

- Phase 1 (Setup): 5 tasks
- Phase 2 (Foundational): 9 tasks
- Phase 3 (US1): 11 tasks (3 tests + 8 implementation)
- Phase 4 (US2): 13 tasks (3 tests + 10 implementation)
- Phase 5 (US3): 7 tasks (3 tests + 4 implementation)
- Phase 6 (Import): 23 tasks (5 tests + 18 implementation)
- Phase 7 (US4): 20 tasks (2 tests + 18 implementation)
- Phase 8 (Edge Cases): 10 tasks
- Phase 9 (Integration): 17 tasks
- Phase 10 (Polish): 12 tasks

**Tests**: 16 test tasks (contract + feature + unit)
**Implementation**: 78 implementation tasks
**Validation**: 17 integration/validation tasks
**Infrastructure**: 16 setup/foundational/polish tasks

**Parallel Opportunities**: 25+ tasks marked [P] can run in parallel within their phases
