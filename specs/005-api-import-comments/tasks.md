# Implementation Tasks: YouTube API Comments Import

**Feature**: YouTube API Comments Import
**Branch**: `005-api-import-comments`
**Date**: 2025-11-17
**Total Tasks**: 35
**Estimated Effort**: 15-20 developer-hours

---

## Overview

This task list implements YouTube API-based comment import functionality with full support for new videos, incremental updates, and reply comment hierarchies. Tasks are organized by user story priority and execution order, enabling parallel development and independent testing.

### User Stories (Priority Order)
- **P1**: US1 - Import Comments for New Video (foundational)
- **P1**: US2 - Incremental Update for Existing Video
- **P1**: US3 - Reply Comments Handling
- **P2**: US4 - UI Integration (low priority, not blocking MVP)

### Implementation Strategy

**MVP Scope**: US1 + US2 + US3 (all P1 stories)
- Completes foundational comment import functionality
- Enables testing with new and existing videos
- Supports full discussion thread preservation
- Does NOT include UI "API 導入" button (P2, optional for MVP)

**Estimated MVP Effort**: 12-15 hours (Tasks T001-T028)
**Full Feature Effort**: 15-20 hours (Tasks T001-T035, includes P2 UI)

### Parallel Opportunities

- **Phase 1 Setup**: All setup tasks [P] (independent, no dependencies)
- **Phase 2 Foundational**: All foundational tasks [P] (parallel after Phase 1)
- **Phase 3 US1**: Service tests + controller tests can run in parallel [P]
- **Phase 4 US2**: Incremental logic tests + implementation [P]
- **Phase 5 US3**: Reply handling tests + implementation [P]

---

## Phase 1: Setup & Configuration

### Goal
Initialize project dependencies and environment configuration for YouTube API integration.

### Independent Test Criteria
- Composer can successfully require google/apiclient
- `.env` file contains valid YOUTUBE_API_KEY
- Google API Client can be instantiated without errors
- All new files are created in correct locations

---

- [ ] T001 Add google/apiclient dependency to composer.json via `composer require google/apiclient:^2.15`
- [ ] T002 [P] Update .env.example with YOUTUBE_API_KEY=your_key_here
- [ ] T003 [P] Create app/Services/YouTubeApiService.php (empty file, will be filled in later)
- [ ] T004 [P] Create app/Http/Controllers/YouTubeApiImportController.php (empty file, will be filled in later)
- [ ] T005 [P] Create tests/Unit/Services/YouTubeApiServiceTest.php (empty file, will be filled in later)
- [ ] T006 [P] Create tests/Feature/YouTubeApiImportTest.php (empty file, will be filled in later)
- [ ] T007 Create database/migrations/YYYY_MM_DD_HHMMSS_add_parent_comment_id_to_comments_table.php migration file

---

## Phase 2: Foundational Prerequisites

### Goal
Set up database schema changes and core models to support comment import feature.

### Independent Test Criteria
- Migration runs successfully without errors
- Comment model has parent_comment_id field accessible
- parent_comment_id foreign key constraint enforced
- Existing comments table data unaffected

---

- [ ] T008 Implement parent_comment_id migration: Add column, foreign key, and index to comments table in database/migrations/YYYY_MM_DD_HHMMSS_add_parent_comment_id_to_comments_table.php
- [ ] T009 Run migration: `php artisan migrate` and verify no errors
- [ ] T010 [P] Update app/Models/Comment.php to add parent_comment_id field and parent/children relationships
- [ ] T011 [P] Create app/Exceptions/YouTubeApiException.php exception class
- [ ] T012 [P] Create app/Exceptions/VideoNotFoundException.php exception class
- [ ] T013 [P] Create app/Exceptions/AuthenticationException.php exception class
- [ ] T014 [P] Create app/Exceptions/InvalidVideoIdException.php exception class

---

## Phase 3: US1 - Import Comments for New Video

### Goal
Implement complete workflow for importing comments from a new YouTube video (not yet in database).

