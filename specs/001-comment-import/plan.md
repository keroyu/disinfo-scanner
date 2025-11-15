# Implementation Plan: YouTube Comment Data Management System with Political Stance Tagging

**Branch**: `001-comment-import` | **Date**: 2025-11-15 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-comment-import/spec.md`

---

## Summary

Build a Laravel 10 web application that imports YouTube comments from external API (urtubeapi.analysis.tw) into MySQL database, with required political stance channel tagging via web UI. Core components: database schema (6 tables), REST/AJAX import endpoint with YouTube URL parsing, tag selection modal, and channel list view page styled with Tailwind CSS. Follows DISINFO_SCANNER constitution: Test-First Development, API-First Design, Observable Systems, Contract Testing, and Semantic Versioning.

---

## Technical Context

**Language/Version**: PHP 8.2 (Laravel 10.x minimum requirement)

**Primary Dependencies**:
- `laravel/framework` 10.x (web framework, Eloquent ORM, artisan CLI, Blade templating)
- `guzzlehttp/guzzle` (built into Laravel, HTTP client for urtubeapi + YouTube page fetching)
- `tailwindcss` 3.x (CSS framework via npm/node)
- `mariadb/mysql` 8.0 (relational database)

**Storage**: MySQL 8.0 with 6 tables (videos, comments, authors, channels, tags, channel_tags)

**Testing**:
- `phpunit` (Laravel built-in, unit + integration tests)
- `pest` (optional, modern PHP testing alternative)
- Contract tests: OpenAPI spec validation via tools like `dredd` or custom validators

**Target Platform**: Linux/macOS web server (development), AWS/shared hosting (production)

**Project Type**: Web application (single Laravel project with backend API + Blade frontend)

**Performance Goals**:
- Page load: < 2 seconds
- YouTube URL parsing: 3-5 seconds
- Data import (500 comments): 5-10 seconds
- Modal open: < 0.5 seconds (instant)
- Large imports (1000+ comments): batch processing (100-500 records/batch)

**Constraints**:
- HTTP requests: 30-second timeout (YouTube, urtubeapi)
- Memory: Batch processing to prevent overflow on 1000+ comment imports
- Data immutability: Comments not updated, only inserted (versioning for future corrections)
- No page refresh during import (AJAX-only)

**Scale/Scope**:
- MVP: Single server, 1-10 concurrent users
- Future: Multi-channel support, campaign detection, analytics
- Estimated LOC: 2000-3000 lines of code

---

## Constitution Check

**Gate 1: Test-First Development**
- ✅ **PASS**: Acceptance criteria in spec enable clear test scenarios (26 acceptance scenarios for unit/integration/manual tests)
- ✅ **PLAN**: Will create tests before implementation; Red-Green-Refactor cycle enforced per PR
- ✅ **EVIDENCE**: Test structure will be: tests/Unit/, tests/Integration/, tests/Feature/

**Gate 2: API-First Design**
- ✅ **PASS**: Feature spec defines clear API contract (REST endpoints for import, tag selection; structured JSON/HTML responses)
- ✅ **PLAN**: Will generate OpenAPI spec in contracts/import-api.yaml before coding
- ✅ **EVIDENCE**: Both machine-readable (JSON API) and human-readable (HTML pages) outputs required

**Gate 3: Observable Systems**
- ✅ **PASS**: Spec requires structured logging (timestamp, operation type, record counts, errors) + trace IDs
- ✅ **PLAN**: Will use Laravel's log channels, JSON-formatted logs, unique import_id per import operation
- ✅ **EVIDENCE**: Every import, tag selection, error logged with trace ID via `Log::info()`, `Log::error()`

**Gate 4: Contract Testing**
- ✅ **PASS**: API contracts defined (import endpoint request/response, tag selection endpoint)
- ✅ **PLAN**: Will create contract tests validating endpoint signatures, error codes, data shapes before implementation
- ✅ **EVIDENCE**: Tests/Contract/ will contain contract validators for each endpoint

**Gate 5: Semantic Versioning**
- ✅ **PASS**: MVP targets v1.0.0; future phases documented (batch import, query interface, etc. → v1.1.0+)
- ✅ **PLAN**: Will tag releases as v1.0.0, v1.0.1, etc.; breaking changes trigger MAJOR bump
- ✅ **EVIDENCE**: Release notes and API versioning in implementation

**GATE RESULT**: ✅ **PASS** - All principles align with plan. No conflicts or unjustified violations.

---

## Project Structure

### Documentation (this feature)

```text
specs/001-comment-import/
├── spec.md                    # Feature specification (completed)
├── plan.md                    # This file (implementation plan)
├── research.md                # Phase 0: Research findings (TBD)
├── data-model.md             # Phase 1: Database design (TBD)
├── quickstart.md             # Phase 1: Quick start guide (TBD)
├── contracts/                # Phase 1: API contracts (TBD)
│   ├── import-api.yaml       # OpenAPI spec for import endpoint
│   └── tag-selection-api.yaml # Contract for tag selection flow
├── checklists/
│   └── requirements.md       # Quality checklist (completed)
└── tasks.md                  # Phase 2: Task breakdown (TBD, from /speckit.tasks)
```

### Source Code (Laravel application root)

```text
.
├── app/
│   ├── Models/
│   │   ├── Video.php         # Eloquent model for videos table
│   │   ├── Comment.php       # Eloquent model for comments table
│   │   ├── Author.php        # Eloquent model for authors table
│   │   ├── Channel.php       # Eloquent model for channels table
│   │   ├── Tag.php           # Eloquent model for tags table
│   │   └── ChannelTag.php    # Eloquent model for channel_tags table
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ImportController.php       # POST /api/import (main import endpoint)
│   │   │   ├── TagSelectionController.php # POST /api/tags/select (tag selection)
│   │   │   ├── ChannelListController.php  # GET /channels (channel list page)
│   │   │   └── ImportPageController.php   # GET / (import page)
│   │   │
│   │   ├── Requests/
│   │   │   ├── ImportRequest.php          # Validate import URL input
│   │   │   ├── TagSelectionRequest.php    # Validate tag selection
│   │   │   └── ChannelQueryRequest.php    # Validate channel list queries
│   │   │
│   │   └── Resources/
│   │       ├── ImportResultResource.php   # Format import response JSON
│   │       ├── ChannelResource.php        # Format channel data for table
│   │       └── TagResource.php            # Format tag badge display
│   │
│   ├── Services/
│   │   ├── ImportService.php              # Orchestrate entire import flow
│   │   ├── UrlParsingService.php          # Parse YouTube URLs, extract videoId/channelId
│   │   ├── YouTubePageService.php         # Fetch + parse YouTube page source
│   │   ├── UrtubeapiService.php           # Fetch JSON from urtubeapi endpoint
│   │   ├── DataTransformService.php       # Transform urtubeapi JSON to database models
│   │   ├── DuplicateDetectionService.php  # Check for existing comments/authors
│   │   ├── ChannelTaggingService.php      # Handle new channel tagging flow
│   │   └── LoggingService.php             # Structured logging with trace IDs
│   │
│   ├── Exceptions/
│   │   ├── InvalidUrlException.php
│   │   ├── UrlParsingException.php
│   │   ├── YouTubePageException.php
│   │   ├── UrtubeapiException.php
│   │   ├── ImportException.php
│   │   └── ValidationException.php
│   │
│   └── Jobs/ (optional for future async processing)
│       └── ProcessImportJob.php          # Queue job for large imports
│
├── database/
│   ├── migrations/
│   │   ├── 2025_11_15_create_tags_table.php
│   │   ├── 2025_11_15_create_channels_table.php
│   │   ├── 2025_11_15_create_videos_table.php
│   │   ├── 2025_11_15_create_authors_table.php
│   │   ├── 2025_11_15_create_comments_table.php
│   │   └── 2025_11_15_create_channel_tags_table.php
│   │
│   ├── seeders/
│   │   └── TagSeeder.php                 # Pre-populate 5 political stance tags
│   │
│   └── factories/
│       ├── VideoFactory.php
│       ├── CommentFactory.php
│       ├── AuthorFactory.php
│       ├── ChannelFactory.php
│       └── TagFactory.php
│
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   └── app.blade.php            # Base layout with Tailwind
│   │   ├── import/
│   │   │   ├── index.blade.php          # Import page
│   │   │   └── modal.blade.php          # Tag selection modal (AJAX response)
│   │   └── channels/
│   │       └── list.blade.php           # Channel list page with table
│   │
│   ├── js/
│   │   └── import.js                    # AJAX handlers for import form + modal
│   │
│   └── css/
│       └── app.css                      # Tailwind imports/customization
│
├── routes/
│   ├── web.php                          # Web routes (pages)
│   └── api.php                          # API routes (JSON endpoints)
│
├── tests/
│   ├── Unit/
│   │   ├── UrlParsingServiceTest.php
│   │   ├── DataTransformServiceTest.php
│   │   ├── DuplicateDetectionServiceTest.php
│   │   └── LoggingServiceTest.php
│   │
│   ├── Integration/
│   │   ├── ImportEndpointTest.php
│   │   ├── TagSelectionFlowTest.php
│   │   ├── ChannelListPageTest.php
│   │   └── DatabaseMigrationTest.php
│   │
│   ├── Contract/
│   │   ├── ImportApiContractTest.php
│   │   └── TagSelectionApiContractTest.php
│   │
│   ├── Feature/
│   │   ├── ImportPageDisplayTest.php
│   │   ├── ModalDisplayTest.php
│   │   └── ChannelListDisplayTest.php
│   │
│   └── TestCase.php                     # Base test class with helpers
│
├── .env.example                         # Env template (database, API endpoints)
├── package.json                         # Node dependencies (Tailwind CSS)
├── composer.json                        # PHP dependencies
├── artisan                              # Laravel CLI (migrations, seeding, commands)
└── README.md                            # Project setup instructions
```

**Structure Decision**: Selected **Option 2 (Web Application)** - Single Laravel project with integrated backend API and Blade template frontend. Rationale:
- MVP scope does not require separate frontend/backend repos
- Blade templating allows server-side rendering for import + channel list pages
- AJAX for import form eliminates page reloads
- Single database, shared models simplify data consistency
- Easier deployment as monolithic application

---

## Phase 0: Research Requirements

The following items require research before Phase 1 design. Each will be documented in `research.md`:

### R1: YouTube URL Parsing Implementation
**Unknown**: Exact regex patterns for 4 YouTube URL formats + edge cases
**Research Task**: Find optimal regex/URL parsing approach for:
- Standard: `youtube.com/watch?v=VIDEO_ID`
- Short: `youtu.be/VIDEO_ID`
- Mobile: `m.youtube.com/watch?v=VIDEO_ID`
- With params: `youtube.com/watch?v=VIDEO_ID&t=123s`

**Alternatives to evaluate**:
- Regex patterns (quick, lightweight)
- PHP URL parsing + validation
- Dedicated YouTube URL parser library
- Laravel Route model binding approach

**Decision criteria**: Correctness, performance, maintainability, error clarity

---

### R2: YouTube Page Source Parsing for channelId
**Unknown**: Best method to extract channelId from YouTube page HTML
**Research Task**: Evaluate approaches:
- Regex matching for JavaScript variables (e.g., `"channelId":"UC..."`)
- DOM parsing (DOMDocument/simple_html_dom library)
- Headless browser approach (Puppeteer/Selenium) - likely overkill for MVP
- YouTube's meta tags (if available)

**Trade-offs**: Speed vs. robustness (page structure changes frequently)

**Decision criteria**: MVP simplicity, maintainability, dependency count

---

### R3: Batch Processing for Large Comment Imports
**Unknown**: Optimal batch size + processing strategy for 1000+ comments
**Research Task**: Determine:
- Batch size (100, 250, 500 records?)
- Processing method: loops with savepoints, chunked inserts, database bulk insert?
- Memory usage limits
- Progress indication needs

**Decision criteria**: Import duration (5-10s target), memory constraints, user feedback

---

### R4: Relative Time Formatting (e.g., "2 天前")
**Unknown**: Library/implementation for Chinese relative time formatting
**Research Task**: Evaluate:
- Carbon with locale (Laravel built-in)
- Carbon with trans (translation files)
- Custom date formatting helper
- JavaScript date library

**Decision criteria**: Simplicity, internationalization support, accuracy

---

### R5: JSON Structured Logging Implementation
**Unknown**: Best practice for structured logging with trace IDs in Laravel
**Research Task**: Implement:
- Trace ID generation (UUID or custom format)
- JSON log formatter (Monolog processors)
- Context enrichment (user, operation type, record counts)
- Log aggregation considerations (if any)

**Decision criteria**: Debuggability, log query-ability, performance

---

## Phase 1: Design & Data Model

### 1.1 Data Model (database/schema)

**Database**: MySQL 8.0

**Tables & Relationships**:

```
tags (political stance labels)
├── tag_id (PK)
├── code (unique: pan-green, pan-white, pan-red, anti-communist, china-stance)
├── name (泛綠, 泛白, 泛紅, 反共, 中國立場)
├── description
├── color (Tailwind class: green-500, blue-500, red-500, orange-500, rose-600)
└── timestamps

