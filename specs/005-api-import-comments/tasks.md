# Implementation Tasks: YouTube API Comments Import

**Feature**: `005-api-import-comments`
**Branch**: `005-api-import-comments`
**Date**: 2025-11-17
**Status**: Ready for Implementation

---

## Overview

This document breaks down the YouTube API comment import feature into independently testable user stories with corresponding implementation tasks. The feature is organized into phases:

- **Phase 1**: Setup & Infrastructure (project initialization, dependencies)
- **Phase 2**: Foundational (blocking prerequisites for all user stories)
- **Phase 3**: User Story 1 - New Video Import (P1)
- **Phase 4**: User Story 2 - Incremental Updates (P1)
- **Phase 5**: User Story 3 - Reply Threading (P1)
- **Phase 6**: User Story 4 - UI Integration (P2)
- **Phase 7**: Polish & Cross-Cutting Concerns

---

## Task Execution Strategy

### Dependency Graph

```
Phase 1 (Setup)
    ↓
Phase 2 (Foundational: Models, Base Services, Database)
    ↓
    ├→ Phase 3 (US1: New Video Import)
    ├→ Phase 4 (US2: Incremental Updates) [depends on US1]
    ├→ Phase 5 (US3: Reply Threading) [integrated into US1 & US2]
    └→ Phase 6 (US4: UI Integration) [depends on US1, US2, US3]
    ↓
Phase 7 (Polish, Error Handling, Documentation)
```

### Parallel Execution

Within each phase, most tasks are parallelizable (marked with `[P]`):
- **Phase 1**: All setup tasks independent
- **Phase 2**: Database migrations and model classes can be done in parallel
- **Phase 3**: Service, controller, and test classes can be implemented in parallel once models exist
- **Phase 4-6**: Similar parallel opportunities

### MVP Scope (Minimal Viable Product)

**Recommended MVP**: Complete User Story 1 (New Video Import) + minimal US4 UI button
- Justification: Demonstrates full feature capability for new videos
- Estimated effort: ~40-50% of total tasks
- Enables testing and gathering feedback before incremental updates

---

## Phase 1: Setup & Infrastructure

**Duration**: ~2-4 hours
**Goal**: Initialize project, install dependencies, configure environment

### Setup Tasks

- [ ] T001 Install google/apiclient dependency via Composer `composer require google/apiclient`
- [ ] T002 Create `.env` configuration with `YOUTUBE_API_KEY` placeholder (do not commit real key)
- [ ] T003 [P] Create database directory structure for migrations in `database/migrations/`
- [ ] T004 [P] Create `app/Services/` directory for service classes
- [ ] T005 [P] Create `app/Http/Controllers/` directory for controller class
- [ ] T006 [P] Create `app/Http/Requests/` directory for form request validation
- [ ] T007 [P] Create `app/Exceptions/` directory for custom exceptions
- [ ] T008 [P] Create `resources/views/comments/` directory for Blade templates
- [ ] T009 [P] Create `tests/Unit/`, `tests/Contract/`, `tests/Integration/` directories
- [ ] T010 Create `routes/web.php` YouTube API import route group (skeleton)

---

## Phase 2: Foundational Prerequisites

**Duration**: ~6-8 hours
**Goal**: Create database schema, models, base service class, custom exceptions

### 2.1 Database Migrations

- [ ] T011 [P] Create migration `create_videos_table` with fields: video_id, channel_id, title, published_at, created_at, updated_at
- [ ] T012 [P] Create migration `create_channels_table` with fields: channel_id, name, video_count, comment_count, first_import_at, last_import_at, created_at, updated_at
- [ ] T013 [P] Create migration `create_comments_table` with fields: comment_id, video_id, author_channel_id, text, like_count, parent_comment_id, published_at, created_at, updated_at
- [ ] T014 [P] Create migration `create_import_sessions_table` for audit logging (optional but recommended)
- [ ] T015 Run migrations: `php artisan migrate` and verify tables created

### 2.2 Model Classes

