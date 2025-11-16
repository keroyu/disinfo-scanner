# Implementation Plan: YouTube API Comments Import

**Branch**: `005-api-import-comments` | **Date**: 2025-11-17 | **Spec**: `/specs/005-api-import-comments/spec.md`
**Input**: Feature specification from `/specs/005-api-import-comments/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Implement a complete YouTube API comment import system for new and existing videos with intelligent incremental updates. The system must fetch video metadata, preview sample comments, and recursively import all comments with proper reply threading. Data integrity is critical: NO data persists to database until ALL comments and replies are successfully fetched. The feature integrates with existing Laravel comments interface via a dedicated service layer.

## Technical Context

**Language/Version**: PHP 8.1+ (Laravel framework)
**Primary Dependencies**: Laravel 10/11, Guzzle HTTP client, YouTube API v3 PHP client library
**Storage**: PostgreSQL (existing comments, videos, channels tables)
**Testing**: PHPUnit with contract tests and integration tests
**Target Platform**: Web application (Laravel backend with Blade UI templates)
**Project Type**: Monolithic web application with independent service layer
**Performance Goals**: Import new video within 30s, incremental updates <10s (excludes API fetch time)
**Constraints**: YouTube API quota limits, transaction-based atomicity for data consistency
**Scale/Scope**: System handles videos with 100+ comment threads, multi-level reply chains (unlimited depth)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Principle I: Test-First Development
**Status**: ✅ PASS
- Will implement tests BEFORE code using PHPUnit contract tests
- Red-Green-Refactor cycle enforced during development
- Test structure: unit tests → contract tests → integration tests

### Principle II: API-First Design
**Status**: ✅ PASS
- Service layer (`YouTubeApiService`) with defined, testable interfaces
- API contracts generated for YouTube API client methods
- Clear separation between data models and service operations

### Principle III: Observable Systems
**Status**: ⚠️ REQUIRES CLARIFICATION
- Need to define structured logging format for import operations
- Will add trace IDs to each import session
- Data collection must log: source (YouTube API), timestamp, record count

### Principle IV: Contract Testing
**Status**: ✅ PASS
- Contract tests for YouTube API client responses before implementation
- Service boundary tests for comment import workflows
- Test shared schema (comment, video, channel entities)

### Principle V: Semantic Versioning
**Status**: ✅ PASS
- Feature implements as MINOR version (new functionality)
- Database schema changes (if any) documented for migrations
- No breaking changes to existing comment import APIs

**Gate Result**: PASS (one clarification noted for structured logging)

## Project Structure

### Documentation (this feature)

```text
specs/005-api-import-comments/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
│   ├── youtube-api-service.md
│   └── import-controller.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── YoutubeApiImportController.php    # Handles import flow UI & requests
│   └── Requests/
│       └── ImportVideoRequest.php             # Validates YouTube URL input
├── Services/
│   ├── YouTubeApiService.php                 # Core service for YouTube API operations
│   └── CommentImportService.php              # Orchestrates import workflow
└── Models/
    ├── Comment.php                           # Existing model
    ├── Video.php                             # Existing model
    └── Channel.php                           # Existing model

resources/
├── views/
│   └── comments/
│       ├── import-modal.blade.php            # URL input form
│       ├── metadata-dialog.blade.php         # Metadata confirmation
│       └── preview-dialog.blade.php          # Comment preview

tests/
├── Unit/
│   ├── YouTubeApiServiceTest.php
│   └── CommentImportServiceTest.php
├── Contract/
│   └── YouTubeApiContractTest.php
└── Integration/
    └── CommentImportWorkflowTest.php
```

**Structure Decision**: Monolithic Laravel application with independent service layer. YouTube API operations isolated in `YouTubeApiService.php`. UI components in Blade templates. No code sharing with other import systems (as per spec requirement). Test structure follows constitution: unit tests for individual methods, contract tests for API boundaries, integration tests for full workflows.

## Complexity Tracking

**No violations** - Constitution Check passed with only a clarification note on structured logging (addressed in Phase 0 research).

---

## Phase 1 Completion Summary


### Phase 1 Deliverables

✅ **Technical Context**: PHP 8.1+, Laravel 10/11, PostgreSQL, google/apiclient, PHPUnit
✅ **Constitution Check**: PASS (all 5 principles satisfied)
✅ **Research Phase**: Complete with all NEEDS CLARIFICATION items resolved
✅ **Data Model**: Full schema with 4 core entities (Video, Channel, Comment, ImportSession)
✅ **API Contracts**: Service layer and controller contracts with complete method signatures
✅ **Quickstart Guide**: Setup, development, testing, integration procedures documented

### Ready for Phase 2

Next step: Run `/speckit.tasks` to generate implementation task breakdown in `tasks.md`
