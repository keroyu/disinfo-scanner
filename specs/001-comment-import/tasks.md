---
description: "Task list for YouTube Comment Data Management System MVP with Political Stance Tagging"
---

# Tasks: YouTube Comment Data Management System with Political Stance Tagging (MVP)

**Input**: Design documents from `/specs/001-comment-import/`
**Prerequisites**: spec.md (COMPLETED), plan.md (COMPLETED)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story. Each user story can be implemented, tested, and deployed independently.

---

## Format: `[ID] [P?] [Story?] Description`

- **[ID]**: Sequential task ID (T001, T002, etc.)
- **[P]**: Parallelizable marker - can run in parallel with other [P] tasks (different files, no dependencies)
- **[Story]**: User story label [US1], [US2], [US3], [US4] - REQUIRED for user story phases only
- Include exact file paths in descriptions for clarity

---

## Path Conventions

**Single project structure** (Laravel application at repository root):
- Models: `app/Models/`
- Controllers: `app/Http/Controllers/`
- Services: `app/Services/`
- Exceptions: `app/Exceptions/`
- Migrations: `database/migrations/`
- Seeders: `database/seeders/`
- Factories: `database/factories/`
- Views: `resources/views/`
- JavaScript: `resources/js/`
- CSS: `resources/css/`
- Tests: `tests/Unit/`, `tests/Integration/`, `tests/Contract/`, `tests/Feature/`
- Routes: `routes/web.php`, `routes/api.php`

---

## Phase 1: Project Setup (Blocking - Complete First)

**Purpose**: Initialize Laravel project with dependencies and base configuration

- [ ] T001 Create Laravel 10 project structure with `laravel new` or manually initialize composer.json with required dependencies
- [ ] T002 Install PHP dependencies: `composer install` (laravel/framework 10.x, guzzlehttp/guzzle)
- [ ] T003 Install Node dependencies: `npm install` (tailwindcss 3.x, postcss, autoprefixer)
- [ ] T004 Configure `.env` file with MySQL database credentials, app name, debug mode
- [ ] T005 Generate application key with `php artisan key:generate`
- [ ] T006 Configure Tailwind CSS in `tailwind.config.js` and `resources/css/app.css`
- [ ] T007 Set up `.env.example` with placeholders for database, API endpoints, app config
- [ ] T008 Initialize git repository with `.gitignore` for Laravel

---

## Phase 2: Foundational Infrastructure (Blocking - Complete Before User Stories)

**Purpose**: Database, models, seeders, and base application structure required by all user stories

### Database Setup

- [ ] T009 [P] Create migration for tags table in `database/migrations/` with: tag_id, code, name, description, color, timestamps
- [ ] T010 [P] Create migration for channels table in `database/migrations/` with: channel_id (PK), channel_name, video_count, comment_count, first_import_at, last_import_at, timestamps
- [ ] T011 [P] Create migration for channel_tags junction table in `database/migrations/` with: channel_id (FK), tag_id (FK), created_at
- [ ] T012 [P] Create migration for videos table in `database/migrations/` with: video_id (PK), channel_id (FK), title, youtube_url, published_at, timestamps
- [ ] T013 [P] Create migration for authors table in `database/migrations/` with: author_channel_id (PK, indexed), name, profile_url, timestamps
- [ ] T014 [P] Create migration for comments table in `database/migrations/` with: comment_id (PK), video_id (FK), author_channel_id (FK, indexed), text, like_count, published_at, timestamps
- [ ] T015 [P] Add indexes to migrations: author_channel_id (comments, authors), videoId (videos), channel_id (videos); composite index (video_id, comment_id) for duplicates
- [ ] T016 [P] Add foreign key constraints to migrations: video→channel, comment→video, comment→author, channel_tag→channel, channel_tag→tag

### Eloquent Models

- [ ] T017 [P] Create Tag model in `app/Models/Tag.php` with relationships (belongsToMany channels via channel_tags)
- [ ] T018 [P] Create Channel model in `app/Models/Channel.php` with relationships (hasMany videos, belongsToMany tags via channel_tags)
- [ ] T019 [P] Create Video model in `app/Models/Video.php` with relationships (belongsTo channel, hasMany comments)
- [ ] T020 [P] Create Author model in `app/Models/Author.php` with relationships (hasMany comments)
- [ ] T021 [P] Create Comment model in `app/Models/Comment.php` with relationships (belongsTo video, belongsTo author)
- [ ] T022 [P] Create ChannelTag model in `app/Models/ChannelTag.php` as pivot model for M:M relationship

