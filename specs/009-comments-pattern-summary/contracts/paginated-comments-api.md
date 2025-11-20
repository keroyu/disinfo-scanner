# API Contract: Paginated Comments by Pattern

**Endpoint**: `GET /api/videos/{videoId}/comments`
**Purpose**: Retrieve paginated comments filtered by pattern type
**Version**: 1.0.0
**Date**: 2025-11-20

---

## Request

### HTTP Method
```
GET
```

### URL Pattern
```
/api/videos/{videoId}/comments
```

### Path Parameters

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| videoId | string | Yes | YouTube video ID | `dQw4w9WgXcQ` |

### Query Parameters

| Parameter | Type | Required | Default | Description | Example |
|-----------|------|----------|---------|-------------|---------|
| pattern | string | No | `all` | Pattern filter type | `repeat` |
| offset | integer | No | `0` | Number of records to skip | `100` |
| limit | integer | No | `100` | Number of records to return | `100` |

**Pattern Values**:
- `all` - All comments (no filtering)
- `repeat` - Comments from repeat commenters only
- `night_time` - Comments from night-time high-frequency commenters
- `aggressive` - (Placeholder, returns empty list)
- `simplified_chinese` - (Placeholder, returns empty list)

**Constraints**:
- `offset`: Must be >= 0
- `limit`: Must be 1-100 (enforced maximum)
- Invalid `pattern` values default to `all`

### Headers

| Header | Required | Value |
|--------|----------|-------|
| Accept | No | `application/json` (default) |

### Request Body

None (GET request)

---

## Response

### Success Response (200 OK)

**Content-Type**: `application/json`

**Schema**:
```json
{
  "data": [
    {
      "comment_id": "string",
      "author_channel_id": "string",
      "author_name": "string",
      "text": "string",
      "like_count": "integer",
      "published_at": "string (formatted)",
      "is_reply": "boolean"
    }
  ],
  "meta": {
    "offset": "integer",
    "limit": "integer",
    "returned": "integer",
    "has_more": "boolean"
  }
}
```

**Example**:
```json
{
  "data": [
    {
      "comment_id": "UgzX9k7_abc123",
      "author_channel_id": "UCxyz789",
      "author_name": "John Doe",
      "text": "Great video! Very informative.",
      "like_count": 42,
      "published_at": "2025/11/20 14:35 (GMT+8)",
      "is_reply": false
    },
    {
      "comment_id": "UgzY2m8_def456",
      "author_channel_id": "UCabc456",
      "author_name": "Jane Smith",
      "text": "Thanks for sharing this content.",
      "like_count": 15,
      "published_at": "2025/11/20 13:22 (GMT+8)",
      "is_reply": true
    }
  ],
  "meta": {
    "offset": 0,
    "limit": 100,
    "returned": 2,
    "has_more": false
  }
}
```

### Field Definitions

| Field | Type | Description | Constraints |
|-------|------|-------------|-------------|
| data | array | Array of comment objects | May be empty |
| data[].comment_id | string | Unique comment identifier | YouTube comment ID format |
| data[].author_channel_id | string | Author's YouTube channel ID | YouTube channel ID format |
| data[].author_name | string | Display name of comment author | Fallback to "Unknown" if author not found |
| data[].text | string | Comment text content | May contain newlines, Unicode |
| data[].like_count | integer | Number of likes on comment | >= 0 |
| data[].published_at | string | Timestamp formatted for display | "YYYY/MM/DD HH:MM (GMT+8)" format |
| data[].is_reply | boolean | True if comment is a reply to another comment | Based on parent_comment_id presence |
| meta.offset | integer | Offset value from request | Same as request parameter |
| meta.limit | integer | Limit value from request | Same as request parameter |
| meta.returned | integer | Actual number of comments returned | 0 <= returned <= limit |
| meta.has_more | boolean | True if more comments exist beyond current page | Indicates if client should request next page |

---

## Error Responses

### 404 Not Found

**Condition**: Video ID does not exist in database

**Response**:
```json
{
  "error": "Video not found",
  "message": "No video found with ID: dQw4w9WgXcQ",
  "video_id": "dQw4w9WgXcQ"
}
```

### 400 Bad Request

**Condition**: Invalid query parameters (offset < 0 or limit > 100)

**Response**:
```json
{
  "error": "Invalid parameters",
  "message": "Limit must be between 1 and 100",
  "provided_limit": 500
}
```

### 500 Internal Server Error

**Condition**: Database query failure or unexpected error

**Response**:
```json
{
  "error": "Internal server error",
  "message": "Failed to retrieve comments",
  "trace_id": "req_abc123xyz"
}
```

---

## Business Rules

1. **Sorting**:
   - ALL comment lists sorted by `published_at DESC` (newest first)
   - Secondary sort by `comment_id ASC` for deterministic ordering

2. **Pattern Filtering**:
   - `all`: No filtering, returns all comments for video
   - `repeat`: Filters to comments where author_channel_id has 2+ comments on this video
   - `night_time`: Filters to comments where author_channel_id has >50% night-time comments across ALL channels
   - `aggressive` / `simplified_chinese`: Returns empty array (placeholders)

