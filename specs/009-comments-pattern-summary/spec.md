# Feature Specification: Comments Pattern Summary

**Feature Branch**: `009-comments-pattern-summary`
**Created**: 2025-11-20
**Status**: Draft
**Input**: User description: "On Video Analysis page, add new features in 'Comments Pattern' block. Display counts and percentages for: repeat commenters (2+ comments in same video), night-time high-frequency commenters (>50% comments at night across all channels), aggressive commenters (placeholder UI), and simplified Chinese commenters (placeholder UI). Clicking statistics shows scrollable comment list on right side panel."

## Clarifications

### Session 2025-11-20

- Q: What is the default behavior of the right panel and how do filters interact with it? → A: Add "所有留言" at top of left side list. Default right panel shows all comments for the video (newest to oldest). Clicking any filter reloads panel with specified filtered comments.
- Q: How should the right panel handle large comment lists? → A: "所有留言" shows first 100 comments only. When scrolling to bottom, load and display more (infinite scroll).
- Q: Do filtered pattern lists use the same pagination behavior? → A: Yes, all filters (repeat commenters, night-time commenters, etc.) show first 100 matching comments with infinite scroll.
- Q: Can the analyst close or hide the right panel? → A: No, panel is always visible. Initially shows all comments by default, filters reload content.
- Q: How many comments should be loaded per infinite scroll batch? → A: 100 comments per batch (same as initial load).
- Q: Should the currently selected filter be visually highlighted? → A: Yes, highlight selected filter with background color or border.
- Q: What layout should be used for displaying comments in the right panel? → A: Reference the existing "完整留言" modal (id="commentModal") layout.

## User Scenarios & Testing *(mandatory)*

### User Story 0 - View All Comments (Priority: P0)

An analyst viewing a video's analysis page wants to immediately see all comments for the video in chronological order to get a complete picture before applying any pattern filters.

**Why this priority**: This is the foundation feature that provides the default view. All filtering functionality builds on top of this base case.

**Independent Test**: Can be fully tested by navigating to any video analysis page and verifying the right panel displays all comments sorted newest to oldest by default, with "所有留言" shown at the top of the left side pattern list.

**Acceptance Scenarios**:

1. **Given** a video with comments loaded, **When** the analyst views the Video Analysis page, **Then** the right panel displays the first 100 comments for the video sorted from newest to oldest
2. **Given** the Comments Pattern block is visible, **When** the analyst views the left side pattern list, **Then** "所有留言" appears at the top of the list and is highlighted as the default selected filter
3. **Given** a filter is currently active, **When** the analyst clicks "所有留言", **Then** the right panel reloads showing the first 100 comments sorted newest to oldest and "所有留言" becomes highlighted
4. **Given** the analyst has scrolled to the bottom of the comment list, **When** the bottom is reached, **Then** the system loads and displays the next batch of comments (infinite scroll)

---

### User Story 1 - View Repeat Commenters Statistics (Priority: P1)

An analyst viewing a video's analysis page wants to quickly identify videos with unusual commenting patterns, specifically accounts that post multiple times on the same video, which may indicate coordinated behavior or spam.

**Why this priority**: Repeat commenting is the most straightforward pattern to detect and often indicates suspicious activity. This provides immediate investigative value without requiring additional data processing.

**Independent Test**: Can be fully tested by navigating to any video analysis page with comments and verifying the "重複留言者有 X 個 (Y%)" statistic displays correctly based on comment data.

**Acceptance Scenarios**:

1. **Given** a video with comments data loaded, **When** the analyst views the Comments Pattern block, **Then** the system displays "重複留言者有 X 個 (Y%)" where X is the count of unique commenter IDs with 2 or more comments, and Y is the percentage relative to total unique commenters
2. **Given** the repeat commenters statistic is displayed, **When** the analyst clicks on the statistic text, **Then** a scrollable panel appears on the right side showing the list of comments from repeat commenters with commenter ID and timestamp in GMT+8

---

### User Story 2 - View Night-time High-Frequency Commenters Statistics (Priority: P2)

An analyst investigating coordinated disinformation campaigns wants to identify commenters whose activity patterns suggest they may be operating from different time zones or using automated systems, indicated by >50% of their comments being posted during night hours (across all channels they comment on).

