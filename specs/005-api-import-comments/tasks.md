---
description: "Task list for YouTube API Comments Import feature implementation"
---

# Tasks: YouTube API Comments Import

**Input**: Design documents from `/specs/005-api-import-comments/`
**Prerequisites**: plan.md (required), spec.md (required for user stories)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3, US4)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and verification of existing infrastructure

- [ ] T001 Review existing Laravel 12 service architecture and existing ImportService in app/Services/ImportService.php
- [ ] T002 Review existing UrtubeapiService in app/Services/UrtubeapiService.php for pagination support needs
- [ ] T003 Review existing Comment model in app/Models/Comment.php for relationships and fields
- [ ] T004 Verify YouTube API credentials are configured in .env file (YOUTUBE_API_KEY)
- [ ] T005 [P] Create migration for api_import_comments_jobs table in database/migrations/ (if using database queue)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T006 [P] Fix channel comment_count calculation bug in app/Services/ImportService.php:249 (use `whereHas('videos')` relationship to count all channel comments, not just one video)
- [ ] T007 [P] Refactor ImportConfirmationController in app/Http/Controllers/ImportConfirmationController.php:95-97 to add public `cancelImport()` method instead of using reflection anti-pattern
- [ ] T008 Add `pageToken` parameter support to UrtubeapiService in app/Services/UrtubeapiService.php for pagination handling
- [ ] T009 Create ApiCommentImportService in app/Services/ApiCommentImportService.php with:
  - `previewComments($videoUrl, $limit = 5)` method to fetch sample comments
  - `importComments($videoId, $incrementalOnly = false)` method for full import
  - `getLatestCommentTimestamp($videoId)` helper for incremental updates
  - Exponential backoff logic for YouTube API rate limiting
  - Comment deduplication logic (track comment_id to prevent duplicates)
  - Recursive reply handling for nested comments (replies to replies)
- [ ] T010 Extend Comment model in app/Models/Comment.php to include:
  - `parent_comment_id` relationship for reply comments
  - `imported_at` timestamp field
  - Proper database relationship to `videos` table
- [ ] T011 Create validation request class ApiImportCommentsRequest in app/Http/Requests/ApiImportCommentsRequest.php with:
  - YouTube URL validation
  - Video existence check (query database)
  - Proper error messages for invalid URLs

**Checkpoint**: Foundation ready - user story implementation can now begin

---

## Phase 3: User Story 1 - Import Comments for New Video (Priority: P1) 🎯 MVP

**Goal**: Enable users to import comments for a new video by first completing the existing "匯入" (import video) dialog for metadata, then automatically proceeding to comment preview and full import.

**Independent Test**: Can be fully tested by:
1. Entering a new video URL (not in database)
2. Completing the "匯入" dialog to capture metadata
3. Verifying comment preview shows 5 sample comments
4. Confirming full import stores all comments with correct fields and relationships
5. Verifying channels and videos tables are updated correctly

### Implementation for User Story 1

- [ ] T012 [P] [US1] Create ApiCommentImportController in app/Http/Controllers/ApiCommentImportController.php with:
  - `showForm()` method for GET /comments/api-import route
  - `preview()` method for POST /comments/api-import/preview route
  - `confirm()` method for POST /comments/api-import/confirm route
- [ ] T013 [P] [US1] Create blade template `resources/views/comments/api-import.blade.php` with:
  - URL input form field
  - Hidden section for preview comments (initially hidden)
  - "確認導入" button (enabled only when preview succeeds)
  - Error message display area
  - Progress indicator for ongoing imports
- [ ] T014 [US1] Implement `preview()` endpoint logic in ApiCommentImportController:
  - Validate input URL using ApiImportCommentsRequest
  - Check if video exists in database (if not, trigger existing "匯入" dialog)
  - If new video: return response that triggers existing import modal
  - If video exists: call ApiCommentImportService::previewComments($videoUrl, 5)
  - Return 5 sample comments to frontend without persisting to database
  - Handle YouTube API errors (invalid key, quota exceeded, video not found) with user-friendly messages
