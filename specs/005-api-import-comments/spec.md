# Feature Specification: YouTube API Comments Import

**Feature Branch**: `005-api-import-comments`
**Created**: 2025-11-16
**Status**: Draft
**Input**: User description: "新增：API導入留言的功能..."

## Clarifications

### Session 2025-11-16

- Q: "API 導入"頁面的具體流程是什麼，以及預覽時應導入多少留言？ → A: 流程為 1. 點「API 導入」進入頁面 → 2. 輸入影片URL → 3. 系統自動檢查存否並顯示預覽 → 4. 預覽時先測試性導入5則留言 → 5. 點「確認導入」開始完整 fetch
- Q: 預覽時的5則留言是否應保存到數據庫？ → A: 只在預覽中顯示，不儲存到數據庫；用戶確認「確認導入」後才儲存留言
- Q: 導入後應更新 channels 和 videos 表的哪些欄位？ → A:
  - **Channels**: video_count (新增時), comment_count, first_import_at, last_import_at, created_at (新增時), updated_at
  - **Videos**: video_id (新增時), channel_id (新頻道時), title (新影片時), published_at (新增時), created_at (新增時), updated_at (無論新舊都更新)
- Q: 使用者取消導入時應如何處理？ → A: 直接關閉對話框，不做任何操作；預覽留言不儲存，導入中斷直接停止。若導入已開始儲存但中斷，下次重新導入時由增量更新邏輯自動處理
- Q: 回複評論的導入深度應該多深？ → A: 導入所有層級的回複（評論 → 回複 → 回複的回複...等全部），完整保留討論上下文
- Q: 新影片的元數據獲取與標籤選擇流程應如何進行？ → A: 採用和「匯入」功能一樣的機制和介面。流程為：輸入 URL → 檢查 DB 存否 → 若新影片，調用現有「匯入」對話框 → 完整「匯入」流程（爬蟲、標籤選擇等） → 匯入完成後自動開始留言導入

---

## User Scenarios & Testing *(mandatory)*

<!--
  IMPORTANT: User stories should be PRIORITIZED as user journeys ordered by importance.
  Each user story/journey must be INDEPENDENTLY TESTABLE - meaning if you implement just ONE of them,
  you should still have a viable MVP (Minimum Viable Product) that delivers value.
  
  Assign priorities (P1, P2, P3, etc.) to each story, where P1 is the most critical.
  Think of each story as a standalone slice of functionality that can be:
  - Developed independently
  - Tested independently
  - Deployed independently
  - Demonstrated to users independently
-->

### User Story 1 - Import Comments for New Video (Priority: P1)

User has a YouTube video URL that has never been imported before. For a new video, the system first invokes the existing "匯入" (import video) dialog to capture metadata, channel info, and tags via web scraping. After video import completes, the system automatically proceeds to import comments using YouTube API.

**Why this priority**: This is the foundational use case. New videos require metadata capture (via existing import mechanism) before comments can be meaningfully stored. The feature integrates seamlessly with the existing import workflow.

**Independent Test**: Can be fully tested by entering a new video URL, completing the "匯入" dialog, and verifying all comments are imported with correct fields and relationships after the video import completes.

**Acceptance Scenarios**:

1. **Given** a video URL that doesn't exist in the database, **When** user enters the URL and the system checks existence, **Then** the existing "匯入" dialog is triggered for the user to complete video metadata entry (channel name, title, tags via web scraping)
2. **Given** user completes the "匯入" process and the video is saved to database, **When** the import dialog closes, **Then** the system automatically initiates comment preview showing the first 5 sample comments from YouTube API
3. **Given** preview is displayed with 5 sample comments, **When** user reviews the preview and clicks "確認導入", **Then** the system fetches all remaining comments via YouTube API and stores them in the database with all required fields populated correctly
4. **Given** the full comment import is in progress, **When** comments are being fetched, **Then** user receives visual feedback (progress indicator or status message)
5. **Given** comment import completes successfully, **When** user views the comments list, **Then** all imported comments are visible and sorted by date

---

### User Story 2 - Incremental Update for Existing Video (Priority: P1)

User has already imported a video before. They want to check for new comments added since the last import without downloading all previous comments again.

**Why this priority**: Essential for keeping data fresh without duplicating effort. Videos with ongoing discussion need regular updates, and re-importing everything would be inefficient.

