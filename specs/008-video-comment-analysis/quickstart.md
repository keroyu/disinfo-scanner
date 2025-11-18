# Quickstart: Video Comment Density Analysis

**Feature**: 008-video-comment-analysis
**Branch**: `008-video-comment-analysis`
**Date**: 2025-11-19

## Prerequisites

- PHP 8.2+
- Laravel 12.0
- MySQL/MariaDB
- Node.js & npm (for Vite assets)
- Existing DISINFO_SCANNER installation

## Setup Steps

### 1. Checkout Feature Branch

```bash
git checkout 008-video-comment-analysis
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Run Database Migration

```bash
php artisan migrate
```

**Migration adds**:
- `videos.views` column (INT NULL)
- `videos.likes` column (INT NULL)

**Verify migration**:
```bash
php artisan migrate:status
```

### 4. Build Frontend Assets

```bash
npm run build
# or for development with watch mode:
npm run dev
```

### 5. Start Development Server

```bash
php artisan serve
```

Server will start at `http://localhost:8000`

## Testing the Feature

### Manual Testing

1. **Navigate to video list**:
   ```
   http://localhost:8000/videos
   ```

2. **Click "分析" button** next to any video's "更新" button

3. **Verify page loads** with:
   - Breadcrumb: 首頁 > 影片列表 > 影片分析
   - Video overview section
   - Chart skeleton/loading state
   - Time range selector

4. **Test time range selection**:
   - Click "發佈後 3 天內" → Chart should show hourly data
   - Click "發佈後 14 天內" → Chart should show daily data
   - Try custom date range → Should validate dates

5. **Test cache behavior**:
   - First visit: Views/likes fetch from YouTube API
   - Refresh within 24 hours: Data served from cache
   - Check `videos.updated_at` to verify cache timestamp

### Automated Testing

Run PHPUnit tests:

```bash
# Run all tests
php artisan test

# Run specific feature tests
php artisan test --filter VideoAnalysis

# Run with coverage
php artisan test --coverage
```

**Expected test files**:
- `tests/Feature/VideoAnalysisPageTest.php`
- `tests/Feature/CommentDensityChartTest.php`
- `tests/Unit/CommentDensityServiceTest.php`
- `tests/Unit/VideoCacheTest.php`

### API Testing with cURL

**Get chart data (3 days)**:
```bash
curl -X GET "http://localhost:8000/api/videos/1/chart-data?range=3days" \
     -H "Accept: application/json"
```

**Get chart data (custom range)**:
```bash
curl -X GET "http://localhost:8000/api/videos/1/chart-data?range=custom&start_date=2025-06-13&end_date=2025-06-15" \
     -H "Accept: application/json"
```

**Get video overview**:
```bash
curl -X GET "http://localhost:8000/api/videos/1/overview" \
     -H "Accept: application/json"
```

## Development Workflow

### TDD Cycle (Red-Green-Refactor)

1. **Red**: Write failing test
   ```bash
   php artisan test --filter test_chart_data_has_correct_granularity
   ```

2. **Green**: Implement minimal code to pass
   ```php
   // app/Services/CommentDensityService.php
   public function getGranularity(string $range): string
   {
       return in_array($range, ['3days', '7days']) ? 'hourly' : 'daily';
   }
   ```

3. **Refactor**: Improve code while tests pass
   ```bash
   php artisan test
   ```

### Adding New Time Ranges

1. **Update validation rules**:
   ```php
   // app/Http/Requests/ChartDataRequest.php
   'range' => 'required|in:3days,7days,14days,30days,60days,custom',
   ```

2. **Update service logic**:
   ```php
   // app/Services/CommentDensityService.php
   protected function getDateRange(string $range): array
   {
       return match($range) {
           '3days' => [now()->subDays(3), now()],
           '60days' => [now()->subDays(60), now()], // NEW
           // ...
       };
   }
   ```

3. **Update frontend**:
   ```html
   <!-- resources/views/videos/analysis.blade.php -->
   <button @click="timeRange = '60days'">發佈後 60 天內</button>
   ```

4. **Write tests**:
   ```php
   public function test_sixty_days_range_uses_daily_granularity()
   {
       // Test implementation
   }
   ```

## Common Tasks

### Clear Cache for Testing

```bash
# Clear application cache
php artisan cache:clear

# Clear view cache
php artisan view:clear

# Restart development server
php artisan serve
```

### Check Database Schema

