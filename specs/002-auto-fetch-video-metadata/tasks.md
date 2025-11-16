# Implementation Tasks: Auto-Fetch Video Metadata with Two-Step Confirmation

**Feature Branch**: `002-auto-fetch-video-metadata`
**Date**: 2025-11-16
**Spec**: `/specs/002-auto-fetch-video-metadata/spec.md`
**Plan**: `/specs/002-auto-fetch-video-metadata/plan.md`

---

## Executive Summary

This document provides an incremental, user-story-driven task breakdown for implementing video metadata scraping and two-step confirmation flow for the YouTube comment import system.

**Total Tasks**: 68
**Estimated Effort**: 4-5 days
**Implementation Strategy**: Incremental with independent testing per user story

### Task Distribution

- **Phase 1 - Setup**: 3 tasks
- **Phase 2 - Foundational Services**: 8 tasks
- **Phase 3 - US-002-01 (Metadata Scraping)**: 9 tasks
- **Phase 4 - US-002-02 (Confirmation Interface)**: 16 tasks
- **Phase 5 - US-002-03 (Data Atomicity)**: 10 tasks
- **Phase 6 - Integration Testing**: 9 tasks
- **Phase 7 - Polish & Documentation**: 13 tasks

---

## Phase 1: Setup & Dependencies

**Goal**: Prepare project environment and install required libraries

- [ ] T001 Run `composer require symfony/dom-crawler symfony/css-selector` to install HTML parsing libraries

---

## Phase 2: Foundational Services

**Goal**: Create YouTubeMetadataService foundation for metadata extraction

- [ ] T002 [P] Create `app/Services/YouTubeMetadataService.php` with class stub and dependencies (Guzzle HTTP client, DomCrawler)

- [ ] T003 [P] Implement `YouTubeMetadataService::__construct()` method to initialize Guzzle client with 10-second timeout in `app/Services/YouTubeMetadataService.php`

- [ ] T004 [P] Implement `YouTubeMetadataService::scrapeMetadata(string $videoId): array` method to fetch YouTube page and return structured metadata in `app/Services/YouTubeMetadataService.php`

- [ ] T005 [P] Implement `YouTubeMetadataService::extractVideoTitle(string $html): ?string` method using DomCrawler to extract title from meta tags in `app/Services/YouTubeMetadataService.php`

- [ ] T006 [P] Implement `YouTubeMetadataService::extractChannelName(string $html): ?string` method using DomCrawler to extract channel name/uploader in `app/Services/YouTubeMetadataService.php`

- [ ] T007 [P] Add error handling to YouTubeMetadataService methods to catch network timeouts, parsing errors, and return null/failed status gracefully in `app/Services/YouTubeMetadataService.php`

- [ ] T008 [P] Create `tests/Unit/YouTubeMetadataServiceTest.php` with unit tests for HTML parsing logic (title extraction, channel extraction, edge cases)

- [ ] T009 Create unit test case in `tests/Unit/YouTubeMetadataServiceTest.php` for timeout handling (mock Guzzle timeout exception, verify graceful degradation)

---

## Phase 3: User Story US-002-01 - Video Metadata Scraping

**Story Goal**: Automatically scrape YouTube video title and channel name when user provides YouTube URL

- [ ] T010 [P] [US1] Modify `app/Services/ImportService.php` - Add `prepareImport(string $url): object` method that handles URL parsing, metadata scraping (via new YouTubeMetadataService), API data fetching, and pending import creation

- [ ] T011 [US1] Implement graceful degradation in prepareImport() - If YouTube metadata scraping fails or times out, proceed with urtubeapi data and return null for missing fields in `app/Services/ImportService.php`

- [ ] T012 [P] [US1] Modify `app/Services/ChannelTaggingService.php` - Extend `createPendingImport()` method signature to accept optional `?string $videoTitle` and `?int $commentCount` parameters

- [ ] T013 [US1] Update cache storage in `app/Services/ChannelTaggingService.php` to persist video_title and comment_count in pending import records

- [ ] T014 [US1] Add integration between ImportService::prepareImport() and YouTubeMetadataService in `app/Services/ImportService.php` - inject YouTubeMetadataService dependency and call scrapeMetadata()

- [ ] T015 [US1] Add logging to prepareImport() in `app/Services/ImportService.php` - log metadata scraping success/failure with video ID and extraction status

- [ ] T016 [P] [US1] Create `tests/Unit/ImportServicePrepareConfirmTest.php` with unit tests for prepareImport() method (mocking metadata service, caching behavior)

- [ ] T017 [P] [US1] Create acceptance test case in `tests/Unit/ImportServicePrepareConfirmTest.php` - prepareImport() returns correct structure with metadata fields

- [ ] T018 [US1] Create acceptance test case in `tests/Unit/ImportServicePrepareConfirmTest.php` - prepareImport() handles metadata scraping timeout gracefully

