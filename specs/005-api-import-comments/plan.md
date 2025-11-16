# Implementation Plan: YouTube API Comments Import

**Branch**: `005-api-import-comments` | **Date**: 2025-11-17 | **Spec**: `/specs/005-api-import-comments/spec.md`
**Input**: Feature specification from `/specs/005-api-import-comments/spec.md`

## Clarifications Integration

This plan fully incorporates all clarifications from spec.md:

| Clarification | Spec Impact | Design Decision |
|---------------|------------|-----------------|
| Preview = 5 comments, not persisted | FR-005, FR-006 | YouTubeApiService::fetchPreviewComments returns array without DB insert |
| Channel/Video field updates | FR-016 | UpdateMetadataService or inline logic in controller after comment store |
| Cancel = close dialog, no action | Edge case | No special exception handling; user cancel triggers return without DB transaction |
| Reply depth = all levels | FR-010 | Recursive fetchReplies loop in YouTubeApiService |
| New video = invoke "匯入" dialog | FR-004 | Controller routes to ImportController if video_id not in DB |
| File separation = YouTubeApiService | Architecture | Separate from UrtubeapiService completely |

---

## Summary

Implement YouTube API-based comment import functionality as a new, isolated feature separate from the existing urtubeapi-based "匯入" (import) workflow.

**Key Clarifications Incorporated** (Session 2025-11-16):

1. **Preview Behavior**: Display up to 5 sample comments WITHOUT persisting to database
   - Only stored after user clicks "確認導入"

2. **Channel/Video Metadata Updates**:
   - **Channels**: Update `comment_count`, `last_import_at`, `updated_at` on all imports
     - Initialize `video_count`, `first_import_at`, `created_at` only for NEW channels
   - **Videos**: Always update `updated_at` on import
     - Initialize `video_id`, `title`, `published_at`, `created_at` only for NEW videos
     - Set `channel_id` only when importing from NEW channel

3. **Cancellation Handling**:
   - Close dialog without action
   - Preview comments NOT saved
   - If import interrupted mid-way, next import uses incremental logic to skip duplicates

4. **Reply Comment Depth**: Import ALL levels recursively (comment → reply → reply-to-reply, etc.)

5. **New Video Workflow**:
   - Input URL → Check DB existence
   - If new → Invoke existing "匯入" dialog (web scraping, tag selection)
   - After "匯入" completes → Auto-start comment preview

6. **File Organization** (Session 2025-11-17):
   - Create separate `YouTubeApiService.php` (NOT in UrtubeapiService)
   - Only reuse UI components for tag/video selection

## Technical Context

**Language/Version**: PHP 8.2+ (Laravel 11.x)
**Primary Dependencies**:
- `google/apiclient` (Google API PHP Client for YouTube API v3)
- Existing: GuzzleHttp (already used by UrtubeapiService)
- Existing: Illuminate Database (Laravel ORM)

**Storage**: MySQL/SQLite (existing comments, videos, channels tables)
**Testing**: Laravel Pest (existing test framework)
**Target Platform**: Laravel web application
**Project Type**: Web (monolithic Laravel backend)
**Performance Goals**:
- Preview fetch: <3 seconds for 5 comments
- Full import: <30 seconds for typical video (1000 comments)
- Incremental update: <10 seconds

**Constraints**:
- YouTube API quota limits (must respect rate limiting)
- No modifications to existing urtubeapi code
- Must preserve existing "匯入" workflow behavior

**Scale/Scope**:
- Single new service file + controller
- New migration for parent_comment_id support
- Reuse existing comment/video/channel models
- Reuse existing tag selection UI

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Principle I: Test-First Development
- **Status**: ✅ PASS
- **Plan**: Contract tests for YouTube API service before implementation; TDD for comment import logic
- **Details**: Will create service tests in `tests/Unit/Services/YouTubeApiServiceTest.php` before implementing service

### Principle II: API-First Design
- **Status**: ✅ PASS
- **Plan**: YouTubeApiService has clear public methods (fetchPreviewComments, fetchAllComments)
- **Details**: Service will expose contract that is independent of any controller/UI implementation

### Principle III: Observable Systems
- **Status**: ✅ PASS
- **Plan**: Structured logging for all API calls, comment fetch operations, database operations
- **Details**: Each import operation will include trace ID, operation type, comment count, timestamps

### Principle IV: Contract Testing
- **Status**: ✅ PASS
- **Plan**: Contract tests for YouTube API v3 comment structure; validation before storage
- **Details**: Will test YouTube API response parsing independently from database storage

### Principle V: Semantic Versioning
- **Status**: ⚠️  DEFERRED
- **Rationale**: Feature is new (no breaking changes to existing APIs); versioning applied at release time

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

