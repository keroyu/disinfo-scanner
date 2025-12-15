<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\EscapesLikeQueries;
use App\Models\Comment;
use App\Models\Channel;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use EscapesLikeQueries;

    /**
     * GET /comments - Display paginated list of all comments with search, filter, and sort
     *
     * Query Parameters:
     * - search: Keyword to search across channel_name, video_title, commenter_id, content
     * - from_date: Start date for date range filter (YYYY-MM-DD)
     * - to_date: End date for date range filter (YYYY-MM-DD)
     * - sort: Column to sort by (likes, date)
     * - direction: Sort direction (asc, desc)
     * - page: Page number for pagination (default: 1, 500 per page)
     */
    public function index(Request $request)
    {
        $query = Comment::with(['video.channel', 'author']);

        // T132: Check if user has permission to use search filters
        // Only Premium Members, Website Editors, and Administrators can use search functionality
        $canSearch = auth()->check() && (
            auth()->user()->roles->contains('name', 'premium_member') ||
            auth()->user()->roles->contains('name', 'website_editor') ||
            auth()->user()->roles->contains('name', 'administrator')
        );

        // Apply keyword search filter (video title, commenter, content)
        // Only apply if user has search permission
        if ($canSearch && $request->filled('search')) {
            $keyword = $request->input('search');
            $query->filterByKeyword($keyword);
        }

        // Apply channel search filter (by name)
        // Only apply if user has search permission
        if ($canSearch && $request->filled('search_channel')) {
            $channelKeyword = $request->input('search_channel');
            $query->filterByChannel($channelKeyword);
        }

        // Apply channel filter (by ID from dropdown or table links)
        // Only apply if user has search permission
        if ($canSearch && $request->filled('channel_id')) {
            $channelId = $request->input('channel_id');
            $query->whereHas('video.channel', function ($q) use ($channelId) {
                $q->where('channel_id', $channelId);
            });
        }

        // Apply video filter
        // Only apply if user has search permission
        if ($canSearch && $request->filled('video_id')) {
            $videoId = $request->input('video_id');
            $query->filterByVideo($videoId);
        }

        // Apply date range filter (default to last 30 days)
        $fromDate = $request->input('from_date', now()->subDays(30)->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        // Always apply date range filter (uses defaults if not provided)
        $query->filterByDateRange($fromDate, $toDate);

        // Apply time period filter
        if ($request->filled('time_period')) {
            $timePeriod = $request->input('time_period');
            $query->filterByTimePeriod($timePeriod);
        }

        // Apply sorting
        $sort = $request->input('sort', 'date');
        $direction = $request->input('direction', 'desc');

        if ($sort === 'likes') {
            $query->sortByLikes($direction);
        } else {
            $query->sortByDate($direction);
        }

        // Paginate results - 100 comments per page
        $comments = $query->paginate(100);

        // Get all channels for the dropdown
        $channels = Channel::orderBy('channel_name')->get();

        // Calculate commenter repeat counts per video (for "重複" label)
        // Build a map: [video_id][author_channel_id] => count
        $videoIds = $comments->pluck('video_id')->unique()->toArray();
        $repeatCounts = [];

        if (!empty($videoIds)) {
            $commentCounts = Comment::whereIn('video_id', $videoIds)
                ->whereNotNull('author_channel_id')
                ->selectRaw('video_id, author_channel_id, COUNT(*) as count')
                ->groupBy('video_id', 'author_channel_id')
                ->get();

            foreach ($commentCounts as $row) {
                $repeatCounts[$row->video_id][$row->author_channel_id] = $row->count;
            }
        }

        $breadcrumbs = [
            ['label' => '首頁', 'url' => route('import.index')],
            ['label' => '留言列表'],
        ];

        return view('comments.list', [
            'comments' => $comments,
            'channels' => $channels,
            'repeatCounts' => $repeatCounts,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
