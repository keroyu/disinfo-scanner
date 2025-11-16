# Implementation Plan: Auto-Fetch Video Metadata with Two-Step Confirmation

**Branch**: `002-auto-fetch-video-metadata` | **Date**: 2025-11-16 | **Spec**: `/specs/002-auto-fetch-video-metadata/spec.md`
**Input**: Incremental enhancement to 001-comment-import with metadata scraping and confirmation interface
**Status**: Planning Phase - Ready for Phase 1 implementation

## Summary

Add automatic video metadata scraping (title, channel name) and a two-step confirmation interface to the existing YouTube comment import system. Users will:
1. Provide URL (YouTube or urtubeapi format)
2. System scrapes video metadata and fetches API data
3. System shows confirmation modal with extracted data + tag selection (if new channel)
4. User reviews and confirms → database writes atomically
5. All writes wrapped in transactions to ensure data integrity

**Key Features**:
- **YouTubeMetadataService**: Scrapes video title and channel name from YouTube pages using Goutte/DomCrawler
- **Two-Step Import Flow**: `prepareImport()` (read-only) → `confirmImport()` (atomic write)
- **Confirmation Modal**: Displays metadata before database write, integrates tag selection
- **Graceful Degradation**: If metadata scraping fails, user can still import with API data
- **Atomic Transactions**: All data writes (video, comments, authors, tags) in single transaction

## Technical Context

**Language/Version**: PHP 8.1 (Laravel 10.x)
**Primary Dependencies**:
- Existing: Guzzle HTTP, Laravel Eloquent, Blade templating
- NEW: Symfony DomCrawler, Symfony CSS Selector (for HTML parsing)

**Storage**: SQLite (dev), supports transactions - NO SCHEMA CHANGES
**Testing**: PHPUnit (existing), added unit + integration tests
**Target Platform**: Web application (Laravel backend + Blade frontend)
**Project Type**: Web application (backend API + frontend UI)
**Performance Goals**:
- Metadata scraping: < 3 seconds per video (10s timeout max)
- Database write: < 2 seconds for 100 comments
- End-to-end import: < 10 seconds total
- Confirmation modal: appears within 1 second after user clicks confirm button

**Constraints**:
- 10-second timeout for YouTube page fetch (graceful degradation)
- No API changes to existing 001-comment-import functionality
- Backward compatibility: existing import flow still works
- No database schema migrations required

**Scale/Scope**:
- Incremental feature (building on existing 001-comment-import)
- 7 new files, 5 modified files
- ~1000 LOC total (services, controller, tests, frontend)
- Estimated effort: 4-5 days development + testing

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Test-First Development ✓

**Status**: COMPLIANT

- Unit tests for YouTubeMetadataService (scraping logic)
- Unit tests for ImportService (prepareImport/confirmImport)
- Integration tests for full confirmation flow
- Manual testing scenarios for all edge cases
- Red-Green-Refactor cycle to be followed during implementation

**Justification**: Existing 001-comment-import has strong test coverage. Incremental feature will maintain same rigor.

---

### II. API-First Design ✓

**Status**: COMPLIANT

- Clear API contract for new endpoints:
  - `POST /api/import` → prepare (existing, modified to return 202)
  - `POST /api/import/confirm` → confirm and write
  - `POST /api/import/cancel` → cancel without write
- All endpoints return structured JSON responses
- Error responses include actionable context (e.g., "請至少選擇一個標籤")
- Frontend calls API via AJAX, not tied to specific UI implementation

**Justification**: New endpoints follow REST conventions. Decoupled from UI.

---

### III. Observable Systems ✓

**Status**: COMPLIANT

- Structured logging for metadata scraping (success/failure)
- Import preparation logged with trace ID (existing)
- Database write operations logged with record counts
- Error logging includes source, timestamp, context
- Metrics: scraping success rate, confirm vs. cancel rate (future dashboard)

**Justification**: Builds on existing observability patterns from 001-comment-import.

---

### IV. Contract Testing ✓

**Status**: COMPLIANT

- YouTubeMetadataService contract: scraping HTML → title/channel output
- ImportService contract: prepareImport → returns importId + metadata
- ImportService contract: confirmImport → writes data atomically or rolls back
- ChannelTaggingService contract: unchanged, existing tests verify compatibility

**Justification**: Service boundaries well-defined. Contract tests ensure backward compatibility.

---

### V. Semantic Versioning ✓

**Status**: COMPLIANT

