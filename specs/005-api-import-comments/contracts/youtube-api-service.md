# Service Contract: YouTubeApiService

**Service**: `App\Services\YouTubeApiService`
**Type**: PHP Service Class
**Purpose**: Manage YouTube API v3 authentication and comment fetching operations
**Feature**: YouTube API Comments Import

---

## Overview

The `YouTubeApiService` is responsible for:
1. Authenticating with YouTube API v3 using Google API Client
2. Fetching preview comments (5 samples)
3. Fetching all comments for a video
4. Handling YouTube API errors and rate limiting
5. Parsing and validating API responses

**Isolation**: This service is completely independent of `UrtubeapiService`. No code sharing except through public models/exceptions.

---

## Constructor

```php
public function __construct()
```

**Behavior**:
- Initialize Google Client with YouTube API v3 credentials from `.env`
- Set timeout to 30 seconds
- Set maximum retries for API calls

**Throws**:
- `Exception`: If YouTube API credentials not found in `.env`

**Example**:
```php
$service = new YouTubeApiService();
```

---

## Public Methods

### 1. fetchPreviewComments

```php
public function fetchPreviewComments(string $videoId, int $limit = 5): array
```

**Purpose**: Fetch sample comments for preview without persisting to database.

**Parameters**:
- `$videoId` (string, required): YouTube video ID
- `$limit` (int, optional): Number of comments to fetch (default: 5)

**Returns**:
```php
[
    'comments' => [
        [
            'comment_id' => 'Ug_abc123...',
            'text' => 'Great video!',
            'author_channel_id' => 'UCxyz...',
            'video_id' => 'dQw4w9WgXcQ',
            'like_count' => 5,
            'published_at' => '2025-11-10T12:00:00Z',
            'is_reply' => false,
            'parent_comment_id' => null,
            'reply_count' => 0
        ],
        // ... up to 5 comments
    ],
    'has_more' => true,
    'total_comments' => 1250
]
```

**Behavior**:
- Query YouTube API `commentThreads.list` with `order=time` (newest first)
- Limit to `$limit` top-level comments
- Include reply count for each comment
- Do NOT fetch actual replies (use separate method)

**Throws**:
- `YouTubeApiException`: If video not found, API error, or invalid API key
- `InvalidVideoIdException`: If videoId format is invalid

**Side Effects**: None (read-only)

**Example**:
```php
try {
    $preview = $service->fetchPreviewComments('dQw4w9WgXcQ');
    foreach ($preview['comments'] as $comment) {
        echo $comment['text'];
    }
} catch (YouTubeApiException $e) {
    echo "Error: " . $e->getMessage();
}
```

---

### 2. fetchAllComments

```php
public function fetchAllComments(
    string $videoId,
    ?string $afterDate = null,
    callable $progressCallback = null
): array
```

**Purpose**: Fetch all comments for a video, with optional filtering for incremental imports.

**Parameters**:
- `$videoId` (string, required): YouTube video ID
- `$afterDate` (string|null, optional): ISO 8601 timestamp; fetch only comments published after this date (for incremental updates)
- `$progressCallback` (callable|null, optional): Callback function called after each batch fetched: `fn($totalFetched, $currentBatch) => void`

**Returns**:
```php
[
    'comments' => [
        [
            'comment_id' => 'Ug_abc123...',
            'text' => 'Comment text',
            'author_channel_id' => 'UCxyz...',
            'video_id' => 'dQw4w9WgXcQ',
            'like_count' => 5,
            'published_at' => '2025-11-10T12:00:00Z',
            'is_reply' => false,
            'parent_comment_id' => null
        ],
        [
            'comment_id' => 'UgxE_reply123...',
            'text' => 'Reply text',
            'author_channel_id' => 'UCreply...',
            'video_id' => 'dQw4w9WgXcQ',
            'like_count' => 0,
            'published_at' => '2025-11-10T13:00:00Z',
            'is_reply' => true,
            'parent_comment_id' => 'Ug_abc123...'
        ]
    ],
    'total_fetched' => 1250,
    'batches' => 7,  // number of API batches
    'last_comment_timestamp' => '2025-11-10T01:00:00Z'
]
```

**Behavior**:
1. Query `commentThreads.list` with `order=time` (newest first)
2. For each top-level comment, recursively fetch all nested replies (all levels)
3. Flatten all comments (top-level + all replies) into single array
4. If `$afterDate` provided, **client-side filtering** (fetch all, then filter)
5. Call `$progressCallback` after each batch of 20 comments
6. Handle pagination automatically (loop until no more pages)

**Throws**:
- `YouTubeApiException`: If API error or quota exceeded
- `InvalidVideoIdException`: If videoId format invalid
- `InvalidDateException`: If afterDate format invalid

**Side Effects**: None (read-only)

**Error Handling**:
- If API returns 403 (quota exceeded), throw `YouTubeApiException` with message: "YouTube API quota exceeded"
- If API returns 404 (video not found), throw `VideoNotFoundException`
- If API returns 401 (invalid credentials), throw `AuthenticationException`

**Example**:
```php
// Fetch all comments with progress tracking
$comments = $service->fetchAllComments(
    'dQw4w9WgXcQ',
    afterDate: '2025-11-10T00:00:00Z',
    progressCallback: function($total, $batch) {
        echo "Fetched {$total} comments in batch of {$batch}\n";
    }
);

// Full recursive hierarchy in $comments['comments']
foreach ($comments['comments'] as $comment) {
    if ($comment['is_reply']) {
        echo "  └─ Reply to {$comment['parent_comment_id']}: {$comment['text']}";
    } else {
        echo "├─ {$comment['text']}";
    }
}
```