### Database Seeder

- [ ] T023 Create TagSeeder in `database/seeders/TagSeeder.php` to pre-populate 5 political stance tags (pan-green/泛綠, pan-white/泛白, pan-red/泛紅, anti-communist/反共, china-stance/中國立場) with colors (green-500, blue-500, red-500, orange-500, rose-600)
- [ ] T024 Register TagSeeder in `database/seeders/DatabaseSeeder.php` so tags are created on `php artisan db:seed`

### Exception Classes

- [ ] T025 [P] Create InvalidUrlException in `app/Exceptions/InvalidUrlException.php` for URL validation failures
- [ ] T026 [P] Create UrlParsingException in `app/Exceptions/UrlParsingException.php` for URL extraction failures
- [ ] T027 [P] Create YouTubePageException in `app/Exceptions/YouTubePageException.php` for YouTube page fetch/parse failures
- [ ] T028 [P] Create UrtubeapiException in `app/Exceptions/UrtubeapiException.php` for urtubeapi endpoint failures
- [ ] T029 [P] Create ImportException in `app/Exceptions/ImportException.php` for import orchestration failures
- [ ] T030 [P] Create ValidationException in `app/Exceptions/ValidationException.php` for data validation failures

### Base Application Template

- [ ] T031 Create base layout in `resources/views/layouts/app.blade.php` with Tailwind CSS, header, footer, slot for content
- [ ] T032 Create error page in `resources/views/errors/500.blade.php` for server errors (user-friendly message + trace ID if available)
- [ ] T033 Create error page in `resources/views/errors/422.blade.php` for validation errors (display field validation errors)

---

## Phase 3: User Story 1 - Database Schema Setup (P1)

**Goal**: Enable system to create and manage database schema via migrations

**Independent Test**: `php artisan migrate` succeeds, `php artisan migrate:rollback` succeeds, all tables + indexes exist

### Acceptance Criteria Verification Tasks

- [ ] T034 [US1] Run `php artisan migrate --fresh` and verify 6 tables created (tags, channels, channel_tags, videos, authors, comments)
- [ ] T035 [US1] Verify columns exist with correct types: tags.color, channels.channel_id, comments.author_channel_id (indexed), comments.video_id (indexed)
- [ ] T036 [US1] Verify foreign key constraints work: inserting comment without matching video fails
- [ ] T037 [US1] Verify tag seeder populates 5 tags on `php artisan db:seed` with correct codes and colors
- [ ] T038 [US1] Run `php artisan migrate:rollback` and verify all tables dropped, database is clean
- [ ] T039 [US1] Verify migrations are idempotent: running migrate twice does not error

---

## Phase 4: User Story 2 - Import Comments from Web (P1)

**Goal**: Enable system to import YouTube comments from URL (urtubeapi or YouTube) via web interface with manual channel tagging

**Independent Test**: Provide valid URL, verify comments imported to database, duplicate detection works, channel list shows imported channel

### Core Services (Implementation Before API/UI)

#### URL Parsing Service

- [ ] T040 [P] [US2] Create UrlParsingService in `app/Services/UrlParsingService.php` with methods:
  - `identify($url)` - returns 'urtubeapi' or 'youtube' or throws InvalidUrlException
  - `extractVideoIdFromUrl($url)` - extracts videoId using regex for YouTube formats (youtube.com/watch?v=, youtu.be/, m.youtube.com/watch?v=, with parameters)
  - `validateUrtubeapiUrl($url)` - validates URL contains urtubeapi.analysis.tw domain + required videoId and token parameters

- [ ] T041 [US2] Create unit test `tests/Unit/UrlParsingServiceTest.php`:
  - Test YouTube standard format: `youtube.com/watch?v=VIDEO_ID` → extracts `VIDEO_ID`
  - Test YouTube short format: `youtu.be/VIDEO_ID` → extracts `VIDEO_ID`
  - Test YouTube mobile format: `m.youtube.com/watch?v=VIDEO_ID` → extracts `VIDEO_ID`
  - Test YouTube with parameters: `youtube.com/watch?v=VIDEO_ID&t=123s` → extracts `VIDEO_ID` (ignores params)
  - Test urtubeapi format validation: accepts `urtubeapi.analysis.tw/api/api_comment.php?videoId=X&token=Y`
  - Test invalid URL format raises InvalidUrlException