**Why this priority**: Night-time commenting patterns can reveal coordinated behavior or bot activity. However, this requires cross-channel analysis making it more complex than P1.

**Independent Test**: Can be tested by verifying the "夜間高頻留言者有 X 個 (Y%)" statistic appears and clicking it displays the correct filtered comment list.

**Acceptance Scenarios**:

1. **Given** comment data includes timestamps and commenter IDs across multiple channels, **When** the analyst views the Comments Pattern block, **Then** the system displays "夜間高頻留言者有 X 個 (Y%)" where X is the count of unique commenter IDs whose total comments are >50% posted during night hours (defined as 01:00-05:59 GMT+8), and Y is the percentage relative to total unique commenters on this video
2. **Given** the night-time commenters statistic is displayed, **When** the analyst clicks on the statistic text, **Then** the right panel shows comments from these commenters with commenter ID and timestamp in GMT+8

---

### User Story 3 - View Aggressive Commenters Placeholder (Priority: P3)

An analyst wants to see a placeholder for future aggressive commenter detection functionality while the manual review process is being established.

**Why this priority**: This requires manual review/classification before implementation. The UI placeholder ensures the design accommodates this future feature.

**Independent Test**: Can be tested by verifying the statistic displays with "X" placeholder and that clicking it shows an empty or placeholder state in the right panel.

**Acceptance Scenarios**:

1. **Given** the video analysis page is loaded, **When** the analyst views the Comments Pattern block, **Then** the system displays "高攻擊性留言者有 X 個 (Y%)" with "X" as a literal placeholder character
2. **Given** the aggressive commenters statistic is displayed, **When** the analyst clicks on the statistic text, **Then** the right panel displays a message indicating this feature is pending manual review implementation

---

### User Story 4 - View Simplified Chinese Commenters Placeholder (Priority: P3)

An analyst wants to see a placeholder for future simplified Chinese commenter detection functionality while the language detection system is being established.

**Why this priority**: This requires language detection implementation or manual review. The UI placeholder ensures the design accommodates this future feature.

**Independent Test**: Can be tested by verifying the statistic displays with "X" placeholder and that clicking it shows an empty or placeholder state in the right panel.

**Acceptance Scenarios**:

1. **Given** the video analysis page is loaded, **When** the analyst views the Comments Pattern block, **Then** the system displays "簡體中文留言者有 X 個 (Y%)" with "X" as a literal placeholder character
2. **Given** the simplified Chinese commenters statistic is displayed, **When** the analyst clicks on the statistic text, **Then** the right panel displays a message indicating this feature is pending language detection implementation

---

### Edge Cases

