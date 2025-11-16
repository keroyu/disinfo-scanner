# API Contracts: Comments List Feature

**Feature**: Comments List View
**Date**: 2025-11-16
**Status**: Contract Definitions

This document defines the API contracts for the Comments List feature. All endpoints follow REST conventions and return JSON responses.

---

## Endpoint: List Comments (GET /api/comments)

### Contract Definition

**Endpoint**: `GET /api/comments`

**Purpose**: Retrieve a paginated list of comments with optional filtering, sorting, and searching.

**Authentication**: Required (user must be logged in)

**Authorization**: Any authenticated user

---

### Request

**Query Parameters**:

| Parameter | Type | Required | Default | Constraints | Purpose |
|-----------|------|----------|---------|-------------|---------|
| `page` | integer | NO | 1 | >= 1 | Pagination page number (1-indexed) |
| `per_page` | integer | NO | 500 | Must be 500 | Comments per page (fixed at 500) |
| `keyword` | string | NO | null | Max 255 chars | Search across channel_name, video_title, commenter_id, content |
| `date_from` | ISO 8601 date | NO | null | Valid date | Start of date range (inclusive) |
| `date_to` | ISO 8601 date | NO | null | Valid date | End of date range (inclusive) |
| `sort` | string | NO | published_at | Enum: published_at, like_count | Column to sort by |
| `direction` | string | NO | DESC | Enum: ASC, DESC | Sort direction |

**Query Parameter Examples**:

```
GET /api/comments
GET /api/comments?keyword=bitcoin
GET /api/comments?date_from=2025-11-01&date_to=2025-11-15
GET /api/comments?sort=like_count&direction=DESC&page=2
GET /api/comments?keyword=election&date_from=2025-11-01&sort=published_at&direction=ASC
```

**Validation Rules**:
- `keyword`: Optional, string, max 255 characters, case-insensitive matching
- `date_from`, `date_to`: Optional, must be valid ISO 8601 dates (YYYY-MM-DD)
- `date_from` should be <= `date_to` (server validates, returns 422 if invalid)
- `sort`: Must be one of [published_at, like_count]
- `direction`: Must be one of [ASC, DESC]
- `page`: Must be positive integer
- `per_page`: Ignored; always 500 (fixed pagination)

---

### Response

**HTTP Status**: 200 OK

**Response Body** (JSON):

