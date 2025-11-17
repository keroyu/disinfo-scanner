# Feature Specification: Videos List

**Feature Branch**: `006-videos-list`
**Created**: 2025-11-18
**Status**: Draft
**Input**: User description: "新增：影片列表

1. 欄位：頻道名稱、影片標題、留言數、最後留言(顯示最後留言時間，ex:2025-07-23 09:58)
- 樣式參考「留言列表」Comments List
- 點擊頻道名稱，跳轉到「留言列表」搜尋頻道關鍵字的結果
- 點擊影片標題，跳轉到「留言列表」搜尋標題關鍵字的結果
- 點擊最後留言時間，跳轉到「留言列表」搜尋標題關鍵字+該日期近90日的結果

2. 搜尋欄：欄位和樣式參考留言列表的搜尋欄
3. 頁面入口和「頻道列表」並列，放在右邊"

## Clarifications

### Session 2025-11-18

- Q: When a user first loads the Videos List page (without any search filters), how should the videos be sorted by default? → A: 按影片發佈時間 (published_at) 從新到舊排序
- Q: When users search for videos by channel name or video title, should the search be case-sensitive or case-insensitive? → A: Case-insensitive matching
- Q: Should users be able to change the sort order of the videos list (e.g., sort by comment count, last comment time, etc.)? → A: Allow sorting by clicking column headers

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View All Videos with Comment Activity (Priority: P1)

As a content analyst, I need to see a comprehensive list of all videos that have collected comments, so I can identify which videos are generating the most discussion and require monitoring.

**Why this priority**: This is the core feature that provides the primary value - visibility into video-level comment activity. Without this, users cannot perform video-level analysis.

**Independent Test**: Can be fully tested by navigating to the Videos List page and verifying that all videos with comments are displayed with their channel name, title, comment count, and last comment timestamp.

**Acceptance Scenarios**:

1. **Given** I am on the Videos List page, **When** the page loads, **Then** I see a table displaying all videos with columns: Channel Name, Video Title, Comment Count, and Last Comment Time, sorted by video publication date (newest first) by default
2. **Given** multiple videos exist with different comment counts, **When** I view the list, **Then** I see accurate comment counts for each video
3. **Given** videos have comments with different timestamps, **When** I view the list, **Then** the Last Comment Time shows the most recent comment timestamp in format "YYYY-MM-DD HH:MM"
4. **Given** a video has no comments, **When** I view the Videos List, **Then** that video should not appear in the list (only videos with at least one comment are shown)
5. **Given** I am viewing the Videos List, **When** I click on the "Comment Count" column header, **Then** the list is re-sorted by comment count with a visual indicator showing the sort direction
6. **Given** I have sorted by a column, **When** I click the same column header again, **Then** the sort direction toggles between ascending and descending

---

### User Story 2 - Navigate from Video to Related Comments (Priority: P1)

As a content analyst, I need to click on video information (channel name, video title, or last comment time) to view related comments in the Comments List, so I can quickly drill down from video-level to comment-level analysis.

**Why this priority**: This navigation is essential for the user workflow - it connects the Videos List to the existing Comments List, enabling seamless analysis transitions.

**Independent Test**: Can be tested by clicking each clickable element (channel name, video title, last comment time) and verifying the correct Comments List search results are displayed with appropriate filters applied.

**Acceptance Scenarios**:

1. **Given** I am viewing the Videos List, **When** I click on a channel name, **Then** I am redirected to the Comments List page with the channel name pre-filled in the search filter
2. **Given** I am viewing the Videos List, **When** I click on a video title, **Then** I am redirected to the Comments List page with the video title pre-filled in the keyword search filter
3. **Given** I am viewing the Videos List and a video's last comment time is "2025-07-23 09:58", **When** I click on that timestamp, **Then** I am redirected to the Comments List page with:
   - The video title pre-filled in the keyword search
   - Date range set from 90 days before the clicked date to the clicked date (approximately 2025-04-24 to 2025-07-23)
   - Only comments for that specific video are displayed

---

### User Story 3 - Search and Filter Videos (Priority: P2)

As a content analyst, I need to search and filter the videos list by various criteria, so I can quickly find specific videos or narrow down my analysis scope.

**Why this priority**: Search functionality is important for usability but the feature provides value even without search (users can still browse the full list).

**Independent Test**: Can be tested independently by entering search terms and applying filters, then verifying the results match the search criteria.

**Acceptance Scenarios**:

1. **Given** I am on the Videos List page, **When** I enter keywords in the search field and click "Apply Filters", **Then** the list shows only videos whose title or channel name matches the keywords
2. **Given** I have applied search filters, **When** I click "Clear Filters", **Then** all filters are reset and the full videos list is displayed
3. **Given** I am viewing filtered results, **When** I apply additional filters, **Then** the results update to match all active filter criteria

