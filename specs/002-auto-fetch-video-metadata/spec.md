# Feature Specification: Auto-Fetch Video Metadata with Two-Step Confirmation (Incremental)

**Feature Branch**: `002-auto-fetch-video-metadata`
**Created**: 2025-11-16
**Status**: Draft
**Input**: Enhancement to 001-comment-import - Add automatic video metadata scraping and two-step confirmation before database write
**Depends On**: 001-comment-import (database schema, URL parsing, API integration)

---

## Project Context

This is an **incremental feature** that enhances the existing 001-comment-import system with:

1. **Automatic Video Metadata Scraping**: When a YouTube URL is provided, system scrapes the YouTube page to extract:
   - Video title (from `<meta property="og:title">` or similar)
   - Channel name/uploader (from `<meta property="og:video:tag">` or channel info)

2. **Two-Step Confirmation Flow**: Before writing data to database:
   - Step 1: Show confirmation interface with extracted metadata + comment count + tag selection (if new channel)
   - Step 2: User reviews and confirms or cancels
   - Only after confirmation → database write

3. **Prevention of Incomplete Data**: Avoid writing invalid or incomplete data by requiring explicit user confirmation with all extracted information visible.

**What's NOT changed**:
- Database schema (already exists from 001-comment-import)
- URL parsing logic (already exists)
- API data fetching (already exists)
- Channel detection logic (already exists)
- Tag selection mechanics (already exists)
- Channel list view (already exists)

---

## User Scenarios & Testing *(mandatory)*

### User Story US-002-01: Scrape Video Metadata from YouTube Page (Priority: P1)

**Actor**: System (automatic during import)

**Scenario**: When a YouTube URL is provided during import, the system automatically fetches the YouTube page and scrapes video title and channel name without user interaction.

**Why this priority**: Prerequisite for confirmation interface; ensures metadata is available before user review.

**Independent Test**: Provide a valid YouTube URL, verify:
1. Page is fetched successfully (not from cache)
2. Video title is correctly extracted
3. Channel name is correctly extracted
4. Extraction handles edge cases (missing fields, special characters, URL encoding)

**Acceptance Scenarios**:

1. **Given** YouTube URL provided during import, **When** system fetches the YouTube page, **Then** page content is retrieved via HTTP request (not cached)
2. **Given** page content retrieved, **When** system scrapes metadata, **Then** video title is correctly extracted from page HTML/metadata
3. **Given** page content retrieved, **When** system scrapes metadata, **Then** channel name (uploader) is correctly extracted from page HTML/metadata
4. **Given** metadata scraping completes, **When** system displays confirmation interface, **Then** extracted title and channel name are clearly visible
5. **Given** title contains special characters (emoji, non-ASCII), **When** metadata is scraped, **Then** characters are preserved without corruption
6. **Given** YouTube page is inaccessible, **When** metadata scraping fails, **Then** error message displays but user can continue import with API data
7. **Given** metadata scraping timeout exceeds 10 seconds, **When** system proceeds, **Then** shows timeout message and uses API data instead

---

### User Story US-002-02: Display Confirmation Interface with Scraped Metadata (Priority: P1)

**Actor**: System user (administrator)

**Scenario**: After metadata is scraped and API data is fetched, system displays a confirmation interface showing all extracted data before writing to database. User reviews and confirms or cancels.

**Why this priority**: Core safety feature preventing incomplete/invalid data insertion. Gives user control over data import.

**Independent Test**: Complete metadata scraping and API fetch, verify:
1. Confirmation interface displays with all required fields
2. Data matches extracted metadata
3. All interactive elements function correctly
4. Cancellation works without database changes

**Acceptance Scenarios**:

1. **Given** metadata scraped and API data fetched, **When** import process completes, **Then** confirmation interface displays with modal overlay
2. **Given** confirmation interface displayed, **When** user views the interface, **Then** shows:
   - 📹 Video Title (extracted from YouTube page)
   - 📺 Channel Name (extracted from YouTube page)
   - 💬 Comment Count (from urtubeapi response)