### Acceptance Criteria
1. When user enters new video URL, system detects it doesn't exist in DB
2. System automatically invokes existing "匯入" dialog for metadata capture
3. After "匯入" completes, system auto-fetches 5 preview comments
4. User can review preview and click "確認導入" to import all comments
5. All comments + replies stored with correct parent_comment_id relationships
6. Channel and video records updated with correct fields
7. All imported comments visible and sorted by date

### Independent Test Criteria
- Contract tests pass for YouTubeApiService::fetchPreviewComments
- Contract tests pass for YouTubeApiService::fetchAllComments
- Controller tests pass for preview endpoint
- Controller tests pass for confirm endpoint
- Integration test passes: new video → preview → confirm full import
- Database records reflect all imported comments with correct timestamps

---

### US1 Service Layer - Contract Tests First (TDD)

- [ ] T015 Implement YouTubeApiServiceTest::test_fetch_preview_comments_returns_5_comments in tests/Unit/Services/YouTubeApiServiceTest.php (RED test)
- [ ] T016 Implement YouTubeApiServiceTest::test_fetch_preview_comments_with_fewer_than_5_comments in tests/Unit/Services/YouTubeApiServiceTest.php (RED test)
- [ ] T017 Implement YouTubeApiServiceTest::test_fetch_all_comments_returns_all_top_level_and_replies in tests/Unit/Services/YouTubeApiServiceTest.php (RED test)
- [ ] T018 Implement YouTubeApiServiceTest::test_fetch_all_comments_with_after_date_filters_results in tests/Unit/Services/YouTubeApiServiceTest.php (RED test)
- [ ] T019 Implement YouTubeApiServiceTest::test_validate_video_id_accepts_valid_format in tests/Unit/Services/YouTubeApiServiceTest.php (RED test)
- [ ] T020 Implement YouTubeApiServiceTest::test_validate_video_id_rejects_invalid_format in tests/Unit/Services/YouTubeApiServiceTest.php (RED test)
- [ ] T021 Implement YouTubeApiServiceTest::test_api_error_handling_throws_proper_exceptions in tests/Unit/Services/YouTubeApiServiceTest.php (RED test)

### US1 Service Layer - Implementation (GREEN)

- [ ] T022 Implement YouTubeApiService::__construct() method: Initialize Google Client with API key from .env
- [ ] T023 Implement YouTubeApiService::fetchPreviewComments(string $videoId): array method
- [ ] T024 Implement YouTubeApiService::fetchAllComments(string $videoId, ?string $afterDate = null, ?callable $progressCallback = null): array method with recursive reply fetching
- [ ] T025 Implement YouTubeApiService::validateVideoId(string $videoId): bool method
- [ ] T026 Implement YouTubeApiService::logOperation(string $traceId, string $operation, int $commentCount, string $status, ?string $error): void method
- [ ] T027 [P] Run all YouTubeApiService tests and verify passing (REFACTOR phase)

### US1 Controller Layer - Contract Tests First (TDD)

- [ ] T028 Implement YouTubeApiImportTest::test_preview_endpoint_fetches_5_comments_for_existing_video in tests/Feature/YouTubeApiImportTest.php (RED test)
- [ ] T029 Implement YouTubeApiImportTest::test_preview_endpoint_detects_new_video_and_returns_action_required in tests/Feature/YouTubeApiImportTest.php (RED test)
- [ ] T030 Implement YouTubeApiImportTest::test_confirm_endpoint_imports_all_comments_with_replies in tests/Feature/YouTubeApiImportTest.php (RED test)

### US1 Controller Layer - Implementation (GREEN)

- [ ] T031 Implement YouTubeApiImportController::preview(Request $request): JsonResponse method in app/Http/Controllers/YouTubeApiImportController.php
  - Extract video ID from URL
  - Check if video exists in database
  - If new → Return "new_video_detected" status (no preview fetch)
  - If exists → Call YouTubeApiService::fetchPreviewComments
  - Return preview array + metadata

