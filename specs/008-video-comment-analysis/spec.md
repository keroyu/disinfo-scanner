# Feature Specification: Video Comment Density Analysis

**Feature Branch**: `008-video-comment-analysis`
**Created**: 2025-11-19
**Status**: Draft
**Input**: User description: "新增：分析影片功能-留言密集度變化

1. 標題右邊的按鈕「更新」右邊加上「分析」，按下該按鈕後到新頁面
2. Navigator，範例： 首頁 > 影片列表 > 影片分析

新頁面「影片分析」包括如下功能：

1. 影片概況：用 Y-API 取得發佈時間、瀏覽次數、按讚數、留言數
2. 根據資料庫，顯示留言密集度變化曲線圖表
以下用選項切換曲線圖和其刻度精確度：
- 發佈後 3 天內：每個小時的留言數字變化
- 發佈後 7 天內：每個小時的留言數字變化
- 發佈後 14 天內：每天的留言數字變化
- 發佈後 30 天內：每天的留言數字變化
- 自由選擇日期範圍，間隔在7天內，刻度為每小時，超過7天，刻度為每天

留 UI 位置給：

1. 重複留言者有 X 個（查看列表）
2. 高攻擊性留言者有 X 個（查看列表）

這個功能的目的是掌握留言的樣態，以識別是否遭到非正常攻擊"

## Clarifications

### Session 2025-11-19

- Q: How should the system authenticate with Y-API and handle rate limits? → A: Use existing YouTube API import functionality
- Q: What should users see during chart loading (up to 5 seconds)? → A: Skeleton/placeholder chart with loading spinner overlay
- Q: Which timezone should be used for displaying timestamps and aggregating time-based data? → A: Server's local timezone (Asia/Taipei for Taiwan-based system)
- Q: What happens when multiple users trigger cache refresh simultaneously for same video? → A: First request locks cache refresh, subsequent requests wait briefly or use stale data
- Q: Which events/errors should be logged for operational monitoring? → A: API failures and cache refresh events (success/failure with video ID and timestamp)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Access Video Analysis from Video List (Priority: P1)

An analyst viewing a video in the video list wants to quickly access detailed analysis of that video's comment patterns to identify potential coordinated attacks or abnormal commenting behavior.

**Why this priority**: This is the primary entry point to the analysis feature and must work first. Without navigation access, the entire feature cannot be used.

**Independent Test**: Can be fully tested by clicking the "分析" (Analysis) button next to the "更新" (Update) button on any video row and verifying navigation to the video analysis page with correct breadcrumb trail (首頁 > 影片列表 > 影片分析).

**Acceptance Scenarios**:

1. **Given** an analyst is viewing the video list page, **When** they click the "分析" button next to a video's "更新" button, **Then** they are navigated to the video analysis page for that specific video
2. **Given** an analyst is on the video analysis page, **When** they view the breadcrumb navigation, **Then** they see "首頁 > 影片列表 > 影片分析" with working links back to each level
3. **Given** an analyst is viewing the video list, **When** they hover over the "分析" button, **Then** the button shows interactive feedback indicating it's clickable

---

### User Story 2 - View Video Overview Statistics (Priority: P1)

An analyst needs to see basic video statistics (publish time, views, likes, comment count) at a glance to understand the video's reach and engagement level before diving into comment pattern analysis.

**Why this priority**: Context about the video is essential for interpreting comment density patterns. High-priority because it provides critical context for all subsequent analysis.

**Independent Test**: Can be fully tested by navigating to the video analysis page and verifying that video overview displays: 發佈時間 (publish time), 瀏覽次數 (view count), 按讚數 (like count), and 留言數 (comment count) with appropriate data freshness.

**Acceptance Scenarios**:

1. **Given** an analyst opens the video analysis page, **When** the page loads, **Then** they see the video overview section displaying publish time, view count, like count, and total comment count
2. **Given** the cached view/like data is less than 24 hours old, **When** the overview section loads, **Then** it displays data from the database cache without calling the external API
3. **Given** the cached view/like data is more than 24 hours old, **When** the overview section loads, **Then** it fetches fresh data from the external API and updates the cache
4. **Given** all metrics are displayed, **When** the overview section loads, **Then** all four metrics are displayed with appropriate formatting (dates, number formatting with commas for large numbers)
5. **Given** the video API is unavailable or returns an error, **When** attempting to refresh cached data, **Then** the user sees the last cached data with an indicator showing data age, or an error message if no cached data exists

---

### User Story 3 - Analyze Comment Density with Preset Time Ranges (Priority: P1)

An analyst wants to visualize how comment volume changes over time using preset time ranges to quickly identify unusual spikes or patterns that might indicate coordinated activity or abnormal attacks.