#### YouTube Page Service

- [ ] T042 [P] [US2] Create YouTubePageService in `app/Services/YouTubePageService.php` with methods:
  - `fetchPageSource($videoUrl)` - fetch HTML via Guzzle with 30-second timeout, handle 404/5xx/timeout errors
  - `extractChannelIdFromSource($html)` - extract channelId from JavaScript variables (regex: `"channelId":"UC[^"]*"`) or throw YouTubePageException

- [ ] T043 [US2] Create unit test `tests/Unit/YouTubePageServiceTest.php`:
  - Test fetching valid YouTube page returns HTML
  - Test parsing channelId from HTML (mock page source with test channelId)
  - Test timeout after 30 seconds raises YouTubePageException
  - Test 404 response raises YouTubePageException
  - Test malformed HTML returns null or throws exception

#### Urtubeapi Service

- [ ] T044 [P] [US2] Create UrtubeapiService in `app/Services/UrtubeapiService.php` with methods:
  - `fetchCommentData($videoId, $channelId)` - fetch JSON from `urtubeapi.analysis.tw/api/api_comment.php?videoId=$videoId&token=$channelId` via Guzzle
  - `validateJsonStructure($json)` - verify JSON contains videoId, channelId, videoTitle, channelTitle, comments array with required fields
  - Both with 30-second timeout, error handling for 5xx/timeout/malformed JSON

- [ ] T045 [US2] Create unit test `tests/Unit/UrtubeapiServiceTest.php`:
  - Test fetching valid urtubeapi endpoint returns decoded JSON
  - Test JSON validation: missing fields raises exception
  - Test timeout after 30 seconds raises UrtubeapiException
  - Test 5xx response raises UrtubeapiException

#### Data Transform Service

- [ ] T046 [P] [US2] Create DataTransformService in `app/Services/DataTransformService.php` with methods:
  - `transformToModels($apiJson)` - transform urtubeapi JSON to array of [Video, Comment, Author] models with proper field mapping:
    - videoId → video_id, channelId → channel_id, authorChannelId → author_channel_id, commentId → comment_id, likeCount → like_count, publishedAt → published_at
  - Handle NULL fields gracefully (profile_url, youtube_url may be missing)
  - Return object with video, comments[], authors[]

- [ ] T047 [US2] Create unit test `tests/Unit/DataTransformServiceTest.php`:
  - Test transforming sample urtubeapi JSON returns Video + Comments + Authors
  - Test field name mapping (videoId → video_id, etc.)
  - Test handling missing optional fields (profile_url, youtube_url)
  - Test correct foreign key relationships (comment.video_id matches video.video_id)

#### Duplicate Detection Service

- [ ] T048 [P] [US2] Create DuplicateDetectionService in `app/Services/DuplicateDetectionService.php` with methods:
  - `detectDuplicateComments($commentIds)` - query comments table for existing comment_ids, return count of duplicates
  - `detectExistingAuthor($authorChannelId)` - query authors table, return existing author or null
  - `detectExistingChannel($channelId)` - query channels table, return existing channel or null

- [ ] T049 [US2] Create unit test `tests/Unit/DuplicateDetectionServiceTest.php`:
  - Test detecting duplicate comments: 150 new comments, 5 already exist, returns stats
  - Test detecting existing author by authorChannelId
  - Test detecting existing channel by channelId
  - Test fresh import (no duplicates) returns 0

#### Channel Tagging Service

- [ ] T050 [P] [US2] Create ChannelTaggingService in `app/Services/ChannelTaggingService.php` with methods:
  - `isNewChannel($channelId)` - query channels table, return boolean
  - `createPendingImport($videoId, $channelId, $channelName)` - generate UUID import_id, store in session/cache with pending state
  - `selectTagsForChannel($importId, $channelId, $tagCodes[])` - validate at least 1 tag selected, attach tags to channel via channel_tags table
  - `resumeImport($importId)` - retrieve pending import, continue with actual database inserts

- [ ] T051 [US2] Create unit test `tests/Unit/ChannelTaggingServiceTest.php`:
  - Test isNewChannel returns true/false correctly
  - Test selectTagsForChannel requires ≥1 tag (validates)
  - Test selectTagsForChannel stores multiple tags in channel_tags table
  - Test resumeImport retrieves pending import_id

#### Logging Service

