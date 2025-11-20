# Feature Specification: Time-Based Comment Filtering from Chart

**Feature Branch**: `010-time-based-comment-filter`
**Created**: 2025-11-20
**Status**: Draft
**Input**: User description: "Multi-select time-based comment filtering from Comments Density chart - users can click multiple time points on the chart to view combined comments from selected time periods"

## Clarifications

### Session 2025-11-20

- Q: How should multiple time ranges be passed to the API endpoint? → A: Comma-separated ISO timestamps (e.g., `?time_points=2025-11-20T08:00:00,2025-11-20T10:00:00,2025-11-20T12:00:00`), backend calculates hourly ranges from each point
- Q: Where should UTC → GMT+8 timezone conversion happen? → A: Backend converts UTC → GMT+8 in API response before sending to frontend (aligns with Constitution Principle VI)
- Q: At what point should the system warn users about performance when selecting multiple time points? → A: Warn at 15 selections (provides 5-point buffer before 20-point tested limit)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Single Time Point Selection (Priority: P0)

An analyst viewing the Comments Density chart wants to investigate comments from a specific hour showing unusual activity. They click on a single time point on the chart and see all comments posted during that hour displayed in the Commenter Pattern Summary panel.

**Why this priority**: This is the core functionality that enables time-based analysis. Without single-point selection, the entire feature cannot work. This delivers immediate value by allowing analysts to drill down from aggregate data to individual comments.

**Independent Test**: Can be fully tested by clicking any data point on the Comments Density chart and verifying that the comments panel displays only comments from that specific hour, sorted by time.

**Acceptance Scenarios**:

1. **Given** the analyst is viewing a video analysis page with the Comments Density chart loaded, **When** they click on a data point representing 14:00-15:00, **Then** the Commenter Pattern Summary panel displays all comments posted between 14:00-15:00 with a visual indicator showing "📍 Showing comments from 14:00-15:00"
2. **Given** a time point is selected, **When** the analyst clicks the "Clear Selection" button, **Then** the panel returns to the default "所有留言" view with all comments displayed
3. **Given** the analyst clicks a time point with zero comments, **When** the comments panel updates, **Then** it displays an empty state message "此時間段沒有留言"
4. **Given** a time point is selected, **When** the analyst scrolls to the bottom of the comments panel, **Then** the system loads additional comments from that same time period using infinite scroll

---

### User Story 2 - Multiple Time Points Selection (Priority: P1)

An analyst wants to compare comments across multiple non-contiguous time periods (e.g., morning peak, lunch time, and evening peak). They click multiple time points on the chart (8:00, 12:00, 18:00) and see combined comments from all three hours (8:00-9:00, 12:00-13:00, 18:00-19:00) merged and sorted chronologically.

**Why this priority**: Multi-select enables pattern analysis across different times of day. This is essential for identifying coordinated behavior that occurs at specific times but not continuously. Delivers high value for detecting suspicious activity patterns.

**Independent Test**: Can be fully tested by clicking 3 different time points on the chart and verifying that comments from all three hourly periods appear combined in the panel, with a count indicator showing "📍 Selected: 3 time periods".

**Acceptance Scenarios**:

1. **Given** the analyst has selected one time point (14:00), **When** they click another time point (16:00), **Then** the panel displays comments from both 14:00-15:00 AND 16:00-17:00 combined, sorted by published_at
2. **Given** multiple time points are selected, **When** the analyst clicks on an already-selected point, **Then** that time period is deselected and removed from the filtered comments
3. **Given** 3 time points are selected (08:00, 10:00, 12:00), **When** the comments panel displays, **Then** it shows "📍 Selected: 3 time periods" with a list showing "08:00-09:00, 10:00-11:00, 12:00-13:00"
4. **Given** multiple time points are selected, **When** the analyst clicks "Clear All", **Then** all selections are removed and the panel returns to default view

---

### User Story 3 - Visual Highlighting of Selected Points (Priority: P2)

An analyst wants to clearly see which time points they have selected on the chart. When they click time points, the selected points are visually highlighted with a different color or style, making it easy to track their selection.

**Why this priority**: Visual feedback is critical for usability but can be implemented after basic selection works. Enhances user confidence and reduces errors but doesn't block core functionality.

**Independent Test**: Can be fully tested by clicking multiple points on the chart and visually confirming that selected points have a different appearance (darker blue fill, border, or marker) compared to unselected points.

**Acceptance Scenarios**:

