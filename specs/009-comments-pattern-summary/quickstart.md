# Quickstart Guide: Comments Pattern Summary

**Feature**: 009-comments-pattern-summary
**Audience**: Developers implementing this feature
**Prerequisites**: PHP 8.2, Laravel 12.38.1, MySQL/MariaDB, Redis (for caching)

---

## Overview

This feature adds comment pattern analysis to the Video Analysis page:
- Left panel: Filter list (所有留言, repeat, night-time, placeholders)
- Right panel: Always-visible comment list with infinite scroll
- Pattern statistics calculated server-side with caching
- All timestamps displayed in GMT+8 (converted from UTC database)

---

## Quick Start (5 Minutes)

### 1. Clone and Setup

```bash
# Assuming you already have the repo cloned
cd /path/to/DISINFO_SCANNER

# Checkout the feature branch
git checkout 009-comments-pattern-summary

# Install dependencies (if not already done)
composer install
npm install

# Ensure Laravel is running
php artisan serve  # Should already be running on port 8000
```

### 2. Verify Prerequisites

```bash
# Check PHP version (should be 8.2+)
php -v

# Check Laravel version (should be 12.38.1)
php artisan --version

# Verify database connection
php artisan db:show

# Check Redis connection (for caching)
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');  # Should return 'value'
>>> exit
```

### 3. Understand Existing Structure

```bash
# View existing Video Analysis page
open http://localhost:8000/videos/{any-video-id}/analysis

# Check existing comment modal layout
# File: resources/views/comments/list.blade.php (id="commentModal")

# Review existing service pattern
# File: app/Services/CommentDensityAnalysisService.php
```

---

## File Structure (What You'll Create/Modify)

```
📁 NEW FILES (7 files to create)
├── app/Http/Controllers/CommentPatternController.php
├── app/Services/CommentPatternService.php
├── app/Http/Resources/PatternStatisticsResource.php
├── app/Http/Resources/CommentListResource.php
├── resources/views/comments/_pattern_item.blade.php
├── resources/js/comment-pattern.js
└── tests/Feature/Api/CommentPatternTest.php

⚠️ MODIFIED FILES (4 files to update)
├── app/Http/Controllers/VideoAnalysisController.php
├── app/Models/Comment.php
├── resources/views/videos/analysis.blade.php
└── routes/api.php
```

---

## Implementation Steps (TDD Workflow)

### Phase 1: Write Tests First (Constitution Principle I)

```bash
# Create test file
touch tests/Feature/Api/CommentPatternTest.php
```

**tests/Feature/Api/CommentPatternTest.php**:
```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Video;
use App\Models\Comment;
use App\Models\Author;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CommentPatternTest extends TestCase
{
    use RefreshDatabase;

    public function test_pattern_statistics_returns_correct_structure()
    {
        // Arrange
        $video = Video::factory()->create();
        $authors = Author::factory()->count(10)->create();

        // Create comments: 10 authors, 2 comments each (all are repeat commenters)
        foreach ($authors as $author) {
            Comment::factory()->count(2)->create([
                'video_id' => $video->video_id,
                'author_channel_id' => $author->channel_id,
            ]);
        }

        // Act
        $response = $this->getJson("/api/videos/{$video->video_id}/pattern-statistics");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'video_id',
                'total_comments',
                'patterns' => [
                    'all' => ['type', 'count', 'percentage', 'total_commenters'],
                    'repeat' => ['type', 'count', 'percentage', 'total_commenters'],
                    'night_time',
                    'aggressive',
                    'simplified_chinese',
                ],
                'calculated_at',
            ])
            ->assertJson([
                'patterns' => [
                    'all' => ['count' => 10, 'percentage' => 100],
                    'repeat' => ['count' => 10, 'percentage' => 100],  // All 10 are repeat
                ],
            ]);
    }

    public function test_paginated_comments_returns_correct_format()
    {
        // Arrange
        $video = Video::factory()->create();
        $author = Author::factory()->create();
        Comment::factory()->count(150)->create([
            'video_id' => $video->video_id,
            'author_channel_id' => $author->channel_id,
        ]);

        // Act
        $response = $this->getJson("/api/videos/{$video->video_id}/comments?offset=0&limit=100");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['comment_id', 'author_channel_id', 'author_name', 'text', 'like_count', 'published_at', 'is_reply'],
                ],
                'meta' => ['offset', 'limit', 'returned', 'has_more'],
            ])
            ->assertJson([
                'meta' => [
                    'offset' => 0,
                    'limit' => 100,
                    'returned' => 100,
                    'has_more' => true,
                ],
            ]);
    }
}
```