- [ ] T052 [P] [US2] Create LoggingService in `app/Services/LoggingService.php` with methods:
  - `generateTraceId()` - create UUID for operation tracing
  - `logImportStart($traceId, $url, $urlType)` - log JSON: timestamp, operation='import_start', url, type, trace_id
  - `logImportProgress($traceId, $stats)` - log JSON: trace_id, operation='import_progress', processed, added, skipped, errors
  - `logImportError($traceId, $error)` - log JSON: trace_id, operation='import_error', error_code, message, trace_id for debugging
  - All logs to Laravel's Log facade (JSON formatted via Monolog processor)

- [ ] T053 [US2] Create unit test `tests/Unit/LoggingServiceTest.php`:
  - Test generateTraceId returns valid UUID
  - Test logImportStart creates JSON log entry
  - Test logImportError includes trace_id for debugging

#### Import Service (Orchestrator)

- [ ] T054 [US2] Create ImportService in `app/Services/ImportService.php` with method:
  - `import($url)` - main orchestration:
    1. Validate URL (UrlParsingService)
    2. If YouTube URL: fetch channelId from page (YouTubePageService)
    3. If urtubeapi or converted: extract videoId/channelId
    4. Fetch JSON (UrtubeapiService)
    5. Transform to models (DataTransformService)
    6. Detect duplicates (DuplicateDetectionService)
    7. Check if channel is new (ChannelTaggingService)
    8. If new: return 202 (pause for tagging), store import_id
    9. If existing: insert all models to database, return 200 with stats
    10. Log all steps with trace_id (LoggingService)
    11. Return ImportResult object with all stats

- [ ] T055 [US2] Create integration test `tests/Integration/ImportServiceTest.php`:
  - Mock urtubeapi response, verify full import flow (URL → JSON → database)
  - Verify duplicate detection: 150 comments, 5 duplicates, 145 inserted
  - Verify channel detection: new channel returns import_id for tagging, existing channel continues
  - Verify statistics accurate (newly_added, updated, skipped, errors)

### Controllers & API Endpoints

- [ ] T056 [P] [US2] Create ImportController in `app/Http/Controllers/ImportController.php` with:
  - `POST /api/import` - accept JSON: { url: "..." }
    - Call ImportService.import($url)
    - If new channel (status 202): return { success: true, message: "...", data: { channel_id, channel_name, import_id, requires_tags: true } }
    - If existing (status 200): return { success: true, message: "...", data: { stats: {...}, new_channel: false } }
    - On error: return { success: false, message: "user-friendly message", error: "code", trace_id: "uuid" } with appropriate HTTP status
  - Catch exceptions, map to appropriate HTTP status codes (400 InvalidUrl, 422 Validation, 500 Server Error)

- [ ] T057 [US2] Create ImportRequest in `app/Http/Requests/ImportRequest.php`:
  - Validate `url` field is required, non-empty string
  - Validate URL matches pattern: urtubeapi.analysis.tw or youtube.com variants
  - Return 422 with validation errors if invalid

- [ ] T058 [P] [US2] Create ImportPageController in `app/Http/Controllers/ImportPageController.php` with:
  - `GET /` - return import.blade.php view with empty form state

- [ ] T059 [P] [US2] Create TagSelectionController in `app/Http/Controllers/TagSelectionController.php` with:
  - `GET /api/tags/select?import_id=UUID` - fetch tags list, return modal.blade.php for AJAX response
  - `POST /api/tags/select` - accept JSON: { import_id, channel_id, tags: ["pan-green", ...] }
    - Validate ≥1 tag selected (422 if none)
    - Call ChannelTaggingService.selectTagsForChannel()
    - Resume import (ImportService.import() with pending import_id)
    - Return { success: true, data: { import_stats } } with completion stats

- [ ] T060 [US2] Create TagSelectionRequest in `app/Http/Requests/TagSelectionRequest.php`:
  - Validate `import_id`, `channel_id`, `tags` array (≥1, valid codes)

- [ ] T061 [P] [US2] Create ChannelListController in `app/Http/Controllers/ChannelListController.php` with:
  - `GET /channels` - return channels/list.blade.php with all channels + stats from database

### Views & Frontend