**Independent Test**: Can be fully tested by importing a video twice with an interval, verifying that only new comments (those added after the last import timestamp) are fetched and stored.

**Acceptance Scenarios**:

1. **Given** a video URL that already exists in the database with previous comments, **When** user enters the same URL and the system checks existence, **Then** a preview is displayed showing up to 5 sample new comments (newer than the most recent stored comment)
2. **Given** preview is displayed with new comments available, **When** user clicks "確認導入", **Then** the system only fetches comments newer than the most recent comment already stored
3. **Given** comments are being fetched in chronological order (newest first), **When** a duplicate comment_id is encountered, **Then** the system stops fetching immediately (avoiding unnecessary API calls)
4. **Given** the incremental import finds new comments, **When** they are stored, **Then** they are merged with existing comments without creating duplicates
5. **Given** the import completes, **When** related reply comments exist, **Then** they are also stored in the database

---

### User Story 3 - Reply Comments Handling (Priority: P1)

When importing comments, if a comment has replies (nested comments at any depth), those replies should be automatically captured and stored alongside parent comments. The system should recursively import all levels of replies to preserve the complete discussion thread structure.

**Why this priority**: YouTube comments have a threading structure with multiple levels of replies, and missing replies means losing important context and discussion continuity. Complete thread preservation is essential for maintaining full discussion history.

**Independent Test**: Can be fully tested by importing a video with multi-level reply comments (replies to replies) and verifying that all comment levels are stored correctly with proper parent-child relationships maintained.

**Acceptance Scenarios**:

1. **Given** a comment has replies at multiple levels from the YouTube API, **When** the parent comment is imported, **Then** all reply comments at all levels are also fetched and stored in the database
2. **Given** multi-level reply comments are stored, **When** they are queried, **Then** each maintains the correct relationship to its parent comment (via parent_comment_id or equivalent)

---

### User Story 4 - UI Integration (Priority: P2)

The "API 導入" button appears alongside existing "匯入" button in the comments interface, providing unified access to video and comment import functionality. The workflow seamlessly integrates with the existing "匯入" mechanism for new videos.

**Why this priority**: UX clarity is important but doesn't block core functionality. Users need to discover the feature easily, and reusing existing "匯入" interface ensures consistency.

**Independent Test**: Can be fully tested by verifying the button exists, is properly positioned, and that both new and existing video flows work correctly with appropriate dialogs appearing.

**Acceptance Scenarios**:

1. **Given** the comments list page is open, **When** user looks at the interface, **Then** they see both "API 導入" and "匯入" buttons positioned on the right side
2. **Given** user clicks "API 導入" button, **When** the action is triggered, **Then** a new import modal/page is presented with a form field for entering the video URL
3. **Given** user enters a new video URL and system detects it doesn't exist, **When** URL validation completes, **Then** the existing "匯入" dialog is automatically invoked for metadata entry
4. **Given** user completes the "匯入" process, **When** video is saved, **Then** the system automatically displays the comment preview with up to 5 sample comments
5. **Given** user enters an existing video URL, **When** URL validation completes, **Then** the comment preview is directly displayed with up to 5 sample comments
6. **Given** preview is displayed, **When** user reviews the sample data, **Then** a "確認導入" button is available to proceed with full comment import

### Edge Cases

- What happens when the YouTube API returns an error during preview fetch (invalid API key, quota exceeded, video not found)?
- What if the video has fewer than 5 comments available? (Should preview show all available comments)
- How does the system handle if preview fetch succeeds but full import fails after user clicks "確認導入"?
- How does the system handle partially imported comments if the process is interrupted mid-way during full import? (Incremental update logic will skip duplicates on next attempt)
- What if a video URL is malformed or invalid?
- What if the database already has comments for a video but they were imported from a different source?
- What if a video has no new comments when doing incremental update? (Should still show preview with 0 new comments message)
- What happens if user closes the import modal/page during preview or after clicking "確認導入"? (Dialog closes without action; import may continue in background if already started, will be handled on next import attempt)
- What if user cancels the "匯入" dialog after entering a new video URL? (Original API import dialog closes; user returns to comment import modal, can try again or cancel)
- What if the existing "匯入" dialog fails when importing a new video? (Error handling depends on "匯入" feature; comment import flow should not proceed)

## Requirements *(mandatory)*

<!--
  ACTION REQUIRED: The content in this section represents placeholders.
  Fill them out with the right functional requirements.
-->

### Functional Requirements