**Run tests (they should FAIL - Red phase)**:
```bash
php artisan test --filter=CommentPatternTest
# Expected: All tests fail (routes/controllers don't exist yet)
```

---

### Phase 2: Implement Backend (Green Phase)

#### Step 2.1: Create Service Layer

**app/Services/CommentPatternService.php**:
```php
<?php

namespace App\Services;

use App\Models\Comment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CommentPatternService
{
    public function getPatternStatistics(string $videoId): array
    {
        $cacheKey = "video:{$videoId}:pattern:all_statistics";

        return Cache::remember($cacheKey, 300, function () use ($videoId) {
            $totalComments = Comment::where('video_id', $videoId)->count();
            $totalCommenters = Comment::where('video_id', $videoId)
                ->distinct('author_channel_id')
                ->count('author_channel_id');

            return [
                'video_id' => $videoId,
                'total_comments' => $totalComments,
                'patterns' => [
                    'all' => $this->allCommentsPattern($videoId, $totalCommenters),
                    'repeat' => $this->repeatCommentersPattern($videoId, $totalCommenters),
                    'night_time' => $this->nightTimePattern($videoId, $totalCommenters),
                    'aggressive' => $this->placeholderPattern('aggressive', $totalCommenters),
                    'simplified_chinese' => $this->placeholderPattern('simplified_chinese', $totalCommenters),
                ],
                'calculated_at' => now()->setTimezone('Asia/Taipei')->toIso8601String(),
            ];
        });
    }

    private function repeatCommentersPattern(string $videoId, int $totalCommenters): array
    {
        $count = DB::table('comments')
            ->where('video_id', $videoId)
            ->select('author_channel_id')
            ->groupBy('author_channel_id')
            ->havingRaw('COUNT(*) >= 2')
            ->count();

        return [
            'type' => 'repeat',
            'count' => $count,
            'percentage' => $totalCommenters > 0 ? round(($count / $totalCommenters) * 100) : 0,
            'total_commenters' => $totalCommenters,
        ];
    }

    private function nightTimePattern(string $videoId, int $totalCommenters): array
    {
        // Get all commenters on this video
        $videoCommenters = Comment::where('video_id', $videoId)
            ->distinct()
            ->pluck('author_channel_id');

        // Find which ones are night-time high-frequency across ALL channels
        $nightTimeCommenters = DB::table('comments')
            ->select('author_channel_id')
            ->selectRaw('
                COUNT(*) as total_comments,
                SUM(CASE
                    WHEN HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) BETWEEN 1 AND 5
                    THEN 1 ELSE 0
                END) as night_comments
            ')
            ->whereIn('author_channel_id', $videoCommenters)
            ->whereNotNull('published_at')
            ->groupBy('author_channel_id')
            ->havingRaw('total_comments >= 2')
            ->havingRaw('night_comments / total_comments > 0.5')
            ->pluck('author_channel_id');

        $count = $nightTimeCommenters->count();

        return [
            'type' => 'night_time',
            'count' => $count,
            'percentage' => $totalCommenters > 0 ? round(($count / $totalCommenters) * 100) : 0,
            'total_commenters' => $totalCommenters,
        ];
    }

    private function allCommentsPattern(string $videoId, int $totalCommenters): array
    {
        return [
            'type' => 'all',
            'count' => $totalCommenters,
            'percentage' => 100,
            'total_commenters' => $totalCommenters,
        ];
    }

    private function placeholderPattern(string $type, int $totalCommenters): array
    {
        return [
            'type' => $type,
            'count' => 0,
            'percentage' => 0,
            'total_commenters' => $totalCommenters,
        ];
    }

    public function getCommentsByPattern(
        string $videoId,
        string $pattern,
        int $offset = 0,
        int $limit = 100
    ): array {
        // Base query
        $query = Comment::where('video_id', $videoId)
            ->with('author')
            ->orderBy('published_at', 'desc')
            ->orderBy('comment_id', 'asc');

        // Apply pattern filter
        if ($pattern === 'repeat') {
            $repeatAuthorIds = DB::table('comments')
                ->where('video_id', $videoId)
                ->select('author_channel_id')
                ->groupBy('author_channel_id')
                ->havingRaw('COUNT(*) >= 2')
                ->pluck('author_channel_id');

            $query->whereIn('author_channel_id', $repeatAuthorIds);
        } elseif ($pattern === 'night_time') {
            // Similar logic as nightTimePattern
            $videoCommenters = Comment::where('video_id', $videoId)
                ->distinct()
                ->pluck('author_channel_id');

            $nightTimeAuthorIds = DB::table('comments')
                ->select('author_channel_id')
                ->selectRaw('
                    COUNT(*) as total_comments,
                    SUM(CASE
                        WHEN HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) BETWEEN 1 AND 5
                        THEN 1 ELSE 0
                    END) as night_comments
                ')
                ->whereIn('author_channel_id', $videoCommenters)
                ->whereNotNull('published_at')
                ->groupBy('author_channel_id')
                ->havingRaw('total_comments >= 2')
                ->havingRaw('night_comments / total_comments > 0.5')
                ->pluck('author_channel_id');

            $query->whereIn('author_channel_id', $nightTimeAuthorIds);
        } elseif (in_array($pattern, ['aggressive', 'simplified_chinese'])) {
            // Placeholder: return empty
            $query->whereRaw('1 = 0');
        }

        // Get paginated results
        $comments = $query->offset($offset)->limit($limit)->get();
        $hasMore = Comment::where('video_id', $videoId)->count() > ($offset + $comments->count());

        return [
            'data' => $comments->map(fn($c) => [
                'comment_id' => $c->comment_id,
                'author_channel_id' => $c->author_channel_id,
                'author_name' => $c->author?->channel_name ?? 'Unknown',
                'text' => $c->text,
                'like_count' => $c->like_count,
                'published_at' => $c->published_at
                    ->setTimezone('Asia/Taipei')
                    ->format('Y/m/d H:i') . ' (GMT+8)',
                'is_reply' => !is_null($c->parent_comment_id),
            ])->toArray(),
            'meta' => [
                'offset' => $offset,
                'limit' => $limit,
                'returned' => $comments->count(),
                'has_more' => $hasMore,
            ],
        ];
    }
}
```