---

## Phase 4: User Story US-002-02 - Confirmation Interface

**Story Goal**: Display confirmation interface showing extracted metadata and tag selection before database write

- [ ] T019 [US2] Modify `app/Http/Controllers/ImportController.php` - Change `store()` method to call `prepareImport()` instead of full `import()`, and return HTTP 202 (Accepted) with metadata

- [ ] T020 [P] [US2] Create `app/Http/Controllers/ImportConfirmationController.php` with `confirm(Request $request)` method that retrieves cached import, validates tags (if required), and calls `confirmImport()`

- [ ] T021 [P] [US2] Implement `cancel(Request $request)` method in `app/Http/Controllers/ImportConfirmationController.php` to clear cached import without writing to database

- [ ] T022 [US2] Add routes to `routes/api.php`:
  - `POST /api/import/confirm` → `ImportConfirmationController@confirm`
  - `POST /api/import/cancel` → `ImportConfirmationController@cancel`

- [ ] T023 [P] [US2] Add confirmation modal HTML to `resources/views/import/index.blade.php` with sections for video title, channel name, comment count display

- [ ] T024 [P] [US2] Add conditional tag selection section to confirmation modal in `resources/views/import/index.blade.php` (visible only when new channel detected)

- [ ] T025 [P] [US2] Update JavaScript in `resources/views/import/index.blade.php` - Modify form submission handler to call `/api/import` and show confirmation modal on HTTP 202 response

- [ ] T026 [P] [US2] Add `showConfirmationModal(importData)` JavaScript function in `resources/views/import/index.blade.php` that populates modal with metadata

- [ ] T027 [P] [US2] Add state management variables to JavaScript in `resources/views/import/index.blade.php`:
  - `currentImportId`, `currentChannelId`, `currentRequiresTags`, `selectedTags`, `availableTags`

- [ ] T028 [P] [US2] Implement "確認並寫入資料" button handler in `resources/views/import/index.blade.php` that validates tags and calls `/api/import/confirm` endpoint

- [ ] T029 [P] [US2] Implement "取消匯入" button handler in `resources/views/import/index.blade.php` that calls `/api/import/cancel` and closes modal

- [ ] T030 [US2] Add button state management in `resources/views/import/index.blade.php` JavaScript - disable "確認並寫入資料" button when new channel and no tags selected, show tooltip message

- [ ] T031 [US2] Add tag selection validation logic in `resources/views/import/index.blade.php` - prevent confirmation unless at least one tag selected for new channels

- [ ] T032 [P] [US2] Create acceptance test case in `tests/Unit/ImportServicePrepareConfirmTest.php` - ImportController::store() returns 202 with metadata

- [ ] T033 [P] [US2] Create acceptance test case in `tests/Unit/ImportServicePrepareConfirmTest.php` - ImportConfirmationController::confirm() writes to database with correct tags

- [ ] T034 [US2] Create acceptance test case in `tests/Unit/ImportServicePrepareConfirmTest.php` - ImportConfirmationController::cancel() clears cache without database write

---

## Phase 5: User Story US-002-03 - Data Atomicity

**Story Goal**: Ensure all database writes (video, comments, authors, tags) are atomic - either all succeed or all fail

- [ ] T035 [US3] Modify `app/Services/ImportService.php` - Add `confirmImport(string $importId, ?array $tags = null): object` method that wraps entire database write in transaction

- [ ] T036 [US3] Implement transaction logic in `confirmImport()` in `app/Services/ImportService.php`:
  - Retrieve cached import data by importId
  - Validate tags if new channel (throw ValidationException if invalid)
  - Wrap in DB::transaction() - insert channel, video, authors, comments, tags
  - Clear cache after successful write
  - Return statistics

- [ ] T037 [US3] Modify existing `import()` method in `app/Services/ImportService.php` for backward compatibility - Internally delegate to prepareImport() + confirmImport() for existing single-step flow

- [ ] T038 [US3] Add error handling to confirmImport() in `app/Services/ImportService.php` - catch database exceptions and let transaction rollback automatically

- [ ] T039 [US3] Add logging to confirmImport() in `app/Services/ImportService.php` - log transaction success with record counts and failure with error context

- [ ] T040 [US3] Ensure ImportConfirmationController::confirm() in `app/Http/Controllers/ImportConfirmationController.php` properly handles confirmImport() exceptions and returns appropriate error responses

- [ ] T041 [P] [US3] Create `tests/Unit/ImportServicePrepareConfirmTest.php` unit tests for confirmImport() method with transaction mocking

- [ ] T042 [P] [US3] Create atomicity test case in `tests/Unit/ImportServicePrepareConfirmTest.php` - if comment insert fails, entire transaction rolls back (no partial writes)