3. **Pagination**:
   - Uses offset-based pagination (not cursor-based)
   - Maximum batch size: 100 comments per request
   - `has_more` calculated by checking if COUNT(*) > offset + returned
   - Empty result (returned = 0) does not necessarily mean has_more = false

4. **Timezone Conversion**:
   - Database stores timestamps in UTC
   - Display format converts to GMT+8 (Asia/Taipei)
   - Format: "YYYY/MM/DD HH:MM (GMT+8)"
   - Null timestamps displayed as "未知時間" (Unknown time)

5. **Author Information**:
   - author_name loaded via relationship (Author model)
   - If author not found: fallback to "Unknown"
   - author_channel_id always present (required field in database)

6. **Edge Cases**:
   - Offset beyond total count: Returns empty data array, has_more = false
   - Limit = 0: Returns 400 Bad Request
   - Negative offset: Returns 400 Bad Request
   - Pattern with zero matches: Returns empty data array, has_more = false
   - Comments with null published_at: Included in results, sorted last

---

## Performance Targets

| Scenario | Target Response Time |
|----------|---------------------|
| Standard query (100 records) | <500ms |
| Large offset (offset > 1000) | <1s |
| Night-time pattern (cross-channel filter) | <1s (with caching) |
| Empty result | <200ms |

---

## Contract Tests

### Test Case 1: First Page of All Comments
```
Given video "abc123" has 250 comments
When GET /api/videos/abc123/comments?pattern=all&offset=0&limit=100
Then status is 200
And response.data.length == 100
And response.meta.offset == 0
And response.meta.limit == 100
And response.meta.returned == 100
And response.meta.has_more == true
And response.data[0].published_at > response.data[1].published_at  # Newest first
```

### Test Case 2: Second Page of All Comments
```
Given video "abc123" has 250 comments
When GET /api/videos/abc123/comments?pattern=all&offset=100&limit=100
Then status is 200
And response.data.length == 100
And response.meta.offset == 100
And response.meta.returned == 100
And response.meta.has_more == true
```

### Test Case 3: Last Page (Partial)
```
Given video "abc123" has 250 comments
When GET /api/videos/abc123/comments?pattern=all&offset=200&limit=100
Then status is 200
And response.data.length == 50
And response.meta.returned == 50
And response.meta.has_more == false
```

### Test Case 4: Repeat Commenters Filter
```
Given video "abc123" has 100 comments
And 10 authors posted 2+ times (20 comments total from repeat authors)
When GET /api/videos/abc123/comments?pattern=repeat&offset=0&limit=100
Then status is 200
And response.data.length == 20
And response.meta.has_more == false
And all comments in data[] have author_channel_id appearing 2+ times in video
```

### Test Case 5: Empty Pattern Result
```
Given video "abc123" has 100 comments
And 0 authors posted during night-time
When GET /api/videos/abc123/comments?pattern=night_time&offset=0&limit=100
Then status is 200
And response.data.length == 0
And response.meta.returned == 0
And response.meta.has_more == false
```

### Test Case 6: Invalid Limit
```
When GET /api/videos/abc123/comments?limit=500
Then status is 400
And response.error == "Invalid parameters"
```

### Test Case 7: Timestamp Format Validation
```
Given any video with comments
When GET /api/videos/{id}/comments?pattern=all
Then all published_at values match format "YYYY/MM/DD HH:MM (GMT+8)"
And timestamps are in descending order (newest first)
```

### Test Case 8: Placeholder Patterns
```
When GET /api/videos/abc123/comments?pattern=aggressive
Then status is 200
And response.data.length == 0
And response.meta.has_more == false

When GET /api/videos/abc123/comments?pattern=simplified_chinese
Then status is 200
And response.data.length == 0
And response.meta.has_more == false
```

---

## Infinite Scroll Integration

This API is designed for infinite scroll UX:

1. **Initial Load**: `GET /comments?pattern=all&offset=0&limit=100`
2. **User Scrolls to Bottom**: Check `meta.has_more`
3. **Load Next Batch**: `GET /comments?pattern=all&offset=100&limit=100`
4. **Continue**: Increment offset by 100 each time, stop when `has_more = false`

**Frontend Implementation**:
```javascript
let offset = 0;
const limit = 100;

async function loadMore() {
    const response = await fetch(
        `/api/videos/${videoId}/comments?pattern=${currentPattern}&offset=${offset}&limit=${limit}`
    );
    const data = await response.json();

    appendCommentsToUI(data.data);
    offset += data.meta.returned;

    if (!data.meta.has_more) {
        hideLoadingIndicator();
    }
}
```

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-11-20 | Initial contract definition |

---

## Notes for Implementers

1. **Query Optimization**: Use existing indexes on (video_id, author_channel_id)
2. **Timezone**: Always use `setTimezone('Asia/Taipei')` for display formatting
3. **Pattern Caching**: Reuse pattern calculation results from statistics endpoint
4. **Null Safety**: Handle null author relationships gracefully with "Unknown" fallback
5. **Sorting Stability**: Use secondary sort on comment_id to ensure deterministic pagination
6. **Testing**: Write contract tests for all edge cases before implementation
7. **Logging**: Log offset, limit, returned count, and execution time for monitoring