- **FR-001**: System MUST read YouTube API key from environment configuration (.env file)
- **FR-002**: System MUST accept a YouTube video URL as input from the user
- **FR-003**: System MUST validate whether a video already exists in the database before processing
- **FR-004**: System MUST invoke the existing "匯入" (import video) dialog when a new (non-existent) video URL is detected, allowing user to complete video metadata entry (channel name, title, tags selection via web scraping)
- **FR-005**: System MUST automatically initiate comment preview after video import completes, displaying up to 5 sample comments from YouTube API without persisting to database
- **FR-006**: System MUST display preview comments on the import form, allowing user to review sample data without persisting to database
- **FR-007**: System MUST provide a "確認導入" button that triggers the full import process after user reviews the preview
- **FR-008**: System MUST fetch all comments from YouTube API when user clicks "確認導入" (for both new and existing videos)
- **FR-009**: System MUST store all imported comments in the database with correct field mapping (matching existing comments table schema)
- **FR-010**: System MUST recursively fetch and store all levels of reply/nested comments from the `replies.comments` section and their replies (replies to replies, etc.) to fully preserve the discussion thread structure
- **FR-011**: System MUST identify the most recent comment timestamp in the database for existing videos to determine the incremental import starting point
- **FR-012**: System MUST fetch comments in reverse chronological order (newest first) when performing incremental updates
- **FR-013**: System MUST track the comment_id of the most recent imported comment to detect duplicates during incremental import
- **FR-014**: System MUST stop the import process immediately upon encountering a duplicate comment_id (already exists in database)
- **FR-015**: System MUST stop the import process when reaching comments older than the most recent stored comment timestamp (fallback safety measure)
- **FR-016**: System MUST update the related database records in `channels` and `videos` tables after importing comments with the following rules:
  - **Channels table**: Update `comment_count`, `last_import_at`, `updated_at` for all imports; Initialize `video_count`, `first_import_at`, `created_at` only for new channels
  - **Videos table**: Always update `updated_at`; Initialize `video_id`, `title`, `published_at`, `created_at` for new videos; Set `channel_id` only when importing from a new channel
- **FR-017**: System MUST display an "API 導入" button alongside the existing "匯入" button in the comments interface
- **FR-018**: System MUST provide user feedback during the import process (e.g., progress indication, success/failure messages)
- **FR-019**: System MUST validate that all required comment fields are populated correctly before storing in the database

### Key Entities *(include if feature involves data)*

- **Video**: Represents a YouTube video; identified by video_id (YouTube's unique ID); key attributes: channel_id, title, published_at, created_at (when first imported), updated_at (always refreshed after import)
- **Comment**: Represents a single YouTube comment; contains author info, text content, timestamp, comment_id (YouTube's unique ID), parent comment reference (for replies), with full field mapping to match existing schema
- **Reply Comment**: A comment that is a child/reply to another comment; maintains reference to parent comment via parent_comment_id or thread_id
- **Channel**: Represents a YouTube channel; key attributes: video_count (incremented for new videos), comment_count (updated after import), first_import_at (set on first import), last_import_at (updated on each import), created_at (set on first import), updated_at (always refreshed after import)

## Success Criteria *(mandatory)*

<!--
  ACTION REQUIRED: Define measurable success criteria.
  These must be technology-agnostic and measurable.
-->

### Measurable Outcomes

- **SC-001**: Users can import all comments from a new video within 30 seconds (including API fetch time for typical videos)
- **SC-002**: Users can update comments for an existing video in under 10 seconds for incremental imports (fetching only new comments)
- **SC-003**: 100% of imported comments are stored with correct database field mappings, with no data loss or corruption
- **SC-004**: System correctly handles 99% of incremental import scenarios without creating duplicate records
- **SC-005**: All reply comments are captured and linked to their parent comments with 100% accuracy
- **SC-006**: Users receive clear confirmation of successful imports and actionable error messages if imports fail

---

## Assumptions

1. YouTube API credentials are properly configured in the .env file and valid
2. User has the necessary YouTube API quota available for fetching comments
3. The existing database schema for comments, videos, and channels tables is understood and documented elsewhere
4. Comment field mapping between YouTube API response and database schema is straightforward (documented separately)
5. Users will only attempt to import public videos where comments are enabled
6. The system has existing infrastructure for handling YouTube API errors and rate limiting
7. "Order by time" descending in YouTube API returns newest comments first (chronologically newest)
