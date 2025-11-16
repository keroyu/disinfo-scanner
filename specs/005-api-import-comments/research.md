# Research Phase: YouTube API Comments Import

**Date**: 2025-11-17
**Feature**: `005-api-import-comments`
**Status**: Complete - All clarifications resolved

---

## Research Findings

### 1. Structured Logging for Observable Systems

**Decision**: Implement structured JSON logging with trace IDs for all import operations.

**Rationale**:
- Aligns with Constitution Principle III (Observable Systems)
- Enables post-hoc analysis of import sessions for debugging campaign detection
- Required for audit trails tracking data source, timestamp, and record counts

**Implementation Details**:
- Use Laravel's `Log` facade with custom JSON formatter (via monolog configuration)
- Each import session gets unique trace ID (UUID)
- Log entry format:
  ```json
  {
    "timestamp": "2025-11-17T10:30:45Z",
    "trace_id": "uuid-here",
    "operation": "comment_import_start",
    "video_id": "youtube-id",
    "source": "youtube_api_v3",
    "record_count": 45,
    "status": "success|failure|partial"
  }
  ```
- Log at operation boundaries: import_start, metadata_fetch, preview_fetch, full_import_start, full_import_complete, db_transaction_commit

**Alternatives Considered**:
- Simple text logging: Lacks structured parsing for monitoring; rejected because system needs to track patterns across sessions
- Custom database logging: Over-engineered for initial MVP; can add later without rework if structured logs exist

---

### 2. YouTube API v3 PHP Client Best Practices

**Decision**: Use `google/apiclient` official package with direct HTTP error handling via Guzzle wrapper.

**Rationale**:
- Official Google package ensures API compatibility and future updates
- Well-documented, actively maintained
- Handles authentication (via .env configured API key) automatically
- Provides typing and IDE support

**Implementation Details**:
- Install via Composer: `google/apiclient` package
- Configuration in `.env`: `YOUTUBE_API_KEY=...`
- Create wrapper service class to handle:
  - API key injection from environment
  - HTTP error mapping (quota exceeded, video not found, invalid key, etc.)
  - Timeout handling (set to 30s per requirement)
  - Rate limit detection (Google Cloud API returns 429 status)
- YouTube API endpoints used:
  - `youtube.videos.list` - fetch video title and channel name (single call)
  - `youtube.commentThreads.list` - fetch top-level comments with auto-pagination
  - Recursive calls for replies (commentThreads includes `replies.comments` nested structure)

**Alternatives Considered**:
- Guzzle raw HTTP: More control but error handling becomes responsibility; rejected for higher maintenance burden
- cURL directly: Too low-level, no type safety; rejected

---

### 3. Transaction-Based Atomicity for Data Consistency

**Decision**: Use Laravel database transactions with rollback on any fetch failure. NO data persists until complete success.

**Rationale**:
- Spec requirement: "NO data is saved to database until ALL comments and replies have been successfully fetched"
- Prevents partial imports corrupting data state
- Enables retry without cleanup on failure

**Implementation Details**:
- Wrap entire import workflow in `DB::transaction()`
- Fetch workflow:
  1. `DB::transaction(function() {`
  2. Get or create channel (if new)
  3. Get or create video (if new)
  4. Fetch ALL comments from YouTube API (only after DB inserts start)
  5. Insert comments batch-by-batch
  6. Insert replies recursively
  7. Update video/channel counts
  8. `}`  - transaction auto-commits on success
- On ANY exception (API error, validation error, DB error): transaction rolls back automatically, nothing persisted
- Trace ID logged BEFORE transaction starts (for audit trail even if import fails)

**Alternatives Considered**:
- Save-as-you-go with cleanup on error: Risk of partial data if cleanup fails; rejected
- Temporary staging table: Over-engineered for scope; simple transaction sufficient

---

### 4. Recursive Comment Fetching Strategy

**Decision**: Build in-memory tree structure during fetch phase, then batch insert with proper parent_comment_id linking.

**Rationale**:
- Spec requires "ALL levels of replies (no depth limit)"
- YouTube API returns `replies.comments` as nested structure in commentThreads response
- Recursive traversal needed to handle arbitrary depth
- Building tree in memory allows transaction rollback for entire structure on failure

**Implementation Details**:

```
fetch_comments(video_id, is_incremental=false):
  if is_incremental:
    max_timestamp = get_max_published_at(video_id)

  comment_tree = []
  page_token = null

  while true:
    response = youtube_api.commentThreads.list(
      videoId=video_id,
      pageToken=page_token,
      part="snippet,replies",
      textFormat="plainText",
      maxResults=100,
      order="relevance"  # YouTube newest-first is 'relevance', oldest is 'time'
    )

    for thread in response.items:
      top_comment = thread.snippet.topLevelComment

      # Incremental: Check primary stopping condition
      if is_incremental and top_comment.published_at <= max_timestamp:
        return comment_tree  # PRIMARY STOP: reached old comments

      # Incremental: Check secondary guard
      if is_incremental and top_comment.comment_id in comment_ids_seen:
        return comment_tree  # SECONDARY STOP: found duplicate

      # Recursively fetch replies
      replies = fetch_replies_recursive(
        top_comment,
        parent_id=None,
        max_timestamp=max_timestamp,
        is_incremental=is_incremental
      )

      comment_tree.append({
        comment: top_comment,
        replies: replies
      })

    page_token = response.nextPageToken
    if not page_token:
      break  # No more pages

  return comment_tree

fetch_replies_recursive(parent_comment, parent_id, max_timestamp, is_incremental):
  replies = []

  if not parent_comment.replies:
    return replies  # No nested replies

  for reply in parent_comment.replies.comments:
    # Incremental: Check stopping conditions
    if is_incremental:
      if reply.published_at <= max_timestamp:
        return replies  # Stop at this depth
      if reply.comment_id in comment_ids_seen:
        return replies  # Duplicate found

    # Recursively fetch replies to this reply
    nested = fetch_replies_recursive(
      reply,
      parent_id=parent_comment.comment_id,
      max_timestamp=max_timestamp,
      is_incremental=is_incremental
    )

    replies.append({
      comment: reply,
      parent_id: parent_id,
      replies: nested
    })

  return replies
```

**Data Persistence** (after full fetch succeeds):
- Flatten tree via DFS traversal
- Insert comments in parent-to-child order (top-level first, then replies, respecting parent_comment_id)
- Use batch insert for performance (Laravel `insert()` method)

**Alternatives Considered**:
- Stream inserts during fetch: Risk of partial inserts on error; rejected
- Recursive DB inserts: N+1 query problem; batch insert more efficient

---

### 5. Incremental Import Edge Cases

**Decision**: Dual-condition stopping strategy (primary: timestamp, secondary: comment ID duplicate detection).

**Rationale**:
- YouTube API may return comments out of order due to pagination or concurrent updates
- Timestamp alone insufficient if API ordering changes
- Comment ID uniqueness guarantees prevents infinite loops
- Spec requires: "stop immediately when reaching a comment with published_at <= max(published_at) in database"

**Implementation Details**:
- Before each fetch cycle: Load all existing comment IDs into memory set (fast lookup)
- Primary condition: Stop loop if `comment.published_at <= max_stored_timestamp`
- Secondary condition: If comment ID exists in set, stop (guards against API ordering issues)
- Log when each condition triggers (useful for debugging edge cases)

**Alternatives Considered**:
- Timestamp only: Risk of infinite loop if API returns unexpected ordering; rejected
- ID-only: Miss new comments if timestamp boundaries not respected; rejected

---

## Technology Stack Decisions

| Component | Choice | Rationale |
|-----------|--------|-----------|
| HTTP Client | Guzzle (via google/apiclient) | Official, maintained, proper error handling |
| API Auth | Environment-based API key | Secure, configurable, no hardcoding |
| Database TX | Laravel's DB::transaction() | Built-in, reliable rollback semantics |
| Logging | Laravel Log + custom JSON formatter | Observable by design, integrates with app |
| Testing | PHPUnit + contract tests | Constitution requirement, matches Laravel standard |
| Comments recursion | In-memory tree + batch insert | Transactional atomicity, prevents partial imports |

---

## Open Questions Resolved

### Q: How to structure logging for observable systems?
**A**: Implement structured JSON logs with trace IDs at operation boundaries (start, fetch success, commit). Each import session gets UUID for audit trail.

### Q: How to handle multi-level comment replies?
**A**: Recursive tree traversal during fetch phase, batch insert after all fetches complete with proper parent_comment_id linking.

### Q: When should incremental import stop?
**A**: Primary: timestamp-based (published_at <= max_stored), Secondary: duplicate comment_id detection for edge cases.

### Q: How to ensure atomicity with NO partial data?
**A**: Wrap entire workflow in DB::transaction(), rollback on any failure, fetch all data BEFORE DB modifications.

---

## Implementation Readiness

✅ All NEEDS CLARIFICATION items resolved
✅ Technology stack identified
✅ Architecture patterns documented
✅ Ready to proceed to Phase 1 (Design & Contracts)