**Incremental changes to existing Laravel structure** (NO new folders):

```text
app/
├── Services/
│   ├── YouTubeApiService.php          [NEW] YouTube API client + comment fetching logic
│   ├── UrtubeapiService.php           [UNCHANGED] Existing service for urtubeapi
│   └── [Other existing services...]
│
├── Http/Controllers/
│   ├── YouTubeApiImportController.php [NEW] Handle preview/confirm import requests
│   ├── ImportController.php           [UNCHANGED] Existing "匯入" workflow
│   └── [Other existing controllers...]
│
└── Models/
    ├── Comment.php                    [MODIFIED] Add parent_comment_id relation
    ├── Video.php                      [UNCHANGED]
    ├── Channel.php                    [UNCHANGED]
    └── [Other existing models...]

database/
├── migrations/
│   └── YYYY_MM_DD_HHMMSS_add_parent_comment_id_to_comments_table.php [NEW]
└── [Other existing migrations...]

tests/
├── Unit/
│   └── Services/
│       └── YouTubeApiServiceTest.php  [NEW] Service contract tests
└── Feature/
    └── YouTubeApiImportTest.php       [NEW] Integration tests for full workflow
```

**Structure Decision**: This is an incremental feature addition to the existing Laravel monolith.
No new directories created. The `YouTubeApiService.php` is completely isolated from `UrtubeapiService.php`.
Controllers are separate to maintain clear responsibility boundaries.

## Complexity Tracking

**Status**: ✅ NO VIOLATIONS — All constitutional principles satisfied

No complexity tracking entries required. The feature design:
- ✅ Follows Test-First Development (TDD tests before implementation)
- ✅ Implements API-First Design (service methods clearly defined)
- ✅ Produces Observable Systems (structured logging with trace IDs)
- ✅ Enables Contract Testing (service + controller contracts documented)
- ✅ Applies Semantic Versioning (new feature, no breaking changes)

**Design Simplicity**: Feature is deliberately minimal:
- Single new service file (YouTubeApiService)
- Single new controller (YouTubeApiImportController)
- One database migration (add parent_comment_id)
- Reuses existing models, routes structure, and tag selection UI
- No new folder structure, no architectural refactoring

---

## Phase 0: Research (NOT REQUIRED)

**Decision**: Skipped — No NEEDS CLARIFICATION items remain from specification

The following areas were already clarified:
- API authentication approach (YouTube API v3, google/apiclient)
- Data model changes (parent_comment_id field)
- Service isolation strategy (separate from UrtubeapiService)
- Reply import depth (recursive, all levels)
- Preview behavior (5 comments, not persisted)
- Incremental update logic (stop at duplicate)

No research tasks needed to proceed to Phase 1.

---

## Phase 1: Design Complete ✅

**Output Artifacts**:

1. **data-model.md** — Complete schema design
   - Comment table with parent_comment_id
   - Field mapping from YouTube API v3 response
   - Validation rules and state transitions
   - Incremental import logic

2. **contracts/youtube-api-service.md** — Service contract
   - Public methods: fetchPreviewComments, fetchAllComments, validateVideoId
   - Return types, error handling, test cases
   - Integration points with models/controllers

3. **contracts/youtube-api-import-controller.md** — Controller contract
   - Endpoints: POST /api/youtube-import/preview, /confirm
   - Request/response schemas
   - Error handling (400, 403, 404, 422, 500)
   - Integration with existing "匯入" workflow

4. **quickstart.md** — Implementation guide
   - Feature overview, key decisions
   - Files to create/modify
   - Dependencies (composer google/apiclient)
   - API endpoint examples
   - Testing strategy

---

## Next Steps

**Next Command**: `/speckit.tasks`

The tasks command will:
1. Generate `tasks.md` with actionable, dependency-ordered implementation tasks
2. Create GitHub issues if requested
3. Provide estimated effort/complexity for each task

**Estimated Tasks** (planning phase):
- Setup & configuration (add google/apiclient, set YOUTUBE_API_KEY)
- Database migration (parent_comment_id column)
- YouTubeApiService implementation (service tests first, TDD)
- YouTubeApiImportController implementation (controller tests first)
- Model modifications (Comment::parent_comment_id relation)
- Route & view updates ("API 導入" button, new routes)
- Integration tests (full user workflows)
- Manual testing & documentation

**Estimated Effort**: 15-20 developer-hours for complete implementation + testing

---

## Phase 2: Implementation (READY)

**Prerequisites Met**:
- ✅ Specification clarified
- ✅ Technical decisions documented
- ✅ Data model finalized
- ✅ API contracts defined
- ✅ Constitution compliance verified
- ✅ No architectural violations

**Ready to execute**: `/speckit.tasks` to generate implementation task list
