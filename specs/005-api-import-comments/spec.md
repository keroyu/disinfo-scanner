# Feature Specification: YouTube API Comments Import

**Feature Branch**: `005-api-import-comments`
**Created**: 2025-11-16
**Status**: Draft
**Input**: User description: "新增：API導入留言的功能..."

---

## Clarifications

### Session 2025-11-20 (Channel Existence Logic)

- Q: 寫入DB前，應該如何檢查channel資料？ → A: 寫入DB時必須同時檢查「影片是否存在」和「頻道是否在DB存在」；如果頻道不存在才新增channels資料，否則只更新channels表中既有資料

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

User has a YouTube video URL that has never been imported before. The system guides the user through a complete workflow: fetch video metadata from YouTube API, select tags, preview sample comments, and import all comments with their reply threads. The entire flow ensures data consistency and provides clear feedback at each step.

**Why this priority**: This is the foundational use case. New videos require metadata capture and tag assignment before comments can be meaningfully stored. The feature provides a streamlined workflow using YouTube API for all metadata retrieval.

**Independent Test**: Can be fully tested by entering a new video URL, confirming metadata and tags, reviewing preview, and verifying all comments (including multi-level replies) are imported with correct fields and relationships.

**Acceptance Scenarios**:

1. **Given** user clicks "官方API導入" button and enters a new video URL that doesn't exist in database, **When** user clicks submit, **Then** the system calls YouTube API to fetch video title and channel name
2. **Given** YouTube API successfully returns metadata, **When** fetching completes, **Then** a dialog displays the fetched title, channel name, and a form to select tags
3. **Given** metadata dialog is displayed, **When** user reviews the metadata and selects tags, **Then** clicking "確認" proceeds to comment preview (metadata NOT yet saved to database)
4. **Given** metadata is confirmed, **When** dialog closes, **Then** system automatically displays comment preview with up to 5 sample comments (newest first) WITHOUT persisting any data to database
5. **Given** video has fewer than 5 comments, **When** preview is displayed, **Then** all available comments are shown
6. **Given** comment preview is displayed, **When** user clicks "確認導入", **Then** system begins fetching ALL remaining comments in reverse chronological order (newest first)
7. **Given** system successfully fetches all comment data, **When** fetching completes without errors, **Then** system saves to database: video metadata, tags, all comments with required fields, and all reply threads
8. **Given** comments are being fetched during full import, **When** import is in progress, **Then** user receives visual feedback (progress indicator or status message)
9. **Given** full import includes multi-level reply comments, **When** comments are stored, **Then** ALL levels of replies are recursively imported and linked to parent comments via parent_comment_id
10. **Given** full comment import completes successfully, **When** user navigates to comments list, **Then** all imported comments and replies are visible, sorted by date, with complete thread structure preserved
11. **Given** fetch fails at any point during comment retrieval, **When** error occurs, **Then** NO data is saved to database; user can retry and system will attempt fetch again
12. **Given** user cancels at any point during the flow (during metadata dialog, preview, or before import starts), **When** cancellation occurs, **Then** dialog closes and NO data is saved
13. **Given** full comment import completes and all data is saved, **When** import finishes, **Then** `channels` and `videos` tables are updated ONCE with: video_count, comment_count, first_import_at, last_import_at, created_at, updated_at (as applicable)

---

### User Story 2 - Incremental Update for Existing Video (Priority: P1)

User has already imported a video before and wants to check for new comments added since the last import without re-downloading all previous comments. The system intelligently fetches only new comments, preserves discussion threads (including replies to new comments), and uses dual stopping conditions to ensure completeness and efficiency.

**Why this priority**: Essential for keeping data fresh without duplicating effort. Videos with ongoing discussion need regular updates, and re-importing everything would be inefficient. Smart incremental logic prevents API waste and database duplication.

**Independent Test**: Can be fully tested by importing a video twice with time interval, verifying only new comments are fetched, reply threads are complete, and no duplicates are created even if API returns unexpected ordering.

**Acceptance Scenarios**:

