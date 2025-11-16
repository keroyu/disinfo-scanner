# Quickstart: Comments List Implementation

**Feature**: Comments List View
**Date**: 2025-11-16
**Status**: Implementation Guide

This document provides a rapid onboarding guide for developers implementing the comments list feature.

---

## Feature Summary

Display a paginated list of YouTube comments (500 per page) with search, filtering, sorting, and navigation capabilities.

**Key Features**:
- ✅ View all comments with metadata (channel name, video title, commenter ID, content, date, likes)
- ✅ Search across multiple fields (channel name, video title, commenter ID, comment content)
- ✅ Filter by date range (inclusive)
- ✅ Sort by likes or comment date (toggle ASC/DESC)
- ✅ Navigate to YouTube channels and videos with comment anchors
- ✅ Pagination with 500 comments per page
- ✅ Performance optimized for 10,000+ records

---

## Architecture Overview

```
HTTP Request → CommentController → CommentFilterService
                                    ↓
                        Comment Model (Eloquent Scopes)
                                    ↓
                              MySQL Database
                                    ↓
              Blade Template → HTML (rendered server-side)
```

### Components

1. **CommentController** (`app/Http/Controllers/CommentController.php`)
   - Routes GET requests to comments list page and API endpoint
   - Validates query parameters
   - Delegates filtering to service

2. **CommentFilterService** (`app/Services/CommentFilterService.php`)
   - Encapsulates filtering, sorting, pagination logic
   - Chainable method calls for composability
   - Returns Laravel Paginator instance

3. **Comment Model** (`app/Models/Comment.php`)
   - Eloquent scopes for filtering: `filterByKeyword()`, `filterByDateRange()`
   - Eloquent scopes for sorting: `sortByLikes()`, `sortByDate()`
   - Relationships to Video and Channel models

4. **Blade Templates** (`resources/views/comments/`)
   - Main list view: `index.blade.php`
   - Reusable components: search bar, date picker, pagination
   - Responsive design with Tailwind CSS

5. **Database Indexes**
   - Composite index on searchable columns for fast LIKE queries
   - Indexes on sort columns (published_at, like_count) for quick ordering

---

## Development Workflow

### Phase 1: Setup & Database

**1a. Create Database Migration** (if indexes not already present)

```php
// database/migrations/2025_11_16_add_comments_indexes.php
Schema::table('comments', function (Blueprint $table) {
    $table->index('published_at');
    $table->index('like_count');
    $table->index('channel_name');
    $table->index('video_title');
    $table->index('commenter_id');
});
```

**1b. Seed Test Data** (if needed)

```bash
php artisan db:seed --class=CommentSeeder
# Ensure you have >500 comments for pagination testing
```

### Phase 2: Core Logic

**2a. Define Comment Model Scopes** (`app/Models/Comment.php`)

```php
public function scopeFilterByKeyword($query, $keyword)
{
    return $query->where(function ($q) use ($keyword) {
        $q->whereRaw("LOWER(channel_name) LIKE LOWER(?)", ["%{$keyword}%"])
          ->orWhereRaw("LOWER(video_title) LIKE LOWER(?)", ["%{$keyword}%"])
          ->orWhereRaw("LOWER(commenter_id) LIKE LOWER(?)", ["%{$keyword}%"])
          ->orWhereRaw("LOWER(content) LIKE LOWER(?)", ["%{$keyword}%"]);
    });
}

public function scopeFilterByDateRange($query, $from, $to)
{
    return $query->whereBetween('published_at', [$from, $to]);
}

public function scopeSortByLikes($query, $direction = 'DESC')
{
    return $query->orderBy('like_count', $direction);
}

public function scopeSortByDate($query, $direction = 'DESC')
{
    return $query->orderBy('published_at', $direction);
}
```

**2b. Create Filter Service** (`app/Services/CommentFilterService.php`)

```php
namespace App\Services;

use App\Models\Comment;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;

class CommentFilterService
{
    private $query;

    public function __construct()
    {
        $this->query = Comment::query();
    }

    public function searchKeyword(?string $keyword)
    {
        if ($keyword) {
            $this->query->filterByKeyword($keyword);
        }
        return $this;
    }

    public function filterByDateRange(?string $from, ?string $to)
    {
        if ($from && $to) {
            $fromDate = Carbon::parse($from)->startOfDay();
            $toDate = Carbon::parse($to)->endOfDay();
            $this->query->filterByDateRange($fromDate, $toDate);
        }
        return $this;
    }

    public function sort(string $column = 'published_at', string $direction = 'DESC')
    {
        if ($column === 'like_count') {
            $this->query->sortByLikes($direction);
        } else {
            $this->query->sortByDate($direction);
        }
        return $this;
    }

    public function paginate(int $perPage = 500): Paginator
    {
        return $this->query->paginate($perPage);
    }
}
```