**Why this priority**: This is the core analytical functionality that enables attack detection. Essential for the feature's primary purpose.

**Independent Test**: Can be fully tested by selecting each preset time range option and verifying the chart displays comment density with appropriate time granularity: 3 days (hourly), 7 days (hourly), 14 days (daily), 30 days (daily).

**Acceptance Scenarios**:

1. **Given** an analyst is on the video analysis page, **When** they select "發佈後 3 天內" (first 3 days after publish), **Then** they see a line chart showing comment count per hour for 72 hours
2. **Given** an analyst is viewing the chart, **When** they select "發佈後 7 天內" (first 7 days after publish), **Then** the chart updates to show comment count per hour for 168 hours
3. **Given** an analyst is viewing the chart, **When** they select "發佈後 14 天內" (first 14 days after publish), **Then** the chart updates to show comment count per day for 14 days
4. **Given** an analyst is viewing the chart, **When** they select "發佈後 30 天內" (first 30 days after publish), **Then** the chart updates to show comment count per day for 30 days
5. **Given** the chart displays hourly data, **When** the analyst hovers over a data point, **Then** they see the exact hour and comment count for that time period
6. **Given** the chart displays daily data, **When** the analyst hovers over a data point, **Then** they see the exact date and comment count for that day
7. **Given** an analyst selects a time range, **When** the chart is loading data, **Then** they see a skeleton/placeholder chart with a loading spinner overlay

---

### User Story 4 - Analyze Comment Density with Custom Date Range (Priority: P2)

An analyst wants to examine comment patterns for a specific time period of interest (e.g., around a specific event or suspected attack) by selecting a custom date range with appropriate granularity.

**Why this priority**: Provides flexibility for deeper investigation but not essential for basic attack detection. Can be implemented after preset ranges work.

**Independent Test**: Can be fully tested by selecting custom start and end dates, then verifying the chart displays with hourly granularity for ranges ≤7 days and daily granularity for ranges >7 days.

**Acceptance Scenarios**:

1. **Given** an analyst is on the video analysis page, **When** they select "自由選擇日期範圍" (custom date range) and choose a 5-day range, **Then** they see a chart with hourly granularity showing comment count for those 5 days
2. **Given** an analyst selects a custom date range, **When** the range is exactly 7 days, **Then** the chart displays hourly granularity
3. **Given** an analyst selects a custom date range, **When** the range is 8 or more days, **Then** the chart displays daily granularity
4. **Given** an analyst attempts to select a custom date range, **When** they select an end date before the start date, **Then** they see a validation error message
5. **Given** an analyst selects a custom date range, **When** the range extends beyond available comment data, **Then** the chart shows available data and indicates the time periods with no data

---

### User Story 5 - Access Repeat Commenter Summary (Priority: P3)

An analyst wants to see how many repeat commenters exist for this video and access a list of those users to investigate potential coordinated behavior or spam.

**Why this priority**: Important for attack detection but lower priority as the UI placeholder can be implemented later. The core density visualization is more critical.

**Independent Test**: Can be fully tested by viewing the repeat commenter count display and clicking the "查看列表" (view list) link to access the full list of repeat commenters (functionality to be implemented separately).

**Acceptance Scenarios**:

1. **Given** an analyst is on the video analysis page, **When** the page loads, **Then** they see a display showing "重複留言者有 X 個" (X repeat commenters) with the correct count
2. **Given** the repeat commenter display shows a count, **When** the analyst clicks "查看列表" (view list), **Then** they are prepared to navigate to a detailed list view (link/button is present and functional, even if destination page is implemented later)
3. **Given** there are no repeat commenters for the video, **When** the page loads, **Then** the display shows "重複留言者有 0 個"

---

### User Story 6 - Access High-Aggression Commenter Summary (Priority: P3)

An analyst wants to see how many high-aggression commenters exist for this video and access a list of those users to investigate potential harassment or attack campaigns.

**Why this priority**: Important for attack detection but lower priority as this requires aggression detection logic which may be implemented separately. The UI placeholder is sufficient initially.

**Independent Test**: Can be fully tested by viewing the high-aggression commenter count display and clicking the "查看列表" (view list) link to access the full list (functionality to be implemented separately).

**Acceptance Scenarios**:

1. **Given** an analyst is on the video analysis page, **When** the page loads, **Then** they see a display showing "高攻擊性留言者有 X 個" (X high-aggression commenters) with the correct count
2. **Given** the high-aggression commenter display shows a count, **When** the analyst clicks "查看列表" (view list), **Then** they are prepared to navigate to a detailed list view (link/button is present and functional, even if destination page is implemented later)
3. **Given** there are no high-aggression commenters for the video, **When** the page loads, **Then** the display shows "高攻擊性留言者有 0 個"