1. **Given** the chart is displayed with default styling, **When** the analyst clicks a data point, **Then** that point's background color changes from light blue (rgba(59, 130, 246, 0.1)) to darker blue (rgba(59, 130, 246, 0.6))
2. **Given** multiple points are selected and highlighted, **When** the analyst deselects one point by clicking it again, **Then** that point returns to the default light blue styling
3. **Given** points are visually highlighted, **When** the analyst clicks "Clear All", **Then** all points return to default styling

---

### User Story 4 - Combine Time Filter with Pattern Filters (Priority: P3)

An analyst wants to narrow down results by combining time-based filtering with pattern filtering. For example, they select time periods 02:00-03:00 and 04:00-05:00, then apply the "重複留言者" (repeat commenters) filter to see only repeat commenters who posted during those night hours.

**Why this priority**: This advanced feature enables sophisticated analysis but depends on both time filtering and pattern filtering working independently first. High value for advanced investigations but not required for basic time-based analysis.

**Independent Test**: Can be fully tested by selecting 2 time points on the chart, then clicking the "重複留言者" filter button, and verifying that only repeat commenters from those time periods appear.

**Acceptance Scenarios**:

1. **Given** 2 time points are selected (02:00, 04:00), **When** the analyst clicks "重複留言者有 X 個", **Then** the panel displays only comments from repeat commenters posted during 02:00-03:00 OR 04:00-05:00
2. **Given** a pattern filter and time filter are both active, **When** the analyst clears the time selection, **Then** the pattern filter remains active showing all repeat commenters (not time-limited)
3. **Given** a pattern filter and time filter are both active, **When** the analyst switches to a different pattern filter, **Then** the time selection remains active and the new pattern is applied to the selected time periods

---

### Edge Cases

- What happens when a selected time point has zero comments? (Display empty state message specific to that time range)
- What happens when the analyst selects 15 or more time points? (Display warning: "Selecting many time periods may slow performance. Consider narrowing your selection." Allow continued selection up to 20 points maximum)
- What happens when the analyst attempts to select more than 20 time points? (Prevent selection and display message: "Maximum 20 time periods can be selected")
- What happens when the analyst switches time range view (24h to 7d) while time points are selected? (Clear selection and show warning: "Selection cleared due to time range change")
- What happens when infinite scroll reaches the end of filtered results? (Display "所有符合條件的留言已載入" message)
- What happens if the user clicks a time point outside the currently displayed time range? (Should not be possible - only visible points are clickable)
- What happens when the analyst refreshes the page with time selections active? (Selection state is lost - return to default view; future: consider URL parameters to preserve state)
- How does the system handle daylight saving time boundaries? (Use GMT+8 consistently; document that all times are Asia/Taipei)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow analysts to click on individual data points in the Comments Density chart to filter comments by time period
- **FR-002**: System MUST support selection of multiple non-contiguous time points on the chart
- **FR-002a**: System MUST accept time points via API query parameter as comma-separated ISO 8601 timestamps (format: `?time_points=2025-11-20T08:00:00,2025-11-20T10:00:00`), with backend calculating 1-hour ranges from each timestamp
- **FR-003**: System MUST display comments from all selected time periods combined and sorted by published_at timestamp
- **FR-004**: System MUST allow deselection of individual time points by clicking them again
- **FR-005**: System MUST provide a "Clear All" button to remove all time selections at once
- **FR-006**: System MUST visually highlight selected time points on the chart with a different color or style
- **FR-007**: System MUST display a visual indicator showing the count and list of selected time periods (e.g., "📍 Selected: 3 time periods: 08:00-09:00, 10:00-11:00, 12:00-13:00")
- **FR-008**: System MUST support infinite scroll for time-filtered comments, loading additional batches of 100 comments
- **FR-009**: System MUST allow combination of time filtering with existing pattern filters (all, top_liked, repeat, night_time)
- **FR-010**: System MUST calculate time ranges as 1-hour buckets (e.g., click on 14:00 = show comments from 14:00:00 to 14:59:59)
- **FR-011**: System MUST use GMT+8 (Asia/Taipei) timezone consistently for all time calculations and displays
- **FR-011a**: Backend MUST convert all UTC timestamps to GMT+8 (Asia/Taipei) before sending to frontend, and MUST accept time filter parameters in GMT+8 format
- **FR-011b**: Frontend MUST display all times with explicit timezone indicators (e.g., "14:30 (GMT+8)") to prevent user confusion
- **FR-012**: System MUST maintain time selection state when switching between pattern filters
- **FR-013**: System MUST clear time selection state when the user switches time range view (24h/3d/7d)
- **FR-014**: System MUST display appropriate empty state messages when selected time periods have no comments
- **FR-015**: System MUST disable pattern filter statistics display when time filtering is active (statistics are for entire video, not filtered time)
- **FR-016**: System MUST display a performance warning when 15 or more time points are selected, informing users that many selections may slow performance
- **FR-017**: System MUST enforce a hard limit of 20 time point selections, preventing further selections with an explanatory message