### Phase 3: Controller & Routes

**3a. Create Controller** (`app/Http/Controllers/CommentController.php`)

```php
namespace App\Http\Controllers;

use App\Services\CommentFilterService;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'keyword' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort' => 'nullable|in:published_at,like_count',
            'direction' => 'nullable|in:ASC,DESC',
            'page' => 'nullable|integer|min:1',
        ]);

        $service = new CommentFilterService();
        $comments = $service
            ->searchKeyword($validated['keyword'] ?? null)
            ->filterByDateRange($validated['date_from'] ?? null, $validated['date_to'] ?? null)
            ->sort($validated['sort'] ?? 'published_at', $validated['direction'] ?? 'DESC')
            ->paginate(500);

        return view('comments.index', [
            'comments' => $comments,
            'filters' => [
                'keyword' => $validated['keyword'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'sort' => $validated['sort'] ?? 'published_at',
                'direction' => $validated['direction'] ?? 'DESC',
            ],
        ]);
    }
}
```

**3b. Register Routes** (`routes/web.php`)

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/comments', [CommentController::class, 'index'])->name('comments.index');
});
```

### Phase 4: Views

**4a. Main List View** (`resources/views/comments/index.blade.php`)

```blade
<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <h1 class="text-3xl font-bold mb-6">Comments List</h1>

        {{-- Search Bar --}}
        <form method="GET" action="{{ route('comments.index') }}" class="mb-6">
            <div class="flex gap-4">
                <input type="text" name="keyword" placeholder="Search..."
                    value="{{ request('keyword') }}" class="flex-1 px-4 py-2 border rounded">
                <input type="date" name="date_from"
                    value="{{ request('date_from') }}" class="px-4 py-2 border rounded">
                <input type="date" name="date_to"
                    value="{{ request('date_to') }}" class="px-4 py-2 border rounded">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded">Search</button>
            </div>
        </form>

        {{-- Comments Table --}}
        @if($comments->count() > 0)
            <table class="w-full border-collapse border">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border p-2 text-left"><a href="{{ route('comments.index', array_merge(request()->query(), ['sort' => 'published_at', 'direction' => request('direction') === 'ASC' ? 'DESC' : 'ASC'])) }}">Date</a></th>
                        <th class="border p-2 text-left">Channel</th>
                        <th class="border p-2 text-left">Video</th>
                        <th class="border p-2 text-left">Commenter</th>
                        <th class="border p-2 text-left">Content</th>
                        <th class="border p-2 text-right"><a href="{{ route('comments.index', array_merge(request()->query(), ['sort' => 'like_count', 'direction' => request('direction') === 'ASC' ? 'DESC' : 'ASC'])) }}">Likes</a></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($comments as $comment)
                        <tr class="border">
                            <td class="border p-2">{{ $comment->published_at->format('Y-m-d H:i') }}</td>
                            <td class="border p-2">
                                <a href="https://www.youtube.com/@{{ $comment->channel_name }}"
                                    target="_blank" class="text-blue-600 underline">
                                    {{ $comment->channel_name }}
                                </a>
                            </td>
                            <td class="border p-2">
                                <a href="https://www.youtube.com/watch?v={{ $comment->video_id }}&lc={{ $comment->id }}"
                                    target="_blank" class="text-blue-600 underline">
                                    {{ $comment->video_title }}
                                </a>
                            </td>
                            <td class="border p-2">{{ $comment->commenter_id }}</td>
                            <td class="border p-2">{{ Str::limit($comment->content, 100) }}</td>
                            <td class="border p-2 text-right">{{ $comment->like_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $comments->links() }}
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                No comments found. Try adjusting your filters.
            </div>
        @endif
    </div>
</x-app-layout>
```

### Phase 5: Testing

**5a. Contract Test** (`tests/Feature/CommentListContractTest.php`)

```php
namespace Tests\Feature;

use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentListContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_list_response_includes_required_fields()
    {
        Comment::factory(10)->create();

        $response = $this->get('/comments');

        $response->assertStatus(200);
        // Assert response contains all required fields
        $response->assertSee('Comments List');
    }

    public function test_search_filters_comments()
    {
        Comment::factory()->create(['channel_name' => 'Bitcoin News']);
        Comment::factory()->create(['channel_name' => 'Tech Channel']);

        $response = $this->get('/comments?keyword=Bitcoin');

        $response->assertSee('Bitcoin News');
        $response->assertDontSee('Tech Channel');
    }
}
```

**5b. Service Unit Test** (`tests/Unit/CommentFilterServiceTest.php`)

```php
namespace Tests\Unit;