- [ ] T015 [US1] Implement `confirm()` endpoint logic in ApiCommentImportController:
  - Validate that video exists in database (created by previous "匯入" step or already existed)
  - Dispatch ApiImportCommentsBatchJob for background processing
  - Return immediate response to user indicating import is in progress
  - Display success/failure status on comments list page
- [ ] T016 [US1] Create ApiImportCommentsBatchJob in app/Jobs/ApiImportCommentsBatchJob.php:
  - Handle method receives $videoId parameter
  - Calls ApiCommentImportService::importComments($videoId, $incrementalOnly = false)
  - Stores all returned comments in database with field mapping (see FR-009)
  - Updates channels table: `comment_count`, `last_import_at`, `updated_at`
  - Updates videos table: `updated_at` (and `video_id`, `title`, `published_at`, `created_at` only for new videos)
  - Implements transaction handling to ensure atomic operations
  - Handles YouTube API errors gracefully with retry logic
- [ ] T017 [US1] Add routes in routes/web.php:
  - GET /comments/api-import → ApiCommentImportController@showForm
  - POST /comments/api-import/preview → ApiCommentImportController@preview
  - POST /comments/api-import/confirm → ApiCommentImportController@confirm
- [ ] T018 [US1] Integrate comment import with existing "匯入" dialog:
  - Modify ImportConfirmationController to dispatch ApiImportCommentsBatchJob after video import completes
  - Ensure comment preview is automatically displayed after video import dialog closes
  - Pass $videoId to comment import flow

**Checkpoint**: User Story 1 should be fully functional - users can import comments for new videos

---

## Phase 4: User Story 2 - Incremental Update for Existing Video (Priority: P1)

**Goal**: Enable users to re-import a video they've already imported before, fetching only new comments added since the last import without duplicating previous comments.

**Independent Test**: Can be fully tested by:
1. Importing a video with comments (uses US1 flow)
2. Waiting for new comments to be added to the video (or manually adding test data)
3. Re-importing the same video
4. Verifying preview shows only new comments (newer than most recent stored comment)
5. Confirming full import only fetches/stores new comments
6. Verifying no duplicate comment_ids exist in database after second import

### Implementation for User Story 2

- [ ] T019 [US2] Implement `getLatestCommentTimestamp($videoId)` method in ApiCommentImportService:
  - Query database for most recent comment on video (WHERE videos.id = $videoId)
  - Return timestamp of newest comment (or null if no comments exist)
  - Use this timestamp to determine incremental import starting point
- [ ] T020 [US2] Implement incremental fetch logic in ApiCommentImportService::importComments($videoId, $incrementalOnly = true):
  - Get latest comment timestamp via getLatestCommentTimestamp($videoId)
  - If $incrementalOnly = true AND timestamp exists: Only fetch comments newer than timestamp
  - Fetch comments in reverse chronological order (newest first)
  - Track comment_id during fetch to detect duplicates
  - Stop immediately when duplicate comment_id is encountered (optimization to avoid wasting API quota)
  - Fallback safety: Stop fetching if reaching comments older than latest timestamp (FR-015)
- [ ] T021 [US2] Modify `preview()` endpoint in ApiCommentImportController:
  - Detect if video already exists in database
  - If existing video: Call ApiCommentImportService::previewComments($videoUrl, 5) with incremental flag
  - Display "No new comments available" message if incremental fetch finds zero new comments
  - Display "X new comments available" message showing count of new comments awaiting import
- [ ] T022 [US2] Modify ApiImportCommentsBatchJob to handle incremental updates:
  - Detect if video already has comments (use comment count > 0)
  - If incremental: Call ApiCommentImportService::importComments($videoId, $incrementalOnly = true)
  - If first import: Call ApiCommentImportService::importComments($videoId, $incrementalOnly = false)
  - Merge new comments with existing without creating duplicates
  - Update channels `last_import_at` timestamp after successful import