- This is a MINOR version bump (new functionality, backward compatible)
- Existing `import()` method still works (no breaking changes)
- New methods are additive (prepareImport, confirmImport)
- New endpoints are new resources (confirm, cancel)
- No database schema changes = no migration version bump

**Justification**: Incremental design ensures backward compatibility.

## Project Structure

### Documentation (this feature)

```text
specs/002-auto-fetch-video-metadata/
├── spec.md              # Feature specification (completed)
├── plan.md              # This file (implementation plan)
├── research.md          # [TO COME] Phase 0: Research findings
├── data-model.md        # [TO COME] Phase 1: Data model & relationships
├── quickstart.md        # [TO COME] Phase 1: Developer quickstart
├── contracts/           # [TO COME] Phase 1: API contracts
└── tasks.md             # [TO COME] Phase 2: Actionable tasks (/speckit.tasks)
```

### Source Code Structure (Incremental)

```text
app/
├── Services/
│   ├── YouTubeMetadataService.php          # [NEW] Metadata scraping logic
│   ├── ImportService.php                    # [MODIFIED] prepareImport/confirmImport
│   ├── ChannelTaggingService.php            # [MODIFIED] Extended createPendingImport()
│   └── [existing services unchanged]

├── Http/
│   ├── Controllers/
│   │   ├── ImportController.php             # [MODIFIED] Use prepareImport()
│   │   ├── ImportConfirmationController.php # [NEW] confirm/cancel endpoints
│   │   └── [existing controllers unchanged]
│   └── [existing routes unchanged]

├── Models/
│   └── [NO CHANGES - schema unchanged]

└── [existing structure unchanged]

tests/
├── Unit/
│   ├── YouTubeMetadataServiceTest.php       # [NEW] Scraping logic tests
│   ├── ImportServiceTest.php                # [NEW] prepareImport/confirmImport tests
│   └── [existing tests unchanged]

├── Integration/
│   ├── ImportConfirmationFlowTest.php       # [NEW] E2E confirmation flow
│   └── [existing tests unchanged]

└── [existing test structure preserved]

resources/
├── views/
│   └── import/
│       └── index.blade.php                  # [MODIFIED] Add confirmation modal + JS
│
└── [existing views unchanged]

routes/
├── api.php                                  # [MODIFIED] Add confirm/cancel routes
└── [existing routes unchanged]
```

**Structure Decision**:
- **Type**: Web application (Laravel backend + Blade templating)
- **Layout**: Keep existing monolithic Laravel structure (single-project approach)
- **New Services**: YouTubeMetadataService (orthogonal to existing services)
- **Modified Services**: ImportService (split into prepare/confirm), ChannelTaggingService (extended)
- **New Controller**: ImportConfirmationController (clear separation of concerns)
- **New Tests**: Organized by type (Unit, Integration) following existing pattern
- **Frontend**: Extend existing import view with confirmation modal component

## Complexity Tracking

> **No Constitution violations identified. All principles satisfied.**

Design decisions justified:
1. **Separate YouTubeMetadataService** (vs. extending YouTubePageService)
   - Different concerns: channelId extraction vs. metadata scraping
   - Different error handling: channelId is blocking, metadata is optional
   - Easier to mock/test independently

2. **Split ImportService into prepareImport + confirmImport** (vs. single method)
   - Enables non-blocking UI with confirmation step
   - Clear separation: prepare (read-only) vs. confirm (write)
   - Easier to test state transitions and transaction atomicity

3. **Cache-based pending imports** (vs. database staging table)
   - No schema migration needed
   - 10-minute TTL appropriate for user review time
   - Simpler cleanup (automatic cache expiry)

4. **Transaction wrapping only in confirmImport** (vs. entire flow)
   - Transactions should be as short as possible (best practice)
   - Metadata scraping is non-transactional (external API)
   - Only write phase needs atomicity guarantee

---

## Core Logic: Complete Comment Import Flow

### Unified Data Source: All Comments from urtubeapi

**Key Principle**: Regardless of input URL format, ALL comment data must be fetched from urtubeapi API.

### Import Flow by URL Type

#### Flow 1: When URL is urtubeapi format

```
Input: https://urtubeapi.analysis.tw/...?videoId=abc123&token=UCxxx

Step 1: URL Parsing
   ├─ Extract videoId: "abc123"
   └─ Extract channelId from token parameter: "UCxxx"
      (No web scraping needed - direct from URL)

Step 2: Fetch from urtubeapi
   └─ API(videoId=abc123, token=UCxxx) → JSON response

Step 3: YouTube Page Scraping (for metadata only)
   ├─ Fetch: https://www.youtube.com/watch?v=abc123
   ├─ Extract: videoTitle, channelName
   └─ Store in metadata (not from API, from scraping)

Step 4: Parse JSON Comments
   ├─ API returns: comment_id, author, text, like_count, etc.
   └─ Transform to models with channelId from Step 1

Step 5: Database Write
   └─ Insert videos, comments, authors using transformed models
```

