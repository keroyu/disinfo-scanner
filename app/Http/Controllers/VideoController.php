<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    /**
     * GET /videos - Display paginated list of all videos with comment activity
     *
     * Query Parameters:
     * - search: Keyword to search across video title and channel name (case-insensitive)
     * - sort: Column to sort by (published_at, actual_comment_count, last_comment_time)
     * - direction: Sort direction (asc, desc)
     * - page: Page number for pagination (default: 1, 500 per page)
     */
    public function index(Request $request)
    {
        // Validate request parameters
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'search_channel' => 'nullable|string|max:255',
            'channel_id' => 'nullable|string|max:255',
            'sort' => 'nullable|in:published_at,actual_comment_count,last_comment_time',
            'direction' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
        ]);

        // Build query with comment statistics
        $query = Video::with('channel')
            ->withCommentStats()
            ->hasComments();

        // Apply search filter if provided
        if ($request->filled('search')) {
            $keyword = $request->input('search');
            $query->searchByKeyword($keyword);
        }

        // Apply channel search filter
        if ($request->filled('search_channel')) {
            $channelKeyword = $request->input('search_channel');
            $query->whereHas('channel', function($q) use ($channelKeyword) {
                $q->where('channel_name', 'like', '%' . $channelKeyword . '%');
            });
        }

        // Apply channel ID filter
        if ($request->filled('channel_id')) {
            $channelId = $request->input('channel_id');
            $query->where('channel_id', $channelId);
        }

        // Apply sorting
        $sort = $request->input('sort', 'published_at');
        $direction = $request->input('direction', 'desc');
        $query->sortByColumn($sort, $direction);

        // Paginate results - 500 videos per page
        $videos = $query->paginate(500);

        // Get all channels for the dropdown
        $channels = \App\Models\Channel::orderBy('channel_name', 'asc')->get();

        $breadcrumbs = [
            ['label' => '首頁', 'url' => route('import.index')],
            ['label' => '影片列表'],
        ];

        return view('videos.list', [
            'videos' => $videos,
            'channels' => $channels,
            'sort' => $sort,
            'direction' => $direction,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