---

### Edge Cases

- What happens when a video has no comments? (Chart should display empty state with message)
- What happens when the video API is unavailable or returns errors? (Display error message in overview section, allow chart to still function with local data)
- What happens when comment data is incomplete for the selected time range? (Chart displays available data with visual indication of gaps)
- What happens when a user selects a custom date range before the video was published? (Validation error or automatic adjustment to valid range)
- What happens when there are extremely high comment spikes (e.g., 10,000+ comments in one hour)? (Chart scale adjusts appropriately, hover tooltip shows exact numbers)
- What happens with videos that have comments spanning years? (All time range options should still work, with 30-day option showing the first 30 days after publish)
- What happens when the analyst clicks "分析" button on a video that was just imported and has no comment timestamp data? (Show appropriate message indicating analysis requires complete comment data)
- What happens when multiple analysts view the same video simultaneously and cache is stale? (First request locks and refreshes cache, subsequent requests wait briefly or use stale data with age indicator)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST add an "分析" (Analysis) button next to the existing "更新" (Update) button on each video row in the video list
- **FR-002**: System MUST navigate users to a video analysis page when they click the "分析" button
- **FR-003**: System MUST display breadcrumb navigation showing "首頁 > 影片列表 > 影片分析" with clickable links on the video analysis page
- **FR-004**: System MUST retrieve and display video overview statistics including: 發佈時間 (publish time), 瀏覽次數 (view count), 按讚數 (like count), and 留言數 (comment count)
- **FR-005**: System MUST retrieve 發佈時間 (publish time) and 留言數 (comment count) from the local database
- **FR-006**: System MUST retrieve 瀏覽次數 (view count) and 按讚數 (like count) from cached database fields (videos.views, videos.likes) when cache is valid
- **FR-007**: System MUST check the videos.updated_at timestamp to determine if cached view/like data is still valid (less than 24 hours old)
- **FR-008**: System MUST fetch fresh 瀏覽次數 and 按讚數 from the external video API (Y-API) using existing YouTube API import functionality when cached data is more than 24 hours old
- **FR-009**: System MUST update videos.views and videos.likes fields after successfully fetching fresh data from Y-API
- **FR-010**: System MUST update videos.updated_at timestamp to current time after successfully caching fresh data
- **FR-011**: System MUST use locking mechanism to prevent duplicate API calls when multiple users trigger cache refresh for the same video simultaneously
- **FR-012**: System MUST display cached data even when Y-API is unavailable, with an appropriate indicator showing data age
- **FR-013**: Videos table MUST have views and likes fields added to support caching (see Database Schema Changes section)
- **FR-014**: System MUST display a line chart showing comment density over time
- **FR-015**: System MUST show a skeleton/placeholder chart with loading spinner overlay while chart data is being fetched or rendered
- **FR-016**: System MUST provide five time range options: 3 days, 7 days, 14 days, 30 days, and custom range
- **FR-017**: System MUST display hourly granularity (comment count per hour) for the "發佈後 3 天內" (first 3 days) option
- **FR-018**: System MUST display hourly granularity for the "發佈後 7 天內" (first 7 days) option
- **FR-019**: System MUST display daily granularity (comment count per day) for the "發佈後 14 天內" (first 14 days) option
- **FR-020**: System MUST display daily granularity for the "發佈後 30 天內" (first 30 days) option
- **FR-021**: System MUST allow users to select custom start and end dates for the comment density chart
- **FR-022**: System MUST use hourly granularity for custom date ranges of 7 days or less
- **FR-023**: System MUST use daily granularity for custom date ranges greater than 7 days
- **FR-024**: System MUST calculate time ranges based on the video's publish time (not current time)
- **FR-025**: System MUST display all timestamps and aggregate time-based data using server's local timezone (Asia/Taipei)
- **FR-026**: System MUST retrieve comment timestamp data from the local database (not the external API)
- **FR-027**: System MUST display the count of repeat commenters for the video
- **FR-028**: System MUST provide a "查看列表" (view list) link for repeat commenters
- **FR-029**: System MUST display the count of high-aggression commenters for the video
- **FR-030**: System MUST provide a "查看列表" (view list) link for high-aggression commenters
- **FR-031**: System MUST show interactive tooltips when users hover over chart data points
- **FR-032**: System MUST format large numbers with appropriate separators (e.g., commas or spaces) in the overview section
- **FR-033**: System MUST handle API errors gracefully and display user-friendly error messages
- **FR-034**: System MUST display an appropriate message when no comment data is available
- **FR-035**: System MUST validate custom date range inputs (end date must be after start date)
- **FR-036**: System MUST log all API failures and cache refresh events including video ID, timestamp, and success/failure status for operational monitoring