**Checkpoint**: User Stories 1 AND 2 should both work independently - users can import and re-import videos

---

## Phase 5: User Story 3 - Reply Comments Handling (Priority: P1)

**Goal**: Automatically capture all nested reply comments at any depth to preserve complete discussion thread structure when importing comments.

**Independent Test**: Can be fully tested by:
1. Importing a video with multi-level reply comments (replies to replies)
2. Verifying all reply levels are stored in database
3. Checking parent-child relationships are correct (parent_comment_id field)
4. Confirming reply count and nesting depth matches YouTube data

### Implementation for User Story 3

- [ ] T023 [US3] Implement recursive reply handling in ApiCommentImportService:
  - Create private method `fetchRepliesRecursively($parentComment)` to handle nested replies
  - YouTube API returns replies in `replies.comments` section
  - For each reply: Extract comment data, create Comment record with parent_comment_id reference
  - Call `fetchRepliesRecursively()` recursively on each reply to capture replies-to-replies
  - Continue until no more replies exist (YouTube API returns empty replies array)
  - Handle YouTube API pagination for replies (use pageToken if replies exceed 100)
- [ ] T024 [US3] Update ApiImportCommentsBatchJob to process replies:
  - Call recursive reply handler during full import (both new and incremental)
  - Store replies with parent_comment_id pointing to parent comment in database
  - Ensure transaction includes all reply levels (atomic)
  - Log reply import statistics (total replies, max depth)
- [ ] T025 [US3] Add database field to Comment model in app/Models/Comment.php:
  - Ensure `parent_comment_id` column exists (nullable foreign key to comments.id)
  - Create relationship method: `replies()` to get all direct children
  - Create relationship method: `parentComment()` to get parent comment reference

**Checkpoint**: User Stories 1, 2, AND 3 should all be complete - users can import videos with full thread structure

---

## Phase 6: User Story 4 - UI Integration (Priority: P2)

**Goal**: Integrate "API 導入" button into the comments interface alongside existing "匯入" button for seamless workflow.

**Independent Test**: Can be fully tested by:
1. Visiting /comments page
2. Verifying both "匯入" and "API 導入" buttons are visible
3. Clicking "API 導入" opens new import form
4. Clicking "匯入" navigates to existing home page import
5. Verifying both workflows work independently without interference

### Implementation for User Story 4

- [ ] T026 [US4] Update comments index view in resources/views/comments/index.blade.php:
  - Add "API 導入" button positioned next to existing "匯入" button
  - "API 導入" button links to GET /comments/api-import
  - "匯入" button continues to link to GET / (unchanged)
  - Style buttons consistently with existing design
- [ ] T027 [US4] Add modal/dialog UI component for api-import.blade.php:
  - Create reusable modal component for URL input form
  - Show error messages if URL validation fails
  - Show loading state during preview fetch
  - Display preview comments in collapsible section
  - "確認導入" button triggers full import job dispatch
- [ ] T028 [US4] Implement frontend validation and error handling in api-import.blade.php:
  - Client-side URL format validation (basic check before server submit)
  - Display server error messages (YouTube API errors, malformed URLs, etc.)
  - Show friendly messages for common errors:
    - "YouTube API configuration missing" → Check .env configuration
    - "Quota exceeded" → Too many API requests today
    - "Video not found" → Video URL is invalid or video is private
    - "Comments disabled" → Video has disabled comments
  - Provide action links in error messages (e.g., "Return to comments list")
- [ ] T029 [US4] Add success/status feedback in comments list page:
  - Display toast notification or flash message after import completes
  - Show "X comments imported successfully" on success
  - Show error details if import fails
  - Provide "View imported comments" link after successful import

**Checkpoint**: Full UI integration complete - users can easily discover and use API import feature

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Improvements affecting multiple user stories, testing, and documentation