- [ ] T032 Implement YouTubeApiImportController::confirm(Request $request): JsonResponse method in app/Http/Controllers/YouTubeApiImportController.php
  - Extract video ID
  - Determine import mode (incremental vs full)
  - Call YouTubeApiService::fetchAllComments
  - For each comment: Check duplicates, insert if new, set parent_comment_id for replies
  - Update channel: comment_count (recalculate), last_import_at, updated_at; Initialize video_count/first_import_at/created_at for NEW channels
  - Update video: updated_at (always); Initialize video_id/title/published_at/created_at for NEW videos
  - Return success with counts

- [ ] T033 [P] Run all YouTubeApiImportTest tests and verify passing (REFACTOR phase)

### US1 Routes & Integration

- [ ] T034 Add routes to routes/api.php: POST /api/youtube-import/preview and POST /api/youtube-import/confirm
- [ ] T035 [P] Run full integration test: new video URL → preview → confirm → verify all comments in DB with correct relationships

---

## Phase 4: US2 - Incremental Update for Existing Video

### Goal
Implement efficient updating of comments for videos that have been imported before.

### Acceptance Criteria
1. When user enters existing video URL, system fetches only NEW comments (since last import)
2. Preview shows up to 5 sample new comments
3. Full import fetches only newer comments (ordered newest first)
4. System stops at first duplicate comment_id (prevents unnecessary API calls)
5. New comments merged with existing without duplicates
6. Reply comments properly linked to parents
7. Channel comment_count recalculated and updated

### Independent Test Criteria
- Contract test passes: incremental fetch with afterDate filters correctly
- Integration test passes: import same video twice, verify only new comments fetched second time
- Duplicate detection stops fetch at expected point
- Comment counts match actual DB records

---

### US2 - Incremental Logic Tests & Implementation

- [ ] T036 Implement YouTubeApiServiceTest::test_incremental_fetch_with_after_date_parameter in tests/Unit/Services/YouTubeApiServiceTest.php
- [ ] T037 Implement YouTubeApiServiceTest::test_fetch_stops_at_duplicate_comment_id in tests/Unit/Services/YouTubeApiServiceTest.php
- [ ] T038 Implement incremental mode logic in YouTubeApiImportController::confirm() method:
  - Find max(published_at) from existing comments for video
  - Call fetchAllComments with afterDate parameter
  - Stop processing when duplicate comment_id detected

- [ ] T039 Implement duplicate detection in confirm() endpoint:
  - Check if comment_id exists before insert
  - Skip insert if exists (no update to like_count)
  - Continue to next comment
  - Stop if duplicate found in incremental mode

- [ ] T040 Implement YouTubeApiImportTest::test_incremental_import_fetches_only_new_comments in tests/Feature/YouTubeApiImportTest.php
- [ ] T041 [P] Run incremental update tests and verify passing

---

## Phase 5: US3 - Reply Comments Handling

### Goal
Implement recursive fetching and storage of reply comments at all nesting levels.

### Acceptance Criteria
1. System recursively fetches replies for each top-level comment
2. All reply levels imported (reply → reply-to-reply → ...)
3. Each reply has correct parent_comment_id pointing to parent comment
4. Parent-child relationships preserved in database
5. Discussion thread structure completely maintained

### Independent Test Criteria
- Contract test passes: multi-level reply comments fetched recursively
- Integration test passes: video with 3-level nested replies imported correctly
- parent_comment_id correctly set for all reply levels
- Queries can reconstruct full thread hierarchy

---

### US3 - Reply Handling Tests & Implementation

- [ ] T042 Implement YouTubeApiServiceTest::test_fetch_comments_recursively_gets_all_reply_levels in tests/Unit/Services/YouTubeApiServiceTest.php
- [ ] T043 Implement YouTubeApiServiceTest::test_parent_comment_id_set_correctly_for_multi_level_replies in tests/Unit/Services/YouTubeApiServiceTest.php
- [ ] T044 Implement recursive reply fetching in YouTubeApiService::fetchAllComments():
  - For each top-level comment with totalReplyCount > 0
  - Call YouTube API comments.list with parentId filter
  - Process replies recursively (reply-to-reply, etc.)
  - Flatten all into single array with parent_comment_id set correctly