1. **Given** a video URL already exists in database with previous comments, **When** user enters the same URL, **Then** system identifies the most recent stored comment's published_at timestamp
2. **Given** system identifies most recent comment timestamp, **When** user clicks "確認導入", **Then** a preview displays up to 5 sample NEW comments (with published_at NEWER than max stored timestamp) WITHOUT saving any data
3. **Given** video has no new comments since last import, **When** preview is displayed, **Then** preview shows message indicating no new comments available but user can still proceed
4. **Given** preview is displayed and user clicks "確認導入", **When** full fetch begins, **Then** system fetches comments in reverse chronological order (newest first) from YouTube API
5. **Given** system is fetching comments incrementally, **When** comments are being processed, **Then** system uses PRIMARY stopping condition: stop immediately when reaching a comment with published_at <= max(published_at) in database
6. **Given** PRIMARY condition is met, **When** system encounters edge cases (API ordering issues, timezone inconsistencies), **Then** SECONDARY guard: additionally track comment_id duplicates and stop if duplicate is found, ensuring no missed comments
7. **Given** system successfully fetches all new comment data, **When** fetching completes without errors, **Then** system saves to database: all new comments with required fields and ALL levels of reply threads
8. **Given** new comments have replies at multiple levels, **When** comments are stored, **Then** ALL levels of replies are recursively imported and linked to parent comments via parent_comment_id
9. **Given** fetch fails during incremental import, **When** error occurs, **Then** NO new data is saved to database; user can retry and next fetch will handle via primary/secondary stopping conditions
10. **Given** incremental import completes successfully and all new data is saved, **When** import finishes, **Then** system prevents duplicates and merges with existing comments seamlessly
11. **Given** import completes and all data is saved, **When** completion occurs, **Then** `channels` and `videos` tables are updated ONCE with: comment_count (recalculated from all comments), last_import_at, updated_at

---

### User Story 3 - Reply Comments Handling (Priority: P1)

When importing comments (both new videos and incremental updates), the system MUST recursively import ALL levels of nested replies (replies, replies to replies, replies to replies to replies, etc.). Each reply maintains correct parent-child relationship, preserving complete discussion context and thread structure.

**Why this priority**: YouTube comments have deep threading structure (multiple reply levels). Missing any level breaks discussion continuity and loses important context. Complete recursive import is essential for data integrity and usability.

**Independent Test**: Can be fully tested by importing video with multi-level reply thread (3+ levels deep) and verifying every reply at every level is stored with correct parent_comment_id linking.

**Acceptance Scenarios**:

1. **Given** a top-level comment has replies at multiple levels (replies → replies to replies → replies to replies to replies, etc.) from YouTube API, **When** the parent comment is imported, **Then** system calls YouTube API's `commentThreads` endpoint to recursively fetch ALL reply levels
2. **Given** all reply levels are fetched from API, **When** storing in database, **Then** each reply stores its parent_comment_id pointing to its direct parent (forming complete tree structure)
3. **Given** multi-level reply tree is stored, **When** comment queries execute, **Then** each reply maintains correct parent-child relationship and full thread can be reconstructed
4. **Given** a reply at ANY depth level has replies, **When** importing that reply, **Then** system recursively imports ALL its replies (no depth limit)
5. **Given** incremental update imports a top-level comment with existing and NEW replies at multiple levels, **When** storing, **Then** NEW replies at all levels are added while EXISTING replies are not duplicated

---

### User Story 4 - UI Integration (Priority: P2)

The "官方API導入" button appears in the comments interface, providing easy access to YouTube API-based comment import. Users can seamlessly import comments using official API with minimal configuration.

**Why this priority**: UX clarity is important but doesn't block core functionality. Users need to discover the feature easily and understand it's a separate import method.

**Independent Test**: Can be fully tested by verifying the button exists, is properly positioned, and that both new and existing video flows work correctly with appropriate dialogs appearing.

**Acceptance Scenarios**:

1. **Given** the comments list page is open, **When** user looks at the interface, **Then** they see the "官方API導入" button clearly visible
2. **Given** user clicks "官方API導入" button, **When** the action is triggered, **Then** a new import modal/page is presented with a form field for entering the video URL
3. **Given** user enters a new video URL and system detects it doesn't exist, **When** URL validation completes, **Then** a metadata dialog is displayed showing YouTube API-fetched title and channel name with a tag selection form
4. **Given** user reviews the metadata and selects tags, **When** user confirms the selection, **Then** the metadata and tags are saved and the system automatically displays the comment preview with up to 5 sample comments
5. **Given** user enters an existing video URL, **When** URL validation completes, **Then** the comment preview is directly displayed with up to 5 sample comments
6. **Given** preview is displayed, **When** user reviews the sample data, **Then** a "確認導入" button is available to proceed with full comment import

### Edge Cases & Error Handling

**API Errors**:
- **Metadata fetch fails** (invalid API key, quota exceeded, video not found, API error): Show error message to user; NO data saved; allow retry or cancel; user returns to URL input
- **Preview fetch fails** after successful metadata entry: Show error message; NO data saved; allow retry of preview or proceed to cancel
- **Comment fetch fails** after user clicks "確認導入": If fetch fails at ANY point (before, during, or after recursive reply fetching), NO data is saved to database; show error message; user can retry - next attempt will refetch all data from scratch (for new videos) or use primary/secondary conditions (for incremental updates)

**Data Edge Cases**:
- **Video has fewer than 5 comments**: Preview displays all available comments (not limited to 5)
- **Video has no new comments** in incremental update: Preview shows "no new comments" message but user can still proceed (flow remains consistent)
- **Malformed or invalid video URL**: URL validation rejects before API call; show error message
- **Database has comments from different source**: System treats as existing video; incremental logic prevents duplicates by comment_id and timestamp matching
- **Partially fetched comments** if process interrupted during full comment fetch: NO partial data saved; next import attempt treats as new (for new videos) or refetches from max stored timestamp (for incremental updates)

**User Actions**:
- **User cancels during metadata dialog**: Dialog closes; NO data saved; user returns to URL input; can retry or cancel entire flow
- **User cancels during preview**: Dialog closes; NO data saved (preview is not persisted); user returns to URL input; can retry or cancel
- **User closes modal/page during comment fetch**: If fetch hasn't completed, NO data saved to database; dialog closes cleanly; user can retry
- **User changes URL between steps**: Treated as new request; previous state is abandoned; NO partial data persists

## Requirements *(mandatory)*

<!--
  ACTION REQUIRED: The content in this section represents placeholders.
  Fill them out with the right functional requirements.
-->

### Functional Requirements

- **FR-001**: System MUST read YouTube API key from environment configuration (.env file)
- **FR-002**: System MUST accept a YouTube video URL as input from the user
- **FR-003**: System MUST validate whether a video already exists in the database before processing
- **FR-004**: System MUST fetch video metadata (title, channel name) from YouTube API when a new (non-existent) video URL is detected, display the fetched metadata in a dialog, and allow user to select tags before confirming the metadata entry; metadata and tags are NOT persisted to database until successful comment fetch completion
- **FR-005**: System MUST automatically initiate comment preview after video import completes, displaying up to 5 sample comments from YouTube API without persisting to database
- **FR-006**: System MUST display preview comments on the import form, allowing user to review sample data without persisting to database
- **FR-007**: System MUST provide a "確認導入" button that triggers the full import process after user reviews the preview
- **FR-008**: System MUST fetch all comments from YouTube API when user clicks "確認導入" (for both new and existing videos)
- **FR-008a**: System MUST NOT persist ANY data to database (video metadata, tags, or comments) until ALL comments and replies have been successfully fetched from YouTube API
- **FR-008b**: System MUST display video metadata and tags in preview before comment fetch, but these are only persisted to database after successful comment fetch completion
- **FR-009**: System MUST store all imported comments in the database with correct field mapping to comments table ONLY AFTER successful completion of all comment and reply fetches:
  - From YouTube API: `comment_id`, `video_id`, `author_channel_id`, `text`, `like_count`, `published_at`
  - System-generated: `parent_comment_id` (set for replies; NULL for top-level), `created_at` (import timestamp), `updated_at` (import timestamp)
