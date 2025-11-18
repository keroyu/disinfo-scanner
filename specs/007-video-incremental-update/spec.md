# Feature Specification: Video Incremental Update

**Feature Branch**: `007-video-incremental-update`
**Created**: 2025-11-18
**Status**: Draft
**Input**: User description: "新增：更新影片功能

1. 把Videos List的Video Title只截取 前15個中文字（後面用...省略）
2. 標題的右邊加上按鈕「更新」，按下該按鈕後彈出 modal 視窗（樣式參考其他modal）
3. modal 視窗功能：
- 先確認資料庫中該影片的**最後留言時間**
- 呼叫官方YouTube API進行**增量更新**，**只導入該時間之後的留言**(需要評估之前完整導入的Y-API適用與否，程式檔要新增或修改)
- 預覽樣式參考其他的modal。顯示**剩下多少則留言**需要導入
- 預覽內容是即將要導入的前5則留言（按時間順序），如資料庫中最後一則留言為 2025/11/5 15:00，則預覽的第1,2則留言的留言時間可能是 2025/11/5 16:00, 2025/11/5 17:30 ....
4. 按「確認更新」後，開始進行完整的**增量更新**，寫入對應的DB，注意要更新 videos.comment_count"

## Clarifications

### Session 2025-11-18

This feature extends the existing video import functionality by adding incremental update capability. It allows users to update existing videos with only new comments that appeared after the last import, avoiding duplicate imports and saving API quota.

- Q: When a user triggers an update while another update is already in progress for the same video, how should the system behave? → A: Allow both updates but prevent duplicates with idempotent inserts (skip if comment_id exists)
- Q: When a video has thousands of new comments available, how should the system handle potential timeout issues? → A: Set hard limit of 500 comments per update; notify user if more exist and require multiple update clicks

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View Update Button and Trigger Incremental Update (Priority: P1)

As a content analyst, I need to see an "Update" button next to each video title in the Videos List, so I can refresh a video's comment data when I know new comments have been posted.

**Why this priority**: This is the core entry point for the incremental update feature. Without the button and modal trigger, users cannot access the update functionality.

**Independent Test**: Can be fully tested by navigating to the Videos List page, verifying that each video row displays an "Update" button, and clicking it opens a modal window.

**Acceptance Scenarios**:

1. **Given** I am viewing the Videos List page, **When** I look at any video row, **Then** I see an "Update" button positioned to the right of the video title
2. **Given** I am viewing a video row, **When** I click the "Update" button, **Then** a modal window opens displaying update options for that specific video
3. **Given** the update modal is open, **When** I click outside the modal or press ESC, **Then** the modal closes without making any changes
4. **Given** the update modal is open, **When** I view the modal header, **Then** I see the video title displayed clearly to identify which video I'm updating

---

### User Story 2 - Preview New Comments Before Importing (Priority: P1)

As a content analyst, I need to see a preview of new comments (the first 5 in chronological order) and the total count of new comments available, so I can verify there is new content worth importing before triggering the full update.

**Why this priority**: This is the core value proposition of the feature - showing users what new content exists before they commit to importing it. The preview prevents wasted API calls and gives users confidence about what they're importing.

**Independent Test**: Can be tested by opening the update modal for a video that has new comments on YouTube, and verifying the modal displays the correct count of new comments and shows a preview of the 5 most recent new comments in chronological order.

**Acceptance Scenarios**:

1. **Given** I opened the update modal for a video, **When** the system queries the database, **Then** it retrieves the last comment timestamp for that video from the comments table
2. **Given** the system knows the last comment timestamp (e.g., 2025/11/5 15:00), **When** the system calls the YouTube API, **Then** it requests only comments published after that timestamp
3. **Given** the YouTube API returns new comments, **When** the modal displays the preview, **Then** I see the total count of new comments available for import (e.g., "剩下 42 則留言需要導入")
4. **Given** there are new comments available, **When** I view the preview section, **Then** I see the first 5 new comments in chronological order (oldest to newest), each showing: author ID, comment text (truncated if long), like count, and published timestamp
5. **Given** the preview shows comment timestamps, **When** the last comment in the database was 2025/11/5 15:00, **Then** the first preview comment shows a timestamp after 15:00 (e.g., 2025/11/5 16:00), and subsequent preview comments show progressively later timestamps (e.g., 2025/11/5 17:30)
6. **Given** there are no new comments, **When** the modal loads, **Then** I see a message stating "No new comments to import" and the "Confirm Update" button is disabled
7. **Given** the YouTube API call fails, **When** fetching preview comments, **Then** I see an error message and a "Retry" button to attempt the fetch again