- [ ] T045 Implement YouTubeApiImportTest::test_multi_level_reply_comments_imported_with_correct_hierarchy in tests/Feature/YouTubeApiImportTest.php
- [ ] T046 [P] Run reply handling tests and verify passing

---

## Phase 6: US4 - UI Integration (P2, Optional for MVP)

### Goal
Add "API 導入" button and integrate with existing comments interface.

### Note
**This phase is P2 (lower priority) and NOT required for MVP.** All P1 functionality is complete without this phase.

### Acceptance Criteria
1. "API 導入" button visible alongside "匯入" button on comments list page
2. Button properly positioned on right side
3. Clicking button opens import modal/form
4. Form accepts video URL input
5. Form validates URL and triggers preview endpoint
6. New video detection routes to existing "匯入" dialog
7. Both flows (new + existing video) work seamlessly

### Independent Test Criteria
- Button exists and positioned correctly
- Form submits to /api/youtube-import/preview endpoint
- URL validation works client-side
- New video detection flow routes correctly

---

### US4 - UI Implementation (Optional)

- [ ] T047 Add "API 導入" button to resources/views/comments/list.blade.php next to "匯入" button
- [ ] T048 Create resources/views/youtube-import-modal.blade.php form view with URL input field
- [ ] T049 Add JavaScript event handler to submit URL to /api/youtube-import/preview endpoint
- [ ] T050 Implement URL validation (client-side): Reject malformed URLs
- [ ] T051 Handle preview response: Display sample comments or "new_video_detected" message
- [ ] T052 Route new video detection to existing "匯入" dialog (JavaScript redirect)
- [ ] T053 Add "確認導入" button to preview display
- [ ] T054 Implement progress indicator during full import (optional enhancement)
- [ ] T055 [P] Manual testing: Both new and existing video workflows through UI

---

## Phase 7: Polish & Documentation

### Goal
Final testing, documentation, and performance optimization.

### Independent Test Criteria
- All manual test scenarios pass
- Performance metrics meet targets
- Error messages are clear and actionable
- Logs contain trace IDs and operation details

---

### Testing & Documentation

- [ ] T056 Manual test scenario: New video with 1000+ comments (verify <30 second import)
- [ ] T057 Manual test scenario: Existing video with 5 new comments (verify <10 second incremental)
- [ ] T058 Manual test scenario: Video with multi-level replies (5 levels deep)
- [ ] T059 Manual test scenario: Invalid video URL (verify error message)
- [ ] T060 Manual test scenario: API quota exceeded (verify user message)
- [ ] T061 Manual test scenario: Network error during import (verify partial state handling)
- [ ] T062 Manual test scenario: User cancels preview (verify dialog closes, no DB changes)
- [ ] T063 Run full test suite: `php artisan test` and verify all passing
- [ ] T064 [P] Performance benchmark: Preview fetch <3 seconds
- [ ] T065 [P] Performance benchmark: Full import <30 seconds
- [ ] T066 [P] Performance benchmark: Incremental update <10 seconds
- [ ] T067 Update documentation: Add API endpoint examples to quickstart.md
- [ ] T068 Add migration rollback instructions to feature README
- [ ] T069 [P] Code review checklist: All files follow Laravel conventions
- [ ] T070 [P] Verify no modifications to UrtubeapiService.php or existing ImportController

---

## Dependency Graph

```
Phase 1: Setup & Configuration (no dependencies)
    ↓
Phase 2: Foundational (depends on Phase 1)
    ↓
Phase 3: US1 - New Video Import (depends on Phase 2)
    ├→ T015-T021 (tests) → T022-T026 (implementation) → T027 (verify)
    └→ T028-T030 (controller tests) → T031-T032 (implementation) → T033 (verify)
    ↓
Phase 4: US2 - Incremental Update (depends on Phase 3)
    └→ T036-T041 (incremental logic)
    ↓
Phase 5: US3 - Reply Comments (depends on Phase 3)
    └→ T042-T046 (reply handling)
    ↓
Phase 6: US4 - UI Integration (depends on Phase 3, optional)
    └→ T047-T055 (UI implementation, P2)
    ↓
Phase 7: Polish & Documentation (depends on all previous)
    └→ T056-T070 (testing, performance, documentation)
```