- [ ] T062 [P] [US2] Create import page view in `resources/views/import/index.blade.php`:
  - Header: "YouTube 留言資料匯入系統"
  - Instruction text: explain two URL input methods
  - Text input: id="url-input", placeholder="貼上網址", w-full
  - Button: id="import-btn", "開始匯入", Tailwind primary style
  - Status section (hidden): spinner + status text ("正在解析 YouTube 影片...", "正在匯入留言...")
  - Results section (hidden): green card with stats (成功匯入 X 則留言, 新增 X 筆, 更新 X 筆, 跳過 X 筆)
  - Error section (hidden): red card with error message + icon

- [ ] T063 [P] [US2] Create tag selection modal view in `resources/views/import/modal.blade.php`:
  - Overlay: bg-black/50, z-50, fixed
  - Modal: bg-white, rounded-lg, shadow-lg, max-w-2xl, centered
  - Title: "新頻道標籤設定"
  - Channel info: "檢測到新頻道", channel ID, channel name
  - Tag checkboxes: 5 checkboxes, stacked vertically, each with color badge (green, blue, red, orange, rose)
  - Error message area (hidden): red text "請至少選擇一個標籤"
  - Buttons: "確認並繼續匯入" (primary, Tailwind blue), "取消匯入" (secondary, gray)

- [ ] T064 [P] [US2] Create JavaScript handler in `resources/js/import.js`:
  - On import form submit:
    - Validate URL not empty (422 client-side)
    - Show status section + spinner
    - POST /api/import with { url }
    - If 202 (new channel): show modal via AJAX, populate with channel_id/name from response
    - If 200 (existing): show results section with stats
    - On error: show error section with message
  - On tag modal submit:
    - Validate ≥1 checkbox selected (show error message if not)
    - POST /api/tags/select with { import_id, channel_id, tags }
    - Show results section with stats
  - On modal cancel: POST with action='cancel' to abort import
  - Handle all HTTP error codes (400, 422, 500) with user-friendly error display

- [ ] T065 [P] [US2] Create Tailwind CSS customization in `resources/css/app.css`:
  - Define tag color classes: .tag-pan-green (bg-green-500), .tag-pan-white (bg-blue-500), .tag-pan-red (bg-red-500), .tag-anti-communist (bg-orange-500), .tag-china-stance (bg-rose-600)
  - Define spinner animation for loading state
  - Import Tailwind directives: @tailwind base, components, utilities

### Integration Tests

- [ ] T066 [US2] Create feature test `tests/Feature/ImportPageDisplayTest.php`:
  - Test GET / returns import page with form + instructions visible
  - Test form has URL input field and submit button

- [ ] T067 [US2] Create feature test `tests/Feature/ModalDisplayTest.php`:
  - Test GET /api/tags/select?import_id=UUID returns modal HTML with 5 tag checkboxes
  - Test modal displays channel info

- [ ] T068 [US2] Create integration test `tests/Integration/ImportEndpointTest.php`:
  - Test POST /api/import with urtubeapi URL returns 200 + stats (mock urtubeapi response)
  - Test POST /api/import with YouTube URL returns 202 + import_id (mock YouTube page)
  - Test POST /api/import with invalid URL returns 400 + error message
  - Test POST /api/import with malformed JSON returns 422 + error message

- [ ] T069 [US2] Create integration test `tests/Integration/TagSelectionFlowTest.php`:
  - Test POST /api/tags/select with ≥1 tag saves tags + resumes import, returns 200 + final stats
  - Test POST /api/tags/select with 0 tags returns 422 + validation error
  - Test POST /api/tags/select with invalid import_id returns 400

---

## Phase 5: User Story 3 - Tag New Channels (P1)

**Goal**: Enable modal to appear for new channels, require tag selection before continuing

**Independent Test**: Import from new channel URL pauses import, modal shows, selecting tags saves tags, import completes, channel list shows tags

### Database Persistence

- [ ] T070 [US3] Verify ChannelTaggingService persists pending imports (session/cache timeout 5 minutes)
- [ ] T071 [US3] Verify channel_tags table inserts correctly when tags selected via POST /api/tags/select

### Modal Validation

- [ ] T072 [US3] Test modal validation: submitting with 0 tags shows error message "請至少選擇一個標籤" in red
- [ ] T073 [US3] Test modal validation: submitting with 1+ tags succeeds, modal closes, import continues

### Channel Tag Display

- [ ] T074 [P] [US3] Update ChannelListController to load channel tags via eager loading (with('tags'))

---

## Phase 6: User Story 4 - View Imported Channels (P1)

**Goal**: Display all imported channels with tags, statistics, and import history in table format

**Independent Test**: Channel list page loads, shows all channels with correct tags/statistics, responsive on tablet