- [ ] T016 [P] Create `app/Models/Video.php` with relationships, validation rules (implement from data-model.md)
- [ ] T017 [P] Create `app/Models/Channel.php` with relationships, validation rules
- [ ] T018 [P] Create `app/Models/Comment.php` with relationships (parent/child), validation rules
- [ ] T019 [P] Create `app/Models/ImportSession.php` (optional) for audit trail
- [ ] T020 Add indexes to models via migration: unique(video_id), index(channel_id), index(parent_comment_id), composite unique(video_id, comment_id)

### 2.3 Custom Exception Class

- [ ] T021 Create `app/Exceptions/YouTubeApiException.php` with properties: httpStatus, youtubeErrorCode, message (per contracts spec)

### 2.4 Base YouTube API Service (Skeleton)

- [ ] T022 Create `app/Services/YouTubeApiService.php` with constructor injecting Google_Client and LoggerInterface
- [ ] T023 Implement YouTube API authentication in constructor (read API key from env)
- [ ] T024 Implement structured JSON logging setup with trace ID support

---

## Phase 3: User Story 1 - New Video Import (P1)

**Duration**: ~10-12 hours
**Goal**: Users can import comments from a new YouTube video with metadata confirmation and preview

**Independent Test Criteria**:
- User enters new video URL → metadata fetched from API and displayed
- User selects tags → metadata NOT persisted until full import
- User reviews preview → 5 sample comments shown
- User confirms import → all comments recursively fetched and saved
- No data persists if import fails or user cancels

### 3.1 YouTube API Service - Metadata Fetch

- [ ] T025 Implement `YouTubeApiService::fetchVideoMetadata(string $videoId)` method (per youtube-api-service contract)
- [ ] T026 [P] Add YouTube API error handling: map HTTP 404/403/401 to exceptions
- [ ] T027 [P] Implement structured logging for metadata fetch operations

### 3.2 YouTube API Service - Comment Fetch (Top-Level)

- [ ] T028 Implement `YouTubeApiService::fetchComments(string $videoId, array $options)` method fetching top-level comments
- [ ] T029 [P] Implement pagination logic to fetch ALL comments (not just first page)
- [ ] T030 [P] Implement recursive reply fetching via `fetchRepliesRecursive()` helper method

### 3.3 YouTube API Service - Reply Threading

- [ ] T031 Implement recursive traversal of `replies.comments` structure (depth-first)
- [ ] T032 Build in-memory tree structure with parent_comment_id references
- [ ] T033 Add validation: parent_comment_id correctly set for all nested replies

### 3.4 Comment Import Service - Transaction Management

- [ ] T034 Create `app/Services/CommentImportService.php` orchestrating import workflow
- [ ] T035 Implement DB::transaction() wrapper for atomic import (all-or-nothing)
- [ ] T036 Implement channel existence check (per FR-015a): INSERT or UPDATE channels table
- [ ] T037 Implement video existence check and INSERT if new video
- [ ] T038 Implement batch insert for comments with correct parent_comment_id linking
- [ ] T039 [P] Implement count updates for videos/channels tables (FR-016)

### 3.5 HTTP Controller - Import Endpoints

- [ ] T040 Create `app/Http/Controllers/YoutubeApiImportController.php` with method stubs
- [ ] T041 Implement `showImportForm()` returning import-modal.blade.php view
- [ ] T042 Implement `getMetadata(Request)` endpoint: fetch metadata, return JSON response
- [ ] T043 Implement `confirmMetadata(Request)` endpoint: validate metadata, store in session
- [ ] T044 Implement `getPreview(Request)` endpoint: fetch 5 sample comments, return JSON (NO DB persist)
- [ ] T045 Implement `confirmImport(Request)` endpoint: execute full import via CommentImportService

### 3.6 Form Validation

- [ ] T046 Create `app/Http/Requests/ImportVideoRequest.php` validating YouTube URL format
- [ ] T047 Extract YouTube video ID from URL (support youtube.com?v=ID and youtu.be/ID formats)