```json
{
  "data": [
    {
      "id": "UgzKpxxxxxx",
      "channel_name": "Example News Channel",
      "video_title": "Daily Update - November 15",
      "commenter_id": "UCuser123",
      "content": "Great video, thanks for the coverage!",
      "published_at": "2025-11-15T14:30:00Z",
      "like_count": 42,
      "video_id": "dQw4w9WgXcQ",
      "channel_id": "UCExampleChannel"
    },
    {
      "id": "UgzKqyyyyyy",
      "channel_name": "Another Channel",
      "video_title": "Daily Update - November 15",
      "commenter_id": "UCuser456",
      "content": "I disagree with this analysis",
      "published_at": "2025-11-15T13:20:00Z",
      "like_count": 5,
      "video_id": "dQw4w9WgXcQ",
      "channel_id": "UCAnother"
    }
  ],
  "pagination": {
    "total": 12500,
    "per_page": 500,
    "current_page": 1,
    "last_page": 25,
    "from": 1,
    "to": 500,
    "next_page_url": "http://localhost:8000/api/comments?page=2",
    "prev_page_url": null,
    "first_page_url": "http://localhost:8000/api/comments?page=1",
    "last_page_url": "http://localhost:8000/api/comments?page=25"
  },
  "filters": {
    "keyword": null,
    "date_from": null,
    "date_to": null,
    "sort": "published_at",
    "direction": "DESC"
  },
  "meta": {
    "timestamp": "2025-11-16T10:30:00Z",
    "trace_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

**Response Field Descriptions**:

**data** (array of Comment objects):
- `id` (string): YouTube comment ID, globally unique
- `channel_name` (string): Name of channel that published the video
- `video_title` (string): Title of video containing the comment
- `commenter_id` (string): YouTube user ID of comment author
- `content` (string): Full text of the comment
- `published_at` (ISO 8601 timestamp): When comment was published on YouTube (UTC)
- `like_count` (integer): Number of likes on the comment (>= 0)
- `video_id` (string): YouTube video ID (for constructing links)
- `channel_id` (string): YouTube channel ID (for constructing links)

**pagination** (object):
- `total` (integer): Total comments in database (including filters)
- `per_page` (integer): Always 500 (fixed)
- `current_page` (integer): Current page number (1-indexed)
- `last_page` (integer): Total number of pages
- `from` (integer): Index of first comment on current page
- `to` (integer): Index of last comment on current page
- `next_page_url` (string|null): URL to next page, or null if on last page
- `prev_page_url` (string|null): URL to previous page, or null if on first page
- `first_page_url` (string): URL to first page
- `last_page_url` (string): URL to last page

**filters** (object, echoes back applied filters):
- `keyword` (string|null): Keyword filter applied (null if not applied)
- `date_from` (ISO 8601 date|null): Start date of range filter (null if not applied)
- `date_to` (ISO 8601 date|null): End date of range filter (null if not applied)
- `sort` (string): Active sort column
- `direction` (string): Sort direction (ASC or DESC)

**meta** (object, monitoring/debugging):
- `timestamp` (ISO 8601 timestamp): Server response timestamp (UTC)
- `trace_id` (UUID): Unique request trace ID for monitoring/logging

---

### Error Responses

**400 Bad Request** - Invalid query parameters

```json
{
  "error": "Invalid parameters",
  "message": "The date_from field must be a valid date before date_to.",
  "code": "INVALID_FILTER_RANGE",
  "trace_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**401 Unauthorized** - User not authenticated

```json
{
  "error": "Unauthenticated",
  "message": "Please log in to access comments.",
  "code": "UNAUTHENTICATED",
  "trace_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**422 Unprocessable Entity** - Validation failed

```json
{
  "error": "Validation failed",
  "message": "The sort field must be one of [published_at, like_count].",
  "code": "VALIDATION_ERROR",
  "errors": {
    "sort": ["The sort field must be one of [published_at, like_count]."]
  },
  "trace_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**500 Internal Server Error** - Unexpected server error

```json
{
  "error": "Internal Server Error",
  "message": "An unexpected error occurred. Please try again later.",
  "code": "INTERNAL_ERROR",
  "trace_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

---

### Performance Contract

**Success Criteria** (from specification):

| Scenario | Time Requirement |
|----------|------------------|
| Initial page load (no filters) | < 3 seconds |
| Keyword search (10k+ records) | < 2 seconds |
| Date range filter | < 1 second |
| Sort by likes or date | < 1 second |
| Combined filters + sort + paginate | < 2 seconds |

**Monitoring**: All queries logged with execution time in structured logs.

---

### Caching Strategy

**Not applicable for MVP** - All requests are real-time queries.

**Future enhancement**: Consider caching popular queries (no filter, default sort) for 5 minutes.

---

### Rate Limiting

**Not specified** - Assume system-wide rate limiting applies.

---

## Endpoint: View Comments Page (GET /comments)

### Contract Definition

**Endpoint**: `GET /comments`

**Purpose**: Render the comments list HTML page (server-side rendered view).

**Authentication**: Required (user must be logged in)

**Authorization**: Any authenticated user

---

### Request

**Query Parameters**: Same as API endpoint

```
GET /comments
GET /comments?keyword=bitcoin&page=2
GET /comments?date_from=2025-11-01&date_to=2025-11-15
```

---

### Response

**HTTP Status**: 200 OK

**Content-Type**: text/html

**Response**: HTML page with comments list, search bar, date picker, sort headers, pagination controls.

**Page Elements** (from specification):
- Comments List heading
- Search bar (text input, case-insensitive)
- Date range picker (from date, to date)
- Comments table with sortable column headers:
  - Channel Name (link to YouTube)
  - Video Title (link to YouTube video with comment anchor)
  - Commenter ID
  - Comment Content
  - Comment Date (sortable, click toggles ASC/DESC)
  - Like Count (sortable, click toggles ASC/DESC)
- Pagination controls (previous, next, page numbers)
- "No results" message when filters return empty set

---

### Error Responses

**401 Unauthorized** - User not authenticated → Redirect to login page

**404 Not Found** - Page does not exist

---

## Shared Schema Components

### Comment Object Schema

```json
{
  "type": "object",
  "required": ["id", "channel_name", "video_title", "commenter_id", "content", "published_at", "like_count", "video_id", "channel_id"],
  "properties": {
    "id": {
      "type": "string",
      "minLength": 1,
      "maxLength": 255,
      "description": "YouTube comment ID (globally unique)"
    },
    "channel_name": {
      "type": "string",
      "minLength": 1,
      "maxLength": 255,
      "description": "Channel name that published the video"
    },
    "video_title": {
      "type": "string",
      "minLength": 1,
      "maxLength": 500,
      "description": "Title of video containing the comment"
    },
    "commenter_id": {
      "type": "string",
      "minLength": 1,
      "maxLength": 255,
      "description": "YouTube user ID of comment author"
    },
    "content": {
      "type": "string",
      "minLength": 1,
      "description": "Full text of the comment"
    },
    "published_at": {
      "type": "string",
      "format": "date-time",
      "description": "ISO 8601 timestamp when comment was published (UTC)"
    },
    "like_count": {
      "type": "integer",
      "minimum": 0,
      "description": "Number of likes on the comment"
    },
    "video_id": {
      "type": "string",
      "minLength": 1,
      "maxLength": 255,
      "description": "YouTube video ID (for constructing links)"
    },
    "channel_id": {
      "type": "string",
      "minLength": 1,
      "maxLength": 255,
      "description": "YouTube channel ID (for constructing links)"
    }
  }
}
```

### Pagination Object Schema

```json
{
  "type": "object",
  "required": ["total", "per_page", "current_page", "last_page", "from", "to"],
  "properties": {
    "total": {
      "type": "integer",
      "minimum": 0,
      "description": "Total comments matching filters"
    },
    "per_page": {
      "type": "integer",
      "enum": [500],
      "description": "Always 500 (fixed)"
    },
    "current_page": {
      "type": "integer",
      "minimum": 1,
      "description": "Current page number (1-indexed)"
    },
    "last_page": {
      "type": "integer",
      "minimum": 1,
      "description": "Total number of pages"
    },
    "from": {
      "type": "integer",
      "minimum": 0,
      "description": "Index of first comment on current page (0-indexed)"
    },
    "to": {
      "type": "integer",
      "minimum": 0,
      "description": "Index of last comment on current page (0-indexed)"
    },
    "next_page_url": {
      "type": ["string", "null"],
      "description": "URL to next page, or null if on last page"
    },
    "prev_page_url": {
      "type": ["string", "null"],
      "description": "URL to previous page, or null if on first page"
    },
    "first_page_url": {
      "type": "string",
      "description": "URL to first page"
    },
    "last_page_url": {
      "type": "string",
      "description": "URL to last page"
    }
  }
}
```

---

## Contract Compliance Testing

**Contract tests validate**:
1. Required fields are always present in responses
2. Data types match specification (string, integer, date-time, etc.)
3. Pagination calculations are correct (total, last_page, from, to)
4. Filters are correctly echoed back in response
5. Error responses include trace_id and actionable messages
6. Status codes match specification (200, 400, 401, 422, 500)

**Test file**: `tests/Feature/CommentListContractTest.php`

---

## Backward Compatibility

**No breaking changes** - This is a new endpoint. Existing endpoints are not modified.

**Future changes** - If API is extended (e.g., new filters, new fields), version endpoint as `/api/v2/comments` or increment MINOR version and document changes clearly.

---

## Summary

The comments list API is a read-only, GET-only endpoint that supports filtering, sorting, and pagination. All responses include metadata for monitoring and debugging. Error handling is consistent across all status codes with actionable messages.
