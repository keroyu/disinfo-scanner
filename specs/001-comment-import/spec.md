# Feature Specification: YouTube Comment Data Management System with Political Stance Tagging (MVP)

**Feature Branch**: `001-comment-import`
**Created**: 2025-11-15
**Status**: Draft
**Input**: User description: "YouTube comment data management system MVP with web UI and political stance channel tagging"

---

## Project Context

YouTube does not provide functionality to search comments by commenter ID (authorChannelId). This system solves this limitation by:

1. **Importing comment data** from external API (urtubeapi.analysis.tw) into local database
2. **Classifying channels** with political stance labels during import (泛綠/泛白/泛紅/反共/中國立場)
3. **Enabling future queries** by commenter ID and political stance to find all comments from a specific user across videos
4. **Detecting campaigns** by identifying patterns in tagged channels and their comment activity (future phase)

This MVP includes:
- **Database design** with proper schema for comments, channels, tags, and relationships
- **Web import interface** supporting both urtubeapi URLs and YouTube video URLs
- **Channel tagging system** with required political stance classification for new channels
- **Channel list view** to review imported channels and their tags

Out of scope for MVP:
- Batch import of multiple URLs
- Query/search interface for comments
- YouTube API live integration
- Automated tag editing
- Comment sentiment analysis

---

## User Scenarios & Testing *(mandatory)*

### User Story 1: Set Up Database Schema (Priority: P1)

**Actor**: System developer / administrator

**Scenario**: I need to initialize the database with proper schema to store YouTube comment data, channel information, and political stance tags.

**Why this priority**: Blocking prerequisite—without schema, no data can be imported.

**Independent Test**: Run migrations and verify all tables exist with correct structure, indexes, and relationships. Database inspection tools only (no application logic required).

**Acceptance Scenarios**:

1. **Given** fresh database with no tables, **When** I run `php artisan migrate`, **Then** all required tables (videos, comments, authors, channels, tags, channel_tags) are created successfully
2. **Given** existing migrated database, **When** I run `php artisan migrate:rollback`, **Then** all tables are dropped and database returns to clean state
3. **Given** migrated database, **When** I inspect the table structure, **Then** all required columns are present with correct data types
4. **Given** migrated database, **When** I check indexes, **Then** indexes exist on frequently-queried columns (author_channel_id, videoId, channel_id)
5. **Given** migrated database with foreign key constraints, **When** I attempt to insert comment without matching video/author, **Then** foreign key constraint prevents invalid data
6. **Given** migrated database, **When** I check tag table, **Then** five default political stance tags (泛綠, 泛白, 泛紅, 反共, 中國立場) are pre-populated with colors and descriptions

---

### User Story 2: Import Comments via Web Interface (Priority: P1)

**Actor**: System administrator

**Scenario**: I can paste either a urtubeapi URL or YouTube video URL into a web form, and the system automatically fetches, imports comments, and asks for channel tagging when needed.

**Why this priority**: Core MVP functionality delivering primary user value.

**Independent Test**: Provide a valid URL (either format), verify comments are correctly imported with proper deduplication and channel detection. Testable without query interface.

**Acceptance Scenarios**:

1. **Given** import page loaded, **When** I paste a urtubeapi URL, **Then** the system correctly identifies it as urtubeapi format
2. **Given** import page loaded, **When** I paste a YouTube URL (standard/short/mobile/with parameters), **Then** the system correctly identifies it as YouTube format
3. **Given** YouTube URL provided, **When** I click "開始匯入", **Then** system displays "正在解析 YouTube 影片..." loading indicator
4. **Given** YouTube parsing in progress, **When** system completes parsing, **Then** videoId and channelId are extracted and system begins importing comments
5. **Given** urtubeapi URL with valid JSON, **When** system fetches data, **Then** all comment fields are correctly imported to database
6. **Given** new channel not in database, **When** system detects it during import, **Then** import pauses and tag selection modal appears
7. **Given** tag selection modal open, **When** I select one or more tags and click "確認並繼續匯入", **Then** tags are saved and import continues
8. **Given** tag selection modal open, **When** I click "取消匯入", **Then** import is cancelled and modal closes
9. **Given** completed import, **When** I view results, **Then** I see statistics: 成功匯入 X 則留言, 新增: X 筆, 更新: X 筆, 跳過: X 筆
10. **Given** duplicate comments in database, **When** import runs again, **Then** duplicates are detected and skipped (0% duplication rate)