- [ ] T030 [P] Add unit tests for ApiCommentImportService in tests/Unit/Services/ApiCommentImportServiceTest.php:
  - Test `getLatestCommentTimestamp()` returns correct timestamp
  - Test `previewComments()` returns exactly 5 comments or fewer
  - Test incremental fetch only returns comments newer than latest timestamp
  - Test duplicate detection stops fetch at first duplicate
  - Test recursive reply handling captures all nesting levels
  - Test comment_count update calculations
  - Test YouTube API error handling (invalid key, quota, not found, etc.)
- [ ] T031 [P] Add integration tests for API import workflow in tests/Feature/ApiCommentImportTest.php:
  - Test complete workflow for new video (preview + confirm + full import)
  - Test incremental workflow for existing video
  - Test that replies are stored with correct parent relationships
  - Test database field updates (channels, videos tables)
  - Test error handling for invalid URLs and API failures
  - Test duplicate prevention across multiple imports
- [ ] T032 [P] Add functional tests for UI flow in tests/Feature/ApiCommentImportControllerTest.php:
  - Test GET /comments/api-import returns form
  - Test POST /comments/api-import/preview with new video triggers "匯入" dialog
  - Test POST /comments/api-import/preview with existing video returns preview data
  - Test POST /comments/api-import/confirm dispatches background job
  - Test error responses for invalid input
- [ ] T033 Create fixtures and test data in tests/Fixtures/CommentsImportFixtures.php:
  - Create factory for test videos with various comment counts
  - Create factory for test comments with different reply depths
  - Create mock YouTube API responses for testing
- [ ] T034 Add rate limiting and exponential backoff in ApiCommentImportService:
  - Implement exponential backoff for API rate limit errors (429 responses)
  - Track API quota usage and warn user if approaching daily limit
  - Add configurable retry strategy (max retries, backoff duration)
- [ ] T035 Add comprehensive error handling:
  - Wrap all YouTube API calls in try-catch blocks
  - Log all API errors with request/response details
  - Return user-friendly error messages without exposing stack traces
  - Implement graceful degradation for partial failures
- [ ] T036 [P] Add validation:
  - Validate all comment fields before database insert (FR-019)
  - Check comment text is not empty and within length limits
  - Verify timestamps are valid and chronological
  - Ensure author information is captured
- [ ] T037 Add logging for audit trail:
  - Log all import operations (preview, confirm, complete) with timestamps
  - Log API quota usage per request
  - Log any skipped/duplicate comments with reasons
  - Log error details for failed imports
- [ ] T038 Add security hardening:
  - Sanitize comment text to prevent XSS attacks (strip/escape HTML)
  - Validate API responses to ensure expected structure
  - Implement CSRF protection on all POST endpoints
  - Validate user authorization (ensure user can perform imports)
- [ ] T039 Update application documentation:
  - Document new ApiCommentImportService API and usage
  - Update API.md with new endpoints (/comments/api-import routes)
  - Add troubleshooting guide for common import errors
  - Document YouTube API quota considerations and best practices
- [ ] T040 [P] Refactor and code quality:
  - Extract common YouTube API client logic into reusable utility class
  - Add type hints to all methods
  - Improve docstring comments for public methods
  - Run code analysis tools (PHPStan, Psalm) to catch potential issues
- [ ] T041 Run final validation and smoke tests:
  - Verify /comments page displays both import buttons
  - Test complete user journeys: new video import, incremental update, reply handling
  - Verify all database records are created/updated correctly
  - Test error scenarios and recovery paths
  - Run PHPUnit test suite (all tests must pass)
  - Validate against success criteria (SC-001 through SC-006)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories (CRITICAL)
- **User Stories (Phase 3-6)**: All depend on Foundational phase completion
  - User stories can proceed in parallel once Foundational is complete
  - OR sequentially in priority order (P1 → P2)
- **Polish (Phase 7)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1 - New Video Import)**: Depends on Foundational (Phase 2) completion
  - NO dependencies on other stories
  - Can be implemented and tested independently
  - Blocks start of Phase 2 foundational tasks (T006-T011) and controller routes