---

## Parallel Execution Examples

### By Phase (Within Phase Parallelization)

**Phase 1 Tasks** (all parallelizable):
```
- [ ] T001 Add google/apiclient
- [ ] T002 [P] Update .env.example
- [ ] T003 [P] Create YouTubeApiService.php
- [ ] T004 [P] Create YouTubeApiImportController.php
- [ ] T005 [P] Create YouTubeApiServiceTest.php
- [ ] T006 [P] Create YouTubeApiImportTest.php
⏭️ (then T007 - migration, depends on PHP files existing)
```

**Phase 2 Tasks** (all parallelizable after Phase 1):
```
- [ ] T008 Implement migration (blocking, must complete first)
- [ ] T009 Run migration (depends on T008)
⏭️ (then all T010-T014 in parallel)
- [ ] T010 [P] Update Comment model
- [ ] T011 [P] Create YouTubeApiException
- [ ] T012 [P] Create VideoNotFoundException
- [ ] T013 [P] Create AuthenticationException
- [ ] T014 [P] Create InvalidVideoIdException
```

**Phase 3 US1** (service tests and controller tests in parallel):
```
Service Track:
- [ ] T015-T021 [P] Write all service tests (RED)
- [ ] T022-T026 Implement service methods (GREEN)
- [ ] T027 [P] Verify service tests passing

Controller Track:
- [ ] T028-T030 [P] Write controller tests (RED)
- [ ] T031-T032 Implement controller methods (GREEN)
- [ ] T033 [P] Verify controller tests passing

Integration:
⏭️ (sync both tracks before T034-T035)
```

---

## MVP vs Full Feature

### MVP Scope (Recommended for v1.0)
**Tasks**: T001-T046 (Phases 1-5)
**Excludes**: US4 UI Integration (P2), Phase 7 polish
**Effort**: 12-15 hours
**Value**: Complete core functionality (new, incremental, replies)
- Users can import via API endpoints
- Full discussion thread preservation
- Incremental updates work perfectly
- All P1 requirements met

### Full Feature (v1.1)
**Tasks**: T001-T070 (All phases)
**Includes**: US4 UI Integration (P2), full testing & documentation
**Effort**: 15-20 hours
**Value**: Production-ready with UI and comprehensive testing

---

## Task Completion Checklist Format

All tasks follow this format:
```
- [ ] [TaskID] [P] [Story] Description with file path
```

Example:
```
- [ ] T015 Write service contract test for fetchPreviewComments in tests/Unit/Services/YouTubeApiServiceTest.php
- [ ] T022 [P] Implement YouTubeApiService::__construct() in app/Services/YouTubeApiService.php
- [ ] T031 [US1] Implement preview() method in app/Http/Controllers/YouTubeApiImportController.php
```

**Breakdown**:
- `[TaskID]`: T001, T002, ... T070 (sequential execution order)
- `[P]`: Parallelizable marker (only when task has no dependencies on incomplete tasks)
- `[Story]`: User Story label (US1, US2, US3, US4 - only for story-specific tasks)
- **Description**: Clear action with exact file path

---

## Status Tracking

Use this checklist to track progress:

- [ ] **Phase 1 Complete** (all setup tasks done)
- [ ] **Phase 2 Complete** (database migration + exceptions ready)
- [ ] **Phase 3 Complete** (US1 - new video import fully tested)
- [ ] **Phase 4 Complete** (US2 - incremental updates working)
- [ ] **Phase 5 Complete** (US3 - reply comments recursive)
- [ ] **Phase 6 Complete** (US4 - UI integration done)
- [ ] **Phase 7 Complete** (all testing & documentation done)

**MVP Ready**: ✅ Phase 1-5 complete
**Full Release Ready**: ✅ Phase 1-7 complete

---

**Generated**: 2025-11-17 | **Status**: Ready for Implementation | **Next**: Use `/speckit.implement` to execute tasks