### 3.7 Blade Templates

- [ ] T048 [P] Create `resources/views/comments/import-modal.blade.php` with URL input form
- [ ] T049 [P] Create `resources/views/comments/metadata-dialog.blade.php` with title/channel display + tag selector
- [ ] T050 [P] Create `resources/views/comments/preview-dialog.blade.php` showing 5 sample comments

### 3.8 Routes

- [ ] T051 Add routes to `routes/web.php`:
  - GET `/comments/import` → showImportForm
  - POST `/comments/import/metadata` → getMetadata
  - POST `/comments/import/confirm-metadata` → confirmMetadata
  - POST `/comments/import/preview` → getPreview
  - POST `/comments/import/confirm-import` → confirmImport

### 3.9 Unit Tests (Contract Tests First - TDD)

- [ ] T052 [P] Create `tests/Contract/YouTubeApiContractTest.php` with fixtures/VCR cassettes for YouTube API responses
- [ ] T053 [P] Test: fetchVideoMetadata returns correct schema (title, channel_id, channel_name, published_at)
- [ ] T054 [P] Test: fetchVideoMetadata handles 404 (video not found) → exception
- [ ] T055 [P] Test: fetchVideoMetadata handles 403 quota exceeded → exception
- [ ] T056 [P] Test: fetchComments returns all top-level comments with correct structure
- [ ] T057 [P] Test: fetchComments handles empty comment list
- [ ] T058 [P] Test: fetchComments handles pagination (>100 comments)

### 3.10 Unit Tests (Service Layer)

- [ ] T059 [P] Create `tests/Unit/YouTubeApiServiceTest.php` with mocked Google API client
- [ ] T060 [P] Test: fetchVideoMetadata parses API response correctly
- [ ] T061 [P] Test: fetchComments builds correct tree structure with parent_comment_id

### 3.11 Integration Tests (Full Workflow)

- [ ] T062 Create `tests/Integration/CommentImportWorkflowTest.php`
- [ ] T063 Test: New video import workflow end-to-end (metadata → preview → full import → DB verification)
- [ ] T064 Test: Transaction rollback on API error (verify NO data persisted)
- [ ] T065 Test: Transaction rollback on user cancel (verify NO data persisted)
- [ ] T066 Test: Verify correct comment count in videos table after import
- [ ] T067 Test: Verify correct comment count in channels table after import
- [ ] T068 Test: Verify channels table INSERT for new channel (FR-015a)

### 3.12 Run Tests & Verify

- [ ] T069 Run full test suite: `php artisan test`
- [ ] T070 Verify all User Story 1 tests passing

---

## Phase 4: User Story 2 - Incremental Updates (P1)

**Duration**: ~8-10 hours
**Goal**: Users can re-import existing videos and fetch only new comments

**Independent Test Criteria**:
- User enters URL for existing video → preview shows only NEW comments
- Primary stopping condition works: stop at max(published_at) boundary
- Secondary guard works: catch duplicate comment_ids
- No duplicates created in database
- Video/channel counts correctly updated

### 4.1 Comment Import Service - Incremental Logic

- [ ] T071 Implement max_timestamp detection: query existing comments for max(published_at)
- [ ] T072 Implement existing_comment_ids set for duplicate detection
- [ ] T073 Implement PRIMARY stopping condition: stop when published_at <= max_timestamp (per FR-013)
- [ ] T074 Implement SECONDARY guard: stop on duplicate comment_id detection (per FR-014)
- [ ] T075 Update comments batch insert: skip duplicates (no UPDATE, immutable records)

### 4.2 Controller - Incremental Preview & Import

- [ ] T076 Update `getPreview()` to detect existing video and filter NEW comments only
- [ ] T077 Implement "no new comments" message in preview (per edge case spec)
- [ ] T078 Update `confirmImport()` to handle existing video path (use stopping conditions)

### 4.3 Contract Tests - Incremental Logic