### Key Entities

- **Video**: The video being analyzed, identified by unique ID, contains metadata including:
  - Publish time (stored in database)
  - View count (cached in videos.views field, refreshed from API when stale)
  - Like count (cached in videos.likes field, refreshed from API when stale)
  - Total comment count (calculated from database)
  - Cache timestamp (videos.updated_at) indicating when view/like data was last refreshed
- **Comment**: Individual comment on the video, contains timestamp, commenter identification, and content
- **Comment Density Data Point**: Aggregated data for chart display, represents comment count for a specific time period (hour or day)
- **Repeat Commenter**: User who has posted multiple comments on the same video, tracked with comment count
- **High-Aggression Commenter**: User identified as posting aggressive or attacking comments (detection criteria to be defined separately)

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Analysts can navigate from video list to video analysis page in one click (via "分析" button)
- **SC-002**: Video overview statistics load and display within 3 seconds of page load
- **SC-003**: Comment density chart renders and displays data within 5 seconds when selecting any preset time range
- **SC-004**: Chart accurately displays comment patterns with correct time granularity (hourly for ≤7 days, daily for >7 days)
- **SC-005**: Analysts can visually identify comment spikes and unusual patterns in the chart within 10 seconds of viewing
- **SC-006**: System successfully handles videos with up to 100,000 comments without performance degradation
- **SC-007**: All chart interactions (selecting time ranges, hovering for tooltips) respond within 1 second
- **SC-008**: Breadcrumb navigation works correctly and allows analysts to return to previous pages in one click
- **SC-009**: Repeat commenter and high-aggression commenter counts display correctly with accuracy of 100%
- **SC-010**: Users can successfully complete a full analysis workflow (navigate to analysis, view overview, examine chart with multiple time ranges) in under 2 minutes
- **SC-011**: System reduces external API calls by serving cached view/like data when less than 24 hours old, improving page load performance
- **SC-012**: Analysis page remains functional and displays cached statistics even when external API is temporarily unavailable

### User Value

The video comment analysis feature enables security analysts and content moderators to quickly identify coordinated attacks, spam campaigns, and abnormal commenting patterns by visualizing comment density over time. This helps protect content creators and platforms from disinformation campaigns and harassment.

## Assumptions

- The external video API (Y-API) provides reliable access to view count and like count data
- Video publish time is already stored in the local database and does not need to be fetched from Y-API
- Comment count can be calculated from the local database and does not need to be fetched from Y-API
- The videos table DOES NOT currently have views and likes fields - these must be added via database migration (see Database Schema Changes section)
- The videos.updated_at field is assumed to exist as a standard Laravel timestamp field
- Comments in the database have accurate timestamp data for density calculation
- All timestamps are stored and displayed using server's local timezone (Asia/Taipei)
- A 24-hour cache validity period is acceptable for view/like statistics (data freshness trade-off for performance)
- The definition and detection logic for "high-aggression commenters" will be defined in a separate feature or can use a placeholder count initially
- "Repeat commenters" are defined as users who have posted 2 or more comments on the same video
- Chart visualization will use a standard charting library available in the current tech stack
- The time ranges (3, 7, 14, 30 days) are calculated from the video publish time, not from the current date
- Analysts have sufficient permissions to view all video data and comment patterns
- The video analysis page will follow the same design language and layout patterns as existing pages in the application
- Displaying slightly stale view/like data (up to 24 hours old) is acceptable when Y-API is unavailable

## Dependencies

- Existing YouTube API import functionality for retrieving fresh view count and like count data (authentication and rate limiting already handled)
- Existing videos table (schema changes required - see Database Schema Changes section below)
- Existing video database with comment timestamp data
- Existing comments table with accurate timestamps for density calculation
- Existing video list page with "更新" (Update) button UI pattern to follow
- Navigation/routing system that supports breadcrumb trails
- UI component library or charting library for rendering line charts

## Database Schema Changes

**Required Changes to videos table**:

The videos table currently does NOT have the following fields required for this feature. These must be added:

- **views** (integer, nullable): Cached view count from Y-API
  - Purpose: Store view count to reduce API calls
  - Default: null (will be populated on first API fetch)

- **likes** (integer, nullable): Cached like count from Y-API
  - Purpose: Store like count to reduce API calls
  - Default: null (will be populated on first API fetch)

**Note**: The videos.updated_at field is assumed to already exist as a standard Laravel timestamp field. If it does not exist, it must also be added.

**Migration Requirements**:
- Add migration to create views and likes columns
- Both fields should be nullable to handle videos that haven't been fetched yet
- Consider adding an index on updated_at if querying for stale cache becomes a performance concern

## Open Questions

None - all requirements are sufficiently clear for implementation planning.