3. **Given** new channel detected, **When** confirmation interface displays, **Then** tag selection checkboxes appear below metadata
4. **Given** existing channel, **When** confirmation interface displays, **Then** tag selection is NOT shown (skip tagging for existing)
5. **Given** confirmation interface shown, **When** user reviews data, **Then** formatting is clear with icons and proper spacing
6. **Given** new channel with no tags selected, **When** user sees confirmation interface, **Then** "確認並寫入資料" button is disabled (grayed out) with tooltip "請選擇至少一個標籤"
7. **Given** new channel with at least one tag selected, **When** user sees confirmation interface, **Then** "確認並寫入資料" button is enabled
8. **Given** user clicks "確認並寫入資料", **When** system processes, **Then** all data (video, comments, authors, tags) are written to database
9. **Given** user clicks "取消匯入", **When** system processes, **Then** NO data is written to database and modal closes
10. **Given** import cancelled, **When** user returns to import page, **Then** import form is reset and ready for new URL

---

### User Story US-002-03: Ensure Data Atomicity (No Partial Writes) (Priority: P1)

**Actor**: System (automatic transaction management)

**Scenario**: Ensure that either all data is written to database (video, comments, authors, tags) or nothing is written. Partial writes are prevented through database transactions.

**Why this priority**: Data integrity critical; prevents orphaned records and inconsistent state.

**Independent Test**: Simulate failures during database write, verify:
1. All-or-nothing behavior is enforced
2. No orphaned records exist
3. Transaction rollback works correctly

**Acceptance Scenarios**:

1. **Given** confirmation interface and user confirms, **When** system begins database write, **Then** entire write operation is wrapped in database transaction
2. **Given** transaction in progress, **When** video insert succeeds but comment insert fails, **Then** entire transaction rolls back (no partial writes)
3. **Given** transaction in progress, **When** tag insert fails, **Then** entire transaction rolls back (video and comments NOT written)
4. **Given** transaction completes successfully, **When** import finishes, **Then** all data (video, comments, authors, tags) are persisted atomically
5. **Given** database error occurs during write, **When** transaction rolls back, **Then** error message displays to user: "系統錯誤，請聯繫管理員"

---

## Requirements *(mandatory)*

### Functional Requirements

**Video Metadata Scraping**

- **FR-012a**: System MUST scrape YouTube page to extract video title when YouTube URL is provided
- **FR-012b**: System MUST scrape YouTube page to extract channel name (uploader) when YouTube URL is provided
- **FR-012c**: System MUST display scraped video title and channel name in confirmation interface before data is written
- **FR-012d**: System MUST use web scraping library (e.g., Goutte + DomCrawler) to parse YouTube HTML/metadata
- **FR-012e**: System MUST handle scraping failures gracefully (timeout, network error, parsing error) with clear user message
- **FR-012e1**: System MUST implement timeout of 10 seconds for YouTube page fetch; if exceeded, proceed with API data and show message
- **FR-012e2**: System MUST catch network exceptions and display "無法抓取影片資訊" message without blocking import
- **FR-012e3**: System MUST allow partial metadata (e.g., title extracted but channel name missing) to proceed to confirmation interface

**Confirmation Interface Display**

- **FR-031a**: System MUST display confirmation interface after metadata scraping and API data fetch complete
- **FR-031b**: Confirmation interface MUST show extracted video metadata: Title, Channel Name, Comment Count
- **FR-031c**: Confirmation interface MUST include tag selection checkboxes if new channel is detected
- **FR-031d**: Confirmation interface MUST provide "確認並寫入資料" button to proceed with database write (disabled if tags required but not selected)
- **FR-031e**: Confirmation interface MUST provide "取消匯入" button to abort import without writing any data
- **FR-031f**: Confirmation interface MUST display clearly formatted data review section with icons and proper spacing
- **FR-031g**: When tags are required but not selected, "確認並寫入資料" button MUST be visually disabled (grayed out) and show tooltip "請選擇至少一個標籤"
- **FR-031h**: Confirmation interface MUST use modal overlay with 40-60% width on desktop, centered on screen
- **FR-031i**: Confirmation interface MUST fade in with 0.2-0.3 second animation when displayed
- **FR-031j**: Modal background MUST be semi-transparent (Tailwind `bg-black/50` or similar)