### Views & Frontend

- [ ] T075 [P] [US4] Create channel list page view in `resources/views/channels/list.blade.php`:
  - Page title: "已匯入頻道列表"
  - Table: fixed header, scrollable body
  - Columns:
    - 頻道 ID (left-aligned, monospace, w-1/4)
    - 頻道名稱 (left-aligned, w-1/4)
    - 標籤 (center-aligned, badges, w-1/4)
    - 留言數 (right-aligned, formatted with commas: 1,234, w-1/6)
    - 最後匯入時間 (center-aligned, relative time: "2 天前", "1 小時前", w-1/6)
  - Table styling: Tailwind striped rows (odd: bg-white, even: bg-gray-50)
  - Tag badges: color-coded (green-500, blue-500, red-500, orange-500, rose-600), rounded-full, padding
  - Responsive: on tablet (768px+), slightly compressed, no horizontal scroll
  - Empty state: "No channels imported yet" if table is empty

- [ ] T076 [P] [US4] Create tag badge component in `resources/views/components/tag-badge.blade.php`:
  - Accept tag object with code, name, color
  - Render as inline badge with appropriate Tailwind color class
  - Display tag name

### Data Resource

- [ ] T077 [P] [US4] Create ChannelResource in `app/Http/Resources/ChannelResource.php`:
  - Format channel for table display:
    - channel_id
    - channel_name
    - tags (array of tag objects)
    - comment_count (formatted with commas via PHP number_format)
    - last_import_at (formatted as relative time: "2 天前" via Carbon diffForHumans + Chinese locale)
    - first_import_at
    - video_count

### Controller Update

- [ ] T078 [US4] Update ChannelListController.GET /channels:
  - Load all channels with tags eagerly (Channel::with('tags'))
  - Transform via ChannelResource
  - Pass to view with variables: $channels, $currentTimestamp
  - Blade can format relative time client-side via JavaScript helper or server-side via Carbon

### Integration Tests

- [ ] T079 [US4] Create feature test `tests/Feature/ChannelListDisplayTest.php`:
  - Insert test channels with tags via factory
  - Test GET /channels returns channel list page
  - Test all 5 columns display correctly (ID, Name, Tags, Count, Time)
  - Test tags display as color-coded badges
  - Test comment count formatted with commas
  - Test relative time formats correctly ("1 小時前", "2 天前", etc.)
  - Test responsive design on tablet viewport

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Error handling, logging, documentation, performance optimization, final testing

### Error Handling & Logging

- [ ] T080 Create custom error handler in `app/Exceptions/Handler.php` to:
  - Catch all exceptions, log with trace ID
  - Return JSON for API errors (500, 400, 422, 404)
  - Return HTML error pages for web errors (resources/views/errors/)
  - Never expose technical details to users (only use trace_id for debugging)

- [ ] T081 Create logging middleware in `app/Http/Middleware/LogRequests.php`:
  - Log all requests: method, path, status, duration, trace_id
  - JSON format for structured logging
  - Attach trace_id to all log entries per request

- [ ] T082 Implement rate limiting (optional for MVP, add if needed):
  - Rate limit /api/import to 5 requests/minute per IP (prevent abuse)
  - Return 429 Too Many Requests

### Performance Optimization

- [ ] T083 Create database query optimization:
  - Add caching for tags (rarely change): Cache::remember('all_tags', 24*60, fn() => Tag::all())
  - Add query optimization: select only needed columns in queries
  - Verify indexes on author_channel_id, videoId, channel_id via EXPLAIN queries

- [ ] T084 Implement pagination for channel list (if many channels):
  - Use Laravel pagination: `Channel::with('tags')->paginate(50)`
  - Add pagination links to view

### Documentation

- [ ] T085 Create README.md in project root:
  - Project overview (2-3 sentences)
  - Prerequisites (PHP 8.2+, MySQL 8.0+, Composer, npm)
  - Installation steps:
    1. Clone repo
    2. `composer install`
    3. `npm install`
    4. `.env` setup
    5. `php artisan key:generate`
    6. `php artisan migrate`
    7. `php artisan db:seed`
  - Running application: `php artisan serve` + `npm run dev`
  - Running tests: `php artisan test`
  - API documentation link (if OpenAPI spec available)