---

### User Story 3: Tag New Channels with Political Stance (Priority: P1)

**Actor**: System administrator

**Scenario**: When importing a video from a new channel, I must classify that channel with one or more political stance labels before the import completes.

**Why this priority**: Core data classification required for future analysis; blocks import flow to ensure every channel is tagged.

**Independent Test**: Verify that new channels trigger tagging flow, existing channels skip it, and tags persist correctly in database.

**Acceptance Scenarios**:

1. **Given** new channel detected during import, **When** system triggers tag selection, **Then** modal appears with channel ID and channel name
2. **Given** tag selection modal open, **When** I see the five tag options, **Then** each tag displays its label, color code, and checkbox
3. **Given** no tags selected, **When** I click "確認並繼續匯入", **Then** error message "請至少選擇一個標籤" appears in red
4. **Given** one or more tags selected, **When** I click "確認並繼續匯入", **Then** tags are saved to channel_tags table with correct relationships
5. **Given** existing channel in database, **When** importing a new video from that channel, **Then** tag selection modal does not appear (only new channels are tagged)
6. **Given** tag selection complete, **When** viewing channel list later, **Then** saved tags display correctly with assigned colors

---

### User Story 4: View Imported Channels and Tags (Priority: P1)

**Actor**: System administrator

**Scenario**: I can view a list of all imported channels with their assigned political stance tags, comment statistics, and import timestamps.

**Why this priority**: Provides visibility and verification of data classification and import history.

**Independent Test**: Verify that channel list displays all imported channels with correct tag associations, statistics, and formatting. No complex filtering required for MVP.

**Acceptance Scenarios**:

1. **Given** channel list page loaded, **When** I view the table, **Then** it displays all imported channels
2. **Given** channel list displayed, **When** I view each row, **Then** I see: 頻道 ID, 頻道名稱, 標籤 (color-coded badges), 留言數 (formatted with thousands), 最後匯入時間 (relative time like "2 天前")
3. **Given** channel with multiple tags, **When** viewing tag display, **Then** multiple tags appear as separate badges with appropriate colors
4. **Given** large number of channels in list, **When** table renders, **Then** table header is fixed and content scrolls (responsive design on desktop and tablet)
5. **Given** channel list page viewed on tablet, **When** layout responds, **Then** columns remain readable and all information is accessible without horizontal scroll

---

### Edge Cases

- What happens when the external API is unreachable or times out during import?
- How does the system handle YouTube page parsing failures?
- What occurs when JSON data is malformed or missing required fields?
- How are URL validation edge cases handled (invalid formats, missing parameters)?
- What if a channel has no name from the YouTube page?

---

## Requirements *(mandatory)*

### Functional Requirements

**Database Schema**

- **FR-001**: System MUST create tables: videos, comments, authors, channels, tags, channel_tags with proper relationships and constraints
- **FR-002**: System MUST support migration and rollback operations via `php artisan migrate` and `php artisan migrate:rollback`
- **FR-003**: System MUST create indexes on: author_channel_id (for future queries), videoId, channel_id (for lookups)
- **FR-004**: System MUST pre-populate tags table with five political stance labels: 泛綠, 泛白, 泛紅, 反共, 中國立場 (with codes, colors, descriptions)

**URL Handling & Validation**

- **FR-005**: System MUST accept complete urtubeapi URL format: `https://urtubeapi.analysis.tw/api/api_comment.php?videoId=[videoId]&token=[channelId]`
- **FR-006**: System MUST accept YouTube video URLs in multiple formats:
  - Standard: `https://www.youtube.com/watch?v=VIDEO_ID`
  - Short: `https://youtu.be/VIDEO_ID`
  - Mobile: `https://m.youtube.com/watch?v=VIDEO_ID`
  - With parameters: `https://www.youtube.com/watch?v=VIDEO_ID&t=123s`