use App\Models\Comment;
use App\Services\CommentFilterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentFilterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_by_keyword()
    {
        Comment::factory()->create(['channel_name' => 'Bitcoin Channel']);
        Comment::factory()->create(['channel_name' => 'Tech Channel']);

        $service = new CommentFilterService();
        $results = $service->searchKeyword('Bitcoin')->paginate();

        $this->assertEquals(1, $results->total());
    }

    public function test_filter_by_date_range()
    {
        Comment::factory()->create(['published_at' => Carbon::parse('2025-11-10')]);
        Comment::factory()->create(['published_at' => Carbon::parse('2025-11-20')]);

        $service = new CommentFilterService();
        $results = $service->filterByDateRange('2025-11-15', '2025-11-25')->paginate();

        $this->assertEquals(1, $results->total());
    }
}
```

---

## Running the Implementation

### Step-by-step:

1. **Create migration** for database indexes:
   ```bash
   php artisan make:migration add_comments_indexes
   # Edit migration and run: php artisan migrate
   ```

2. **Create model scopes** in `app/Models/Comment.php`

3. **Create service** at `app/Services/CommentFilterService.php`

4. **Create controller** at `app/Http/Controllers/CommentController.php`

5. **Register routes** in `routes/web.php`

6. **Create Blade template** in `resources/views/comments/index.blade.php`

7. **Write tests** in `tests/Feature/` and `tests/Unit/`

8. **Run tests**:
   ```bash
   php artisan test
   ```

9. **Start development server**:
   ```bash
   php artisan serve
   ```

10. **Navigate to** `http://localhost:8000/comments`

---

## File Checklist

- [ ] `database/migrations/[timestamp]_add_comments_indexes.php`
- [ ] `app/Models/Comment.php` (add scopes)
- [ ] `app/Services/CommentFilterService.php`
- [ ] `app/Http/Controllers/CommentController.php`
- [ ] `routes/web.php` (add comment routes)
- [ ] `resources/views/comments/index.blade.php`
- [ ] `resources/views/layouts/app.blade.php` (update navigation)
- [ ] `tests/Feature/CommentListContractTest.php`
- [ ] `tests/Unit/CommentFilterServiceTest.php`

---

## Performance Checklist

Before deployment:

- [ ] Database indexes created and verified: `SHOW INDEX FROM comments;`
- [ ] Page load time <3s (test with 10k+ records)
- [ ] Search response <2s for 10k+ records
- [ ] Sort operations <1s
- [ ] All tests passing: `php artisan test`
- [ ] No N+1 query problems (verify with Laravel Debugbar)
- [ ] Pagination working correctly (test pages 1, 5, 25)

---

## Troubleshooting

**Q: Search is slow**
A: Ensure database indexes exist. Run `SHOW INDEX FROM comments;` and verify indexes on channel_name, video_title, commenter_id, content.

**Q: Pagination shows 0 results**
A: Check your date/keyword filters are actually matching records. Verify with: `SELECT COUNT(*) FROM comments WHERE channel_name LIKE '%keyword%';`

**Q: YouTube links are broken**
A: Verify channel_name and video_id fields contain actual YouTube data. Test with: `SELECT channel_name, video_id FROM comments LIMIT 1;`

---

## Next Steps

After implementation:

1. **API Endpoint** (optional): Create `/api/comments` endpoint for JSON responses (see contracts/CONTRACTS.md)
2. **Performance Monitoring**: Add structured logging with trace IDs
3. **Advanced Filters**: Add commenter ID exact-match filter, like count range filter
4. **Caching**: Consider Redis caching for popular queries (no filter, default sort)
5. **Export**: Add "Export to CSV" functionality for analyst reports

---

## References

- **Specification**: `specs/004-comments-list/spec.md`
- **Data Model**: `specs/004-comments-list/data-model.md`
- **API Contracts**: `specs/004-comments-list/contracts/CONTRACTS.md`
- **Implementation Plan**: `specs/004-comments-list/plan.md`
- **Research**: `specs/004-comments-list/research.md`

---

## Support

For questions or issues:
1. Check troubleshooting section above
2. Review existing tests for examples
3. Refer to data model for field descriptions
4. Check API contracts for expected behavior