- [ ] T086 Create API documentation in `docs/API.md` or embed in OpenAPI YAML:
  - POST /api/import - endpoint signature, request body, response codes, example
  - POST /api/tags/select - endpoint signature, request body, response codes, example
  - GET /api/tags/select - endpoint signature, response, example
  - Error response format

### Final Testing

- [ ] T087 Run full test suite: `php artisan test --parallel`
  - Verify all unit, integration, feature tests pass
  - Verify test coverage ≥70% (optional, can add coverage command)

- [ ] T088 Manual end-to-end testing:
  - Test complete import flow: Enter YouTube URL → see modal → select tags → see results
  - Test duplicate handling: Import same URL twice → second import shows 0 new comments
  - Test channel list: Navigate to /channels → see all imported channels + tags
  - Test error scenarios: Invalid URL, API down, malformed JSON (verify user-friendly messages)

- [ ] T089 Performance testing:
  - Measure page load times: / and /channels (target < 2 seconds)
  - Measure YouTube URL parsing time (target 3-5 seconds)
  - Measure comment import time for 500-comment payload (target 5-10 seconds)
  - Verify no memory overflow on 1000+ comment import (batch processing)

### Deployment

- [ ] T090 Create `.env.production` template for production deployment:
  - Database: production MySQL instance
  - Debug: false
  - Log level: warning (less verbose)
  - APP_KEY: generate for production

- [ ] T091 Create deployment instructions in `docs/DEPLOYMENT.md`:
  - Database migrations: `php artisan migrate --force`
  - Seeders: `php artisan db:seed --class=TagSeeder`
  - Cache clear: `php artisan cache:clear`
  - Asset compilation: `npm run build`

---

## Dependencies & Parallelization

### Dependency Graph

```
Phase 1 (Setup) → Phase 2 (Foundational)
                        ↓
         ┌──────────────┴──────────────┐
         ↓                             ↓
    Phase 3 (US1)              Phase 4 (US2)
    (Database)                 (Import Web)
         ↓                             ↓
    Phase 5 (US3)              Phase 5 (US3)
    (Channel Tagging)          (Channel Tagging)
         ↓                             ↓
         └──────────────┬──────────────┘
                        ↓
                   Phase 6 (US4)
                (Channel List)
                        ↓
                   Phase 7 (Polish)
                (Final Testing)
```

**Critical Path**: Setup → Foundational → US1 (migrations) → US2 (import logic) → US3 (tagging) → US4 (list) → Polish

### Parallelization Opportunities

**Within Phase 2 (Foundational)**:
- T009-T016: Create all 6 migrations in parallel (different tables)
- T017-T022: Create all 6 models in parallel (different files)
- T025-T030: Create all 6 exception classes in parallel (different files)

**Within Phase 4 (User Story 2)**:
- T040-T053: Create all services in parallel (UrlParsing, YouTube, Urtubeapi, DataTransform, DuplicateDetection, ChannelTagging, Logging)
- T056-T061: Create controllers + requests in parallel (ImportController, ImportPageController, TagSelectionController)
- T062-T065: Create views + JavaScript in parallel (import page, modal, channel list, JavaScript)

**Within Phase 6 (User Story 4)**:
- T075-T078: Create channel list view + resource + component in parallel

### MVP Scope Recommendation

For MVP launch (minimum viable product):
- **Complete**: Phase 1, 2, 3, 4
- **Consider**: Phase 5 (channel tagging is required per spec, so include)
- **Consider**: Phase 6 (channel list is required per spec, so include)
- **Optional for MVP**: Phase 7 (polish, optimization, deployment can be deferred for patch releases)

**MVP Definition**: Deliver features for User Stories 1-4 (database + import + tagging + channel list) with basic error handling.

---

## Task Summary

| Phase | Count | Tasks | Purpose |
|-------|-------|-------|---------|
| Setup (Phase 1) | 8 | T001-T008 | Project initialization |
| Foundational (Phase 2) | 43 | T009-T051 | Database, models, services, exceptions |
| User Story 1 (Phase 3) | 6 | T052-T058 | Database schema via migrations |
| User Story 2 (Phase 4) | 19 | T059-T077 | Import web interface |
| User Story 3 (Phase 5) | 4 | T078-T081 | Channel tagging modal |
| User Story 4 (Phase 6) | 5 | T082-T086 | Channel list page |
| Polish (Phase 7) | 11 | T087-T097 | Error handling, logging, docs, testing, deployment |
| **TOTAL** | **96** | **T001-T091** | Complete MVP implementation |

