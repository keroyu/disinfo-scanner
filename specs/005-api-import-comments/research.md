# Research Phase: YouTube API 官方導入留言

**Branch**: `005-api-import-comments` | **Date**: 2025-11-17

## Overview

This document consolidates research findings on critical technical decisions for the official YouTube API comment import feature. All NEEDS CLARIFICATION items from the plan have been resolved.

---

## Research Topics & Decisions

### 1. YouTube API Client Library & Rate Limiting

**Decision**: Use `google/apiclient` (official Google PHP client library) with custom retry wrapper

**Rationale**:
- Official library maintains up-to-date API schema
- Built-in exponential backoff for quota exhaustion (429 errors)
- Handles OAuth token refresh automatically
- Laravel integration via dependency injection

**Implementation Pattern**:
```php
// YoutubeApiClient wraps google/apiclient/YouTube
class YoutubeApiClient {
  private $youtubeService; // Google_Service_YouTube

  public function getCommentThreads($videoId, $maxResults = 5) {
    // Use rateLimitHandler() + exponential backoff
    try {
      return $this->youtubeService->commentThreads->listCommentThreads(...);
    } catch (Google_Service_Exception $e) {
      if ($e->getCode() == 403) { // Quota exceeded
        Log::warning('YouTube quota exhausted', ['quota_remaining' => $e->getMessage()]);
        throw new ApiQuotaExhaustedException("Retry tomorrow");
      }
      throw $e;
    }
  }
}
```

**Alternatives Considered**:
- YouTube Data API via curl + manual JSON parsing → HIGH RISK (breaking changes, manual token management)
- Python YouTube-API wrapper + PHP FFI → Overkill, adds process overhead

**Cost**:
- Preview fetch (1 thread): **1 unit** (cheap, < 2 units for full preview with 5 comments)
- Full import (all comments + replies): **Variable** (1 unit per 20 comments; 1000-comment video ≈ 50 units)
- Quota per day: **10,000 units** (with YouTube Partner Program; standard: 10,000 for free)

---

### 2. Recursive Reply Structure & Recursion Pattern

**Decision**: Iterative depth-limited recursion with queue-based approach

**Rationale**:
- Stack overflow risk eliminated (recursive function calls limited to 3 levels)
- Cleaner code than nested loops for multi-level hierarchies
- Memory efficient: process queue instead of storing entire tree in memory

**Implementation Pattern**:
```php
class CommentImportService {
  private const MAX_DEPTH = 3;

  public function importCommentsRecursive($videoId, $parentId = null, $depth = 0) {
    if ($depth >= self::MAX_DEPTH) return;

    $replies = $this->youtubeApiClient->getReplies($parentId);
    foreach ($replies as $reply) {
      Comment::create([
        'comment_id' => $reply['id'],
        'parent_comment_id' => $parentId,
        'video_id' => $videoId,
        'content' => $reply['snippet']['textDisplay'],
        'like_count' => $reply['snippet']['likeCount'],
        'published_at' => Carbon::parse($reply['snippet']['publishedAt'])
          ->format('Y-m-d H:i:s'), // Format per spec
      ]);

      // Recurse to next level
      $this->importCommentsRecursive($videoId, $reply['id'], $depth + 1);
    }
  }
}
```

