# Contract: YouTubeApiService

**Module**: `app/Services/YouTubeApiService.php`
**Responsibility**: All YouTube API operations for fetching video metadata, comments, and reply threading
**Testing**: Contract tests in `tests/Contract/YouTubeApiContractTest.php`

---

## Service Contract

The `YouTubeApiService` is a concrete service (not an interface) with the following public methods. All methods use dependency injection for testability.

### Constructor

```php
public function __construct(
    YouTubeClient $youtubeClient,  // Injected: google/apiclient YouTube service
    LoggerInterface $logger         // Injected: structured JSON logger
)
```

**Parameters**:
- `$youtubeClient`: Instance of YouTube API client (from google/apiclient)
- `$logger`: PSR-3 logger for structured logging

---

## Method: `fetchVideoMetadata(string $videoId): array`

Fetches video metadata from YouTube API.

### Signature

```php
public function fetchVideoMetadata(string $videoId): array
```

### Input

- `$videoId` (string): YouTube video ID (11 alphanumeric characters)

### Output (Success)

Returns associative array:

```php
[
    'video_id' => 'dQw4w9WgXcQ',              // YouTube video ID
    'title' => 'Video Title Here',            // From videos.list snippet.title
    'channel_id' => 'UC...',                  // YouTube channel ID
    'channel_name' => 'Channel Name',         // From videos.list snippet.channelTitle
    'published_at' => '2023-01-15T10:30:00Z', // ISO 8601 timestamp
    'description' => '...',                   // Optional: video description
]
```

### Output (Error)

Throws `YouTubeApiException` with message:
- `"Video not found"` - 404 from YouTube API
- `"API quota exceeded"` - 403 quota limit
- `"Invalid API key"` - 401 authentication error
- `"API error: {status_code} {reason}"` - Generic API error

### Side Effects

- Logs operation with trace ID:
  ```json
  {
    "operation": "metadata_fetch",
    "video_id": "...",
    "status": "success",
    "timestamp": "..."
  }
  ```

### Contract Test Cases

1. Valid video ID returns complete metadata
2. Invalid video ID throws "Video not found" exception
3. Missing API key throws "Invalid API key" exception
4. Quota exceeded throws "API quota exceeded" exception
5. Response fields match YouTube API schema

---

## Method: `fetchComments(string $videoId, array $options = []): array`

Fetches all top-level comments for a video, with optional incremental filtering.

### Signature

```php
public function fetchComments(string $videoId, array $options = []): array
```

### Input

- `$videoId` (string): YouTube video ID
- `$options` (array, optional):
  - `'max_timestamp'` (string, ISO 8601): For incremental imports, fetch only comments published after this timestamp
  - `'skip_older_than'` (string, ISO 8601): Alternative to max_timestamp, same purpose
  - `'existing_comment_ids'` (array): Set of already-imported comment IDs for duplicate detection (secondary guard)

### Output (Success)

Returns associative array:

```php
[
    'comments' => [
        [
            'comment_id' => 'Ugf...',              // YouTube comment ID
            'video_id' => 'dQw4w9WgXcQ',          // Same as input
            'author_channel_id' => 'UCa...',       // YouTube channel ID of author (can be null)
            'text' => 'Great video!',              // Comment text (plaintext)
            'like_count' => 42,                    // Number of likes at fetch time
            'published_at' => '2023-01-16T08:15:00Z', // ISO 8601 timestamp
            'parent_comment_id' => null,           // NULL for top-level comments
            'replies' => [                         // Nested replies (recursive structure)
                [
                    'comment_id' => 'Ugf...',
                    'author_channel_id' => '...',
                    'text' => 'Reply to original',
                    'like_count' => 5,
                    'published_at' => '2023-01-16T09:00:00Z',
                    'parent_comment_id' => 'Ugf...',  // Points to parent
                    'replies' => [                    // Can nest further (no depth limit)
                        // ... deeper replies
                    ]
                ]
            ]
        ]
    ],
    'total_count' => 156,              // Total comments fetched (including all recursive replies)
    'stopped_by' => 'timestamp',       // 'timestamp' or 'duplicate_id' (incremental only)
    'oldest_fetched' => '2023-01-01T...',  // Timestamp of oldest comment fetched
]
```

### Output (Error)

Throws `YouTubeApiException` with message:
- `"Video not found"` - Video no longer exists or is private
- `"Comments disabled"` - Video has comments disabled
- `"API error: {message}"` - Generic API error

### Incremental Import Logic

When `$options['max_timestamp']` provided:

1. Fetch comments in reverse chronological order (newest first)
2. **Primary Condition**: Stop immediately when encountering comment with `published_at <= max_timestamp`
3. **Secondary Guard**: Also track `$options['existing_comment_ids']` and stop if duplicate found
4. Return `'stopped_by'` field to indicate which condition triggered

### Side Effects

- Logs operation with trace ID:
  ```json
  {
    "operation": "comments_fetch",
    "video_id": "...",
    "comment_count": 45,
    "stopped_by": "timestamp",
    "status": "success"
  }
  ```