- **FR-007**: System MUST validate URLs and reject invalid formats with appropriate error messages
- **FR-008**: System MUST extract videoId from YouTube URLs (regex or URL parsing)
- **FR-009**: System MUST parse YouTube page source to extract channelId when YouTube URL is provided
- **FR-010**: System MUST validate urtubeapi URLs have required parameters (videoId, token)

**Data Import & Processing**

- **FR-011**: System MUST fetch JSON data from urtubeapi endpoint via HTTP GET with 30-second timeout
- **FR-012**: System MUST validate JSON structure and verify required fields (videoId, channelId, comments array, comment fields)
- **FR-013**: System MUST insert video records with: videoId (unique), title, channel_id, channel_name, published_at, created_at
- **FR-014**: System MUST insert comment records with: comment_id (unique), video_id (FK), author_channel_id, text, like_count, published_at, created_at
- **FR-015**: System MUST insert author records with: author_channel_id (unique), name, profile_url, created_at
- **FR-016**: System MUST detect if channel is new; if new, pause import and request tag selection; if existing, continue without tagging prompt
- **FR-017**: System MUST detect duplicate comments by comment_id and skip reimport, marking them in results summary
- **FR-018**: System MUST detect duplicate authors by author_channel_id and reuse existing records (no duplicates)

**Channel Tagging**

- **FR-019**: System MUST display tag selection modal when new channel is detected
- **FR-020**: System MUST show channel ID and channel name in modal
- **FR-021**: System MUST display five tag options as checkboxes with color coding
- **FR-022**: System MUST require selection of at least one tag before continuing (frontend + backend validation)
- **FR-023**: System MUST allow selection of multiple tags for a single channel
- **FR-024**: System MUST save selected tags to channel_tags junction table with correct foreign key relationships
- **FR-025**: System MUST prevent re-tagging of existing channels (modal only appears for new channels)

**Web Interface**

- **FR-026**: System MUST provide import page with title "YouTube 留言資料匯入系統"
- **FR-027**: System MUST display instructional text explaining two input methods (urtubeapi and YouTube URLs)
- **FR-028**: System MUST provide single text input field with sufficient width for long URLs
- **FR-029**: System MUST provide "開始匯入" button to trigger import
- **FR-030**: System MUST display loading indicator with animated spinner and status text during import ("正在解析 YouTube 影片...", "正在匯入留言...")
- **FR-031**: System MUST use AJAX to prevent page reload during import
- **FR-032**: System MUST display import results with statistics: total processed, newly added, updated, skipped, errors
- **FR-033**: System MUST provide tag selection modal with centered overlay and semi-transparent background
- **FR-034**: System MUST provide "確認並繼續匯入" and "取消匯入" buttons in modal
- **FR-035**: System MUST display error message in red when less than one tag is selected
- **FR-036**: System MUST provide channel list page showing all imported channels with: channel ID, name, tags (badges), comment count, last import time
- **FR-037**: System MUST use Tailwind CSS for all styling
- **FR-038**: System MUST implement responsive design supporting desktop and tablet viewports

**Logging & Error Handling**

