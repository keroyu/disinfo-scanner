# API Contract: Pattern Statistics

**Endpoint**: `GET /api/videos/{videoId}/pattern-statistics`
**Purpose**: Retrieve all comment pattern statistics for a video
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
/api/videos/{videoId}/pattern-statistics
```

### Path Parameters

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| videoId | string | Yes | YouTube video ID | `dQw4w9WgXcQ` |

### Query Parameters

None

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
  "video_id": "string",
  "total_comments": "integer",
  "patterns": {
    "all": {
      "type": "string (enum)",
      "count": "integer",
      "percentage": "integer",
      "total_commenters": "integer"
    },
    "repeat": { /* same structure */ },
    "night_time": { /* same structure */ },
    "aggressive": { /* same structure */ },
    "simplified_chinese": { /* same structure */ }
  },
  "calculated_at": "string (ISO 8601)"
}
```

**Example**:
```json
{
  "video_id": "dQw4w9WgXcQ",
  "total_comments": 1247,
  "patterns": {
    "all": {
      "type": "all",
      "count": 892,
      "percentage": 100,
      "total_commenters": 892
    },
    "repeat": {
      "type": "repeat",
      "count": 67,
      "percentage": 8,
      "total_commenters": 892
    },
    "night_time": {
      "type": "night_time",
      "count": 43,
      "percentage": 5,
      "total_commenters": 892
    },
    "aggressive": {
      "type": "aggressive",
      "count": 0,
      "percentage": 0,
      "total_commenters": 892
    },
    "simplified_chinese": {
      "type": "simplified_chinese",
      "count": 0,
      "percentage": 0,
      "total_commenters": 892
    }
  },
  "calculated_at": "2025-11-20T14:35:27+08:00"
}
```

### Field Definitions

| Field | Type | Description | Constraints |
|-------|------|-------------|-------------|
| video_id | string | YouTube video ID from request | Same as request parameter |
| total_comments | integer | Total comment count on video (including replies) | >= 0 |
| patterns | object | Map of pattern type to statistics | Contains 5 keys: all, repeat, night_time, aggressive, simplified_chinese |
| patterns.{type}.type | string | Pattern identifier | One of: "all", "repeat", "night_time", "aggressive", "simplified_chinese" |
| patterns.{type}.count | integer | Number of unique commenters matching pattern | >= 0, <= total_commenters |
| patterns.{type}.percentage | integer | Percentage of total unique commenters (rounded) | 0-100 (inclusive) |
| patterns.{type}.total_commenters | integer | Total unique commenters on video | Same across all patterns, >= 0 |
| calculated_at | string | Timestamp when statistics calculated (ISO 8601, GMT+8) | ISO 8601 format with timezone |

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

### 500 Internal Server Error

**Condition**: Database query failure or unexpected error

**Response**:
```json
{
  "error": "Internal server error",
  "message": "Failed to calculate pattern statistics",
  "trace_id": "req_abc123xyz"
}
```

---

## Business Rules

1. **Repeat Commenters**:
   - Definition: Unique commenter IDs with 2 or more comments on the same video
   - Count: Number of unique author_channel_id values with COUNT(*) >= 2
   - Percentage: (count / total_commenters) * 100, rounded to nearest integer

2. **Night-Time High-Frequency Commenters**:
   - Definition: Unique commenter IDs with >50% of ALL their comments (across all channels) posted during 01:00-05:59 GMT+8
   - Minimum comments: Must have at least 2 total comments to be eligible
   - Timezone: Database timestamps are UTC, must convert to GMT+8 before hour extraction
   - Calculation: Cross-channel query (expensive, uses caching)

3. **Aggressive Commenters** (Placeholder):
   - Always returns count: 0, percentage: 0
   - Pending manual review classification implementation

4. **Simplified Chinese Commenters** (Placeholder):
   - Always returns count: 0, percentage: 0
   - Pending language detection implementation

5. **Total Commenters**:
   - Count of DISTINCT author_channel_id on the video
   - Same value appears in all pattern objects
   - Used as denominator for percentage calculations

6. **Edge Cases**:
   - Video with zero comments: All counts = 0, percentages = 0
   - Comments with null published_at: Excluded from night-time calculation, included in other patterns
   - Commenter with exactly 50% night-time: NOT included (requires >50%)

---

## Caching Behavior

- Statistics are cached for 5 minutes (300 seconds)
- Cache key pattern: `video:{videoId}:pattern:all_statistics`
- Cached data may be up to 5 minutes stale
- `calculated_at` timestamp reflects when cache entry was created, not request time

---

## Performance Targets

| Scenario | Target Response Time |
|----------|---------------------|
| Cache hit (warm) | <200ms |
| Cache miss (cold, simple video) | <2s |
| Cache miss (cold, complex cross-channel) | <3s |

---

## Contract Tests

### Test Case 1: Video with Comments
```
Given video "abc123" has 100 comments from 75 unique commenters
And 10 commenters posted 2+ times (repeat)
And 5 commenters have >50% night-time comments
When GET /api/videos/abc123/pattern-statistics
Then status is 200
And response.patterns.all.count == 75
And response.patterns.repeat.count == 10
And response.patterns.repeat.percentage == 13  # (10/75)*100 = 13.33 → 13
And response.patterns.night_time.count == 5
And response.patterns.aggressive.count == 0
And response.patterns.simplified_chinese.count == 0
```

### Test Case 2: Video with No Comments
```
Given video "empty123" has 0 comments
When GET /api/videos/empty123/pattern-statistics
Then status is 200
And response.total_comments == 0
And response.patterns.all.count == 0
And response.patterns.all.percentage == 0
And all pattern counts == 0
```

### Test Case 3: Non-Existent Video
```
Given video "nonexist" does not exist
When GET /api/videos/nonexist/pattern-statistics
Then status is 404
And response.error == "Video not found"
```

### Test Case 4: Response Schema Validation
```
Given any valid video ID
When GET /api/videos/{id}/pattern-statistics
Then response contains exactly 5 pattern types
And each pattern has fields: type, count, percentage, total_commenters
And percentage values are integers (not floats)
And calculated_at is valid ISO 8601 with timezone
```

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-11-20 | Initial contract definition |

---

## Notes for Implementers

1. **Timezone Conversion**: Use `CONVERT_TZ(published_at, '+00:00', '+08:00')` in MySQL queries for night-time filtering
2. **Rounding**: Use `round()` function to convert float percentages to integers
3. **Null Handling**: Filter out `WHERE published_at IS NOT NULL` before night-time calculations
4. **Caching**: Implement caching at service layer, not controller layer
5. **Logging**: Log query execution time and cache hit/miss for monitoring
6. **Testing**: Write contract tests before implementation (TDD per constitution)