- What happens when a video has no comments? (Display all statistics as "0 個 (0%)")
- What happens when a video has comments but no repeat commenters? (Display "重複留言者有 0 個 (0%)")
- What happens when comment timestamps are missing or invalid for night-time analysis? (Exclude those comments from night-time calculation)
- What happens when the right panel is already showing a different comment list? (Replace with the newly selected pattern's comments, showing first 100)
- What happens when clicking the same statistic twice? (Reload the same filtered view from the beginning, showing first 100)
- How does the system handle very large comment lists (1000+)? (Show first 100, then infinite scroll loads more when reaching bottom)
- What happens when a commenter has exactly 50% night-time comments? (Not included in night-time high-frequency, requires >50%)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display a Comments Pattern block on the Video Analysis page with "所有留言" at the top, followed by four pattern statistics: repeat commenters, night-time high-frequency commenters, aggressive commenters, and simplified Chinese commenters
- **FR-001c**: System MUST visually highlight the currently selected filter in the left side pattern list using background color or border to indicate which view is displayed in the right panel
- **FR-001a**: System MUST display all comments in the right panel by default when the Video Analysis page loads, sorted from newest to oldest, showing the first 100 comments; the right panel is always visible and cannot be closed
- **FR-001b**: System MUST implement infinite scroll in the right panel that loads additional comments in batches of 100 when the analyst scrolls to the bottom of the current list, applicable to both "所有留言" and all pattern filters
- **FR-002**: System MUST calculate and display the count of repeat commenters, defined as unique commenter IDs with 2 or more comments on the current video
- **FR-003**: System MUST calculate and display the percentage of repeat commenters relative to total unique commenters on the current video, formatted as "(Y%)" where Y is rounded to the nearest whole number
- **FR-004**: System MUST calculate and display the count of night-time high-frequency commenters, defined as unique commenter IDs where >50% of their total comments across all channels are posted between 01:00-05:59 GMT+8
- **FR-005**: System MUST calculate and display the percentage of night-time high-frequency commenters relative to total unique commenters on the current video
- **FR-006**: System MUST display "高攻擊性留言者有 X 個 (Y%)" with literal character "X" for count and percentage until manual review classification is implemented
- **FR-007**: System MUST display "簡體中文留言者有 X 個 (Y%)" with literal character "X" for count and percentage until language detection is implemented
- **FR-008**: System MUST make "所有留言" and each pattern statistic text clickable to trigger display of corresponding comment list
- **FR-009**: System MUST display clicked statistic's comment list in a right side panel with scrollable overflow, initially showing the first 100 comments matching the selected pattern
- **FR-010**: System MUST show each comment in the list with commenter ID and timestamp formatted as "YYYY/MM/DD HH:MM (GMT+8)"
- **FR-011**: System MUST display comment content in the right panel list using the same layout format as the existing "完整留言" modal (id="commentModal")
- **FR-012**: System MUST handle videos with zero comments by displaying all statistics as "0 個 (0%)"
- **FR-013**: System MUST exclude comments with missing or invalid timestamps from night-time calculations
- **FR-014**: System MUST reload the right panel content when any statistic is clicked, replacing current view with the first 100 comments of the newly selected pattern, and update the visual highlight to indicate the newly selected filter
- **FR-015**: System MUST sort all comment lists from newest to oldest based on comment timestamp

### Key Entities

- **Commenter Pattern**: Represents aggregated statistics about commenting behavior, including total count of commenters matching a pattern and percentage relative to video's total unique commenters
- **Comment**: Individual comment entity with commenter ID, content, timestamp, and video association
- **Commenter Activity Profile**: Cross-channel aggregation of a commenter's activity used to calculate night-time frequency (percentage of total comments posted during night hours)

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Analysts can identify repeat commenter patterns on a video within 5 seconds of loading the Video Analysis page
- **SC-002**: The Comments Pattern block displays statistics with accurate counts and percentages matching database query results with 100% accuracy
- **SC-003**: Clicking any statistic displays the corresponding comment list in the right panel within 2 seconds
- **SC-004**: The right panel loads the first 100 comments within 2 seconds, and subsequent infinite scroll batches (100 comments each) load within 1 second of reaching bottom
- **SC-005**: All timestamps display consistently in GMT+8 timezone across the interface
- **SC-006**: Analysts can quickly scan the comment list UI to identify commenter IDs and posting times without horizontal scrolling

## Assumptions

- **A-001**: Comment data includes commenter ID, content, timestamp, and video/channel association in the existing database
- **A-002**: Night hours are defined as 01:00-05:59 GMT+8 (approximately 5 hours)
- **A-003**: The Video Analysis page already has a Comments Pattern block or section where these statistics can be added
- **A-004**: Clicking behavior follows standard web interaction patterns (single click to activate)
- **A-005**: The right panel is always visible and cannot be closed; it updates content when different statistics are selected
- **A-006**: Percentage calculations round to nearest whole number for display purposes
- **A-007**: For night-time high-frequency calculation, a commenter needs at least 2 total comments to be eligible (to avoid 100% from single comment edge case)
- **A-008**: The wireframe's green color scheme is for illustration purposes; actual implementation will follow existing application design system
- **A-009**: An existing "完整留言" modal (id="commentModal") exists with a comment display layout that can be referenced for the right panel comment list format

## Out of Scope

- Automatic detection or classification of aggressive commenters (manual review required first)
- Automatic detection or classification of simplified Chinese language in comments (language detection system required first)
- Filtering or sorting within the right panel comment list
- Exporting comment lists to external formats
- Real-time updates when new comments are added to the video
- Comparative analysis across multiple videos
- Historical trend analysis of commenter patterns over time