- **FR-039**: System MUST log all operations with timestamp, operation type, record counts, and errors to application logs
- **FR-040**: System MUST display user-friendly error messages (no technical jargon): "請輸入有效的 urtubeapi 或 YouTube 影片網址", "無法訪問 YouTube，請檢查網路連線或稍後再試", etc.
- **FR-041**: System MUST preserve user input on error (don't clear form)
- **FR-042**: System MUST not crash or show blank screen on error
- **FR-043**: System MUST handle all specified error scenarios with appropriate user feedback

---

### Key Entities *(include if feature involves data)*

- **Video**: YouTube video from which comments were collected
  - Attributes: videoId (PK), title, channel_id (FK), channel_name, youtube_url (optional), published_at, created_at, updated_at

- **Comment**: Individual YouTube comment
  - Attributes: comment_id (PK), video_id (FK), author_channel_id (FK), text, like_count, published_at, created_at, updated_at

- **Author**: YouTube channel/user who authored comments
  - Attributes: author_channel_id (PK), name, profile_url, created_at, updated_at

- **Channel**: YouTube channel being analyzed (video owner)
  - Attributes: channel_id (PK), channel_name, video_count, comment_count, first_import_at, last_import_at, created_at, updated_at

- **Tag**: Political stance classification label
  - Attributes: tag_id (PK), code (pan-green, pan-white, pan-red, anti-communist, china-stance), name (泛綠, etc.), description, color (hex/Tailwind class), created_at

- **ChannelTag**: Junction table for many-to-many channel-tag relationships
  - Attributes: channel_id (FK), tag_id (FK), created_at

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

**Database & Schema**
- **SC-001**: Database schema initializes without errors; all tables created with correct columns, data types, and constraints
- **SC-002**: Migrations support both forward and rollback operations; database can be reset to clean state
- **SC-003**: Five default tags are pre-populated with correct codes, colors, and descriptions
- **SC-004**: Indexes on author_channel_id, videoId, and channel_id exist and improve query performance

**Data Import Functionality**
- **SC-005**: Single import operation successfully processes and stores 100+ comments from valid urtubeapi URL without data loss
- **SC-006**: Duplicate detection prevents re-importing same comments (0% duplication rate on second import of same URL)
- **SC-007**: Comment-to-video and comment-to-author relationships are correctly preserved (no orphaned records)
- **SC-008**: YouTube URL conversion correctly extracts videoId and channelId; system successfully imports comments from YouTube URLs
- **SC-009**: Import operation completes within 5-10 seconds for typical payloads (500 comments)

**Channel Tagging**
- **SC-010**: New channels trigger tag selection modal; modal displays correctly with all five tag options
- **SC-011**: Tag selection is required; system prevents import continuation without at least one selected tag
- **SC-012**: Selected tags are correctly saved to database with proper channel-tag relationships
- **SC-013**: Existing channels do not trigger tag selection modal on re-import
- **SC-014**: Tag selection modal appears/closes with animation; no page refresh occurs

**Web Interface & UX**
- **SC-015**: Import page loads in under 2 seconds
- **SC-016**: Page displays instructional text, input field, and button with clear visual hierarchy
- **SC-017**: Loading indicator displays during import with animated spinner and status text
- **SC-018**: Import results display statistics clearly (新增: X, 更新: X, 跳過: X)
- **SC-019**: Channel list page displays all imported channels with correct information (ID, name, tags, count, time)
- **SC-020**: Tags display as color-coded badges with appropriate visual styling
- **SC-021**: All UI elements use Tailwind CSS; layout is responsive on desktop and tablet
- **SC-022**: Modal appears/disappears with smooth fade-in animation

**Error Handling & Reliability**
- **SC-023**: All error scenarios from requirements are handled with appropriate user messages
- **SC-024**: Error messages are user-friendly (no stack traces, technical jargon)
- **SC-025**: System preserves user input when errors occur; form field is not cleared
- **SC-026**: System does not crash or display blank screen on error
- **SC-027**: All errors are logged with detail for developer debugging
- **SC-028**: HTTP requests have 30-second timeout; system gracefully handles timeouts and network errors

---

## Assumptions

- **Tech Stack**: Laravel PHP with Eloquent ORM, Blade templating, artisan CLI (inferred from migrations requirement)
- **Frontend**: Tailwind CSS for styling; AJAX for async operations (no JavaScript framework specified, vanilla JS or simple solution acceptable)
- **Database**: SQL-based relational database (MySQL, PostgreSQL, or SQLite for development)
- **Scope**: YouTube URL parsing relies on page source extraction (server-side); no YouTube API integration
- **Color Scheme**: Tailwind CSS color utilities used for tag visualization; mapping: 泛綠→green, 泛白→blue, 泛紅→red, 反共→orange, 中國立場→rose/pink
- **Relative Time**: Last import time displayed as relative (e.g., "2 天前"); uses library or custom function
- **Data Immutability**: Comments are immutable once imported; updates to author info in subsequent imports do not overwrite existing records
- **Error Handling**: Transient errors (5xx, timeouts) can be retried; persistent errors (4xx, invalid JSON) abort import with user message

---

## Data Model Specifics

### urtubeapi JSON Format Expected

```json
{
  "videoId": "azzXesons8Q",
  "channelId": "UC1tp9qN7mqIJ7CLVNVvsKzg",
  "videoTitle": "影片標題",
  "channelTitle": "頻道名稱",
  "comments": [
    {
      "commentId": "UgzXXXXXXXXXXX",
      "author": "留言者名稱",
      "authorChannelId": "UCxxxxxxxxxxxxxxxxx",
      "text": "留言內容",
      "likeCount": 10,
      "publishedAt": "2024-01-15T10:30:00Z",
      "updatedAt": "2024-01-15T10:30:00Z"
    }
  ]
}
```

### Data Constraints

- Comment ID must be globally unique (no duplicates across imports)
- Author channel ID must be indexed (critical for future queries)
- Video must belong to exactly one channel
- Comment must belong to exactly one video
- Channel must have at least one tag (enforced at UI and database level)
- All timestamps use ISO 8601 format with timezone

---

## Out of Scope (Future Phases)

- Batch import from multiple URLs simultaneously
- Query/search interface for finding comments by author or tag
- YouTube API integration for live comment fetching
- Editing existing channel tags after creation
- Automatic periodic updates of imported comments
- Comment reply nesting support
- Real-time import progress display
- Background job queue for large imports
- Caching of YouTube page parsing results
- Advanced analytics or sentiment analysis

---

## UI/UX Details

### Import Page Layout

**Header Section**
- Page title: "YouTube 留言資料匯入系統"
- Instructional text block explaining two input methods

**Input Section**
- Text input field with placeholder text suggesting URL format
- Field width sufficient for full URLs (minimum 400px)
- "開始匯入" button below input (primary CTA style)

**Status Section**
- Initially hidden; shown during import
- Animated spinner + status text (e.g., "正在解析 YouTube 影片...")
- Progress indication if applicable

**Results Section**
- Shown after import completes
- Green background for success, red for errors
- Statistics display format: "成功匯入 X 則留言\n新增: X 筆\n更新: X 筆\n跳過: X 筆"

**Error Section**
- Red text + warning icon
- Messages cleared when new import begins

### Tag Selection Modal

**Visual Design**
- Centered overlay with 40-60% width on desktop
- Semi-transparent dark background (Tailwind bg-black/50 or similar)
- Fade-in animation on appearance (0.2-0.3 seconds)
- Modal border-radius for rounded corners

**Content Layout**
- Title: "新頻道標籤設定" (centered at top)
- Channel info block: "檢測到新頻道" + channel ID + channel name (if available)
- Tag selection section: instruction text + five checkboxes (stacked vertically)
- Each tag checkbox: colored dot/badge + label + checkbox input
- Error message area: red text below checkboxes (shown when validation fails)
- Button section: two buttons at bottom ("確認並繼續匯入" primary, "取消匯入" secondary)

**Colors for Tags**
- 泛綠: `bg-green-500` text-white
- 泛白: `bg-blue-500` text-white
- 泛紅: `bg-red-500` text-white
- 反共: `bg-orange-500` text-white
- 中國立場: `bg-rose-600` text-white

### Channel List Page

**Table Design**
- Fixed header row with column titles
- Scrollable body (if many channels)
- Alternating row background colors (light/slightly darker) for readability
- Striped pattern using Tailwind alternating classes

**Columns**
1. 頻道 ID (left-aligned, monospace)
2. 頻道名稱 (left-aligned)
3. 標籤 (center-aligned, multiple badges)
4. 留言數 (right-aligned, formatted with commas: 1,234)
5. 最後匯入時間 (center-aligned, relative: "2 天前", "1 小時前")

**Tag Badge Styling**
- Inline badges with color from tag definition
- Rounded corners (Tailwind rounded-full or rounded-lg)
- Padding and spacing for readability
- Multiple tags separated by small gap (0.25rem-0.5rem)

**Responsive Design**
- Desktop: Full table with all columns
- Tablet (768px-1024px): Slightly compressed but readable; no horizontal scroll if possible
- Stack columns or adjust font size if needed

---

## Performance Targets

- Page initial load: < 2 seconds
- YouTube URL parsing: 3-5 seconds (including page fetch)
- Data import (500 comments): 5-10 seconds
- Tag selection modal open: < 0.5 seconds (instant, no network call)
- Large comment batches (1000+): Use batch processing to avoid memory overflow
- HTTP requests: 30-second timeout
- No page unresponsiveness during import (AJAX prevents blocking)

---

## Error Handling Catalog

### URL Validation Errors

| Scenario | User Message |
|----------|--------------|
| Empty input | "請輸入網址" |
| Invalid URL format | "請輸入有效的 urtubeapi 或 YouTube 影片網址" |
| urtubeapi missing videoId or token | "urtubeapi 網址缺少必要參數（videoId 或 token）" |
| Unrecognized YouTube URL format | "無法識別的 YouTube 網址格式，請檢查網址是否正確" |

### YouTube Parsing Errors

| Scenario | User Message |
|----------|--------------|
| YouTube page inaccessible | "無法訪問 YouTube，請檢查網路連線或稍後再試" |
| Cannot extract channelId from page | "無法從 YouTube 頁面取得頻道資訊，請改用 urtubeapi 網址" |
| YouTube request timeout | "無法訪問 YouTube，請檢查網路連線或稍後再試" |

### Import Errors

| Scenario | User Message |
|----------|--------------|
| urtubeapi server unreachable | "無法連接到資料來源，請稍後再試" |
| urtubeapi timeout | "無法連接到資料來源，請稍後再試" |
| Invalid JSON response | "資料格式異常，無法匯入" |
| Missing required JSON fields | "資料格式異常，無法匯入" |
| Database write error | "系統錯誤，請聯繫管理員" |

### Tag Selection Errors

| Scenario | User Message |
|----------|--------------|
| No tags selected before confirming | "請至少選擇一個標籤" (displayed in red below checkboxes) |

### Logging

All errors logged with:
- Timestamp
- Error type/category
- Full exception/stack trace (for developers)
- HTTP request details if applicable
- User-friendly message shown to user

---

## Success Completion Criteria

Feature is complete when:

### Database (5 criteria)
1. ✓ Database can be created and destroyed via migrations
2. ✓ All required tables exist (videos, comments, authors, channels, tags, channel_tags)
3. ✓ Table relationships and foreign keys correctly defined
4. ✓ Indexes created on critical columns (author_channel_id, videoId, channel_id)
5. ✓ Five default tags pre-populated with codes, colors, descriptions

### Functionality (14 criteria)
6. ✓ urtubeapi URLs correctly parsed and validated
7. ✓ YouTube URLs (all four formats) correctly parsed and validated
8. ✓ YouTube page source parsed to extract channelId
9. ✓ Both URL types successfully import comments to database
10. ✓ Duplicate comments are detected and skipped on re-import
11. ✓ New channels trigger tag selection modal
12. ✓ Existing channels skip tag selection on re-import
13. ✓ Tag selection correctly saved to database
14. ✓ Channel list displays all imported channels with correct data
15. ✓ Statistics displayed accurately (added, updated, skipped counts)
16. ✓ Import results summary shows proper statistics
17. ✓ Modal can be cancelled to abort import
18. ✓ AJAX prevents page reload during import
19. ✓ Loading states displayed with spinner and status text

### User Interface (5 criteria)
20. ✓ All pages styled with Tailwind CSS
21. ✓ Import page displays clear layout and instructions
22. ✓ Channel list page responsive on desktop and tablet
23. ✓ Tag selection modal centered with overlay
24. ✓ Tags displayed as color-coded badges

### Error Handling (4 criteria)
25. ✓ All error scenarios handled with appropriate user messages
26. ✓ Error messages are user-friendly (no jargon)
27. ✓ Errors logged with detail for debugging
28. ✓ System does not crash on errors; gracefully handles failures

---

## Testing Strategy

**Unit Tests**
- URL parsing logic (all YouTube formats + urtubeapi)
- JSON validation logic
- Data transformation (API response → database models)
- Tag selection validation

**Integration Tests**
- End-to-end import flow with real urtubeapi response
- Database migrations and rollback
- Duplicate detection logic
- Channel detection (new vs. existing)
- Tag selection and database persistence
- Modal trigger conditions

**Manual Testing**
- Import from multiple urtubeapi URLs
- Import from multiple YouTube URL formats
- Verify data integrity in database post-import
- Test YouTube URL parsing with page source extraction
- Manual channel tag selection and persistence
- Channel list display accuracy
- Error scenario validation (network failures, invalid data)
- Responsive design on various screen sizes
- Modal animations and interactions

