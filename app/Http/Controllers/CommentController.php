<?php

namespace App\Http\Controllers;

use App\Models\Comment;
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

        // Apply keyword search filter
        if ($request->filled('search')) {
            $keyword = $request->input('search');
            $query->filterByKeyword($keyword);
        }

        // Apply date range filter
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $query->filterByDateRange($fromDate, $toDate);
        }

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

        return view('comments.list', [
            'comments' => $comments,
        ]);
    }
}
