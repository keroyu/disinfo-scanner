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

// Get single comment data (for parent comment display in modal)
Route::get('/comments/{commentId}', function (string $commentId) {
    $comment = \App\Models\Comment::where('comment_id', $commentId)
        ->with('author')
        ->first();

    if (!$comment) {
        return response()->json(['error' => 'Comment not found'], 404);
    }

    return response()->json([
        'comment_id' => $comment->comment_id,
        'text' => $comment->text,
        'author_name' => $comment->author?->name ?? $comment->author_channel_id,
        'like_count' => $comment->like_count ?? 0,
        'published_at' => $comment->published_at?->setTimezone('Asia/Taipei')->format('Y-m-d H:i') ?? 'N/A',
    ]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