#### Step 2.2: Create Controller

**app/Http/Controllers/CommentPatternController.php**:
```php
<?php

namespace App\Http\Controllers;

use App\Services\CommentPatternService;
use Illuminate\Http\Request;

class CommentPatternController extends Controller
{
    public function __construct(
        private CommentPatternService $service
    ) {}

    public function getPatternStatistics(string $videoId)
    {
        return response()->json(
            $this->service->getPatternStatistics($videoId)
        );
    }

    public function getCommentsByPattern(Request $request, string $videoId)
    {
        $validated = $request->validate([
            'pattern' => 'sometimes|string|in:all,repeat,night_time,aggressive,simplified_chinese',
            'offset' => 'sometimes|integer|min:0',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        return response()->json(
            $this->service->getCommentsByPattern(
                $videoId,
                $validated['pattern'] ?? 'all',
                $validated['offset'] ?? 0,
                $validated['limit'] ?? 100
            )
        );
    }
}
```

#### Step 2.3: Register Routes

**routes/api.php** (add these lines):
```php
use App\Http\Controllers\CommentPatternController;

// Pattern statistics
Route::get('/videos/{videoId}/pattern-statistics',
    [CommentPatternController::class, 'getPatternStatistics']);

// Paginated comments by pattern
Route::get('/videos/{videoId}/comments',
    [CommentPatternController::class, 'getCommentsByPattern']);
```