- **FR-010**: System MUST recursively fetch and store all levels of reply/nested comments from the `replies.comments` section and their replies (replies to replies, etc.) to fully preserve the discussion thread structure; this recursive fetch must complete successfully before ANY data is persisted to database
- **FR-010a**: If comment fetch fails at any point during full import (including during recursive reply fetching), system MUST rollback and persist NO data to database; user can retry
- **FR-011**: System MUST identify the most recent comment timestamp in the database for existing videos to determine the incremental import starting point
- **FR-012**: System MUST fetch comments in reverse chronological order (newest first) when performing incremental updates
- **FR-013**: System MUST use timestamp-based break as PRIMARY condition: stop immediately when reaching comments older than or equal to max(published_at) in database
- **FR-014**: System MUST use comment_id duplicate detection as SECONDARY guard: additionally track if comment_id already exists to catch edge cases
- **FR-015**: System MUST stop the import process when EITHER primary (timestamp) or secondary (duplicate comment_id) condition is met
- **FR-015a**: System MUST check channel existence in database BEFORE writing any data; determines whether to INSERT or UPDATE channels table
- **FR-016**: System MUST update the related database records in `channels` and `videos` tables **AFTER completing the full comment import** with the following rules:
  - **Timing**: Perform a single update/insert operation AFTER all comments are inserted (not per-comment)
  - **Channels table - Channel does NOT exist in DB**: INSERT new channel record with: `video_count` (initialized to 1), `comment_count` (count of imported comments), `first_import_at` (import timestamp), `last_import_at` (import timestamp), `created_at` (import timestamp), `updated_at` (import timestamp)
  - **Channels table - Channel already exists in DB**: UPDATE existing channel record with: `video_count` (increment if this is a new video for the channel), `comment_count` (recalculate from all comments), `last_import_at` (import timestamp), `updated_at` (import timestamp)
  - **Videos table - Video does NOT exist in DB**: INSERT new video record with: `video_id`, `title`, `channel_id`, `published_at`, `created_at` (import timestamp), `updated_at` (import timestamp)
  - **Videos table - Video already exists in DB**: UPDATE existing video record with: `updated_at` (import timestamp)
- **FR-017**: System MUST display "官方API導入" button in the comments list page to provide access to YouTube API-based comment import
- **FR-018**: System MUST provide user feedback during the import process (e.g., progress indication, success/failure messages)
- **FR-019**: System MUST validate that all required comment fields are populated correctly before storing in the database

---

## Architecture & Code Organization

### Service Separation - Complete Independence

The YouTube API comment import feature **MUST be implemented as a completely independent service** with its own dedicated code:

- **Service**: `app/Services/YouTubeApiService.php` (handles all YouTube API operations for comment and metadata import)
- **UI**: Independent dialog/form for video metadata confirmation and comment preview
- **No Code Sharing**: This feature does not share any code with other import tools. All functionality is self-contained.

**Rationale**:
- Prevents cross-contamination and reduces regression risk
- Simplifies testing and maintenance with clear, single responsibility
- Allows independent evolution of YouTube API features without affecting other systems
- Clear separation makes it easier to debug issues and understand feature boundaries
- Reduces complexity by eliminating dependencies on other import implementations

---

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
2. User has the necessary YouTube API quota available for fetching video metadata and comments
3. The existing database schema for comments, videos, and channels tables is understood and documented elsewhere
4. Comment field mapping between YouTube API response and database schema is straightforward (documented separately)
5. Users will only attempt to import public videos where comments are enabled
6. The system has existing infrastructure for handling YouTube API errors and rate limiting
7. YouTube API v3 `videos.list` endpoint provides reliable data for video title and channel name
8. YouTube API v3 `commentThreads.list` endpoint returns comments in descending order by time (newest first)
9. This YouTube API import feature is implemented completely independently with its own dedicated service code
