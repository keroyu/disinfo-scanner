# API Response Contracts: Video Comment Density Analysis

**Feature**: 008-video-comment-analysis
**Date**: 2025-11-19

## Response 1: Chart Data (Success)

**Endpoint**: `GET /api/videos/{video}/chart-data`
**Status Code**: `200 OK`
**Content-Type**: `application/json`

### Hourly Granularity Example (≤7 days)

```json
{
    "video_id": "123",
    "time_range": "3days",
    "granularity": "hourly",
    "labels": [
        "2025-06-13 14:00",
        "2025-06-13 15:00",
        "2025-06-13 16:00",
        "2025-06-13 17:00"
    ],
    "values": [
        23,
        45,
        67,
        89
    ],
    "total_comments": 224,
    "generated_at": "2025-06-13 21:00:03"
}
```

### Daily Granularity Example (>7 days)

```json
{
    "video_id": "123",
    "time_range": "14days",
    "granularity": "daily",
    "labels": [
        "2025-06-01",
        "2025-06-02",
        "2025-06-03",
        "2025-06-04"
    ],
    "values": [
        150,
        200,
        180,
        220
    ],
    "total_comments": 750,
    "generated_at": "2025-06-13 21:00:03"
}
```

### Custom Range Example

```json
{
    "video_id": "123",
    "time_range": "custom",
    "start_date": "2025-06-10",
    "end_date": "2025-06-12",
    "granularity": "hourly",
    "labels": [
        "2025-06-10 00:00",
        "2025-06-10 01:00",
        "2025-06-10 02:00"
    ],
    "values": [
        12,
        8,
        15
    ],
    "total_comments": 35,
    "generated_at": "2025-06-13 21:00:03"
}
```

### Schema Definition

```typescript
interface ChartDataResponse {
    video_id: string;           // Video ID as string
    time_range: string;         // '3days' | '7days' | '14days' | '30days' | 'custom'
    start_date?: string;        // Only present if time_range === 'custom' (Y-m-d)
    end_date?: string;          // Only present if time_range === 'custom' (Y-m-d)
    granularity: string;        // 'hourly' | 'daily'
    labels: string[];           // Time labels (hourly: 'Y-m-d H:i', daily: 'Y-m-d')
    values: number[];           // Comment counts (non-negative integers)
    total_comments: number;     // Sum of all values
    generated_at: string;       // Generation timestamp (Y-m-d H:i:s)
}
```

### Validation Rules

- `labels` and `values` arrays MUST have equal length
- `values` MUST contain only non-negative integers
- `total_comments` MUST equal sum of `values`
- `granularity` MUST be 'hourly' if range ≤7 days, 'daily' if >7 days
- Timestamps MUST be in Asia/Taipei timezone

---

## Response 2: Video Overview (Success)

**Endpoint**: `GET /api/videos/{video}/overview`
**Status Code**: `200 OK`
**Content-Type**: `application/json`

### Example Response

```json
{
    "video_id": 123,
    "youtube_id": "dQw4w9WgXcQ",
    "title": "Example Video Title",
    "published_at": "2025-06-01 12:30:45",
    "views": 1500000,
    "likes": 50000,
    "comment_count": 12345,
    "cache_age_hours": 12,
    "cache_is_fresh": true,
    "last_updated": "2025-06-13 09:00:00"
}
```

### Fresh Cache Example

```json
{
    "video_id": 123,
    "youtube_id": "dQw4w9WgXcQ",
    "title": "Example Video Title",
    "published_at": "2025-06-01 12:30:45",
    "views": 1500000,
    "likes": 50000,
    "comment_count": 12345,
    "cache_age_hours": 2,
    "cache_is_fresh": true,
    "last_updated": "2025-06-13 19:00:00"
}
```

### Stale Cache Example

```json
{
    "video_id": 123,
    "youtube_id": "dQw4w9WgXcQ",
    "title": "Example Video Title",
    "published_at": "2025-06-01 12:30:45",
    "views": 1450000,
    "likes": 48000,
    "comment_count": 12345,
    "cache_age_hours": 36,
    "cache_is_fresh": false,
    "last_updated": "2025-06-12 09:00:00",
    "cache_warning": "Data is more than 24 hours old"
}
```

### Never Cached Example

```json
{
    "video_id": 123,
    "youtube_id": "dQw4w9WgXcQ",
    "title": "Example Video Title",
    "published_at": "2025-06-01 12:30:45",
    "views": null,
    "likes": null,
    "comment_count": 12345,
    "cache_age_hours": null,
    "cache_is_fresh": false,
    "last_updated": null,
    "cache_warning": "Statistics not yet fetched from YouTube"
}
```

### Schema Definition

