# Feature Specification: Comments List View

**Feature Branch**: `004-comments-list`
**Created**: 2025-11-16
**Status**: Draft
**Input**: Add comments list feature with search, filtering, and navigation to channels and videos with comment links. Display 500 comments per page. Support sorting by likes and comment date (ascending/descending).

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

### User Story 1 - View Comments List (Priority: P1)

Analysts need to browse a comprehensive list of all comments collected from YouTube videos. The comments list view provides a dedicated interface showing all comments alongside their metadata, allowing analysts to review community sentiment and engagement patterns.

**Why this priority**: This is the foundational feature that enables all downstream analytics and investigation work. Without viewing comments, analysts cannot perform their core function of monitoring and analyzing YouTube discussions.

**Independent Test**: Can be fully tested by navigating to the comments list page and verifying comments are displayed with all relevant metadata (channel name, video title, commenter ID, comment content, comment date).

**Acceptance Scenarios**:

1. **Given** the analyst is logged in and comments have been imported, **When** they navigate to the Comments List page, **Then** they see a list of comments with channel name, video title, commenter ID, comment content, comment date, and like count displayed
2. **Given** the comments list is displayed, **When** no comments exist in the database, **Then** they see a message indicating no comments are available
3. **Given** the comments list is displayed with comments, **When** viewing the page, **Then** pagination shows exactly 500 comments per page with navigation controls to move between pages

---

### User Story 2 - Search Comments by Content (Priority: P1)

Analysts need to quickly locate comments matching specific keywords. Multi-field search across channel names, video titles, commenter IDs, and comment content allows analysts to find relevant discussions efficiently.

**Why this priority**: Keyword search is essential for investigators to find comments related to specific topics, disinformation narratives, or actors of interest. This enables efficient filtering of large datasets.

**Independent Test**: Can be fully tested by entering a keyword in the search field and verifying that only comments matching the keyword (in channel name, video title, commenter ID, or content) are displayed.

**Acceptance Scenarios**:

1. **Given** the comments list is displayed, **When** an analyst enters a keyword in the search field, **Then** the list filters to show only comments where the keyword appears in channel name, video title, commenter ID, or comment content
2. **Given** a search is performed, **When** no comments match the keyword, **Then** a "no results" message is displayed
3. **Given** a search is performed, **When** the keyword appears in multiple fields (e.g., channel name and comment content), **Then** those comments are included in the results
4. **Given** a search has been performed, **When** the analyst clears the search field, **Then** the list displays all comments again

---

### User Story 3 - Filter Comments by Date Range (Priority: P1)

Analysts need to focus on comments within specific time periods to investigate emerging narratives or temporal patterns in discussions. Date range filtering enables analysts to correlate comment activity with real-world events.

**Why this priority**: Temporal analysis is critical for disinformation detection. Analysts need to see comments posted around specific dates to understand narrative spread and timing.

**Independent Test**: Can be fully tested by selecting a date range and verifying that only comments within the selected date range are displayed.

**Acceptance Scenarios**:

1. **Given** the comments list is displayed, **When** an analyst selects a date range using the date picker, **Then** the list filters to show only comments with comment dates within the selected range (inclusive)
2. **Given** a date range has been selected, **When** no comments exist within that date range, **Then** a "no results" message is displayed
3. **Given** a date range filter is active, **When** the analyst clears the date range, **Then** the list displays comments across all dates
4. **Given** a date range is selected, **When** the analyst also performs a keyword search, **Then** both filters are applied simultaneously (intersection of results)

---

### User Story 6 - Sort Comments by Likes (Priority: P1)

Analysts need to prioritize reviewing comments by engagement level. Sorting by like count allows them to focus on the most-liked (and potentially most influential) comments first.

**Why this priority**: Understanding which comments receive the most engagement helps analysts identify influential voices and dominant narratives in discussions.

**Independent Test**: Can be fully tested by clicking the likes column header and verifying comments are reordered by like count in ascending or descending order.