---

### 3. validateVideoId

```php
public function validateVideoId(string $videoId): bool
```

**Purpose**: Validate that a string is a valid YouTube video ID format.

**Parameters**:
- `$videoId` (string): Video ID to validate

**Returns**:
- `true` if valid format (11 alphanumeric characters, no special chars)
- `false` otherwise

**Side Effects**: None

**Example**:
```php
if (!$service->validateVideoId($videoId)) {
    throw new InvalidVideoIdException("Invalid video ID format");
}
```

---

### 4. logOperation

```php
public function logOperation(
    string $traceId,
    string $operation,
    int $commentCount,
    string $status = 'success',
    ?string $error = null
): void
```

**Purpose**: Log API operations for observability (Observable Systems principle).

**Parameters**:
- `$traceId` (string): Unique trace ID for request
- `$operation` (string): Operation type (e.g., 'preview_fetch', 'full_fetch', 'incremental_fetch')
- `$commentCount` (int): Number of comments fetched
- `$status` (string): 'success', 'partial', 'error'
- `$error` (string|null): Error message if status is 'error'

**Behavior**:
- Write structured JSON log to Laravel logs with timestamp, operation type, comment count
- Include trace ID for audit trail
- Do NOT throw exceptions

**Example**:
```php
$service->logOperation(
    'trace-abc123',
    'full_fetch',
    1250,
    'success'
);
```

---

## Exception Classes

### YouTubeApiException

```php
class YouTubeApiException extends Exception
```

Base exception for all YouTube API errors.

**Messages**:
- "Invalid API key or authentication failed"
- "YouTube API quota exceeded"
- "Video not found on YouTube"
- "Invalid video ID format"
- "API response format invalid"

---

### VideoNotFoundException

```php
class VideoNotFoundException extends YouTubeApiException
```

Thrown when video ID not found on YouTube.

---

### AuthenticationException

```php
class AuthenticationException extends YouTubeApiException
```

Thrown when YouTube API credentials are invalid or missing.

---

## Contract Tests

**Test File**: `tests/Unit/Services/YouTubeApiServiceTest.php`

### Test Cases

1. **Valid Preview Fetch**
   - Given: Valid video ID with 20+ comments
   - When: `fetchPreviewComments('dQw4w9WgXcQ')`
   - Then: Returns array with 5 comments, has_more=true, total_comments >= 5

2. **Preview with Fewer Comments**
   - Given: Valid video ID with 3 total comments
   - When: `fetchPreviewComments('dQw4w9WgXcQ', limit: 5)`
   - Then: Returns array with 3 comments, has_more=false

3. **Full Fetch with Replies**
   - Given: Valid video ID with top-level and reply comments
   - When: `fetchAllComments('dQw4w9WgXcQ')`
   - Then: Flattened array includes all comments + all replies with parent_comment_id set

4. **Incremental Fetch**
   - Given: afterDate = '2025-11-01T00:00:00Z'
   - When: `fetchAllComments('dQw4w9WgXcQ', afterDate: ...)`
   - Then: Only comments published after date returned

5. **Invalid Video ID**
   - Given: videoId = '123' (too short)
   - When: `fetchPreviewComments('123')`
   - Then: Throws `InvalidVideoIdException`

6. **Video Not Found**
   - Given: videoId = 'invalidxxxxxxxxx' (valid format, doesn't exist)
   - When: `fetchPreviewComments('invalidxxxxxxxxx')`
   - Then: Throws `VideoNotFoundException`

7. **API Quota Exceeded**
   - Given: Google API returns 403 quota error
   - When: `fetchAllComments(...)`
   - Then: Throws `YouTubeApiException` with message containing "quota"

8. **Progress Callback**
   - Given: progressCallback function
   - When: `fetchAllComments(...)` fetches 100 comments
   - Then: Callback invoked multiple times with cumulative count

9. **Valid ID Format**
   - Given: videoId = 'dQw4w9WgXcQ'
   - When: `validateVideoId('dQw4w9WgXcQ')`
   - Then: Returns true

10. **Invalid ID Format**
    - Given: videoId = 'not-a-valid-id'
    - When: `validateVideoId('not-a-valid-id')`
    - Then: Returns false

---

## Integration Points

### YouTube API v3 SDK

Uses `Google\Client` and `Google\Service\YouTube`:

```php
$client = new Google\Client();
$client->setApplicationName('DISINFO_SCANNER');
$client->setDeveloperKey(env('YOUTUBE_API_KEY'));

$youtube = new Google\Service\YouTube($client);
$response = $youtube->commentThreads->listCommentThreads(
    $videoId,
    ['part' => 'snippet,replies'],
    ['order' => 'time', 'maxResults' => 20]
);
```

### Models & Database

- Uses `Comment` model to insert fetched comments
- Uses `Video` model to check existence
- Uses `Channel` model to update metadata
- Uses `Author` model to verify author exists

### Controllers

- Called by `YouTubeApiImportController` to fetch comments
- Results passed to controller for persistence layer

---

## Configuration

**Environment Variables**:
```env
YOUTUBE_API_KEY=AIzaSyD...  # YouTube Data API v3 key from Google Cloud Console
YOUTUBE_API_TIMEOUT=30      # Optional: timeout in seconds (default 30)
```

**Error Handling**: Service logs all errors to Laravel's default logger.

---

**Status**: ✅ Contract finalized, ready for implementation