**Database Write Operation**

- **FR-012f**: System MUST NOT write any data to database until user confirms in confirmation interface
- **FR-012g**: System MUST wrap all database write operations (video, comments, authors, tags) in a single transaction
- **FR-012h**: System MUST provide "確認並寫入資料" button to proceed with database write after confirmation
- **FR-012i**: System MUST provide "取消匯入" button to cancel import without writing any data
- **FR-012j**: If tag selection is required (new channel), system MUST require tags to be selected before "確認並寫入資料" is enabled
- **FR-012k**: If "取消匯入" is clicked, system MUST discard all extracted data and return to import page without database changes

**User Interface**

- **FR-030**: System MUST update loading indicator status text to include "正在抓取影片資訊..." (fetching video info) during metadata scraping phase
- **FR-034**: System MUST provide "確認並寫入資料" and "取消匯入" buttons in confirmation interface (replacing old "確認並繼續匯入")

---

### Key Entities *(no changes from 001-comment-import)*

All entities remain unchanged:
- **Video**: YouTube video (videoId, title, channel_id, channel_name, youtube_url, published_at)
- **Comment**: YouTube comment (comment_id, video_id, author_channel_id, text, like_count, published_at)
- **Author**: YouTube channel author (author_channel_id, name, profile_url)
- **Channel**: YouTube channel (channel_id, channel_name, video_count, comment_count, first_import_at, last_import_at)
- **Tag**: Political stance label (tag_id, code, name, description, color)
- **ChannelTag**: Junction table (channel_id, tag_id)

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

**Video Metadata Scraping**
- **SC-009a**: Video title is correctly scraped from YouTube page and displayed in confirmation interface
- **SC-009b**: Channel name (uploader) is correctly scraped from YouTube page and displayed in confirmation interface
- **SC-009c**: Scraped metadata matches actual YouTube page content with 100% accuracy (spot check 10+ videos)
- **SC-009d**: Scraping failures are handled gracefully with user-friendly error messages (no data written on scraping failure)
- **SC-009e1**: Timeout of 10 seconds is enforced; if exceeded, system proceeds with API data and shows message
- **SC-009e2**: Network errors are caught and displayed without blocking import workflow

**Confirmation Interface**
- **SC-009e**: Confirmation interface displays after metadata scraping and API data fetch complete
- **SC-009f**: Confirmation interface shows Video Title, Channel Name, and Comment Count before database write
- **SC-009g**: NO data is written to database until user clicks "確認並寫入資料" button
- **SC-009h**: Clicking "取消匯入" button cancels import without writing any data to database
- **SC-009i**: New channel detection triggers tag selection in confirmation interface
- **SC-009j**: "確認並寫入資料" button is disabled (visually grayed out) if tags are required but not selected
- **SC-009k**: Tag selection is properly persisted to database when user confirms in confirmation interface

**Data Atomicity**
- **SC-009l**: Database transaction wraps all write operations (video, comments, authors, tags)
- **SC-009m**: If any insert fails, entire transaction rolls back with no partial writes
- **SC-009n**: Successful transaction persists all data atomically in single operation
- **SC-009o**: No orphaned records exist after rollback

---

## Assumptions

- **Scraping Library**: Will use Goutte or similar PHP HTTP client with DomCrawler for HTML parsing
- **YouTube Page Structure**: YouTube page HTML structure assumed stable; if structure changes, scraping logic may need updates
- **Timeout Handling**: 10-second timeout for YouTube page fetch is acceptable; if exceeded, system proceeds with API data
- **Partial Metadata**: If either title or channel name cannot be scraped, system proceeds with available data
- **Transaction Support**: Database supports transactions (SQLite, MySQL, PostgreSQL all support)
- **UI Framework**: Tailwind CSS used for all styling; modal overlay and animations built with CSS