**Acceptance Scenarios**:

1. **Given** the comments list is displayed, **When** the analyst clicks the like count column header, **Then** comments are sorted by like count in ascending order (lowest to highest)
2. **Given** comments are sorted by likes in ascending order, **When** the analyst clicks the like count header again, **Then** comments are sorted in descending order (highest to lowest)
3. **Given** comments are sorted by likes, **When** filtering or searching is applied, **Then** the sort order is preserved within the filtered results

---

### User Story 7 - Sort Comments by Date (Priority: P1)

Analysts need to review comments in chronological order to understand narrative evolution over time. Sorting by comment date allows them to see the temporal progression of discussions.

**Why this priority**: Chronological analysis is essential for tracking how narratives develop and spread across comment threads over time.

**Independent Test**: Can be fully tested by clicking the comment date column header and verifying comments are reordered chronologically in ascending or descending order.

**Acceptance Scenarios**:

1. **Given** the comments list is displayed, **When** the analyst clicks the comment date column header, **Then** comments are sorted by date in ascending order (oldest first)
2. **Given** comments are sorted by date in ascending order, **When** the analyst clicks the comment date header again, **Then** comments are sorted in descending order (newest first)
3. **Given** comments are sorted by date, **When** filtering or searching is applied, **Then** the sort order is preserved within the filtered results

---

### User Story 4 - Navigate to Channel Details (Priority: P2)

Analysts need quick access to channel information when investigating comment patterns. Clicking a channel name in the comments list should navigate to the channel's main page, allowing analysts to review channel metadata and overall engagement.

**Why this priority**: This provides context about channels producing comments, helping analysts understand the source and credibility of discussions.

**Independent Test**: Can be fully tested by clicking a channel name in the comments list and verifying navigation to the correct YouTube channel main page.

**Acceptance Scenarios**:

1. **Given** a comment is displayed in the list, **When** the analyst clicks the channel name, **Then** they are navigated to the channel's YouTube main page (e.g., https://www.youtube.com/@channelname)
2. **Given** a channel name is clicked, **When** the channel URL is constructed, **Then** it uses the correct channel identifier from the comment data

---

### User Story 5 - Navigate to Video with Comment Anchor (Priority: P2)

Analysts need to view a comment within its video context to understand the discussion thread and video content. Clicking a video title should navigate to the video with the specific comment highlighted or accessible via URL parameter.

**Why this priority**: Contextualizing comments within videos helps analysts understand the discussion's relevance and verify the authenticity of comments.

**Independent Test**: Can be fully tested by clicking a video title in the comments list and verifying navigation to YouTube video with the comment_id parameter included in the URL.

**Acceptance Scenarios**:

1. **Given** a comment is displayed in the list, **When** the analyst clicks the video title, **Then** they are navigated to the YouTube video page with the comment_id parameter appended (format: https://www.youtube.com/watch?v=[VIDEO_ID]&lc=[COMMENT_ID])
2. **Given** navigation to a video occurs, **When** the URL is constructed, **Then** the video ID and comment ID are correctly extracted from the comment record
3. **Given** a video is opened with a comment_id parameter, **When** YouTube loads the page, **Then** YouTube navigates to and highlights the specified comment (this is YouTube's native behavior)

### Edge Cases

- What happens when the database contains duplicate comments?
- How does the system handle comments deleted from YouTube after they were imported?
- What is displayed when a video or channel has been deleted from YouTube but comments still exist in the database?
- How does the system handle very long comments that exceed typical display widths?
- What happens when search returns thousands of results - are there performance implications?

## Requirements *(mandatory)*

<!--
  ACTION REQUIRED: The content in this section represents placeholders.
  Fill them out with the right functional requirements.
-->

### Functional Requirements

- **FR-001**: System MUST display a paginated list of all comments with exactly 500 comments per page, showing fields: channel name, video title, commenter ID, comment content, comment date, and like count
- **FR-002**: System MUST provide pagination controls to navigate between pages (previous, next, page numbers)
- **FR-003**: System MUST provide a search input field that filters comments across multiple fields (channel name, video title, commenter ID, comment content) using keyword matching
- **FR-004**: System MUST support case-insensitive keyword search across all searchable fields
- **FR-005**: System MUST provide a date range picker that allows analysts to filter comments by comment publication date
- **FR-006**: System MUST apply date range filter as inclusive (comments on start and end dates are included)
- **FR-007**: System MUST support simultaneous application of keyword search and date range filters (results are the intersection of both filters)
- **FR-008**: System MUST allow clearing individual filters (search field, date range) to reset that filter without affecting others
- **FR-009**: System MUST render like count as a clickable column header that sorts comments by like count
- **FR-010**: System MUST toggle sort order (ascending/descending) when clicking the same sort column header twice
- **FR-011**: System MUST render comment date as a clickable column header that sorts comments by publication date
- **FR-012**: System MUST preserve sort order when filters are applied (sorted results are paginated)
- **FR-013**: System MUST render channel names as clickable links that navigate to the channel's YouTube main page
- **FR-014**: System MUST render video titles as clickable links that navigate to the YouTube video page with comment_id parameter (format: https://www.youtube.com/watch?v=[VIDEO_ID]&lc=[COMMENT_ID])
- **FR-015**: System MUST display a "Comments List" button in the main navigation alongside the existing "Channel List" button
- **FR-016**: System MUST display "no results" messaging when filters produce empty result sets

### Key Entities

- **Comment**: Represents a YouTube comment with attributes: id, video_id, channel_id, channel_name, video_title, commenter_id, content, published_at, and internal metadata (imported_at, updated_at)
- **Video**: Related entity containing video_id, title, and link to parent channel
- **Channel**: Related entity containing channel_id, channel_name, and YouTube channel URL

## Success Criteria *(mandatory)*

<!--
  ACTION REQUIRED: Define measurable success criteria.
  These must be technology-agnostic and measurable.
-->

### Measurable Outcomes

- **SC-001**: Analysts can view all imported comments in under 3 seconds on average (page load time for initial comment list view)
- **SC-002**: Keyword search returns filtered results in under 2 seconds, even with 10,000+ comments in the database
- **SC-003**: Date range filtering operates efficiently without noticeable lag when applied to 10,000+ comment datasets
- **SC-004**: Sorting by likes or date completes in under 1 second for any page of results
- **SC-005**: 100% of comments displayed have accurate channel names, video titles, commenter IDs, content, dates, and like counts
- **SC-006**: Channel and video links correctly navigate users to the intended YouTube pages
- **SC-007**: Comment lists with 10,000+ results display, filter, sort, and paginate without errors or UI degradation
- **SC-008**: Combined keyword + date range filters operate correctly and return accurate intersection of matching results
- **SC-009**: Pagination displays exactly 500 comments per page with correct total page count calculations

## Assumptions

- Comments have already been imported into the system via the comment import feature (001-comment-import)
- Like count data is available for all comments in the database
- YouTube comment IDs are available in the comment records for constructing deep links
- Video IDs are available in the comment records for constructing video links
- Channel identifiers are available for constructing channel navigation links
- The system uses a relational database capable of efficient querying and sorting on indexed fields (channel name, video title, commenter ID, comment content, comment date, like count)
- Pagination displays exactly 500 comments per page
- Comments are sorted by most recent (newest first) by default on initial page load
- Clickable column headers are the standard UI pattern for initiating sorts

## Constraints

- Comments list should remain fast even with 10,000+ records
- Pagination is fixed at 500 comments per page (not user-configurable)
- Sorting and filtering operations must complete in under 1 second
- Search and filtering should not require complex database queries that impact overall system performance
- YouTube channel and video links must follow YouTube's standard URL format
- The feature must not modify or delete comments from the database
- Sort direction toggles are limited to single-click on column headers (no separate ascending/descending buttons)
