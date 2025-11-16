# Implementation Plan: YouTube API Comments Import

**Branch**: `005-api-import-comments` | **Date**: 2025-11-16 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/005-api-import-comments/spec.md`

**Note**: This plan builds on architectural review findings and integrates with existing Laravel 12 service-oriented patterns.

## Summary

Implement YouTube API comment import capability for the DISINFO_SCANNER platform, extending the existing prepare-confirm import workflow. The feature supports both new videos (triggering existing "匯入" metadata import) and existing videos (direct comment fetch). Key technical requirements include incremental update support (fetch only new comments), recursive reply handling, and proper database field updates. Implementation leverages existing service layer patterns (ImportService, UrtubeapiService) with new CommentsImportService and background job support for scalability.

## Technical Context

**Language/Version**: PHP 8.2 with Laravel Framework 12.0
**Primary Dependencies**:
  - UrtubeAPI Service (existing third-party proxy for YouTube data)
  - YouTubeMetadataService (web scraping via Symfony DomCrawler)
  - DataTransformService (model transformation)
  - YouTube Data API v3 (credentials configured in .env)

**Storage**: PostgreSQL database with existing schema (videos, comments, channels, authors tables)
**Testing**: PHPUnit 11.0+ (configured via phpunit.xml)
**Target Platform**: Web application (Laravel web server)
**Project Type**: Web application with service-oriented architecture
**Performance Goals**:
  - Comments import completes within 60 seconds for videos with <1000 comments
  - Preview fetch (5 comments) completes within 5 seconds
  - Incremental update processes 100 comments in <10 seconds

**Constraints**:
  - YouTube API quota: 10,000 units/day (1 comment fetch = 1 unit)
  - Database transaction timeout: Default 600 seconds (may need increase for large batches)
  - HTTP request timeout: 30 seconds (may need increase for large imports)

**Scale/Scope**:
  - Expected volume: 1000+ videos × 100-500 comments/video = 100k-500k total comments
  - Current users: System users (non-public audience)
  - Frontend framework: Blade templates with HTMX/JavaScript for interactive elements

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Architecture Principles** ✅ PASS
- ✅ Service-oriented design (follows ImportService pattern)
- ✅ Separation of concerns (UrtubeAPI vs DataTransform vs Persistence)
- ✅ Dependency injection ready (services accept dependencies)
- ⚠️  Reflection anti-pattern exists in ImportConfirmationController (noted for refactoring)

**Code Quality Standards** ✅ PASS
- ✅ Follows Laravel coding standards
- ✅ Uses Eloquent ORM for database access
- ✅ Implements proper transaction handling
- ⚠️  Constructor injection needs to be unified across services

**Security Requirements** ✅ PASS (with clarifications)
- ✅ YouTube API credentials in .env (not hardcoded)
- ⚠️  Comment text sanitization needed (prevent XSS)
- ⚠️  Rate limiting not yet implemented (must add exponential backoff)

**Database Schema** ✅ PASS
- ✅ Existing comments, videos, channels tables properly designed
- ⚠️  Channel comment_count calculation bug found (requires fix: FR-016)
- ⚠️  No full-text search index (acceptable for <1M comments)

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

**Selected Structure: Laravel Web Application**

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── ApiCommentImportController.php   (NEW - API comment import endpoints)
│   │   └── ImportConfirmationController.php (EXISTING - to be refactored)
│   └── Requests/
│       └── ApiImportCommentsRequest.php     (NEW - validation)
│
├── Models/
│   ├── Comment.php          (EXISTING - extend relationships)
│   ├── Video.php            (EXISTING - no changes)
│   └── Channel.php          (EXISTING - no changes)
│
├── Services/
│   ├── ImportService.php              (EXISTING - fix comment_count bug)
│   ├── ApiCommentImportService.php    (NEW - API comments-specific logic)
│   ├── UrtubeapiService.php           (EXISTING - add pagination support)
│   └── DataTransformService.php       (EXISTING - no changes)
│
└── Jobs/
    └── ApiImportCommentsBatchJob.php  (NEW - background processing)

resources/
├── views/
│   └── comments/
│       └── api-import.blade.php       (NEW - API import form UI)
│
tests/
├── Feature/
│   ├── ApiCommentImportTest.php       (NEW - integration tests)
│   └── ImportWorkflowTest.php         (EXISTING - expand if needed)
│
├── Unit/Services/
│   └── ApiCommentImportServiceTest.php (NEW - service unit tests)
│
└── Fixtures/
    └── CommentsImportFixtures.php     (NEW - test data)

routes/
├── web.php          (EXISTING - add comments import routes)
└── api.php          (EXISTING - add API endpoints if needed)

database/
└── migrations/
    └── 2025_11_16_000000_create_comments_import_job_table.php  (NEW - if using database queue)
```

**Structure Decision**: Follow existing Laravel application structure (Option 1 - Single Project). All new classes integrate with existing service-oriented architecture in `/app/Services/`. No new packages or microservices required. Database queue already configured, so no queue service provider changes needed.

## Complexity Tracking

**Violations Found and Justified:**

| Issue | Why Needed | Resolution |
|-------|-----------|-----------|
| Channel comment_count bug in ImportService.php:249 | Counts comments for ONE video only, not entire channel; breaks FR-016 | Fix calculation to use `whereHas('videos')` relationship (HIGH PRIORITY) |
| Reflection anti-pattern in ImportConfirmationController:95-97 | Breaks encapsulation, makes testing difficult | Refactor to add public `cancelImport()` method to ImportService |
| No pagination support in UrtubeapiService | YouTube API returns max 100 comments; videos with 500+ comments silently truncated | Add `pageToken` parameter support and recursive fetch logic |
| Synchronous full import blocks user | For 500+ comment videos, import takes 30-60 seconds | Implement background jobs (ImportCommentsBatchJob) with async processing |
| No rate limiting on YouTube API | API quota: 10,000 units/day; can exhaust quota with large imports | Add exponential backoff and quota tracking in UrtubeapiService |
| Missing incremental import logic | Current code always fetches ALL comments; wastes quota and causes duplicates | Add `getLatestCommentTimestamp()` method and conditional API fetch |

**Mitigations:**
- Issues are design/implementation gaps, not architectural violations
- All can be resolved within existing service-oriented pattern
- No new architectural patterns or dependencies required
- Phased implementation: fixes first (Phase 1), then enhancements (Phase 2-3)