### Key Entities

- **TimePoint**: Represents a clickable point on the Comments Density chart, with attributes: timestamp (start of hour), count (number of comments in that hour), selected status (boolean)
- **TimeRange**: Represents a selected time period, with attributes: from_time (ISO 8601), to_time (ISO 8601), derived from clicked TimePoint
- **FilterState**: Tracks current filtering state, with attributes: mode ('pattern' | 'time-filtered' | 'combined'), selected time ranges (array), active pattern (string), maintains UI synchronization

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Analysts can select and view comments from a single time point within 2 seconds of clicking the chart
- **SC-002**: System supports selection of up to 20 time points without performance degradation (response time < 3 seconds)
- **SC-003**: Visual feedback (highlighting) appears within 200ms of clicking a chart point
- **SC-004**: Time-filtered comments load and display within 2 seconds for datasets up to 10,000 comments
- **SC-005**: Infinite scroll loads additional time-filtered comments within 1 second per batch
- **SC-006**: Combined pattern + time filtering returns accurate results (100% match rate in testing)
- **SC-007**: Analysts can successfully identify and investigate time-based patterns 60% faster compared to manual scrolling through all comments

## Assumptions *(optional)*

1. **Hourly granularity**: Time buckets are fixed at 1-hour intervals matching the Comments Density chart's hourly data points
2. **Chart interaction**: Chart.js library supports onClick events with element detection
3. **Existing API extensibility**: The `/api/videos/{videoId}/comments` endpoint can be extended to accept time range parameters without breaking existing functionality
4. **Browser compatibility**: Analysts use modern browsers supporting ES6 JavaScript features
5. **Performance baseline**: Existing comment loading infrastructure can handle multiple WHERE clauses (OR conditions for time ranges) without significant query performance impact
6. **State management**: Selection state is client-side only; no server-side session storage required
7. **Timezone consistency**: All existing data is stored in UTC and can be converted to GMT+8 for filtering
8. **UI space**: The Commenter Pattern Summary panel has sufficient space to display time selection indicators without layout issues

## Out of Scope *(optional)*

- **Range selection via dragging**: Selecting a continuous range by clicking and dragging across multiple points (Phase 2 enhancement)
- **Keyboard shortcuts**: Using keyboard to select/deselect points (e.g., Ctrl+Click, Shift+Click for range)
- **Persistent state across sessions**: Saving time selections in URL parameters or local storage
- **Date range picker**: Alternative UI for selecting time ranges via calendar/time picker instead of chart clicks
- **Export filtered comments**: Downloading selected comments as CSV/JSON (separate feature)
- **Time aggregation controls**: Changing bucket size from 1 hour to 15 minutes, 30 minutes, or 2 hours
- **Cross-video time comparison**: Comparing comments across multiple videos at the same time periods
- **Scheduled time filters**: Saving and reusing common time selection patterns (e.g., "peak hours" preset)
- **Real-time updates**: Live updating of comments as new data arrives while time filter is active

## Dependencies *(optional)*

### Internal Dependencies

- **Feature 009 (comments-pattern-summary)**: Requires the Commenter Pattern Summary UI and APIs to be implemented and stable
- **Feature 008 (comment-density)**: Requires the Comments Density chart to be implemented with Chart.js
- **Comments API**: Depends on existing `/api/videos/{videoId}/comments` endpoint structure

### External Dependencies

- **Chart.js library**: Version 4.4.0+ with onClick event support
- **Browser APIs**: Intersection Observer for infinite scroll (already in use)
- **Database indexes**: Efficient querying requires index on `comments.published_at` column (likely already exists)

## Technical Constraints *(optional)*

- **Database query performance**: Multiple OR conditions in WHERE clause may impact query performance for large datasets (>100k comments); requires query optimization or caching strategy
- **Client-side memory**: Storing selection state and highlighted points in browser memory; limit of 20 selections helps prevent memory issues
- **Chart rendering**: Re-rendering chart with highlighted points may cause brief flicker; use Chart.js update() method to minimize
- **Time zone calculations**: All time conversions happen in backend layer per Constitution Principle VI; database stores UTC, backend converts to GMT+8 for API responses, frontend displays converted times with timezone indicators