**Run tests again (should PASS - Green phase)**:
```bash
php artisan test --filter=CommentPatternTest
# Expected: All tests pass
```

---

### Phase 3: Frontend Implementation

#### Step 3.1: Update Analysis Page Blade Template

**resources/views/videos/analysis.blade.php** (add before closing `</div>`):
```html
<!-- Comments Pattern Summary Section -->
<div class="bg-white rounded-lg shadow-lg p-6 mt-6">
    <h2 class="text-xl font-bold mb-4">留言模式分析</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Left Panel: Pattern Filters -->
        <div>
            <h3 class="text-lg font-semibold mb-3">模式篩選</h3>
            <div id="pattern-filter-list" class="space-y-2">
                <!-- Populated by JavaScript -->
            </div>
        </div>

        <!-- Right Panel: Comment List -->
        <div>
            <h3 class="text-lg font-semibold mb-3">留言列表</h3>
            <div id="comment-list-container" class="border rounded-lg p-4 h-96 overflow-y-auto">
                <div id="comment-list" class="space-y-4">
                    <!-- Populated by JavaScript -->
                </div>
                <div id="comment-list-sentinel" class="h-4"></div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/comment-pattern.js') }}"></script>
<script>
    const commentPatternUI = new CommentPatternUI('{{ $video->video_id }}');
</script>
```

#### Step 3.2: Create JavaScript Module

**resources/js/comment-pattern.js**:
```javascript
class CommentPatternUI {
    constructor(videoId) {
        this.videoId = videoId;
        this.offset = 0;
        this.loading = false;
        this.hasMore = true;
        this.currentPattern = 'all';
        this.statistics = null;

        this.init();
    }

    async init() {
        await this.loadStatistics();
        this.renderFilterList();
        await this.loadComments();
        this.setupInfiniteScroll();
    }

    async loadStatistics() {
        const response = await fetch(`/api/videos/${this.videoId}/pattern-statistics`);
        this.statistics = await response.json();
    }

    renderFilterList() {
        const filterList = document.getElementById('pattern-filter-list');
        const patterns = [
            { key: 'all', label: '所有留言' },
            { key: 'repeat', label: '重複留言者' },
            { key: 'night_time', label: '夜間高頻留言者' },
            { key: 'aggressive', label: '高攻擊性留言者' },
            { key: 'simplified_chinese', label: '簡體中文留言者' },
        ];

        filterList.innerHTML = patterns.map(p => {
            const stats = this.statistics.patterns[p.key];
            const isActive = p.key === this.currentPattern;
            const count = p.key === 'all' ? stats.count : (stats.count === 0 ? 'X' : stats.count);
            const percentage = stats.percentage;

            return `
                <button
                    class="w-full text-left px-4 py-2 rounded transition-colors ${
                        isActive ? 'bg-blue-100 border-2 border-blue-500' : 'bg-gray-50 hover:bg-gray-100'
                    }"
                    onclick="commentPatternUI.switchPattern('${p.key}')"
                >
                    ${p.label}有 ${count} 個 (${percentage}%)
                </button>
            `;
        }).join('');
    }

    async loadComments() {
        if (this.loading || !this.hasMore) return;

        this.loading = true;
        const response = await fetch(
            `/api/videos/${this.videoId}/comments?pattern=${this.currentPattern}&offset=${this.offset}&limit=100`
        );
        const data = await response.json();

        this.appendComments(data.data);
        this.offset += data.meta.returned;
        this.hasMore = data.meta.has_more;
        this.loading = false;
    }

    appendComments(comments) {
        const list = document.getElementById('comment-list');
        comments.forEach(comment => {
            const div = document.createElement('div');
            div.className = 'border-b pb-3';
            div.innerHTML = `
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 rounded-full bg-gray-300 flex-shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-baseline space-x-2">
                            <span class="font-semibold text-sm text-gray-900">${comment.author_name}</span>
                            <span class="text-xs text-gray-500">${comment.published_at}</span>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-wrap break-words mt-1">${comment.text}</p>
                        <div class="flex items-center space-x-4 mt-2 text-xs text-gray-600">
                            <span><i class="fas fa-thumbs-up"></i> ${comment.like_count}</span>
                        </div>
                    </div>
                </div>
            `;
            list.appendChild(div);
        });
    }

    switchPattern(pattern) {
        this.currentPattern = pattern;
        this.offset = 0;
        this.hasMore = true;
        document.getElementById('comment-list').innerHTML = '';
        this.renderFilterList();  // Update highlighting
        this.loadComments();
    }

    setupInfiniteScroll() {
        const sentinel = document.getElementById('comment-list-sentinel');
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && this.hasMore && !this.loading) {
                this.loadComments();
            }
        }, { threshold: 0.1 });

        observer.observe(sentinel);
    }
}
```