```typescript
interface VideoOverviewResponse {
    video_id: number;           // Video ID
    youtube_id: string;         // YouTube video ID
    title: string;              // Video title
    published_at: string;       // Publish datetime (Y-m-d H:i:s)
    views: number | null;       // View count (null if never fetched)
    likes: number | null;       // Like count (null if never fetched)
    comment_count: number;      // Total comments (from database)
    cache_age_hours: number | null;  // Hours since last update (null if never fetched)
    cache_is_fresh: boolean;    // True if cache age < 24 hours
    last_updated: string | null;     // Last cache update (Y-m-d H:i:s, null if never)
    cache_warning?: string;     // Optional warning message if stale or missing
}
```

---

## Response 3: Validation Error (422)

**Endpoint**: Any API endpoint with invalid input
**Status Code**: `422 Unprocessable Entity`
**Content-Type**: `application/json`

### Example: Missing Required Parameter

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "range": [
            "The range field is required."
        ]
    }
}
```

### Example: Invalid Date Range

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "end_date": [
            "The end date must be a date after start date."
        ]
    }
}
```

### Example: Invalid Range Value

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "range": [
            "The selected range is invalid."
        ]
    }
}
```

### Schema Definition

```typescript
interface ValidationErrorResponse {
    message: string;                    // Error summary message
    errors: {
        [field: string]: string[];      // Field-specific error messages
    };
}
```

---

## Response 4: Not Found (404)

**Endpoint**: Any endpoint with non-existent video
**Status Code**: `404 Not Found`
**Content-Type**: `application/json`

### Example Response

```json
{
    "message": "Video not found."
}
```

### Schema Definition

```typescript
interface NotFoundResponse {
    message: string;    // Error message
}
```

---

## Response 5: Server Error (500)

**Endpoint**: Any endpoint experiencing server issues
**Status Code**: `500 Internal Server Error`
**Content-Type**: `application/json`

### Example Response (Production)

```json
{
    "message": "Server Error"
}
```

### Example Response (Development)

```json
{
    "message": "Database connection failed",
    "exception": "Illuminate\\Database\\QueryException",
    "file": "/path/to/file.php",
    "line": 123,
    "trace": [
        "..."
    ]
}
```

### Schema Definition

```typescript
interface ServerErrorResponse {
    message: string;        // Error message
    exception?: string;     // Exception class (development only)
    file?: string;          // File path (development only)
    line?: number;          // Line number (development only)
    trace?: string[];       // Stack trace (development only)
}
```

---

## Response Headers

All API responses include standard headers:

```http
Content-Type: application/json; charset=utf-8
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
```

**Caching Headers** (for chart data):
```http
Cache-Control: private, max-age=300
Vary: Accept
```

**Rate Limiting Headers**:
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
Retry-After: 60
```

---

## Contract Testing Examples

### Test: Chart Data Structure

```php
public function test_chart_data_has_required_fields()
{
    $video = Video::factory()->create();

    $response = $this->getJson("/api/videos/{$video->id}/chart-data?range=3days");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'video_id',
            'time_range',
            'granularity',
            'labels',
            'values',
            'total_comments',
            'generated_at'
        ]);
}
```

### Test: Label-Value Array Equality

```php
public function test_chart_data_arrays_have_equal_length()
{
    $video = Video::factory()->create();

    $response = $this->getJson("/api/videos/{$video->id}/chart-data?range=7days");

    $data = $response->json();
    $this->assertCount(count($data['labels']), $data['values']);
}
```

### Test: Total Comments Calculation

```php
public function test_total_comments_equals_sum_of_values()
{
    $video = Video::factory()->create();

    $response = $this->getJson("/api/videos/{$video->id}/chart-data?range=3days");

    $data = $response->json();
    $this->assertEquals(array_sum($data['values']), $data['total_comments']);
}
```

### Test: Granularity Rules

```php
public function test_hourly_granularity_for_short_ranges()
{
    $video = Video::factory()->create();

    $response = $this->getJson("/api/videos/{$video->id}/chart-data?range=7days");

    $response->assertJson(['granularity' => 'hourly']);
}

public function test_daily_granularity_for_long_ranges()
{
    $video = Video::factory()->create();

    $response = $this->getJson("/api/videos/{$video->id}/chart-data?range=14days");

    $response->assertJson(['granularity' => 'daily']);
}
```

---

## Performance Expectations

| Endpoint | Target Response Time | Max Response Size |
|----------|---------------------|-------------------|
| `/chart-data` (3/7 days) | < 1 second | ~10 KB |
| `/chart-data` (14/30 days) | < 1 second | ~5 KB |
| `/overview` | < 500 ms | ~1 KB |

**Note**: Response times assume database queries are optimized with proper indexes.