#### Flow 2: When URL is YouTube format

```
Input: https://www.youtube.com/watch?v=abc123

Step 1: YouTube Page Scraping (extract channelId)
   ├─ Fetch: https://www.youtube.com/watch?v=abc123
   ├─ Extract channelId from HTML: "UCxxx"
   └─ Extract videoId from URL: "abc123"

Step 2: Construct urtubeapi URL
   └─ Build: urtubeapi.analysis.tw?videoId=abc123&token=UCxxx

Step 3: Fetch from urtubeapi
   └─ API(videoId=abc123, token=UCxxx) → JSON response

Step 4: YouTube Page Scraping (for metadata)
   ├─ Fetch: https://www.youtube.com/watch?v=abc123 (already done in Step 1)
   ├─ Extract: videoTitle, channelName
   └─ Store in metadata

Step 5: Parse JSON Comments
   ├─ API returns: comment_id, author, text, like_count, etc.
   └─ Transform to models with channelId from Step 1

Step 6: Database Write
   └─ Insert videos, comments, authors using transformed models
```

### Critical Data Points

**Sources of channelId**:
- ✅ urtubeapi URL: Extract from `token` parameter (fastest)
- ✅ YouTube URL: Extract from HTML via web scraper
- ❌ API response: Does NOT contain channelId (it's a request parameter, not a response field)

**Sources of Comments**:
- ✅ ONLY from urtubeapi API
- API returns: JSON with `comment_id`, `author_channel_id`, `like_count`, `published_at` (all snake_case)

**Sources of Metadata** (videoTitle, channelName):
- ✅ YouTube page scraping (not from API)
- Requires accessing YouTube video page regardless of input URL type

### Data Format Handling

**API Response Format** (urtubeapi):
```json
{
  "videoId": "abc123",
  "videoTitle": "Title from API",
  "channelTitle": "Channel from API",
  "comments": [
    {
      "comment_id": "comment_123",
      "author": "Author Name",
      "author_channel_id": "UCyyy",
      "text": "Comment text",
      "like_count": 5,
      "published_at": "2025-11-16T10:00:00Z"
    }
  ]
}
```

**Database Storage Format** (snake_case):
```sql
-- videos table
INSERT INTO videos (video_id, channel_id, title, youtube_url, published_at)

-- comments table
INSERT INTO comments (comment_id, video_id, author_channel_id, text, like_count, published_at)

-- authors table
INSERT INTO authors (author_channel_id, name, profile_url)
```

### Key Implementation Rules

1. **channelId Parameter**: Must be passed to `transformToModels()` because:
   - API does NOT return it (it's a request parameter only)
   - Web scraper OR URL parsing provides it
   - NEVER try to extract from API response

2. **Comment Fields**: Handle both camelCase and snake_case:
   - API returns snake_case: `comment_id`, `author_channel_id`, `like_count`, `published_at`
   - Code must support both variants via `??` operator
   - Example: `$id = $data['commentId'] ?? $data['comment_id'] ?? null`

3. **Validation**: Check for minimum required fields:
   - Must have: `commentId` (or `comment_id`) AND `text`
   - Optional: `authorChannelId`, `author` (graceful degradation)

---

## Service Layer Architecture

### Service 1: YouTubeMetadataService (NEW)

**File**: `app/Services/YouTubeMetadataService.php`

**Dependencies**:
- Guzzle HTTP Client
- Symfony DomCrawler
- Symfony CSS Selector

**Key Methods**:

```php
class YouTubeMetadataService
{
    /**
     * Scrape video title and channel name from YouTube page
     * @return ['videoTitle' => string|null, 'channelName' => string|null, 'scrapingStatus' => 'success'|'partial'|'failed']
     */
    public function scrapeMetadata(string $videoId): array

    /**
     * Extract title from HTML meta tags
     */
    protected function extractVideoTitle(string $html): ?string

    /**
     * Extract channel name from HTML
     */
    protected function extractChannelName(string $html): ?string
}
```

**Error Handling**:
- Network timeout → return failed status, don't throw
- Parsing error → return partial results
- Always log errors but never block import flow

**Why separate service?**
- Different from YouTubePageService (which extracts channelId)
- Metadata scraping is optional enhancement
- Easier to test and mock independently

---

### Service 2: ImportService (MODIFIED)

**File**: `app/Services/ImportService.php`

**New Methods**:

```php
/**
 * Prepare import: URL parsing + metadata scraping + API fetch
 * NO database writes
 * @return object {import_id, video_id, channel_id, video_title, channel_name, comment_count, requires_tags, api_data}
 */
public function prepareImport(string $url): object

/**
 * Confirm and execute import: Write to database atomically
 * @param string $importId
 * @param array|null $tags
 * @return object Statistics {newly_added, updated, skipped, total_processed}
 */
public function confirmImport(string $importId, ?array $tags = null): object
```

**Modified Flow**:
- OLD: `import()` → URL parse → API fetch → DB write → return stats
- NEW: `prepareImport()` → URL parse → metadata scrape → API fetch → cache data → return importId
- NEW: `confirmImport()` → retrieve cached data → validate tags → transaction write → return stats
- COMPAT: `import()` delegates to prepareImport() + confirmImport() for backward compatibility

**Transaction Management**:
- Only `confirmImport()` uses DB transaction
- Wraps: video insert, comments insert, authors insert, tags insert
- Rollback on any failure

---

### Service 3: ChannelTaggingService (MODIFIED)

**File**: `app/Services/ChannelTaggingService.php`

**Modified Method**:

```php
public function createPendingImport(
    string $videoId,
    string $channelId,
    ?string $channelName,
    ?string $videoTitle = null,      // NEW
    ?int $commentCount = null         // NEW
): string
```

**Storage**: Laravel cache with 10-minute TTL

**Why extend?**
- Already manages pending imports
- Logically related to import workflow
- Minimal code changes

---

## Controller Architecture

### Controller 1: ImportController (MODIFIED)

**File**: `app/Http/Controllers/ImportController.php`

**Changed Behavior**:
- `POST /api/import` now calls `prepareImport()` instead of full `import()`
- Always returns HTTP 202 (Accepted)
- Response includes metadata for confirmation interface

**Response**:
```json
{
  "success": true,
  "message": "影片資料已載入，請確認後匯入",
  "data": {
    "import_id": "uuid...",
    "video_id": "videoId",
    "channel_id": "channelId",
    "video_title": "Title from YouTube",
    "channel_name": "Channel Name",
    "comment_count": 150,
    "requires_tags": false
  }
}
```

---

### Controller 2: ImportConfirmationController (NEW)

**File**: `app/Http/Controllers/ImportConfirmationController.php`

**Endpoints**:

```php
/**
 * POST /api/import/confirm
 * Request: {import_id, tags}
 * Response: 200 with statistics, 422 with error
 */
public function confirm(Request $request)

/**
 * POST /api/import/cancel
 * Request: {import_id}
 * Response: 200 with success message
 */
public function cancel(Request $request)
```

---

## Frontend Architecture

### Modal Component: Confirmation Interface

**File**: `resources/views/import/index.blade.php`

**Structure**:
1. Modal overlay (semi-transparent background)
2. Data review section:
   - Video Title (with icon)
   - Channel Name (with icon)
   - Comment Count (with icon)
3. Conditional tag selection (if new channel)
4. Action buttons: "確認並寫入資料", "取消匯入"

**JavaScript State Management**:
- `currentImportId`: Track which import being confirmed
- `currentChannelId`: Detect if new or existing channel
- `currentRequiresTags`: Show/hide tag section
- `selectedTags`: Track selected tag codes
- `availableTags`: Cache loaded tag list

**Button State Logic**:
- "確認並寫入資料": Disabled if new channel & no tags selected
- "取消匯入": Always enabled
- Both disabled while request in flight

---

## API Contracts

### Contract 1: POST /api/import

**Request**:
```
POST /api/import
Content-Type: application/json

{
  "url": "https://www.youtube.com/watch?v=videoId"
}
```

**Response 202** (Preparation Complete):
```json
{
  "success": true,
  "message": "影片資料已載入，請確認後匯入",
  "data": {
    "import_id": "550e8400-e29b-41d4-a716-446655440000",
    "video_id": "videoId123",
    "channel_id": "UCxxxxx",
    "video_title": "Actual Video Title",
    "channel_name": "Channel Name Here",
    "comment_count": 250,
    "requires_tags": false
  }
}
```

**Response 422** (Error):
```json
{
  "success": false,
  "message": "請輸入有效的 YouTube 或 urtubeapi 網址"
}
```

---

### Contract 2: POST /api/import/confirm

**Request**:
```
POST /api/import/confirm
Content-Type: application/json

{
  "import_id": "550e8400-e29b-41d4-a716-446655440000",
  "tags": ["pan-green", "anti-communist"]  // null or [] if existing channel
}
```

**Response 200** (Success):
```json
{
  "success": true,
  "message": "成功匯入",
  "data": {
    "stats": {
      "newly_added": 150,
      "updated": 0,
      "skipped": 100,
      "total_processed": 250
    }
  }
}
```

**Response 422** (Validation Error):
```json
{
  "success": false,
  "message": "請至少選擇一個標籤",
  "error": "validation_error"
}
```

**Response 422** (Expired):
```json
{
  "success": false,
  "message": "匯入不存在或已過期",
  "error": "import_expired"
}
```

---

### Contract 3: POST /api/import/cancel

**Request**:
```
POST /api/import/cancel
Content-Type: application/json

{
  "import_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Response 200**:
```json
{
  "success": true,
  "message": "已取消匯入"
}
```

---

## Implementation Phases

### Phase 1: Backend Foundation (Days 1-2)

**Goal**: Add metadata scraping + split import flow

**Deliverables**:
1. YouTubeMetadataService (scraping logic)
2. ImportService refactor (prepare/confirm methods)
3. ChannelTaggingService extension (metadata fields)
4. ImportConfirmationController (confirm/cancel endpoints)
5. API routes
6. Unit tests for services

**Validation**: All tests pass, no existing functionality broken

---

### Phase 2: Frontend Confirmation UI (Days 3-4)

**Goal**: Add confirmation modal and wire up JavaScript

**Deliverables**:
1. Confirmation modal HTML
2. JavaScript state management
3. Confirm/cancel button handlers
4. Tag validation logic
5. Integration with existing import flow

**Validation**: Manual testing with real data

---

### Phase 3: Integration & Testing (Day 5)

**Goal**: End-to-end testing and atomicity verification

**Deliverables**:
1. Integration tests (full confirmation flow)
2. Database atomicity tests (rollback scenarios)
3. Manual testing (all edge cases)
4. Performance validation

**Validation**: All tests pass, performance targets met

---

### Phase 4: Polish & Documentation (Day 6)

**Goal**: Documentation and code quality

**Deliverables**:
1. Error handling improvements
2. Logging enhancements
3. Code documentation
4. Developer guide

**Validation**: Code review approved

---

## Risk Assessment

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|-----------|
| YouTube HTML structure changes | HIGH | MEDIUM | Multiple extraction strategies, graceful degradation |
| Database transaction deadlock | MEDIUM | LOW | Read-committed isolation, retry logic |
| Cache expiry during review | LOW | LOW | 10-min TTL, clear expiry message |
| Frontend state bugs | MEDIUM | MEDIUM | Comprehensive manual testing, semantic JS |
| Backward compatibility break | HIGH | LOW | Existing `import()` method preserved |

---

## Success Metrics

**Technical**:
- Scraping success rate > 95%
- Transaction rollback rate < 1%
- Confirmation rate > 90%
- End-to-end time < 10 seconds

**User**:
- Import errors < 5%
- Support tickets about lost data = 0
- Confirmation UX clear > 95% of users

---

## Rollback Plan

**Scenario 1**: Critical bug in production
- Revert Git commits
- Clear cache
- Verify old import flow restored

**Scenario 2**: Performance issues
- Keep backend, revert frontend to skip confirmation modal
- Backend still works with old UI

**Scenario 3**: Metadata scraping problems
- Disable scraping in config
- Continue with NULL titles/names

---

## Files Summary

**NEW (5 files)**:
- `app/Services/YouTubeMetadataService.php`
- `app/Http/Controllers/ImportConfirmationController.php`
- `tests/Unit/YouTubeMetadataServiceTest.php`
- `tests/Unit/ImportServicePrepareConfirmTest.php`
- `tests/Integration/ImportConfirmationFlowTest.php`

**MODIFIED (5 files)**:
- `app/Services/ImportService.php`
- `app/Services/ChannelTaggingService.php`
- `app/Http/Controllers/ImportController.php`
- `resources/views/import/index.blade.php`
- `routes/api.php`

**DEPENDENCIES**: Add via Composer
```bash
composer require symfony/dom-crawler symfony/css-selector
```

---

**Plan Status**: ✅ READY FOR PHASE 1 IMPLEMENTATION

All Constitutional requirements satisfied. Architecture designed for incremental integration. Ready to proceed with service layer implementation.