---

## Error Handling Catalog

### Video Metadata Scraping Errors

| Scenario | User Message | Action |
|----------|--------------|--------|
| Cannot extract video title from page | "無法取得影片標題，但您可以繼續確認並匯入資料" | Show warning in confirmation interface, allow continuation |
| Cannot extract channel name from page | "無法取得頻道名稱，但您可以繼續確認並匯入資料" | Show warning in confirmation interface, allow continuation |
| Scraping timeout (> 10 seconds) | "抓取影片資訊逾時，請稍後再試" | Use API data, show message in confirmation interface |
| Scraping network error | "無法抓取影片資訊，請檢查網路連線或稍後再試" | Use API data, show message in confirmation interface |

### Confirmation Interface Errors

| Scenario | User Message | Action |
|----------|--------------|--------|
| No tags selected when required | "請至少選擇一個標籤" (displayed in red, button disabled) | Prevent database write until tags selected |
| User clicks "取消匯入" | Import cancelled | Close modal, return to import form, discard all extracted data |
| Database write fails | "系統錯誤，請聯繫管理員" | Rollback transaction, show error message |

---

## Out of Scope

- Caching of scraped metadata across imports
- Multiple URL batch import
- Advanced metadata extraction (publish date, video duration, etc.)
- Handling YouTube's JavaScript-rendered content (assumes static HTML is sufficient)
- Re-scraping metadata for existing videos on re-import

---

## Performance Targets

- YouTube page fetch: 3-5 seconds (including network latency)
- Metadata scraping: 1-2 seconds
- Confirmation interface display: < 0.5 seconds
- Database transaction (video + comments + authors + tags): < 5 seconds for 500 comments
- Modal fade-in animation: 0.2-0.3 seconds

---

## Testing Strategy

**Unit Tests**
- YouTube page scraping logic (title and channel extraction from HTML)
- Metadata parsing with edge cases (missing fields, special characters, URL encoding)
- Timeout handling for page fetch
- Transaction rollback simulation

**Integration Tests**
- End-to-end flow: YouTube URL → metadata scraping → confirmation interface display → user confirmation → database write
- Confirmation interface trigger conditions (new vs. existing channel)
- Database transaction atomicity (success and failure cases)
- Cancellation flow verification (no database writes)
- Tag selection in confirmation interface

**Manual Testing**
- Scrape metadata from 10+ diverse YouTube videos
- Verify title and channel name accuracy
- Test timeout behavior (simulate slow network)
- Test network error handling
- Verify confirmation interface displays correct data
- Test "確認並寫入資料" and "取消匯入" button functionality
- Verify database contains no partial data after cancellation
- Test tag selection and persistence in confirmation interface
- Responsive design on desktop and tablet
- Modal animation and visual state

---

## Success Completion Criteria

Feature is complete when:

### Functionality (10 criteria)
1. ✓ YouTube page is fetched and parsed successfully
2. ✓ Video title is accurately scraped from page
3. ✓ Channel name (uploader) is accurately scraped from page
4. ✓ Confirmation interface displays after metadata scraping
5. ✓ Confirmation interface shows Title, Channel Name, Comment Count
6. ✓ NO data written to database until user confirms
7. ✓ Clicking "確認並寫入資料" triggers database write with tag persistence
8. ✓ Clicking "取消匯入" cancels import without database write
9. ✓ Database transaction ensures all-or-nothing write behavior
10. ✓ Timeout and error handling work gracefully

### User Interface (3 criteria)
11. ✓ Confirmation interface styled with Tailwind CSS, centered modal with overlay
12. ✓ Loading status text includes "正在抓取影片資訊..." during scraping phase
13. ✓ "確認並寫入資料" button disabled when tags required but not selected

### Error Handling (3 criteria)
14. ✓ Metadata scraping failures handled gracefully (show warning, allow continuation)
15. ✓ Network errors display user-friendly messages
16. ✓ Database errors trigger transaction rollback with error message