- **User Story 2 (P1 - Incremental Update)**: Depends on Foundational + should integrate with US1
  - Can proceed in parallel with US1 (uses same endpoints and database)
  - Validates that re-importing same video works correctly
  - Recommended to do after US1 is complete for clarity

- **User Story 3 (P1 - Reply Comments)**: Depends on Foundational + can proceed with US1/US2
  - Core recursive logic can be developed independently
  - Should be integrated into all import flows (US1 and US2)
  - Validates nested comment structure

- **User Story 4 (P2 - UI Integration)**: Depends on US1/US2/US3 business logic being complete
  - UI depends on controller endpoints being ready
  - Can be developed in parallel with US1-3 if using feature branch merging
  - Should be tested after backend features are working

### Within Each User Story

- Setup phase before Foundational phase
- Foundational phase (T006-T011) before ANY user story work
- Controllers (T012) before routes (T017)
- Routes before UI templates (T013)
- Service logic (part of T009) before job dispatch logic

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel (T001-T005)
- All Foundational tasks marked [P] can run in parallel (T006-T008)
- Once Foundational is complete:
  - All US1 models/services tasks can run in parallel (T019, T020 for US2)
  - All US3 tasks can run in parallel with US1/US2 (recursive logic is independent)
  - US4 UI tasks can start once routing is in place
- Test tasks (T030-T033) marked [P] can run in parallel after implementation
- Code quality tasks (T034-T040) marked [P] can run in parallel

---

## Parallel Example: User Story 1 + US2 Service Logic

```
Once Foundational (T006-T011) is complete:

Parallel Development Option 1 (Backend Focus):
Task T012: Create ApiCommentImportController
Task T013: Create api-import.blade.php template
Task T019: Implement getLatestCommentTimestamp() method
Task T020: Implement incremental fetch logic

Task T014: Implement preview() endpoint (depends on T019)
Task T015: Implement confirm() endpoint (depends on US1 logic)
Task T016: Create ApiImportCommentsBatchJob
Task T017: Add routes
Task T021: Modify preview() for incremental (depends on T020)
Task T022: Modify job for incremental (depends on T020)

Parallel Development Option 2 (Separate Teams):
Team A: US1 Implementation (T012-T018)
Team B: US2 Incremental Logic (T019-T022)
Team C: US3 Reply Handling (T023-T025)
Team D: US4 UI Integration (T026-T029)
All teams work in parallel once Foundational is done
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001-T005)
2. Complete Phase 2: Foundational (T006-T011) ← CRITICAL - must complete first
3. Complete Phase 3: User Story 1 (T012-T018)
4. Add basic tests (T030-T032)
5. **STOP and VALIDATE**: Test User Story 1 independently
6. Manual testing: Import new video → preview → confirm → verify data
7. Deploy if ready ✅ (MVP is complete)

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → Deploy/Demo (MVP!)
3. Add User Story 2 → Test independently → Deploy/Demo (incremental updates)
4. Add User Story 3 → Test independently → Deploy/Demo (thread preservation)
5. Add User Story 4 → Test independently → Deploy/Demo (polished UI)
6. Add Polish & Documentation → Final polish → Deploy

### Recommended Sequence (Single Developer)

1. Phases 1-2: Setup + Foundational (foundation building)
2. Phase 3: User Story 1 (new video import works)
3. Phase 5: User Story 3 (add reply handling to US1 flow)
4. Phase 4: User Story 2 (incremental updates)
5. Phase 6: User Story 4 (UI polish)
6. Phase 7: Tests + Polish (validation + code quality)

---

## Notes

- [P] tasks = different files, no dependencies (can run in parallel)
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- **CRITICAL**: Foundational phase (T006-T011) MUST complete before any story work
- Avoid vague tasks - each task has specific file paths and methods
- Avoid: same file conflicts, cross-story dependencies that break independence
- Tests are OPTIONAL - only needed for quality assurance and regression prevention
- Commit after each phase or logical group (e.g., after T012-T018 for US1)
- Stop at any checkpoint to validate story independently before proceeding
