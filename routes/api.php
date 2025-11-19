<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

// U-API (urtubeapi Third-Party) Import endpoints - Grouped under /api/uapi/*
Route::prefix('uapi')->group(function () {
    Route::post('/import', [\App\Http\Controllers\UrtubeApiImportController::class, 'store']);
    Route::post('/confirm', [\App\Http\Controllers\UrtubeApiConfirmationController::class, 'confirm']);
    Route::post('/cancel', [\App\Http\Controllers\UrtubeApiConfirmationController::class, 'cancel']);
    Route::get('/tags', [\App\Http\Controllers\UrtubeApiTagSelectionController::class, 'index']);
    Route::post('/tags/select', [\App\Http\Controllers\UrtubeApiTagSelectionController::class, 'store']);
});

// YouTube API import endpoints
Route::post('/youtube-import/preview', [\App\Http\Controllers\YouTubeApiImportController::class, 'preview']);
Route::post('/youtube-import/confirm', [\App\Http\Controllers\YouTubeApiImportController::class, 'confirm']);

// Official YouTube API comment import endpoints (005-api-import-comments)
Route::post('/comments/check', [\App\Http\Controllers\Api\ImportCommentsController::class, 'check']);
Route::post('/comments/import', [\App\Http\Controllers\Api\ImportCommentsController::class, 'import']);

// Video Incremental Update endpoints (007-video-incremental-update)
Route::prefix('video-update')->group(function () {
    Route::post('/preview', [\App\Http\Controllers\Api\VideoUpdateController::class, 'preview']);
    Route::post('/import', [\App\Http\Controllers\Api\VideoUpdateController::class, 'import']);
});

// Video Comment Density Analysis endpoint (008-video-comment-density)
Route::get('/videos/{videoId}/comment-density', [\App\Http\Controllers\VideoAnalysisController::class, 'getCommentDensityData']);

// Get single comment data with parent and siblings (for modal display)
Route::get('/comments/{commentId}', function (string $commentId) {
    $comment = \App\Models\Comment::where('comment_id', $commentId)
        ->with('author')
        ->first();

    if (!$comment) {
        return response()->json(['error' => 'Comment not found'], 404);
    }

    $response = [
        'comment_id' => $comment->comment_id,
        'text' => $comment->text,
        'author_name' => $comment->author?->name ?? $comment->author_channel_id,
        'like_count' => $comment->like_count ?? 0,
        'published_at' => $comment->published_at?->setTimezone('Asia/Taipei')->format('Y-m-d H:i') ?? 'N/A',
        'parent_comment_id' => $comment->parent_comment_id,
    ];

    // If this comment has a parent (it's a reply), fetch parent and all siblings
    if ($comment->parent_comment_id) {
        // Fetch parent comment
        $parentComment = \App\Models\Comment::where('comment_id', $comment->parent_comment_id)
            ->with('author')
            ->first();

        if ($parentComment) {
            $response['parent'] = [
                'comment_id' => $parentComment->comment_id,
                'text' => $parentComment->text,
                'author_name' => $parentComment->author?->name ?? $parentComment->author_channel_id,
                'like_count' => $parentComment->like_count ?? 0,
                'published_at' => $parentComment->published_at?->setTimezone('Asia/Taipei')->format('Y-m-d H:i') ?? 'N/A',
            ];

            // Fetch all sibling replies (including the requested comment)
            $siblings = \App\Models\Comment::where('parent_comment_id', $comment->parent_comment_id)
                ->with('author')
                ->orderBy('published_at', 'asc')
                ->get()
                ->map(function ($sibling) {
                    return [
                        'comment_id' => $sibling->comment_id,
                        'text' => $sibling->text,
                        'author_name' => $sibling->author?->name ?? $sibling->author_channel_id,
                        'like_count' => $sibling->like_count ?? 0,
                        'published_at' => $sibling->published_at?->setTimezone('Asia/Taipei')->format('Y-m-d H:i') ?? 'N/A',
                    ];
                });

            $response['siblings'] = $siblings;
        }
    }

    return response()->json($response);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
