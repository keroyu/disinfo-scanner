# Route Contracts: Video Comment Density Analysis

**Feature**: 008-video-comment-analysis
**Date**: 2025-11-19

## Web Routes

### 1. Video Analysis Page

**Route**: `GET /videos/{video}/analysis`
**Name**: `videos.analysis`
**Controller**: `VideoAnalysisController@show`
**Middleware**: `web` (session, CSRF)

**Purpose**: Display the video analysis page with overview and chart

**Parameters**:
- `{video}`: Video ID (integer, must exist in videos table)

**Response**: HTML (Blade view)
- **Success (200)**: Renders `videos/analysis.blade.php`
- **Not Found (404)**: Video doesn't exist
- **Error (500)**: Server error with error page

**Example**:
```
GET /videos/123/analysis
```

**Breadcrumb**: 首頁 > 影片列表 > 影片分析

---

## API Routes

### 2. Chart Data API

**Route**: `GET /api/videos/{video}/chart-data`
**Name**: `api.videos.chart-data`
**Controller**: `Api\VideoChartDataController@index`
**Middleware**: `api` (stateless, no CSRF)

**Purpose**: Return JSON chart data for specified time range

**Parameters**:
- `{video}`: Video ID (integer, required)

**Query Parameters**:
- `range`: Time range selector (string, required)
  - Values: `3days`, `7days`, `14days`, `30days`, `custom`
- `start_date`: Start date for custom range (string, required if range=custom)
  - Format: `Y-m-d` (e.g., `2025-06-13`)
- `end_date`: End date for custom range (string, required if range=custom)
  - Format: `Y-m-d` (e.g., `2025-06-20`)

**Response**: JSON (see api-responses.md)
- **Success (200)**: Chart data with labels and values
- **Bad Request (400)**: Invalid parameters
- **Not Found (404)**: Video doesn't exist
- **Unprocessable Entity (422)**: Validation errors

**Examples**:
```
GET /api/videos/123/chart-data?range=3days
GET /api/videos/123/chart-data?range=custom&start_date=2025-06-13&end_date=2025-06-20
```

---

### 3. Video Overview API (Optional - for AJAX loading)

**Route**: `GET /api/videos/{video}/overview`
**Name**: `api.videos.overview`
**Controller**: `Api\VideoChartDataController@overview`
**Middleware**: `api`

**Purpose**: Return video statistics (views, likes, etc.) with cache status

**Parameters**:
- `{video}`: Video ID (integer, required)

**Response**: JSON
```json
{
    "video_id": 123,
    "youtube_id": "dQw4w9WgXcQ",
    "title": "Video Title",
    "published_at": "2025-06-13 21:00:03",
    "views": 1500000,
    "likes": 50000,
    "comment_count": 12345,
    "cache_age_hours": 12,
    "cache_is_fresh": true,
    "last_updated": "2025-06-13 21:00:03"
}
```

**Status Codes**:
- **200**: Success
- **404**: Video not found
- **500**: Server error

---

## Route Registration (routes/web.php)

```php
use App\Http\Controllers\VideoAnalysisController;
use App\Http\Controllers\Api\VideoChartDataController;

// Web routes
Route::get('/videos/{video}/analysis', [VideoAnalysisController::class, 'show'])
    ->name('videos.analysis');

// API routes
Route::prefix('api')->group(function () {
    Route::get('/videos/{video}/chart-data', [VideoChartDataController::class, 'index'])
        ->name('api.videos.chart-data');

    Route::get('/videos/{video}/overview', [VideoChartDataController::class, 'overview'])
        ->name('api.videos.overview');
});
```

## Route Parameters Validation

### Video ID Validation
```php
// Automatic model binding with validation
public function show(Video $video)
{
    // Laravel automatically validates video exists
    // Returns 404 if not found
}
```

### Query Parameter Validation (Form Request)
```php
// app/Http/Requests/ChartDataRequest.php
public function rules(): array
{
    return [
        'range' => 'required|in:3days,7days,14days,30days,custom',
        'start_date' => 'required_if:range,custom|date_format:Y-m-d',
        'end_date' => 'required_if:range,custom|date_format:Y-m-d|after:start_date',
    ];
}
```

## URL Examples

**Production URLs**:
```
https://example.com/videos/123/analysis
https://example.com/api/videos/123/chart-data?range=7days
https://example.com/api/videos/123/chart-data?range=custom&start_date=2025-06-13&end_date=2025-06-20
```

**Development URLs**:
```
http://localhost:8000/videos/123/analysis
http://localhost:8000/api/videos/123/chart-data?range=3days
```

## Error Responses

All routes follow Laravel standard error response format:

**404 Not Found**:
```json
{
    "message": "Video not found."
}
```

**422 Validation Error**:
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "range": ["The range field is required."],
        "end_date": ["The end date must be a date after start date."]
    }
}
```

**500 Server Error**:
```json
{
    "message": "Server Error"
}
```

## Rate Limiting

**API Routes**: Apply Laravel's default rate limiting
```php
// Default: 60 requests per minute per IP
Route::middleware('throttle:60,1')->group(function () {
    // API routes
});
```

## Security Considerations

1. **CSRF Protection**: Web routes require CSRF token (handled by `web` middleware)
2. **Input Validation**: All user input validated via Form Requests
3. **SQL Injection**: Laravel Eloquent/Query Builder prevents SQL injection
4. **XSS Protection**: Blade templates auto-escape output
5. **Mass Assignment**: Video model uses `$fillable` or `$guarded`

## Testing Routes

**PHPUnit Route Tests**:
```php
// tests/Feature/VideoAnalysisRoutesTest.php
public function test_analysis_page_accessible()
{
    $video = Video::factory()->create();

    $response = $this->get("/videos/{$video->id}/analysis");

    $response->assertStatus(200);
    $response->assertViewIs('videos.analysis');
}

public function test_chart_data_api_returns_json()
{
    $video = Video::factory()->create();

    $response = $this->getJson("/api/videos/{$video->id}/chart-data?range=3days");

    $response->assertStatus(200);
    $response->assertJsonStructure(['labels', 'values', 'granularity']);
}
```
