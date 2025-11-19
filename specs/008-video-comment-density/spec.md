# Feature Specification: Video Comment Density Analysis

**Feature Branch**: `008-video-comment-density`
**Created**: 2025-11-19
**Status**: Draft
**Input**: User description: "新增：008-分析影片功能-留言密集度變化

1. 標題右邊的按鈕「更新」右邊加上「分析」，按下該按鈕後到新頁面
2. Navigator，範例： 首頁 > 影片列表 > 影片分析

新頁面「影片分析」包括如下功能：

根據資料庫，顯示留言密集度變化曲線圖表
以下用選項切換曲線圖和其刻度精確度：
- 發佈後 3 天內：每個小時的留言密集度變化
- 發佈後 7 天內：每個小時的留言密集度變化
- 發佈後 14 天內：每個小時的留言密集度變化
- 發佈後 30 天內：每天的留言密集度變化
- 自由選擇日期範圍，間隔在7天內，刻度為每個小時，超過14天，刻度為每天

這個功能的目的是掌握留言的樣態，以識別是否遭到非正常攻擊"

## Clarifications

### Session 2025-11-19

- Q: When the user interface displays timestamps and time ranges, how should the system handle timezone presentation? → A: Display times in Asia/Taipei timezone (matching the project's unified timezone) with clear "(GMT+8)" label
- Q: When a database query fails while loading chart data (e.g., database connection timeout, query error), how should the system respond? → A: Display technical error details to help with debugging
- Q: While chart data is being loaded and aggregated from the database, what should be displayed to the user? → A: Skeleton chart outline with loading spinner and text "Loading chart data..."
- Q: If data aggregation takes longer than the 3-second target (SC-002) for large datasets or complex queries, how should the system respond? → A: Continue loading with updated message "Large dataset detected. This may take a moment..." and show cancel button
- Q: The feature aims to help identify "abnormal comment spikes" that might indicate attacks. How should the system help users identify what constitutes "abnormal"? → A: Visual-only: users interpret patterns themselves with no system-defined thresholds or alerts

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Quick Attack Detection via Preset Time Ranges (Priority: P1)

Content moderators and analysts need to quickly identify abnormal comment patterns on videos to detect potential coordinated attacks or bot activity. They navigate to a video's analysis page and select from preset time range options (3 days, 7 days, 14 days, or 30 days after publication) to view comment density patterns.

**Why this priority**: This is the core value proposition - enabling rapid identification of abnormal comment activity patterns. Without this, the feature has no purpose.

**Independent Test**: Can be fully tested by selecting a video, clicking the "Analysis" button, choosing any preset time range (e.g., "3 days after publication"), and verifying that an hourly or daily comment density chart appears with appropriate granularity.

**Acceptance Scenarios**:

1. **Given** a user is viewing a video list page, **When** they click the "Analysis" button next to the "Update" button on a video, **Then** they are navigated to the video analysis page with breadcrumb navigation showing "Home > Video List > Video Analysis"

2. **Given** a user is on the video analysis page, **When** they select the "3 days after publication" option, **Then** the system displays a line chart showing hourly comment counts for the first 72 hours after the video was published

3. **Given** a user is on the video analysis page, **When** they select the "7 days after publication" option, **Then** the system displays a line chart showing hourly comment counts for the first 168 hours after the video was published

4. **Given** a user is on the video analysis page, **When** they select the "14 days after publication" option, **Then** the system displays a line chart showing hourly comment counts for the first 14 days after the video was published

5. **Given** a user is on the video analysis page, **When** they select the "30 days after publication" option, **Then** the system displays a line chart showing daily comment counts for the first 30 days after the video was published

6. **Given** a user views a comment density chart, **When** they observe a sudden spike in comments within a short time period, **Then** they can identify this as a potential coordinated attack or abnormal activity

---

### User Story 2 - Custom Date Range Analysis (Priority: P2)

Analysts need flexibility to investigate specific time periods when they suspect unusual activity or want to compare comment patterns across custom timeframes. They can select custom start and end dates, and the system automatically chooses the appropriate granularity (hourly for ranges ≤14 days, daily for ranges >14 days).

**Why this priority**: While preset ranges cover most use cases, custom ranges are essential for deeper investigation and comparative analysis. This is a secondary enhancement to the core functionality.

**Independent Test**: Can be fully tested by selecting a video's analysis page, choosing the "Custom date range" option, entering start and end dates, and verifying that the chart displays with correct granularity (hourly if range ≤14 days, daily if >14 days).

**Acceptance Scenarios**:

1. **Given** a user is on the video analysis page, **When** they select the "Custom date range" option and choose dates spanning 7 days or less, **Then** the system displays an hourly comment density chart for that exact time period

2. **Given** a user is on the video analysis page, **When** they select the "Custom date range" option and choose dates spanning more than 14 days, **Then** the system displays a daily comment density chart for that exact time period

3. **Given** a user selects a custom date range starting before the video publication date, **When** they submit the range, **Then** the system adjusts the start date to the video publication time and displays a notification explaining the adjustment

4. **Given** a user selects a custom date range ending after the current date, **When** they submit the range, **Then** the system adjusts the end date to the current time and displays a notification explaining the adjustment

---

### User Story 3 - Chart Interaction and Data Interpretation (Priority: P3)

Users need to interact with the chart to examine specific data points and understand the exact comment counts at particular times. They can hover over or click on chart points to see detailed information.

**Why this priority**: This enhances the analytical experience but the basic chart visualization in P1 already provides core value. Interactive features improve usability but aren't essential for initial attack detection.

**Independent Test**: Can be fully tested by displaying any comment density chart and verifying that hovering over or clicking data points shows tooltips with exact timestamp and comment count information.

**Acceptance Scenarios**:

1. **Given** a user is viewing a comment density chart, **When** they hover over a data point, **Then** a tooltip appears showing the exact timestamp and comment count for that period

2. **Given** a user is viewing a comment density chart, **When** they observe multiple data points, **Then** each point is clearly labeled with its time marker on the X-axis and the Y-axis shows the comment count scale

3. **Given** a user is viewing a chart with high comment density variation, **When** they examine the chart, **Then** the Y-axis automatically scales to show both low and high values clearly

---

### Edge Cases

- What happens when a video has zero comments in the selected time range? (Display empty chart with message "No comments found in this period")
- How does the system handle videos published less than 3 days ago when selecting the "3 days after publication" option? (Display data only up to current time, with notation indicating "Data available up to [current date]")
- What happens when a user selects a custom date range with start date after end date? (Display validation error "Start date must be before end date")
- How does the system handle extremely high comment volumes (e.g., 10,000+ comments in one hour)? (Chart scales automatically, all data points remain visible)
- What happens when comment timestamps are missing or invalid in the database? (Exclude invalid entries, log warning for data quality review)
- How does the system handle timezone differences between video publication time and current user time? (All times are displayed in Asia/Taipei timezone with "(GMT+8)" label for consistency)
- What happens when a database query fails while loading chart data? (Display technical error details including error type, message, and relevant query information to help with debugging)
- What happens when data aggregation takes longer than 3 seconds for large datasets? (After 3 seconds, update loading message to "Large dataset detected. This may take a moment..." and display a cancel button allowing users to abort the query)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST add an "Analysis" button next to the existing "Update" button on each video in the video list page
- **FR-002**: System MUST navigate users to a dedicated video analysis page when they click the "Analysis" button
- **FR-003**: System MUST display breadcrumb navigation on the analysis page showing "Home > Video List > Video Analysis"
- **FR-004**: System MUST provide five time range selection options: "3 days after publication", "7 days after publication", "14 days after publication", "30 days after publication", and "Custom date range"
- **FR-005**: System MUST display comment density as a line chart with time on the X-axis and comment count on the Y-axis
- **FR-006**: System MUST use hourly granularity (1-hour intervals) for time ranges up to and including 14 days
- **FR-007**: System MUST use daily granularity (1-day intervals) for time ranges exceeding 14 days
- **FR-008**: System MUST calculate all time periods relative to the video's publication timestamp
- **FR-009**: System MUST aggregate comment counts by time period (hourly or daily) based on each comment's timestamp
- **FR-010**: System MUST allow users to input custom start and end dates for the analysis period
- **FR-011**: System MUST automatically determine granularity for custom ranges (hourly if ≤14 days, daily if >14 days)
- **FR-012**: System MUST display the total number of comments in the selected time range
- **FR-013**: System MUST handle cases where the selected time range extends beyond available data by showing data only up to the current time
- **FR-014**: System MUST validate custom date range inputs and display appropriate error messages for invalid ranges
- **FR-015**: System MUST display interactive chart elements allowing users to view exact comment counts at specific time points
- **FR-016**: System MUST display all timestamps in Asia/Taipei timezone (GMT+8) with clear "(GMT+8)" timezone indicator
- **FR-017**: System MUST display technical error details (error type, message, query information) when database queries fail to assist with debugging
- **FR-018**: System MUST display a skeleton chart outline with loading spinner and text "Loading chart data..." while data is being aggregated and loaded
- **FR-019**: System MUST update the loading message to "Large dataset detected. This may take a moment..." if data aggregation exceeds 3 seconds
- **FR-020**: System MUST provide a cancel button during extended loading operations (>3 seconds) allowing users to abort the query and return to the previous state

### Key Entities

- **Video**: Represents a video with attributes including publication timestamp, total comment count, and associated comments
- **Comment**: Represents individual comments with timestamp attribute indicating when the comment was posted
- **Time Period**: Represents aggregated time intervals (hourly or daily buckets) containing comment counts for that specific period
- **Analysis Request**: Represents a user's request to analyze a video with specific time range parameters (preset or custom dates)

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can access the video analysis page from the video list within 2 clicks
- **SC-002**: Users can view comment density charts for any preset time range (3, 7, 14, 30 days) within 3 seconds of selection for typical datasets; extended loading with clear feedback for large datasets
- **SC-003**: Users can identify abnormal comment spikes by visual inspection of the chart without requiring additional tools
- **SC-004**: System displays accurate comment counts matching the actual number of comments in the database for any selected time period (100% accuracy)
- **SC-005**: Charts render correctly for videos with comment counts ranging from 0 to 100,000+ comments
- **SC-006**: Users can successfully create and view custom date range charts on their first attempt (90% success rate based on usability testing)
- **SC-007**: Analysts can reduce time spent identifying coordinated attack patterns by 60% compared to manual database queries
- **SC-008**: System handles concurrent analysis requests from multiple users viewing different videos without performance degradation

### Assumptions

- Video publication timestamps are stored accurately in the database
- Comment timestamps reflect the actual time the comment was posted
- The existing database contains both videos and comments tables with timestamp fields
- Users have appropriate permissions to view video and comment data
- All timestamps are displayed in Asia/Taipei timezone (GMT+8) to match the project's unified timezone standard
- Chart rendering will use a standard charting visualization approach compatible with modern browsers
- Data aggregation queries can be performed efficiently on the existing database schema
- Users accessing this feature have basic familiarity with line charts and data visualization
- The system provides visual data representation only; users are responsible for interpreting patterns and determining what constitutes "abnormal" activity based on their domain expertise