---

### User Story 3 - Confirm and Execute Incremental Import (Priority: P1)

As a content analyst, I need to click "Confirm Update" to import all new comments into the database and update the video's comment count, so the system's data stays current with YouTube's actual comment activity.

**Why this priority**: This completes the incremental update workflow by actually persisting the new data. Without this, the preview alone provides no lasting value.

**Independent Test**: Can be tested by confirming an update in the modal, then verifying that: (1) new comments appear in the Comments List for that video, (2) the video's comment count increases by the correct amount, and (3) the "last comment time" in Videos List reflects the newest comment.

**Acceptance Scenarios**:

1. **Given** I have reviewed the preview of new comments, **When** I click the "Confirm Update" button, **Then** the system calls the YouTube API to fetch all new comments (not just the 5 preview comments) published after the last comment timestamp
2. **Given** the system is fetching all new comments, **When** the import is in progress, **Then** I see a loading indicator (spinner) and a message like "正在導入留言，請稍候..."
3. **Given** the YouTube API returns all new comments, **When** the system processes them, **Then** each new comment is inserted into the comments table with all required fields: comment_id, video_id, author_channel_id, text, like_count, published_at, parent_comment_id (if it's a reply)
4. **Given** all new comments have been inserted, **When** the import completes, **Then** the system recalculates the video's total comment count and updates both the videos.comment_count field and videos.updated_at timestamp
5. **Given** the import completed successfully, **When** the process finishes, **Then** the modal displays a success message showing the number of comments imported (e.g., "成功導入 42 則留言")
6. **Given** the success message is displayed, **When** I close the modal, **Then** the Videos List page updates to reflect the new comment count and last comment time without requiring a manual page refresh
7. **Given** the import encounters an error (API failure, network issue, etc.), **When** the error occurs, **Then** I see an error message explaining what went wrong and a "Retry" button to attempt the import again

---

### User Story 4 - Truncate Video Titles to 15 Chinese Characters (Priority: P2)

As a content analyst viewing the Videos List, I need video titles truncated to 15 Chinese characters with an ellipsis, so the table layout remains clean and readable even with long video titles.

**Why this priority**: This is a UI improvement that enhances readability but doesn't affect core functionality. The feature works without it, but the user experience is better with proper title truncation.

**Independent Test**: Can be tested by viewing the Videos List and verifying that all video titles display at most 15 Chinese characters followed by "..." if the original title is longer.

**Acceptance Scenarios**:

1. **Given** I am viewing the Videos List, **When** a video has a title with 15 or fewer Chinese characters, **Then** the full title is displayed without truncation
2. **Given** I am viewing the Videos List, **When** a video has a title with more than 15 Chinese characters, **Then** only the first 15 characters are displayed, followed by "..." (ellipsis)
3. **Given** a video title is truncated, **When** I hover over the title, **Then** a tooltip displays the full, untruncated video title
4. **Given** the title is truncated, **When** I click on it, **Then** it still functions as a link to the Comments List with the video title filter applied (using the full, untruncated title)

---

### Edge Cases

- What happens when a video has no existing comments in the database (last_comment_time is null)?
  - The system should treat this as a "first import" scenario and fetch all comments from the video's publication date, not incremental update
- What happens when the YouTube API returns no new comments (the database is already up-to-date)?
  - Display message "No new comments to import" and disable the "Confirm Update" button
- What happens when the YouTube API rate limit is exceeded during preview or import?
  - Display error message explaining the rate limit issue with timestamp when quota may reset
- What happens when a video was deleted from YouTube but still exists in our database?
  - Display error message "Video not found on YouTube" and suggest removing it from the database
- What happens when new comments include replies (nested comments) with parent_comment_id?
  - The system must correctly identify and import replies with their parent_comment_id preserved, just like the initial import
- What happens when a user tries to update the same video twice simultaneously (race condition)?
  - Both updates are allowed to proceed; the system uses idempotent inserts that check if comment_id already exists before inserting, automatically skipping duplicates without error
- What happens when importing thousands of new comments (very large incremental update)?
  - System enforces a hard limit of 500 comments per update operation; if more than 500 new comments exist, import the first 500 chronologically and display message: "成功導入 500 則留言。還有 X 則新留言可用，請再次點擊更新按鈕繼續導入。"
- What happens when the modal is open and the user navigates away from the page?
  - The modal should close automatically, and no partial import should occur

## Requirements *(mandatory)*

### Functional Requirements

#### UI Display Requirements

- **FR-001**: System MUST display video titles in the Videos List truncated to exactly 15 Chinese characters (or full title if 15 or fewer characters)
- **FR-002**: System MUST append "..." (ellipsis) to truncated video titles to indicate truncation
- **FR-003**: System MUST display a full, untruncated video title in a tooltip when users hover over a truncated title
- **FR-004**: System MUST display an "Update" button to the right of each video title in the Videos List table
- **FR-005**: "Update" button MUST be styled consistently with other action buttons in the application

#### Modal Interaction Requirements

- **FR-006**: System MUST open a modal window when the "Update" button is clicked
- **FR-007**: Modal MUST be styled consistently with existing modals in the application (matching import-modal.blade.php styling)
- **FR-008**: Modal MUST display the video title in the header to identify which video is being updated
- **FR-009**: Modal MUST support closing via ESC key or close button (×) without making any changes
- **FR-010**: Modal MUST support closing by clicking outside the modal area (on the backdrop)

#### Last Comment Detection Requirements

- **FR-011**: System MUST query the comments table to find the most recent comment timestamp (MAX(published_at)) for the target video
- **FR-012**: System MUST use the last comment timestamp as the starting point for incremental update queries
- **FR-013**: If no comments exist for the video (last_comment_time is null), system MUST treat this as a full import scenario

#### YouTube API Integration Requirements

- **FR-014**: System MUST call the YouTube Data API v3 with a publishedAfter parameter set to the last comment timestamp
- **FR-015**: System MUST reuse existing YouTube API service (YouTubeApiService) for API calls
- **FR-016**: System MUST handle YouTube API errors gracefully with clear error messages and retry options
- **FR-017**: API calls MUST include proper authentication using the YOUTUBE_API_KEY from configuration

#### Preview Display Requirements

- **FR-018**: Modal MUST display the total count of new comments available for import (e.g., "剩下 42 則留言需要導入")
- **FR-019**: Modal MUST display a preview of the first 5 new comments in chronological order (oldest to newest)
- **FR-020**: Each preview comment MUST display: author channel ID, comment text (truncated to 150 characters), like count, and published timestamp
- **FR-021**: Preview comments MUST be sorted by published_at in ascending order (earliest new comment first)
- **FR-022**: If there are no new comments, modal MUST display "No new comments to import" and disable the "Confirm Update" button
- **FR-023**: Preview MUST visually distinguish between top-level comments and reply comments (similar to existing preview styling)

#### Import Execution Requirements

- **FR-024**: System MUST fetch all new comments (not just the 5 preview comments) when user clicks "Confirm Update"
- **FR-024a**: System MUST enforce a maximum limit of 500 comments per single update operation to prevent timeout issues
- **FR-024b**: If more than 500 new comments are available, system MUST import only the first 500 in chronological order (oldest first) and notify user of remaining count
- **FR-025**: System MUST display a loading indicator during the import process
- **FR-026**: System MUST insert each new comment into the comments table with all required fields populated
- **FR-027**: System MUST handle reply comments by correctly setting parent_comment_id
- **FR-028**: System MUST avoid duplicate imports by checking if comment_id already exists before inserting
- **FR-029**: System MUST recalculate the video's total comment count after import completes
- **FR-030**: System MUST update the videos.comment_count field with the accurate total count
- **FR-030a**: System MUST update the videos.updated_at timestamp to reflect when the incremental import was completed
- **FR-031**: System MUST display a success message showing the number of imported comments (e.g., "成功導入 42 則留言")

#### Post-Import Behavior Requirements

- **FR-032**: System MUST refresh the Videos List data after successful import without requiring full page reload
- **FR-033**: Updated comment count MUST be reflected immediately in the Videos List table
- **FR-034**: Updated last_comment_time MUST be reflected immediately in the Videos List table
- **FR-035**: Modal MUST remain open showing the success message until user manually closes it

#### Error Handling Requirements

- **FR-036**: System MUST display clear error messages when YouTube API calls fail
- **FR-037**: System MUST provide a "Retry" button when errors occur, allowing users to retry the operation
- **FR-038**: System MUST handle rate limit errors with specific messaging about quota limits
- **FR-039**: System MUST handle "video not found" errors when videos are deleted from YouTube

### Key Entities

- **Video**: Represents a YouTube video in the system; key attributes include video_id (primary key), channel_id, title, published_at, comment_count, updated_at. The comment_count and updated_at fields must be updated after each incremental import to reflect the latest import operation. Related to Comments (one video has many comments).

- **Comment**: Represents a comment on a video; key attributes include comment_id (primary key), video_id, author_channel_id, text, like_count, published_at, parent_comment_id (for replies). The published_at field is critical for incremental updates as it determines the "last comment timestamp". Related to Video (many comments belong to one video).

- **Incremental Update Session**: Represents a single update operation; includes: target video, last comment timestamp (query baseline), preview comments (first 5), total new comment count, import status, and final imported count.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can trigger an incremental update from the Videos List page in under 5 seconds (click Update button → see preview modal)
- **SC-002**: Preview modal loads and displays new comment count and first 5 preview comments within 3 seconds of opening
- **SC-003**: System accurately identifies the last comment timestamp and only fetches comments published after that time (0 duplicate imports)
- **SC-004**: Users can see exactly how many new comments are available before deciding to import them
- **SC-005**: Incremental import completes within 60 seconds for up to 500 new comments (the maximum per update operation)
- **SC-006**: After import completes, the videos.comment_count field accurately reflects the total number of comments (verified by COUNT query)
- **SC-007**: After import completes, the Videos List immediately reflects updated comment count and last comment time without manual refresh
- **SC-008**: Video titles in the Videos List are consistently truncated to 15 Chinese characters (or less if title is shorter) with no layout overflow issues
- **SC-009**: 100% of new comments (including nested replies) are correctly imported with proper parent_comment_id relationships preserved
- **SC-010**: Users receive clear feedback at each stage: preview loading, import in progress, success/error messages

## Assumptions

- The existing YouTubeApiService can be extended or modified to support filtering comments by publishedAfter parameter
- The comments table already has a published_at column with proper datetime indexing for efficient MAX() queries
- The videos table has both comment_count and updated_at fields (comment_count added in spec 005-api-import-comments, updated_at is a standard Laravel timestamp field)
- Users have sufficient YouTube API quota to perform incremental updates
- The modal component can be created based on existing modal templates (import-modal.blade.php and uapi-import-modal.blade.php)
- JavaScript on the Videos List page can dynamically update table data after import without full page reload
- Chinese character counting for title truncation counts multi-byte characters correctly (not byte length)
- The system uses UTF-8 encoding and properly handles multi-byte Chinese characters

## Constraints

- Feature must reuse existing YouTube API integration (YouTubeApiService) to maintain consistency
- Modal styling must match existing modals for visual consistency
- Incremental updates must not duplicate existing comments (enforce comment_id uniqueness)
- YouTube API quota limitations apply - excessive updates may hit rate limits
- Title truncation must count Chinese characters correctly (1 character = 1 count, regardless of byte size)
- The system must handle videos with thousands of new comments without timeout issues
- All UI text should be in Traditional Chinese (matching existing interface language)
