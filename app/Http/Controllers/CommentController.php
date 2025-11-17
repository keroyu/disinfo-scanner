<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Channel;
use Illuminate\Http\Request;

class CommentController extends Controller
{
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

        // Apply keyword search filter (video title, commenter, content)
        if ($request->filled('search')) {
            $keyword = $request->input('search');
            $query->filterByKeyword($keyword);
        }

        // Apply channel search filter
        if ($request->filled('search_channel')) {
            $channelKeyword = $request->input('search_channel');
            $query->filterByChannel($channelKeyword);
        }

        // Apply date range filter (default to last 30 days)
        $fromDate = $request->input('from_date', now()->subDays(30)->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        // Always apply date range filter (uses defaults if not provided)
        $query->filterByDateRange($fromDate, $toDate);

        // Apply sorting
        $sort = $request->input('sort', 'date');
        $direction = $request->input('direction', 'desc');

        if ($sort === 'likes') {
            $query->sortByLikes($direction);
        } else {
            $query->sortByDate($direction);
        }

        // Paginate results - 500 comments per page
        $comments = $query->paginate(500);

        // Get all channels for the dropdown
        $channels = Channel::orderBy('channel_name')->get();

        return view('comments.list', [
            'comments' => $comments,
            'channels' => $channels,
        ]);
    }
}