- [ ] T043 [P] [US3] Create atomicity test case in `tests/Unit/ImportServicePrepareConfirmTest.php` - if tag insert fails, video/comments/authors also rolled back

- [ ] T044 [US3] Create atomicity test case in `tests/Unit/ImportServicePrepareConfirmTest.php` - successful confirmImport() persists all data atomically in single transaction

---

## Phase 6: Integration Testing

**Goal**: Validate end-to-end confirmation flow and database integrity

- [ ] T045 [P] Create `tests/Integration/ImportConfirmationFlowTest.php` with test case for full happy path - existing channel (no tags required)

- [ ] T046 [P] Add integration test case in `tests/Integration/ImportConfirmationFlowTest.php` for new channel with tag selection

- [ ] T047 [P] Add integration test case in `tests/Integration/ImportConfirmationFlowTest.php` - cancel flow leaves no database records

- [ ] T048 [P] Add integration test case in `tests/Integration/ImportConfirmationFlowTest.php` - expired import (> 10 min) returns error

- [ ] T049 [P] Add integration test case in `tests/Integration/ImportConfirmationFlowTest.php` - metadata scraping failure shows graceful error message

- [ ] T050 [P] Add integration test case in `tests/Integration/ImportConfirmationFlowTest.php` - database atomicity (transaction rollback on error)

- [ ] T051 Add manual test scenario documentation for metadata scraping with real YouTube URLs (10+ videos)

- [ ] T052 Add manual test scenario documentation for confirmation modal UI with various screen sizes

- [ ] T053 Add manual test scenario documentation for error scenarios (timeout, network failure, invalid data)

---

## Phase 7: Polish & Documentation

**Goal**: Error handling, logging, code quality, and developer documentation

- [ ] T054 [P] Add comprehensive error handling to `app/Services/YouTubeMetadataService.php` - Network timeouts, parsing failures, invalid responses all logged and handled gracefully

- [ ] T055 [P] Add error handling to `app/Http/Controllers/ImportConfirmationController.php` - Validate import_id exists, tags are array, return appropriate error messages

- [ ] T056 [P] Add error message mapping in `app/Services/ImportService.php` confirmImport() - Convert exceptions to user-friendly Chinese messages

- [ ] T057 Add structured logging to YouTubeMetadataService in `app/Services/YouTubeMetadataService.php` - Log scraping attempts, success/failure, extraction status

- [ ] T058 Add structured logging to ImportService methods in `app/Services/ImportService.php` - Log prepare/confirm operations with trace ID and record counts

- [ ] T059 Add inline code documentation to all new service methods in `app/Services/YouTubeMetadataService.php` and modified methods in `app/Services/ImportService.php`

- [ ] T060 [P] Optimize HTTP request handling in YouTubeMetadataService - Add connection pool reuse, HTTP keep-alive, caching headers

- [ ] T061 [P] Optimize database transaction in confirmImport() in `app/Services/ImportService.php` - Batch inserts where possible, minimize transaction scope

- [ ] T062 [P] Add performance monitoring in `app/Http/Controllers/ImportConfirmationController.php` - Log request/response times for scraping and database operations

- [ ] T063 Run full test suite: `php artisan test` - Verify no regressions in existing 001-comment-import tests

- [ ] T064 Run existing feature tests to ensure backward compatibility with `import()` method

- [ ] T065 Manual testing checklist - Test all UI flows, error scenarios, edge cases with real data

- [ ] T066 Performance validation - Measure metadata scraping time, database write time, E2E time against targets

---

## Dependency Graph

```
Phase 1 (T001)
    ↓
Phase 2 (T002-T009) - YouTubeMetadataService
    ↓
Phase 3 (T010-T018) - US-002-01 Metadata Scraping
    ├→ Phase 4 (T019-T034) - US-002-02 Confirmation Interface
    │   ├→ Phase 5 (T035-T044) - US-002-03 Data Atomicity
    │       ↓
    │   Phase 6 (T045-T053) - Integration Testing
    │       ↓
    │   Phase 7 (T054-T066) - Polish & Documentation
```

---

## Parallel Execution Opportunities

**Can run concurrently**:
- T002-T007: All YouTubeMetadataService implementation
- T008-T009: YouTubeMetadataService tests
- T012, T016: Service modifications and tests
- T020-T021: Both ImportConfirmationController methods
- T023-T024: HTML sections
- T025-T031: JavaScript functions
- T054-T062: Error handling and optimization
- T045-T050: Integration tests

**Estimated speedup**: 25-30% reduction in total execution time with parallel work

---

## Success Criteria

✅ All 66 tasks completed and checked
✅ All unit tests pass
✅ All integration tests pass  
✅ Manual testing completed
✅ Performance targets met (< 10s E2E)
✅ Zero regressions in 001-comment-import
✅ Code documentation complete
