# API Contract: Videos List

**Feature**: Videos List
**Date**: 2025-11-18
**Phase**: Phase 1 - API Contract Design

---

## HTTP Endpoint

### GET /videos

**Description**: Display paginated list of videos with comment activity, with optional search and sorting

**Route Name**: `videos.index`

**Controller**: `VideoController@index`

**Authentication**: None required (public access, matching Comments List pattern)

---

## Request Specification

### URL Pattern
```
GET /videos?search={video_title}&search_channel={channel_name}&channel_id={id}&sort={column}&direction={asc|desc}&page={number}
```

### Query Parameters

| Parameter | Type | Required | Default | Valid Values | Description |
|-----------|------|----------|---------|--------------|-------------|
| `search` | string | No | - | Any string (max 255 chars) | Case-insensitive search in video title only |
| `search_channel` | string | No | - | Any string (max 255 chars) | Case-insensitive search in channel name |
| `channel_id` | string | No | - | Valid channel ID | Exact channel ID for filtering by specific channel |
| `sort` | string | No | `published_at` | `published_at`, `actual_comment_count`, `last_comment_time` | Column to sort by |
| `direction` | string | No | `desc` | `asc`, `desc` | Sort direction (ascending or descending) |
| `page` | integer | No | `1` | Positive integer | Page number for pagination |

### Example Requests

**Default (no filters)**:
```http
GET /videos
```

**Search by video title**:
```http
GET /videos?search=climate
```

**Search by channel name**:
```http
GET /videos?search_channel=CNN
```

**Filter by exact channel ID**:
```http
GET /videos?channel_id=UC_x5XG1OV2P6uZZ5FSM9Ttw
```

**Sort by comment count (highest first)**:
```http
GET /videos?sort=actual_comment_count&direction=desc
```

**Combined filters with pagination**:
```http
GET /videos?search=climate&search_channel=BBC&sort=last_comment_time&direction=desc&page=2
```

---

## Response Specification

### Success Response (200 OK)

**Content-Type**: `text/html; charset=utf-8`

**Body**: Blade-rendered HTML page with the following structure:

#### Page Header
- Title: "Videos List"
- Description text
- Import button (if applicable, following Comments List pattern)

#### Search & Filter Section
- Two separate search fields:
  - "Search Videos" input field (video title search)
  - "Search Channel" field with:
    - Text input for channel name
    - Dropdown selector showing all channels
- "Apply Filters" button
- "Clear Filters" button (redirects to `/videos` without parameters)

#### Data Table

**Column Headers** (clickable for sorting):
| Column | Sortable | Display Format | Click Behavior |
|--------|----------|----------------|----------------|
| Channel Name | No | Text (truncated if long) | Navigate to Comments List with channel filter |
| Video Title | No | Text (truncated if long) | Navigate to Comments List with title search |
| Comment Count | Yes | Integer | Sort by `actual_comment_count` |
| Last Comment Time | Yes | YYYY-MM-DD HH:MM | Navigate to Comments List with date range |

**Table Rows** (500 per page):
- Each row represents one video
- Clickable links in each cell (except Comment Count which is sortable header only)

#### Pagination Controls
- Page numbers
- Previous/Next buttons
- Total count display: "Showing X to Y of Z videos"
- Pagination links preserve all query parameters (search, sort, direction)

### Empty State Response (200 OK)

When no videos match criteria:
```html
<div class="p-8 text-center text-gray-500">
    <p class="text-lg">No videos found.</p>
    <p class="text-sm mt-2">Try adjusting your search filters.</p>
</div>
```

### Error Responses

#### 422 Unprocessable Entity

**Trigger**: Invalid query parameter (e.g., `sort=invalid_column`)

**Response**: Redirect to `/videos` with error flash message

**Example**:
```http
HTTP/1.1 302 Found
Location: /videos
```

Flash message: "Invalid sort parameter"

---

## Navigation Link Behaviors

### Channel Name Click

**Action**: Stay on Videos List and filter by channel

**URL Pattern**:
```
/videos?search_channel={channel_name}
```

**Example**:
```
https://example.com/videos?search_channel=CNN
```

**Result**: Videos List reloads showing only videos from that channel

### Video Title Click

**Action**: Redirect to Comments List filtered by video title

**URL Pattern**:
```
/comments?search={video_title}
```

**Example**:
```
https://example.com/comments?search=Climate%20Change%20Documentary
```

### Last Comment Time Click

**Action**: Redirect to Comments List with video title AND 90-day date range

**URL Pattern**:
```
/comments?search={video_title}&from_date={clicked_date - 90 days}&to_date={clicked_date}
```

**Example**:
If last comment time is `2025-07-23 09:58`:
```
https://example.com/comments?search=Climate%20Change%20Documentary&from_date=2025-04-24&to_date=2025-07-23
```

**Date Calculation**:
- `from_date` = clicked date - 90 days (format: Y-m-d)
- `to_date` = clicked date (format: Y-m-d)
- Uses Carbon library: `Carbon::parse($lastCommentTime)->subDays(90)->format('Y-m-d')`

---

## Data Contract

### Video List Item Structure

Each video item in the response contains:

| Field | Type | Source | Display | Nullable |
|-------|------|--------|---------|----------|
| `video_id` | string | Database | Used in URLs only | No |
| `title` | string | Database | Truncated to 50 chars with "..." | Yes |
| `channel.channel_name` | string | Relationship | Truncated to 30 chars with "..." | Yes* |
| `actual_comment_count` | integer | Computed | Raw number | No |
| `last_comment_time` | timestamp | Computed | YYYY-MM-DD HH:MM | Yes* |

*Note: Nullable fields display fallback text:
- Missing channel: "Unknown Channel"
- Missing last comment time: "N/A"

---

## Performance Contract

### Response Time Targets

| Scenario | Target | Acceptable | Notes |
|----------|--------|------------|-------|
| Page load (default sort) | < 1s | < 2s | For databases with ≤10,000 videos |
| Search query | < 500ms | < 1s | Case-insensitive LIKE query |
| Sort operation | < 500ms | < 1s | Using indexed columns |
| Pagination navigation | < 500ms | < 1s | Using offset-based pagination |

### Resource Limits

- **Max items per page**: 500 (fixed, matching Comments List)
- **Max search keyword length**: 255 characters
- **Database queries per request**: 2 (main query + count query)

---

## Validation Rules

### Controller Validation

```php
$request->validate([
    'search' => 'nullable|string|max:255',
    'search_channel' => 'nullable|string|max:255',
    'channel_id' => 'nullable|string|max:255',
    'sort' => 'nullable|in:published_at,actual_comment_count,last_comment_time',
    'direction' => 'nullable|in:asc,desc',
    'page' => 'nullable|integer|min:1',
]);
```

### Default Values (Applied if Parameter Missing)

```php
$sort = $request->input('sort', 'published_at');
$direction = $request->input('direction', 'desc');
$page = $request->input('page', 1);
```

---

## Security Considerations

### SQL Injection Prevention
- **Query Parameters**: Validated enum values (whitelist)
- **Search Keywords**: Parameterized queries (Laravel Query Builder escapes automatically)
- **Sort Column**: Whitelisted columns only

### XSS Prevention
- **Blade Template**: Auto-escapes all output via `{{ }}` syntax
- **Exception**: URLs use `{{ }}` (already safe)

### CSRF Protection
- **GET Requests**: No CSRF token required (read-only operation)
- **POST Forms**: Not applicable (no POST endpoints in this feature)

---

## Versioning

**API Version**: Not versioned (internal web route, not public API)

**Breaking Changes**: None expected (new feature, no existing dependencies)

---

## Testing Contract

### Required Test Cases

1. **Feature Test**: Page displays correctly with default sort
2. **Feature Test**: Search filters results correctly
3. **Feature Test**: Sort parameters work for all columns
4. **Feature Test**: Pagination preserves query parameters
5. **Feature Test**: Navigation links generate correct URLs
6. **Browser Test**: Visual consistency with Comments List
7. **Browser Test**: Column headers are clickable for sorting

### Test Data Requirements

- Minimum 1500 videos (to test pagination across 3 pages)
- At least 100 videos with same channel (to test channel filtering)
- Videos with various comment counts (0, 1, 10, 100+ comments)
- Videos with missing channel relationships (to test "Unknown Channel" fallback)

---

## Monitoring & Observability

### Logged Events

| Event | Log Level | Conditions |
|-------|-----------|------------|
| Slow query (>1s) | WARNING | Query execution time exceeds threshold |
| Empty search result | INFO | Search returns 0 results |
| Missing channel data | WARNING | Video has invalid `channel_id` |
| Invalid sort parameter | ERROR | User provides non-whitelisted sort column |

### Metrics to Track

- Page load time (p50, p95, p99)
- Search query time
- Most common search keywords
- Most used sort columns
- Empty result rate

---

## Example HTTP Session

```http
# Request 1: Load default page
GET /videos HTTP/1.1
Host: example.com

# Response 1
HTTP/1.1 200 OK
Content-Type: text/html; charset=utf-8

[HTML content with videos sorted by published_at DESC, page 1]

# Request 2: Search for "climate" in video titles
GET /videos?search=climate HTTP/1.1
Host: example.com

# Response 2
HTTP/1.1 200 OK
Content-Type: text/html; charset=utf-8

[HTML content with filtered videos containing "climate" in title]

# Request 3: Click on channel name "BBC" to filter by channel
GET /videos?search_channel=BBC HTTP/1.1
Host: example.com

# Response 3
HTTP/1.1 200 OK
Content-Type: text/html; charset=utf-8

[HTML content showing only videos from BBC channel]

# Request 4: Sort BBC videos by comment count
GET /videos?search_channel=BBC&sort=actual_comment_count&direction=desc HTTP/1.1
Host: example.com

# Response 3
HTTP/1.1 200 OK
Content-Type: text/html; charset=utf-8

[HTML content with filtered and sorted videos, highest comment count first]
```

---

## Contract Compliance Checklist

- [ ] Endpoint follows Laravel routing conventions
- [ ] Query parameters are validated
- [ ] Response times meet performance targets
- [ ] Error states are handled gracefully
- [ ] Security measures (SQL injection, XSS) are in place
- [ ] Logging and monitoring are implemented
- [ ] Tests cover all required scenarios
- [ ] Documentation is complete and accurate