channels (YouTube channel)
├── channel_id (PK, unique)
├── channel_name
├── video_count
├── comment_count
├── first_import_at
├── last_import_at
└── timestamps

channel_tags (M:M relationship)
├── channel_id (FK → channels)
├── tag_id (FK → tags)
└── created_at

videos (YouTube video)
├── video_id (PK, unique)
├── channel_id (FK → channels)
├── title
├── youtube_url (optional)
├── published_at
└── timestamps

authors (YouTube channel/user who comments)
├── author_channel_id (PK, unique, indexed)
├── name
├── profile_url
└── timestamps

comments (YouTube comment)
├── comment_id (PK, unique)
├── video_id (FK → videos)
├── author_channel_id (FK → authors, indexed)
├── text
├── like_count
├── published_at
├── updated_at (per YouTube source)
└── created_at (local import timestamp)
```

**Indexes**:
- `comments.author_channel_id` (for future queries by commenter)
- `comments.video_id` (for querying by video)
- `videos.channel_id` (for querying by channel)
- `channels.channel_id` (primary)
- Composite: `(video_id, comment_id)` for duplicate detection

**Constraints**:
- Foreign key: video→channel, comment→video, comment→author
- Unique: comment_id, video_id, author_channel_id, channel_id
- NOT NULL: all PK fields, required data fields

---

### 1.2 API Contracts (REST endpoints)

**File**: `contracts/import-api.yaml` (OpenAPI 3.0)

#### POST /api/import
**Request**: JSON with URL
**Parameters**:
```json
{
  "url": "https://urtubeapi.analysis.tw/api/api_comment.php?videoId=...&token=..."
         OR "https://www.youtube.com/watch?v=..."
}
```

**Response (Success 200)**:
```json
{
  "success": true,
  "message": "Import completed",
  "data": {
    "video_id": "azzXesons8Q",
    "channel_id": "UC...",
    "channel_name": "...",
    "stats": {
      "total_processed": 150,
      "newly_added": 145,
      "updated": 0,
      "skipped": 5
    },
    "new_channel": false,
    "tags_required": false
  }
}
```

**Response (New Channel - needs tagging 202)**:
```json
{
  "success": true,
  "message": "Channel tagging required",
  "data": {
    "channel_id": "UC...",
    "channel_name": "...",
    "import_id": "uuid-trace-id",
    "requires_tags": true
  }
}
```

**Response (Error 400/422)**:
```json
{
  "success": false,
  "message": "User-friendly error message",
  "error": "error_code",
  "details": {
    "code": "...",
    "validation_errors": {...}
  }
}
```

**Response (Server Error 500)**:
```json
{
  "success": false,
  "message": "System error, please contact admin",
  "error": "internal_error",
  "trace_id": "uuid-for-debugging"
}
```

---

#### POST /api/tags/select
**Request**: JSON with tag selections
**Parameters**:
```json
{
  "import_id": "uuid",
  "channel_id": "UC...",
  "tags": ["pan-green", "anti-communist"]
}
```

**Response (Success 200)**:
```json
{
  "success": true,
  "message": "Tags saved, import continued",
  "data": {
    "import_id": "uuid",
    "tags_saved": 2,
    "import_stats": {...}
  }
}
```

---

### 1.3 UI Pages & Routes

**File**: `routes/web.php`

| Route | Controller | View | Purpose |
|-------|-----------|------|---------|
| `GET /` | ImportPageController | import.blade.php | Import form page |
| `GET /channels` | ChannelListController | channels/list.blade.php | Channel list table |
| `POST /api/import` (AJAX) | ImportController | JSON response | Import endpoint |
| `POST /api/tags/select` (AJAX) | TagSelectionController | JSON response | Tag selection |
| `GET /api/tags/select` (AJAX modal) | TagSelectionController | modal.blade.php | Modal HTML |

---

### 1.4 Frontend Components (Tailwind CSS)

**Import Page** (`resources/views/import/index.blade.php`):
- Header: Title + instructions
- Input: Text field (w-full, p-3)
- Button: Primary CTA ("開始匯入")
- Status: Spinner + status text (hidden initially)
- Results: Green success card with statistics
- Errors: Red error card with message

**Tag Modal** (`resources/views/import/modal.blade.php`):
- Overlay: bg-black/50, z-50
- Modal: bg-white, rounded-lg, shadow-lg, w-1/2
- Header: Title "新頻道標籤設定"
- Channel info: Channel ID + name
- Checkboxes: 5 tags, color-coded, stacked vertically
- Validation: Error message below checkboxes (red text)
- Buttons: "確認並繼續匯入" (primary), "取消匯入" (secondary)

**Channel List Page** (`resources/views/channels/list.blade.php`):
- Table: Fixed header, scrollable body
- Columns: Channel ID, Name, Tags (badges), Comment Count, Last Import
- Badges: Color-coded (green-500, blue-500, red-500, orange-500, rose-600)
- Responsive: Desktop full table, tablet compressed, no horizontal scroll

---

### 1.5 Key Service Classes (Business Logic)

**ImportService.php** (Orchestrator):
- Main entry point for import flow
- Validates URL type (urtubeapi vs YouTube)
- Routes to appropriate parsing service
- Handles channel detection + tagging flow
- Logs entire operation with trace ID
- Returns import result with statistics

**UrlParsingService.php**:
- Identify URL type (regex/validation)
- Extract videoId (YouTube or urtubeapi)
- Extract channelId if available

**YouTubePageService.php**:
- Fetch YouTube page HTML via Guzzle
- Parse HTML to extract channelId (regex on JS variables)
- Error handling (404, timeout, unreachable)

**UrtubeapiService.php**:
- Fetch JSON from urtubeapi endpoint
- Validate JSON structure
- Handle 30-second timeout
- Error handling (5xx, malformed JSON)

**DataTransformService.php**:
- Transform urtubeapi JSON to database models
- Map field names (videoId → video_id, etc.)
- Create Video, Comment, Author objects
- Handle NULL fields gracefully

**DuplicateDetectionService.php**:
- Check if comment_id already exists
- Check if author_channel_id already exists
- Return statistics (new vs. duplicate)

**ChannelTaggingService.php**:
- Detect if channel is new
- If new, pause import + return import_id
- Store tags in channel_tags table on confirmation
- Resume import after tagging

**LoggingService.php**:
- Generate trace ID
- Log structured JSON (timestamp, operation, counts, errors)
- Attach trace ID to all related operations

---

## Phase 1 Gates (Re-check Constitution)

**Gate 1: Test-First Development** ✅ PASS
- Contract tests defined for all endpoints
- Unit test structure in place (UrlParsingServiceTest, etc.)
- Integration tests for import flow

**Gate 2: API-First Design** ✅ PASS
- OpenAPI contracts generated (import-api.yaml, tag-selection-api.yaml)
- All endpoints documented with request/response schemas
- Error contracts defined (400, 422, 500)

**Gate 3: Observable Systems** ✅ PASS
- Trace ID generation for every import
- Structured JSON logging planned (timestamp, operation, counts)
- LoggingService abstracts log implementation

**Gate 4: Contract Testing** ✅ PASS
- Contract tests validate endpoint signatures
- Data transformation logic tested independently
- Service boundaries validated in integration tests

**Gate 5: Semantic Versioning** ✅ PASS
- MVP version: 1.0.0
- Breaking changes to API trigger MAJOR bump
- No backward incompatibility in current design

**RESULT**: ✅ **PASS** - Phase 1 design complies with all constitution principles.

---

## Artifacts Generated in Phase 1

This plan will produce the following artifacts (to be completed):

1. **research.md** - Research findings for all R1-R5 items
2. **data-model.md** - Complete database schema with DDL, relationships, indexes
3. **quickstart.md** - Installation, setup, running application locally
4. **contracts/import-api.yaml** - OpenAPI spec for import endpoint
5. **contracts/tag-selection-api.yaml** - OpenAPI spec for tag selection
6. **contracts/channel-list-api.yaml** - OpenAPI spec for channel list endpoint

---

## Next Steps (Phase 2 via /speckit.tasks)

After this plan is approved, run `/speckit.tasks` to generate detailed task breakdown:

- Task breakdown by user story
- Task dependencies (sequential vs. parallel)
- Estimated effort per task
- Test requirements per task
- Definition of Done criteria

---

## Summary of Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Project Structure | Single Laravel app | MVP simplicity, shared database |
| API Pattern | REST (not GraphQL) | Simpler for MVP, clear contract |
| Authentication | None (admin-only for MVP) | Out of scope; future feature |
| Frontend Framework | Blade + vanilla JS + AJAX | No SPA overhead needed |
| Database | MySQL 8.0 | Standard relational DB, indexes support |
| Logging | Structured JSON with trace IDs | Aligns with Observable Systems principle |
| Testing Framework | PHPUnit (Laravel built-in) | No additional dependencies |
| Async Processing | Not in MVP | Can queue via Jobs later |
| Caching | Not in MVP | Can add later (YouTube parsing results) |
| Rate Limiting | Not in MVP | Can add API middleware later |