---

### User Story 4 - Access Videos List from Navigation (Priority: P2)

As a user, I need to access the Videos List page from the main navigation, positioned to the right of the Channels List link, so I can easily navigate between different analysis views.

**Why this priority**: Navigation is important for discoverability, but the feature can function without perfect navigation placement during initial development.

**Independent Test**: Can be tested by viewing the navigation menu and clicking the Videos List link to verify it opens the correct page.

**Acceptance Scenarios**:

1. **Given** I am on any page of the application, **When** I view the main navigation, **Then** I see a "Videos List" link positioned to the right of the "Channels List" link
2. **Given** I am viewing the navigation, **When** I click the "Videos List" link, **Then** I am taken to the Videos List page
3. **Given** I am on the Videos List page, **When** I view the navigation, **Then** the "Videos List" link is visually highlighted to indicate the current page

---

### Edge Cases

- What happens when a video has comments but the channel information is missing or deleted?
  - Display "Unknown Channel" in the channel name column
- What happens when there are no videos with comments in the database?
  - Display an empty state message: "No videos found. Try adjusting your search filters."
- What happens when clicking on last comment time for a video that was just imported (last comment is very recent)?
  - Calculate 90-day range from 90 days before the clicked date to the clicked date (from_date = clicked_date - 90 days, to_date = clicked_date), showing only comments for that specific video
- What happens when the list contains thousands of videos?
  - Implement pagination (500 videos per page, matching Comments List pagination)
- What happens when a user clicks on a video title or channel name that contains special characters or quotes?
  - URL-encode search parameters properly to ensure accurate filtering in Comments List

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display a Videos List page showing all videos that have at least one comment
- **FR-002**: System MUST display the following columns for each video: Channel Name, Video Title, Comment Count, Last Comment Time
- **FR-003**: Comment Count MUST accurately reflect the total number of comments associated with each video
- **FR-004**: Last Comment Time MUST display the timestamp of the most recent comment for that video in format "YYYY-MM-DD HH:MM"
- **FR-005**: Channel Name MUST be clickable and redirect to Comments List with the channel name as search filter
- **FR-006**: Video Title MUST be clickable and redirect to Comments List with the video title as keyword search filter
- **FR-007**: Last Comment Time MUST be clickable and redirect to Comments List with video title as keyword search AND date range set from 90 days before the clicked date to the clicked date, filtering only comments for that specific video
- **FR-008**: System MUST provide a search interface matching the Comments List styling with keyword search field
- **FR-009**: Search functionality MUST filter videos by channel name and video title using case-insensitive matching
- **FR-010**: System MUST provide "Apply Filters" and "Clear Filters" buttons matching Comments List functionality
- **FR-011**: System MUST display a navigation link to "Videos List" positioned to the right of "Channels List" link
- **FR-012**: System MUST paginate results at 500 videos per page, matching Comments List pagination behavior
- **FR-013**: System MUST display an empty state message when no videos match the current filters
- **FR-014**: System MUST handle missing channel information by displaying "Unknown Channel"
- **FR-015**: Page styling MUST match the Comments List design (same card styles, table layout, colors, spacing)
- **FR-016**: System MUST sort videos by video publication date (published_at) in descending order (newest first) by default when the page loads without search filters
- **FR-017**: System MUST allow users to sort videos by clicking on column headers (Comment Count, Last Comment Time) with toggle between ascending and descending order
- **FR-018**: System MUST display visual indicators (e.g., up/down arrows) on sortable column headers to show current sort column and direction

### Key Entities *(include if feature involves data)*

- **Video**: Represents a YouTube video; key attributes include video_id (primary key), channel_id, title, published_at, comment_count. Related to Channel (many videos belong to one channel) and Comments (one video has many comments).
- **Comment**: Represents a comment on a video; key attributes include comment_id, video_id, published_at, text. Used to calculate total comment count and identify the last (most recent) comment timestamp for each video.
- **Channel**: Represents a YouTube channel; key attributes include channel_id, channel_name. Related to Videos (one channel has many videos).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can view a complete list of all videos with comments within 2 seconds of page load (for databases with up to 10,000 videos)
- **SC-002**: Users can successfully navigate from any video in the Videos List to the relevant filtered Comments List view in one click
- **SC-003**: 100% of videos display accurate comment counts matching the actual number of comments in the database
- **SC-004**: Users can complete a workflow of "find video → click to view comments" in under 30 seconds
- **SC-005**: Search filters reduce the displayed videos list to matching results within 1 second of clicking "Apply Filters"
- **SC-006**: The Videos List page visual design is consistent with Comments List (verified by comparing styling elements: table structure, button colors, spacing, typography)