```bash
# Show videos table structure
php artisan db:show --table=videos

# Or use MySQL directly
mysql -u root -e "DESCRIBE disinfo_scanner.videos"
```

### View Logs

```bash
# Tail Laravel log
tail -f storage/logs/laravel.log

# Tail API log (structured logging)
tail -f storage/logs/api.log | jq
```

### Seed Test Data

```bash
# Create test video with comments
php artisan tinker
```

```php
$video = \App\Models\Video::factory()->create();
\App\Models\Comment::factory()->count(100)->create(['video_id' => $video->id]);
```

## Troubleshooting

### Issue: "Column 'views' not found"

**Solution**: Run migration
```bash
php artisan migrate
```

### Issue: Chart not rendering

**Check**:
1. JavaScript console for errors
2. Chart.js loaded: `<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>`
3. Alpine.js initialized: `Alpine.start()` called

**Debug**:
```javascript
// Add to resources/js/video-analysis.js
console.log('Chart component initialized', this.$data);
```

### Issue: API returns 404

**Check**:
1. Route registered: `php artisan route:list | grep chart-data`
2. Video exists: `SELECT * FROM videos WHERE id = ?`
3. Controller exists: `ls -la app/Http/Controllers/Api/`

### Issue: Cache not refreshing

**Check**:
1. `videos.updated_at` timestamp
2. YouTube API credentials configured
3. API logs: `tail -f storage/logs/api.log`

**Force cache refresh**:
```php
php artisan tinker
```

```php
$video = \App\Models\Video::find(1);
$video->updated_at = now()->subDays(2); // Make stale
$video->save();
```

### Issue: Timezone incorrect

**Verify config**:
```bash
grep -r "timezone" config/app.php
# Should show: 'timezone' => 'Asia/Taipei',
```

**Test in Tinker**:
```php
php artisan tinker
```

```php
now()->toDateTimeString(); // Should show Asia/Taipei time
```

## Performance Profiling

### Measure Chart Data Query Time

```bash
# Enable query logging
php artisan tinker
```

```php
DB::enableQueryLog();
$service = app(\App\Services\CommentDensityService::class);
$data = $service->getChartData(1, '7days');
dd(DB::getQueryLog());
```

### Profile Page Load

Install Laravel Debugbar (dev only):
```bash
composer require barryvdh/laravel-debugbar --dev
```

Access page and check Debugbar for:
- Database queries count
- Query execution time
- Memory usage

## Code Quality Checks

### Run Laravel Pint (Code Style)

```bash
./vendor/bin/pint
```

### Run PHPStan (Static Analysis)

```bash
composer require phpstan/phpstan --dev
./vendor/bin/phpstan analyse app
```

### Run Tests with Coverage

```bash
php artisan test --coverage --min=80
```

## Deployment Checklist

Before merging to main:

- [ ] All tests passing (`php artisan test`)
- [ ] Migration tested (`php artisan migrate:fresh`)
- [ ] Code style checked (`./vendor/bin/pint`)
- [ ] API contracts validated
- [ ] Frontend assets built (`npm run build`)
- [ ] Logs verified (no errors in `storage/logs/`)
- [ ] Performance targets met (<5s chart render)
- [ ] Documentation updated

## Next Steps

After basic feature works:

1. **Implement P3 features**:
   - Repeat commenter detection
   - High-aggression commenter detection

2. **Optimize performance**:
   - Add Redis caching for chart data
   - Implement query result caching

3. **Enhance UX**:
   - Add export chart as PNG
   - Add date range presets
   - Add comparison mode (multiple videos)

4. **Monitoring**:
   - Set up log aggregation
   - Create dashboard for API metrics
   - Alert on cache refresh failures

## Resources

- **Spec**: `specs/008-video-comment-analysis/spec.md`
- **Plan**: `specs/008-video-comment-analysis/plan.md`
- **Data Model**: `specs/008-video-comment-analysis/data-model.md`
- **Contracts**: `specs/008-video-comment-analysis/contracts/`
- **Laravel Docs**: https://laravel.com/docs/12.x
- **Chart.js Docs**: https://www.chartjs.org/docs/latest/
- **Alpine.js Docs**: https://alpinejs.dev/

## Support

For questions or issues:
1. Check existing tests for usage examples
2. Review contracts documentation
3. Check Laravel logs: `storage/logs/laravel.log`
4. Run `php artisan route:list` to verify routes