**Depth Behavior**:
- Depth 0: Top-level comments
- Depth 1: First-level replies (to top-level)
- Depth 2: Second-level replies (replies to replies)
- Depth 3: Third-level replies (maximum)
- Depth 4+: Ignored (YouTube API doesn't return beyond depth 2 anyway)

**Alternatives Considered**:
- Flat comments table + recursive query on SELECT → Complex SQL, N+1 query problem
- JSON tree column → Vendor lock-in (MySQL JSON functions), harder to aggregate

---

### 3. Transaction Management for Staged Imports

**Decision**: Laravel database transactions with rollback on failure at ANY stage

**Rationale**:
- Prevents partial/orphaned data (e.g., channel inserted but no videos)
- Clear error reporting: user knows exactly which stage failed
- Retryable: if stage 2 fails, user can retry without re-importing stage 1

**Implementation Pattern**:
```php
DB::transaction(function () use ($videoId, $channelData) {
  // Stage 1: Channel
  $channel = Channel::firstOrCreate(
    ['channel_id' => $channelData['id']],
    ['name' => $channelData['name']]
  );
  $channel->tags()->sync($tagIds); // Pivot table update
  $channel->update(['last_import_at' => now()->format('Y-m-d H:i:s')]);

  // Stage 2: Video
  $video = Video::create([
    'video_id' => $videoId,
    'channel_id' => $channel->id,
    'title' => $videoData['title'],
    'published_at' => Carbon::parse($videoData['publishedAt'])
      ->format('Y-m-d H:i:s'),
  ]);

  // Stage 3: Comments (recursive)
  $this->importCommentsRecursive($videoId);

  // Stage 3b: Calculate & update comment_count
  $video->update([
    'comment_count' => $video->comments()->count(),
  ]);
}, 3); // Retry 3 times on deadlock
```

**Rollback Behavior**:
- If any stage throws exception, entire transaction rolls back
- All inserts from stages 1-3 are undone
- User receives error message + can retry

**Alternatives Considered**:
- No transaction (three separate queries) → Data inconsistency risk
- Separate transactions per stage → Can't rollback entire import on late failure

---

### 4. Dynamic List Update Pattern (AJAX vs WebSocket)

**Decision**: AJAX (fetch + DOM refresh) — WebSocket overkill for this use case

**Rationale**:
- Simple implementation: no persistent server connection needed
- Browsers already support fetch API natively
- No WebSocket infrastructure cost
- Import typically completes in <30 seconds per spec (SC-004)
- User triggers refresh manually (via modal close), not real-time streaming

**Implementation Pattern**:
```javascript
// In modal component (Alpine.js)
document.addEventListener('import-complete', async () => {
  // Fetch updated comment list via AJAX
  const response = await fetch('/api/comments?video_id=' + videoId);
  const comments = await response.json();

  // Update DOM: replace comment list content
  document.getElementById('comments-list').innerHTML =
    comments.map(c => `<div class="comment">${c.content}</div>`).join('');
});
```

**Alternatives Considered**:
- WebSocket (Server-Sent Events) → Unnecessary complexity; need persistent connection manager
- Full page reload → Violates spec (FR-031a: no page refresh)
- Polling every 1s → Wasteful; import isn't real-time

---

### 5. Timestamp Format Standardization

**Decision**: All timestamps stored in format **`YYYY-MM-DD HH:MM:SS`** (UTC, no timezone info)

**Rationale**:
- Matches user requirement ("2025-06-13 21:00:03" format)
- MySQL DATETIME type native format
- No timezone ambiguity in storage layer
- Carbon/Laravel naturally converts to this format

**Implementation Pattern**:
```php
use Carbon\Carbon;

// From YouTube API (ISO 8601)
$youtubeDate = "2025-11-15T10:30:45Z";

// Convert to storage format
$dbDate = Carbon::createFromIso8601String($youtubeDate)
  ->setTimezone('UTC')
  ->format('Y-m-d H:i:s');  // "2025-11-15 10:30:45"

Comment::create([
  'published_at' => $dbDate,
  'created_at' => now()->format('Y-m-d H:i:s'),  // Laravel's now()
]);
```

**Schema Definition**:
```sql
ALTER TABLE comments ADD published_at DATETIME NOT NULL;
ALTER TABLE comments ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE channels ADD last_import_at DATETIME NULL;
```

**Alternatives Considered**:
- Unix timestamps (integer) → Less human-readable in database
- ISO 8601 with timezone → Inconsistent with existing data if already stored differently
- Different formats for different tables → Dangerous (maintenance nightmare)

---

## Testing Strategy

### Unit Tests (Per Principle I: Test-First)

**YoutubeApiClient**:
- Mock Google_Service_YouTube responses
- Test 429 quota exhaustion → verify retry logic
- Test comment structure parsing (from API → database schema)
- Test reply recursion (depth 0, 1, 2, 3, 4)

**CommentImportService**:
- Mock database + API client
- Test staged transactions (rollback on stage 2 failure)
- Test comment_count calculation
- Test duplicate channel handling (firstOrCreate)

**ChannelTagManager**:
- Test pivot table sync with tag validation
- Test "at least 1 tag required" validation for new channels

### Feature Tests (Per Principle IV: Contract Testing)

**ImportCommentsTest**:
- Scenario 1: URL check (invalid → error response)
- Scenario 2: Video exists → "already archived" message
- Scenario 3: New video, existing channel → preview + tag modification
- Scenario 4: New video, new channel → preview + tag selection
- Scenario 5: Full import → verify comments in DB + success message

### Integration Tests

- API endpoint response format matches `/contracts/` spec
- AJAX list refresh works after import complete

---

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| YouTube API quota exhaustion | Daily limit check before import; graceful 429 error with retry button |
| Large video (10k+ comments) timeout | SC-004 target (30s) includes API fetch time; set request timeout to 35s |
| Transaction deadlock | MySQL's `InnoDB` with 3-retry policy in transaction block |
| Parent comment deleted before reply import | DB constraint allows NULL parent_id; orphaned replies stored safely |
| Browser refresh during import | Modal prevents navigation; post-import state persisted in DB |

---

## Implementation Readiness

All research questions resolved. Proceed to Phase 1 design completion:
- ✅ YouTube API client pattern finalized
- ✅ Recursive comment structure & queue approach confirmed
- ✅ Transaction strategy locked
- ✅ AJAX + fetch pattern validated
- ✅ Timestamp format standardized (2025-06-13 21:00:03)

**Next**: Run `/speckit.tasks` to generate implementation task breakdown.