- [ ] T079 [P] Test: fetchComments with max_timestamp filters correctly
- [ ] T080 [P] Test: fetchComments stops at primary condition (timestamp boundary)
- [ ] T081 [P] Test: fetchComments stops at secondary condition (duplicate_id)

### 4.4 Integration Tests - Incremental Workflow

- [ ] T082 Test: Incremental import workflow end-to-end (existing video → only new comments fetched)
- [ ] T083 Test: No duplicates created on re-import
- [ ] T084 Test: Duplicate comment_ids handled correctly (skipped)
- [ ] T085 Test: Video/channel counts updated correctly for existing records (FR-016)

### 4.5 Edge Case Tests

- [ ] T086 Test: Video with no new comments shows "no new comments" message but import still works
- [ ] T087 Test: API ordering edge case: comments returned out of order (secondary guard catches)

---

## Phase 5: User Story 3 - Reply Threading (P1)

**Duration**: ~4-6 hours
**Goal**: Ensure all reply levels are recursively imported and linked correctly

**Independent Test Criteria**:
- Multi-level reply threads imported (3+ levels)
- Every reply linked via parent_comment_id
- No missing replies at any depth

*Note: Reply threading is implemented in Phase 3 (US1) via fetchRepliesRecursive(). This phase focuses on comprehensive testing.*

### 5.1 Integration Tests - Reply Threading

- [ ] T088 Test: Video with 3+ level reply threads: all replies imported with correct parent_comment_id
- [ ] T089 Test: Deeply nested replies (10+ levels) all captured and linked
- [ ] T090 Test: Replies to new top-level comments in incremental import: all new reply levels captured
- [ ] T091 Test: Duplicate replies in incremental update: not re-imported

---

## Phase 6: User Story 4 - UI Integration (P2)

**Duration**: ~4-6 hours
**Goal**: Integrate YouTube API import button into existing comments interface

**Independent Test Criteria**:
- Button visible on comments list page
- Button opens import modal
- Both new and existing video flows work correctly

### 6.1 UI Button Integration

- [ ] T092 Add "官方API導入" button to `resources/views/comments/index.blade.php`
- [ ] T093 [P] Implement JavaScript event handler: open import modal on button click
- [ ] T094 [P] Implement AJAX form submission for URL input
- [ ] T095 [P] Implement success/error message display

### 6.2 User Flow Tests

- [ ] T096 Test: Button visible on comments page
- [ ] T097 Test: Button click opens import modal
- [ ] T098 Test: New video flow: URL → metadata dialog → preview → import
- [ ] T099 Test: Existing video flow: URL → preview → import (skip metadata)
- [ ] T100 Test: Modal closes cleanly on import success

---

## Phase 7: Polish & Cross-Cutting Concerns

**Duration**: ~4-6 hours
**Goal**: Error handling, documentation, performance optimization

### 7.1 Error Handling & User Feedback

- [ ] T101 Implement structured JSON error responses for all endpoints (per import-controller contract)
- [ ] T102 [P] Handle YouTube API errors: quota exceeded, rate limit, invalid key, video not found
- [ ] T103 [P] Handle network timeouts (implement retry logic with exponential backoff)
- [ ] T104 [P] Implement user-friendly error messages in Chinese (per spec)
- [ ] T105 [P] Implement progress indicators for long-running imports (>5 seconds)

### 7.2 Structured Logging (Observable Systems)

- [ ] T106 Implement trace ID generation for each import session (UUID)
- [ ] T107 [P] Log operation boundaries: import_start, metadata_fetch, preview_fetch, full_import_start, db_commit
- [ ] T108 [P] Log JSON format: timestamp, trace_id, operation, video_id, source, record_count, status
- [ ] T109 [P] Implement ImportSession model persistence (optional) for audit trail

### 7.3 Performance Optimization

- [ ] T110 Implement comment batch insert (avoid N+1 query problem)
- [ ] T111 [P] Test import performance: new video <30s, incremental <10s (per success criteria)
- [ ] T112 [P] Profile database queries: verify no unnecessary queries during import