### Contract Test Cases

1. Valid video with comments returns all comments with correct structure
2. Video with fewer than 100 comments returns all (no pagination needed)
3. Video with 500+ comments returns all (pagination works)
4. Replies at multiple levels (3+ deep) all returned with correct parent_comment_id
5. Video with comments disabled throws "Comments disabled"
6. Incremental: max_timestamp filters correctly (only newer comments)
7. Incremental: secondary guard stops on duplicate_id detection
8. Fields match YouTube API schema (comment_id, author_channel_id, published_at, etc.)
9. Empty video (0 comments) returns empty array
10. Reply structure preserved: parent_comment_id correctly set for all replies

---

## Method: `fetchReplies(string $commentId, array $options = []): array`

Fetches all replies to a specific comment (for recursive threading).

### Signature

```php
public function fetchReplies(string $commentId, array $options = []): array
```

### Input

- `$commentId` (string): YouTube comment ID of parent comment
- `$options` (array, optional):
  - `'max_timestamp'` (string, ISO 8601): Only fetch replies published after this timestamp (for incremental)
  - `'existing_reply_ids'` (array): Set of already-imported reply IDs

### Output (Success)

Returns associative array (same structure as comments in `fetchComments`):

```php
[
    'replies' => [
        [
            'comment_id' => 'UgfABC...',
            'author_channel_id' => 'UCdef...',
            'text' => 'This is a reply',
            'like_count' => 3,
            'published_at' => '2023-01-17T...',
            'parent_comment_id' => 'UgfABC...',  // Points to the parent comment passed in
            'replies' => [                        // Can have nested replies (recursive)
                // ... more nested replies
            ]
        ]
    ],
    'total_count' => 12,
    'stopped_by' => null  // or 'timestamp'/'duplicate_id'
]
```

### Output (Error)

Throws `YouTubeApiException` with message:
- `"Comment not found"` - Comment ID invalid or deleted
- `"API error: {message}"` - Generic error

### Note on Recursion

This method is called recursively during comment tree traversal. The tree-building happens in the service logic, not here - this method is responsible only for fetching replies to one specific comment.

### Contract Test Cases

1. Comment with replies returns all replies
2. Comment with no replies returns empty array
3. Replies maintain correct parent_comment_id
4. Multi-level nesting (replies to replies to replies) all returned
5. Incremental: max_timestamp filters correctly

---

## Exception Handling

All methods throw `YouTubeApiException` (custom exception class):

```php
// app/Exceptions/YouTubeApiException.php
class YouTubeApiException extends Exception
{
    public function __construct(
        string $message = "",
        int $httpStatus = 0,
        string $youtubeErrorCode = ""
    )
}
```

**Specific Error Codes Mapped**:

| HTTP Status | YouTube Error Code | Exception Message | Handling |
|------------|-------------------|-------------------|----------|
| 401 | AUTHENTICATION_ERROR | "Invalid API key" | Retry with new key |
| 403 | QUOTA_EXCEEDED | "API quota exceeded" | Retry later or increase quota |
| 404 | NOT_FOUND | "Video not found" or "Comment not found" | User should verify URL |
| 403 | DISABLED_COMMENTS | "Comments disabled" | Inform user |
| 429 | RATE_LIMIT | "API rate limited" | Implement backoff |
| 5xx | SERVER_ERROR | "YouTube service error" | Retry with exponential backoff |

---

## Implementation Notes

### YouTube API Client Library

Uses `google/apiclient` package with configuration:

```php
// In service constructor or factory
$client = new Google_Client();
$client->setApplicationName("DISINFO_SCANNER");
$client->setDeveloperKey(env('YOUTUBE_API_KEY'));
$youTubeService = new Google_Service_YouTube($client);
```

### API Endpoints Used

- `youtube.videos.list(part=snippet,contentDetails)`
- `youtube.commentThreads.list(part=snippet,replies)` with `textFormat=plainText`
- Pagination via `pageToken` (handled internally)

### Structured Logging

Each operation logged as JSON:

```php
$this->logger->info('youtube_api_operation', [
    'trace_id' => $traceId,
    'operation' => 'fetch_comments',
    'video_id' => $videoId,
    'result' => 'success',
    'record_count' => 45,
    'timestamp' => now()->toIso8601String(),
]);
```

---

## Testing Strategy

Contract tests verify:

1. **YouTube API Response Shapes**: Mock YouTube API responses, verify service parses correctly
2. **Error Mapping**: Each YouTube error code maps to correct exception message
3. **Field Mapping**: YouTube response fields correctly mapped to output array keys
4. **Recursive Structure**: Reply nesting preserved accurately
5. **Incremental Logic**: Primary/secondary stopping conditions work correctly
6. **Timestamp Handling**: Timestamps correctly parsed as ISO 8601 and comparable

No mocking of actual YouTube API calls in contract tests - use fixtures/VCR cassettes to record real API responses, then replay them. This ensures compatibility with future API changes.
