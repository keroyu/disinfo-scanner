# Implementation Plan: Video Incremental Update

**Branch**: `007-video-incremental-update` | **Date**: 2025-11-18 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/007-video-incremental-update/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Add incremental update capability to the Videos List page, allowing users to import only new comments posted after the last recorded comment timestamp. Users can click an "Update" button next to each video title to trigger a modal that previews new comments (first 5 chronologically) and shows the total count before importing. The system enforces a 500-comment limit per update to prevent timeouts, handles concurrent updates with idempotent inserts, and automatically updates the videos.comment_count and videos.updated_at fields after successful import.

## Technical Context

**Language/Version**: PHP 8.2 with Laravel Framework 12.38.1
**Primary Dependencies**:
- Laravel Framework 12.38.1
- Google API Client 2.18 (for YouTube Data API v3)
- Blade templating engine
- Tailwind CSS (frontend styling)

**Storage**: MySQL/MariaDB via Laravel Eloquent ORM
- videos table (existing: video_id, channel_id, title, published_at, comment_count, updated_at)
- comments table (existing: comment_id, video_id, author_channel_id, text, like_count, published_at, parent_comment_id)

**Testing**: PHPUnit (Laravel's built-in testing framework)
**Target Platform**: Web application (server-side: PHP/Laravel, client-side: JavaScript + Blade templates)
**Project Type**: Web application (Laravel MVC with Blade views)
**Performance Goals**:
- Preview modal loads within 3 seconds
- Import completes within 60 seconds for 500 comments
- Zero duplicate imports (idempotent inserts)

**Constraints**:
- YouTube API quota limitations (conservative API usage)
- 500-comment limit per update operation (prevent timeouts)
- Chinese character truncation must count multi-byte characters correctly (15 chars)
- All UI text in Traditional Chinese
- Database datetime format: YYYY-MM-DD HH:MM:SS (e.g., 2025-06-13 21:00:03)

**Scale/Scope**:
- Extends existing Videos List page (already paginated at 500 videos/page)
- Reuses existing YouTubeApiService and CommentImportService infrastructure
- Adds 1 new modal component, 1 new API controller, and extends 1 existing service

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Test-First Development ✅ PASS
- **Status**: Will implement tests before code
- **Approach**:
  - Write contract tests for new API endpoints (preview, incremental import)
  - Write integration tests for incremental update logic (500-limit, idempotent inserts)
  - Write feature tests for modal interaction and UI updates

### II. API-First Design ✅ PASS
- **Status**: Clear API contracts defined from spec
- **Contracts**:
  - `POST /api/video-update/preview` - Fetch preview of new comments
  - `POST /api/video-update/import` - Execute incremental import
  - Both endpoints return structured JSON with actionable error messages

### III. Observable Systems ✅ PASS
- **Status**: Will implement structured logging
- **Logging Strategy**:
  - Log each incremental update operation (video_id, last_comment_timestamp, new_count)
  - Log API calls to YouTube (quota tracking)
  - Include trace IDs for debugging concurrent update scenarios

### IV. Contract Testing ✅ PASS
- **Status**: Will validate service boundaries
- **Contract Tests**:
  - YouTubeApiService extension for publishedAfter filtering
  - Preview endpoint schema validation
  - Import endpoint idempotency validation (duplicate comment_id handling)

### V. Semantic Versioning ⚠️ DEFER
- **Status**: Feature addition (MINOR version bump)
- **Rationale**: This is a backward-compatible feature addition to existing Videos List functionality
- **Action**: No breaking changes; existing import flows unchanged

**Gate Result**: ✅ PASS - All critical principles satisfied

---

## Constitution Re-Check (Post-Design)

*Re-evaluated after Phase 1 design completion*

### I. Test-First Development ✅ PASS
- **Contract tests defined**: See [contracts/preview-api.yaml](./contracts/preview-api.yaml) and [contracts/import-api.yaml](./contracts/import-api.yaml)
- **Test strategy documented**: TDD workflow in [quickstart.md](./quickstart.md) with RED-GREEN-REFACTOR cycle
- **Feature tests planned**: `VideoIncrementalUpdateTest.php`, `IncrementalImportServiceTest.php`

### II. API-First Design ✅ PASS
- **OpenAPI contracts created**: Full request/response schemas for both endpoints
- **Error responses documented**: 400, 404, 429, 500 with actionable error messages
- **JSON-first**: All endpoints return structured JSON, no HTML responses

### III. Observable Systems ✅ PASS
- **Structured logging planned**: Log video_id, last_comment_timestamp, imported_count
- **Trace IDs**: Leverage Laravel's built-in request ID for audit trails
- **API quota tracking**: Log YouTube API calls for quota monitoring

### IV. Contract Testing ✅ PASS
- **Service boundary validation**: Tests for YouTubeApiService publishedAfter filtering
- **Idempotency tests**: Verify firstOrCreate() prevents duplicates
- **Schema validation**: OpenAPI specs serve as contract test basis

### V. Semantic Versioning ✅ PASS
- **Version impact**: MINOR version bump (backward-compatible feature addition)
- **No breaking changes**: Existing import flows unchanged
- **Migration-free**: No schema changes, no database migrations

**Final Gate Result**: ✅ PASS - All principles validated post-design

## Project Structure

### Documentation (this feature)

```text
specs/007-video-incremental-update/
├── plan.md              # This file (/speckit.plan command output)
├── spec.md              # Feature specification (completed)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
│   ├── preview-api.yaml
│   └── import-api.yaml
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Http/
│   └── Controllers/
│       ├── Api/
│       │   └── VideoUpdateController.php       # NEW: Handles preview + import endpoints
│       └── VideoController.php                 # MODIFIED: Add modal trigger support
├── Services/
│   ├── YouTubeApiService.php                  # MODIFIED: Add publishedAfter filtering
│   ├── CommentImportService.php               # MODIFIED: Add incremental import method
│   └── VideoIncrementalUpdateService.php      # NEW: Orchestrates incremental updates
└── Models/
    └── Video.php                               # EXISTING: Already has comment_count, updated_at

resources/
└── views/
    └── videos/
        ├── list.blade.php                      # MODIFIED: Add Update button, include modal
        └── incremental-update-modal.blade.php  # NEW: Modal component

routes/
└── api.php                                     # MODIFIED: Add /api/video-update routes

tests/
├── Feature/
│   └── VideoIncrementalUpdateTest.php          # NEW: Feature tests for update flow
├── Integration/
│   └── IncrementalImportServiceTest.php        # NEW: Test 500-limit, idempotency
└── Unit/
    └── YouTubeApiServiceTest.php               # MODIFIED: Test publishedAfter param
```

**Structure Decision**: Web application structure (Laravel MVC). Frontend uses Blade templates with inline JavaScript for modal interactions. Backend API endpoints return JSON for AJAX calls. This aligns with existing import modal patterns (import-modal.blade.php, uapi-import-modal.blade.php).

## Complexity Tracking

> No violations requiring justification - all constitution gates passed.

## Files to Create/Modify

### Files to CREATE (6 new files):

1. **`app/Http/Controllers/Api/VideoUpdateController.php`**
   - Purpose: Handle preview and incremental import API requests
   - Methods: `preview()`, `import()`

2. **`app/Services/VideoIncrementalUpdateService.php`**
   - Purpose: Orchestrate incremental update workflow (query last comment, call API, import with 500-limit)
   - Dependencies: YouTubeApiService, CommentImportService

3. **`resources/views/videos/incremental-update-modal.blade.php`**
   - Purpose: Modal UI for preview and import confirmation
   - Features: Display preview comments, show count, confirm button

4. **`tests/Feature/VideoIncrementalUpdateTest.php`**
   - Purpose: End-to-end tests for incremental update workflow

5. **`tests/Integration/IncrementalImportServiceTest.php`**
   - Purpose: Test 500-comment limit enforcement, idempotent inserts

6. **`specs/007-video-incremental-update/contracts/preview-api.yaml`**
   - Purpose: OpenAPI contract for preview endpoint

7. **`specs/007-video-incremental-update/contracts/import-api.yaml`**
   - Purpose: OpenAPI contract for import endpoint

### Files to MODIFY (5 existing files):

1. **`app/Services/YouTubeApiService.php`**
   - Change: Add method `fetchCommentsAfter(string $videoId, string $publishedAfter, int $maxResults = 500)`
   - Reason: Enable filtering comments by publishedAfter parameter

2. **`app/Services/CommentImportService.php`**
   - Change: Add method `importIncrementalComments(string $videoId, array $comments, int $limit = 500)`
   - Reason: Handle incremental import with 500-comment limit and idempotency checks

3. **`resources/views/videos/list.blade.php`**
   - Change:
     - Truncate video titles to 15 Chinese characters with tooltip
     - Add "Update" button next to each video title
     - Include incremental-update-modal component
   - Reason: Add entry point for incremental update feature

4. **`routes/api.php`**
   - Change: Add routes for `/api/video-update/preview` and `/api/video-update/import`
   - Reason: Expose API endpoints for AJAX calls from modal

5. **`tests/Unit/YouTubeApiServiceTest.php`**
   - Change: Add tests for `fetchCommentsAfter()` method
   - Reason: Validate publishedAfter filtering works correctly

### Database Datetime Format Standard

All datetime fields written to database MUST use format: `YYYY-MM-DD HH:MM:SS`

**Example**: `2025-06-13 21:00:03`

**Implementation**: Laravel's Carbon/Eloquent automatically handles this via `$casts = ['published_at' => 'datetime']` in models. When setting `updated_at`, use:
```php
$video->updated_at = now(); // Laravel automatically formats to Y-m-d H:i:s
```