**Build assets**:
```bash
npm run build
# or for development:
npm run dev
```

---

## Testing Your Implementation

### Manual Testing

1. **Start Laravel**:
   ```bash
   php artisan serve
   ```

2. **Visit Video Analysis Page**:
   ```
   http://localhost:8000/videos/{any-video-id}/analysis
   ```

3. **Verify**:
   - [ ] Left panel shows 5 filter buttons
   - [ ] "所有留言" is highlighted by default
   - [ ] Right panel shows first 100 comments
   - [ ] Timestamps show "(GMT+8)" format
   - [ ] Scrolling to bottom loads more comments
   - [ ] Clicking different filters reloads comment list
   - [ ] Repeat/night-time show correct counts
   - [ ] Aggressive/Chinese show "X 個 (0%)"

### Automated Testing

```bash
# Run all tests
php artisan test

# Run only pattern tests
php artisan test --filter=CommentPatternTest

# Run with coverage
php artisan test --coverage
```

---

## Troubleshooting

### Issue: Timezone conversion not working

```bash
# Check MySQL timezone tables
mysql -u root -p
SELECT CONVERT_TZ('2025-01-01 00:00:00', '+00:00', '+08:00');
# Should return: 2025-01-01 08:00:00

# If NULL, load timezone tables:
mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql
```

### Issue: Redis caching not working

```bash
# Check Redis connection
php artisan tinker
>>> Cache::driver();  # Should be 'redis'
>>> Cache::put('test', 'hello', 60);
>>> Cache::get('test');  # Should return 'hello'
```

### Issue: JavaScript not loading

```bash
# Rebuild assets
npm run build

# Check browser console for errors
# Open DevTools → Console
```

---

## Performance Monitoring

### Add Logging to Service

```php
use Illuminate\Support\Facades\Log;

public function getPatternStatistics(string $videoId): array
{
    $start = microtime(true);
    $result = Cache::remember(...);
    $duration = (microtime(true) - $start) * 1000;

    Log::info('Pattern statistics calculated', [
        'video_id' => $videoId,
        'duration_ms' => round($duration, 2),
        'cache_hit' => Cache::has($cacheKey),
    ]);

    return $result;
}
```

### Monitor Logs

```bash
tail -f storage/logs/laravel.log | grep "Pattern statistics"
```

---

## Next Steps

1. Run `/speckit.tasks` to generate task breakdown
2. Implement tasks in order (TDD workflow)
3. Write additional edge case tests
4. Optimize night-time query if needed
5. Add error handling and user feedback
6. Create pull request when feature complete

---

## References

- [Feature Spec](./spec.md)
- [Research Document](./research.md)
- [Data Model](./data-model.md)
- [API Contracts](./contracts/)
- [Laravel Docs](https://laravel.com/docs/12.x)
