# API Contract: Time-Filtered Comments

**Feature**: 010-time-based-comment-filter
**Version**: 1.0.0
**Date**: 2025-11-20
**Endpoint**: `/api/videos/{videoId}/comments`

## Overview

This contract extends the existing `/api/videos/{videoId}/comments` endpoint (from Feature 009) with optional time-based filtering. The extension is **backward compatible** - existing clients without the `time_points` parameter continue to work unchanged.

---

## Endpoint Details

### HTTP Method
`GET`

### URL Pattern
```
/api/videos/{videoId}/comments
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `videoId` | string | Yes | YouTube video ID (primary key in videos table) |

### Query Parameters

| Parameter | Type | Required | Constraints | Default | Description |
|-----------|------|----------|-------------|---------|-------------|
| `pattern` | string | Yes | One of: `all`, `top_liked`, `repeat`, `night_time`, `aggressive`, `simplified_chinese` | - | Comment pattern filter type |
| `time_points` | string | No | Comma-separated ISO 8601 timestamps (GMT+8), max 20 points | null | Time ranges for filtering (new in this feature) |
| `offset` | integer | No | >= 0 | 0 | Pagination offset |
| `limit` | integer | No | 1-100 | 100 | Number of comments per page |

**New Parameter Details: `time_points`**
- Format: Comma-separated ISO 8601 timestamps in GMT+8 timezone
- Example: `2025-11-20T14:00:00+08:00,2025-11-20T16:00:00+08:00`
- Each timestamp represents the start of a 1-hour range
- Maximum 20 timestamps allowed (hard limit)
- Backend converts GMT+8 → UTC for database query
- If omitted or empty, no time filtering applied (backward compatible)

---

## Request Examples

### Example 1: Basic Pattern Filter (No Time Filter - Backward Compatible)
```http
GET /api/videos/abc123/comments?pattern=all&offset=0&limit=100
```

### Example 2: Single Time Range
```http
GET /api/videos/abc123/comments?pattern=all&time_points=2025-11-20T14:00:00+08:00&offset=0&limit=100
```

### Example 3: Multiple Non-Contiguous Time Ranges
```http
GET /api/videos/abc123/comments?pattern=repeat&time_points=2025-11-20T08:00:00+08:00,2025-11-20T12:00:00+08:00,2025-11-20T18:00:00+08:00&offset=0&limit=100
```

### Example 4: Combined Pattern + Time Filter with Pagination
```http
GET /api/videos/abc123/comments?pattern=night_time&time_points=2025-11-20T02:00:00+08:00,2025-11-20T04:00:00+08:00&offset=100&limit=100
```

---

## Success Response (HTTP 200)

### Response Headers
```
Content-Type: application/json
X-Execution-Time-Ms: {milliseconds}
```

### Response Body Schema

```json
{
  "video_id": "string",
  "pattern": "string",
  "offset": "integer",
  "limit": "integer",
  "time_filter": {  // ⚠️ Only present when time_points parameter provided
    "ranges": [
      {
        "start": "string (YYYY-MM-DD HH:MM (GMT+8))",
        "end": "string (YYYY-MM-DD HH:MM (GMT+8))"
      }
    ],
    "count": "integer"
  },
  "comments": [
    {
      "comment_id": "string",
      "author_channel_id": "string",
      "author_name": "string",
      "text": "string",
      "like_count": "integer",
      "published_at": "string (YYYY-MM-DD HH:MM (GMT+8))",
      "is_reply": "boolean"
    }
  ],
  "has_more": "boolean",
  "total": "integer"
}
```

### Response Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| `video_id` | string | Echoed from request |
| `pattern` | string | Echoed from request |
| `offset` | integer | Echoed from request |
| `limit` | integer | Echoed from request |
| `time_filter` | object | **NEW** - Only present when `time_points` parameter provided. Contains time range metadata. |
| `time_filter.ranges` | array | Array of time range objects, one per input timestamp |
| `time_filter.ranges[].start` | string | Start time in GMT+8 (inclusive) |
| `time_filter.ranges[].end` | string | End time in GMT+8 (exclusive) |
| `time_filter.count` | integer | Number of time ranges (same as length of `ranges` array) |
| `comments` | array | Array of comment objects matching all filters |
| `comments[].comment_id` | string | YouTube comment ID (primary key) |
| `comments[].author_channel_id` | string | YouTube channel ID of commenter |
| `comments[].author_name` | string | Display name of commenter |
| `comments[].text` | string | Comment text content |
| `comments[].like_count` | integer | Number of likes |
| `comments[].published_at` | string | Publication timestamp in GMT+8 with explicit timezone indicator |
| `comments[].is_reply` | boolean | True if this is a reply to another comment |
| `has_more` | boolean | True if more comments exist beyond current offset + limit |
| `total` | integer | Total number of comments matching all filters |

### Example Success Response (With Time Filter)

```json
{
  "video_id": "abc123",
  "pattern": "repeat",
  "offset": 0,
  "limit": 100,
  "time_filter": {
    "ranges": [
      {
        "start": "2025-11-20 08:00 (GMT+8)",
        "end": "2025-11-20 09:00 (GMT+8)"
      },
      {
        "start": "2025-11-20 12:00 (GMT+8)",
        "end": "2025-11-20 13:00 (GMT+8)"
      }
    ],
    "count": 2
  },
  "comments": [
    {
      "comment_id": "comment123",
      "author_channel_id": "channel456",
      "author_name": "John Doe",
      "text": "Great video!",
      "like_count": 15,
      "published_at": "2025-11-20 08:30 (GMT+8)",
      "is_reply": false
    },
    {
      "comment_id": "comment124",
      "author_channel_id": "channel456",
      "author_name": "John Doe",
      "text": "Amazing!",
      "like_count": 8,
      "published_at": "2025-11-20 12:15 (GMT+8)",
      "is_reply": false
    }
  ],
  "has_more": false,
  "total": 2
}
```

### Example Success Response (Without Time Filter - Backward Compatible)

```json
{
  "video_id": "abc123",
  "pattern": "all",
  "offset": 0,
  "limit": 100,
  "comments": [
    {
      "comment_id": "comment125",
      "author_channel_id": "channel789",
      "author_name": "Jane Smith",
      "text": "Nice work!",
      "like_count": 3,
      "published_at": "2025-11-20 10:45 (GMT+8)",
      "is_reply": false
    }
  ],
  "has_more": true,
  "total": 450
}
```

---

## Error Responses

### HTTP 404 - Video Not Found

**When**: Requested video_id does not exist in database

```json
{
  "error": {
    "type": "VideoNotFound",
    "message": "Video not found",
    "details": {
      "video_id": "abc123"
    }
  }
}
```

---

### HTTP 422 - Validation Error (Invalid Pattern)

**When**: `pattern` parameter is invalid

```json
{
  "error": {
    "type": "ValidationError",
    "message": "Invalid request parameters",
    "details": {
      "pattern": [
        "The selected pattern is invalid."
      ]
    }
  }
}
```

---

### HTTP 422 - Validation Error (Too Many Time Points)

**When**: `time_points` contains more than 20 timestamps

```json
{
  "error": {
    "type": "ValidationError",
    "message": "Maximum 20 time points allowed",
    "details": {
      "count": 25,
      "limit": 20
    }
  }
}
```

---

### HTTP 422 - Validation Error (Invalid Timestamp Format)

**When**: One or more timestamps in `time_points` cannot be parsed

```json
{
  "error": {
    "type": "ValidationError",
    "message": "Invalid timestamp format",
    "details": {
      "timestamp": "2025-11-20T25:00:00"
    }
  }
}
```

---

### HTTP 422 - Validation Error (Invalid Offset/Limit)

**When**: `offset` or `limit` parameters are out of range

```json
{
  "error": {
    "type": "ValidationError",
    "message": "Invalid request parameters",
    "details": {
      "offset": [
        "The offset must be at least 0."
      ],
      "limit": [
        "The limit must be between 1 and 100."
      ]
    }
  }
}
```

---

### HTTP 500 - Server Error

**When**: Unexpected server-side error (database connection, query timeout, etc.)

```json
{
  "error": {
    "type": "ServerError",
    "message": "Failed to retrieve comments",
    "details": {
      "error": "Database query timeout"
    }
  }
}
```

---

## Timezone Handling Contract

### Input Timezone: GMT+8 (Asia/Taipei)
- Frontend MUST send all timestamps in `time_points` parameter as ISO 8601 with GMT+8 offset
- Valid format examples:
  - `2025-11-20T14:00:00+08:00`
  - `2025-11-20T14:00:00+0800`
  - `2025-11-20T14:00:00Z` is INVALID (must explicitly specify +08:00)

### Database Query Timezone: UTC
- Backend converts all input timestamps from GMT+8 to UTC before querying
- Example: `2025-11-20T14:00:00+08:00` → `2025-11-20T06:00:00Z` (UTC)
- Database `published_at` column stores UTC timestamps

### Output Timezone: GMT+8 (Asia/Taipei)
- Backend converts all `published_at` timestamps from UTC to GMT+8 before returning
- Format: `YYYY-MM-DD HH:MM (GMT+8)` with explicit timezone indicator
- Example: `2025-11-20 14:30 (GMT+8)`

### Timezone Conversion Guarantee
- **Input-to-Query**: GMT+8 → UTC (backend)
- **Query-to-Output**: UTC → GMT+8 (backend)
- **No frontend timezone conversions**: Frontend receives pre-converted timestamps

---

## Performance Characteristics

### Expected Response Times

| Scenario | Expected Response Time |
|----------|------------------------|
| Single time range, <1000 comments | <500ms |
| Single time range, 1000-10000 comments | <2s |
| Multiple ranges (5-10), <10000 comments | <2s |
| Multiple ranges (15-20), <10000 comments | <3s |
| Query timeout (hard limit) | 5s |

### Performance Considerations

1. **Query Complexity**: Each time range adds an OR clause to the WHERE condition
2. **Index Usage**: Queries utilize composite index on (video_id, published_at)
3. **Limit Enforcement**: Maximum 20 time ranges prevents excessive query complexity
4. **Pagination**: Clients should use offset/limit for large result sets

---

## Backward Compatibility

### Contract Compatibility Guarantee

✅ **This API extension is 100% backward compatible**

- Existing clients not using `time_points` parameter: **No changes required**
- Response structure for non-time-filtered requests: **Unchanged**
- All existing query parameters: **Maintained with same behavior**
- Error response format: **Consistent with existing API**

### Migration Path

No migration required. Clients can adopt time filtering incrementally:

1. **Phase 1**: Continue using API without `time_points` (existing behavior)
2. **Phase 2**: Add `time_points` parameter when needed (new feature)
3. **No breaking changes**: API version remains compatible

---

## Security Considerations

### Input Validation
- ✅ Video ID validated against database (prevents SQL injection)
- ✅ Pattern validated against whitelist (prevents arbitrary input)
- ✅ Time points count limited to 20 (prevents DoS via query complexity)
- ✅ Timestamp format validated (prevents malformed input)
- ✅ Offset/limit validated (prevents excessive memory usage)

### Query Timeout
- Maximum query execution time: 5 seconds
- Prevents long-running queries from blocking server resources

### Rate Limiting
- Not specified in this contract (handled at infrastructure layer)
- Recommendation: Standard API rate limiting applies

---

## Testing Contract

### Contract Test Requirements

All implementations MUST pass these contract tests:

#### 1. Backward Compatibility Test
```php
Given: Existing client calls API without time_points parameter
When: GET /api/videos/abc123/comments?pattern=all&offset=0&limit=100
Then: Response matches pre-feature schema (no time_filter field)
And: Response time < 2s
```

#### 2. Single Time Range Test
```php
Given: Client requests single time range
When: GET /api/videos/abc123/comments?pattern=all&time_points=2025-11-20T14:00:00+08:00
Then: Response includes time_filter.count = 1
And: All returned comments have published_at between 14:00-15:00 (GMT+8)
And: Response time < 2s
```

#### 3. Multiple Time Ranges Test
```php
Given: Client requests 3 non-contiguous time ranges
When: time_points contains 08:00, 12:00, 18:00
Then: Response includes time_filter.count = 3
And: All returned comments fall within one of the three hourly ranges
And: Comments are sorted by published_at DESC
And: Response time < 2s
```

#### 4. Combined Filter Test
```php
Given: Client requests pattern=repeat with time filter
When: time_points contains 02:00, 04:00
Then: Response includes only repeat commenters from those time ranges
And: Result is intersection (AND logic, not union)
```

#### 5. Validation Test: Too Many Points
```php
Given: Client sends 21 time points
When: Request is sent
Then: Response is HTTP 422
And: Error type is "ValidationError"
And: Error message mentions "Maximum 20 time points"
```

#### 6. Validation Test: Invalid Timestamp
```php
Given: Client sends malformed timestamp "2025-13-40T99:00:00"
When: Request is sent
Then: Response is HTTP 422
And: Error type is "ValidationError"
And: Error details include the invalid timestamp
```

#### 7. Timezone Conversion Test
```php
Given: Database has comment with published_at = "2025-11-20 06:30:00" (UTC)
When: Client requests time_points=2025-11-20T14:00:00+08:00
Then: Comment is included in results (06:30 UTC = 14:30 GMT+8)
And: Response shows published_at = "2025-11-20 14:30 (GMT+8)"
```

#### 8. Empty Result Test
```php
Given: Client requests time range with no comments
When: time_points contains a range with 0 comments
Then: Response is HTTP 200
And: comments array is empty
And: total = 0
And: has_more = false
```

#### 9. Pagination Test
```php
Given: Time-filtered query returns 250 total comments
When: offset=0, limit=100
Then: has_more = true
And: comments array length = 100
When: offset=100, limit=100
Then: has_more = true
And: comments array length = 100
When: offset=200, limit=100
Then: has_more = false
And: comments array length = 50
```

---

## Change Log

### Version 1.0.0 (2025-11-20)
- **Added**: Optional `time_points` query parameter for time-based filtering
- **Added**: `time_filter` object in response (when time filtering active)
- **Maintained**: Full backward compatibility with existing API
- **Maintained**: Same error response structure
- **Maintained**: Same timezone handling (UTC storage, GMT+8 display)

---

## References

- Feature Specification: `specs/010-time-based-comment-filter/spec.md`
- Data Model: `specs/010-time-based-comment-filter/data-model.md`
- Existing Comments API (Feature 009): `specs/009-comments-pattern-summary/contracts/`
- Constitution Principle VI: Timezone Consistency