### 7.4 Documentation & Code Quality

- [ ] T113 [P] Add PHPDoc comments to all public methods in services and controllers
- [ ] T114 [P] Create API documentation: endpoint signatures, request/response examples
- [ ] T115 [P] Add inline comments for complex logic: recursive reply fetching, stopping conditions
- [ ] T116 [P] Create deployment guide for setting YouTube API key in production

### 7.5 Final Testing & Validation

- [ ] T117 Run full test suite: `php artisan test` (target: >90% coverage)
- [ ] T118 [P] Manual testing: import various video types (new, existing, different comment volumes)
- [ ] T119 [P] Manual testing: error scenarios (invalid URL, video not found, quota exceeded)
- [ ] T120 [P] Manual testing: UI workflows on different browsers (Chrome, Firefox, Safari)

### 7.6 Code Review & Quality Gates

- [ ] T121 Run code quality tools: phpstan, phpcs (PSR-12 standard)
- [ ] T122 Fix any type errors or code style violations
- [ ] T123 Ensure all test names are descriptive and follow convention
- [ ] T124 Document any architectural decisions in comments or separate ARCHITECTURE.md

---

## Execution Checklist

Use this section to track overall progress:

- [ ] **Phase 1**: Setup & Infrastructure (10 tasks) - START HERE
- [ ] **Phase 2**: Foundational (13 tasks)
- [ ] **Phase 3**: User Story 1 - New Video Import (45 tasks)
- [ ] **Phase 4**: User Story 2 - Incremental Updates (15 tasks)
- [ ] **Phase 5**: User Story 3 - Reply Threading (4 tasks)
- [ ] **Phase 6**: User Story 4 - UI Integration (9 tasks)
- [ ] **Phase 7**: Polish & Cross-Cutting Concerns (15 tasks)

**Total**: 124 implementation tasks

---

## Parallel Execution Examples

### Within Phase 3 (US1):

These tasks can be executed in parallel once models exist (after T020):
- T025-T033: YouTube API Service methods (parallel)
- T034-T039: CommentImportService methods (parallel)
- T040-T050: Controller and templates (mostly parallel)

Example:
```
Developer A: Implement YouTubeApiService methods (T025-T033)
Developer B: Implement CommentImportService methods (T034-T039)
Developer C: Create Blade templates (T048-T050)
Developer D: Create form validation (T046-T047)
→ Then merge and test together (T052-T070)
```

### Within Phase 2:

These tasks are fully parallel:
- T011-T014: All migrations (parallel)
- T016-T019: All model classes (parallel)

---

## Notes for Implementation

1. **TDD Approach**: Write contract tests (T052-T058) BEFORE implementing service methods (T025-T033)
2. **Transaction Testing**: Verify rollback behavior thoroughly (T064-T065)
3. **API Quota**: Use VCR cassettes in tests to avoid hitting real YouTube API during development
4. **Logging**: Implement structured logging from the start (not as an afterthought in Phase 7)
5. **Error Messages**: Keep user messages in Chinese per spec context
6. **Documentation**: Refer to research.md and data-model.md during implementation for design decisions

---

## Success Metrics

Upon completion:
- ✅ All 124 tasks completed and tested
- ✅ User Story 1 (new video) fully working and tested
- ✅ User Story 2 (incremental) fully working and tested
- ✅ User Story 3 (replies) fully working and tested
- ✅ User Story 4 (UI) fully working and tested
- ✅ Test coverage >90%
- ✅ No failing tests
- ✅ Code quality: zero phpstan errors, PSR-12 compliant
- ✅ Performance: <30s new video import, <10s incremental

---

## Quick Start

1. Start with **Phase 1** (setup): 10 tasks, ~2-4 hours
2. Then **Phase 2** (foundational): 13 tasks, ~6-8 hours
3. Then pick **Phase 3** (MVP) for immediate value: 45 tasks, ~10-12 hours
4. Phases 4-7 follow MVP completion

Estimated total effort: **~50-70 hours** for full feature with one developer
