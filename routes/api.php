<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

// Authentication Routes (011-member-system)
Route::prefix('auth')->group(function () {
    // Public routes (with 'web' middleware for session support)
    Route::middleware('web')->group(function () {
        Route::post('/register', [RegisterController::class, 'register']);
        Route::get('/verify-email', [EmailVerificationController::class, 'verify']); // API endpoint (no name to avoid conflict with web route)
        Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend']);
        Route::post('/login', [LoginController::class, 'login']);
    });

    // Password reset routes (T055: User Story 2)
    Route::post('/password/reset/request', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/password/reset', [PasswordResetController::class, 'reset']);

    // Authenticated routes (supports both web sessions and Sanctum tokens)
    // Note: Added 'web' middleware to enable session support for these endpoints
    Route::middleware(['web', 'auth'])->group(function () {
        Route::post('/logout', [LoginController::class, 'logout']);
        Route::get('/me', [LoginController::class, 'me']);

        // Password change route (T055: User Story 2)
        Route::post('/password/change', [PasswordChangeController::class, 'change']);
    });
});

// U-API (urtubeapi Third-Party) Import endpoints - Grouped under /api/uapi/*
Route::prefix('uapi')->group(function () {
    Route::post('/import', [\App\Http\Controllers\Api\UApi\ImportController::class, 'store']);
    Route::post('/confirm', [\App\Http\Controllers\Api\UApi\ConfirmationController::class, 'confirm']);
    Route::post('/cancel', [\App\Http\Controllers\Api\UApi\ConfirmationController::class, 'cancel']);
    Route::get('/tags', [\App\Http\Controllers\Api\UApi\TagSelectionController::class, 'index']);
    Route::post('/tags/select', [\App\Http\Controllers\Api\UApi\TagSelectionController::class, 'store']);
});

// Official YouTube API comment import endpoints (005-api-import-comments)
Route::post('/comments/check', [\App\Http\Controllers\Api\ImportCommentsController::class, 'check']);
Route::post('/comments/import', [\App\Http\Controllers\Api\ImportCommentsController::class, 'import']);

// Video Incremental Update endpoints (007-video-incremental-update)
// Requires authentication and user's YouTube API key
Route::prefix('video-update')->middleware(['web', 'auth'])->group(function () {
    Route::post('/preview', [\App\Http\Controllers\Api\VideoUpdateController::class, 'preview']);
    Route::post('/import', [\App\Http\Controllers\Api\VideoUpdateController::class, 'import']);
});

// Video Comment Density Analysis endpoint (008-video-comment-density)
// Constraint: YouTube video IDs are 11 characters (base64url encoding)
Route::get('/videos/{videoId}/comment-density', [\App\Http\Controllers\VideoAnalysisController::class, 'getCommentDensityData'])
    ->where('videoId', '[A-Za-z0-9_-]{11}');

// Comment Pattern Analysis endpoints (009-comments-pattern-summary)
// Constraint: YouTube video IDs are 11 characters
Route::get('/videos/{videoId}/pattern-statistics', [\App\Http\Controllers\CommentPatternController::class, 'getPatternStatistics'])
    ->where('videoId', '[A-Za-z0-9_-]{11}');
Route::get('/videos/{videoId}/comments', [\App\Http\Controllers\CommentPatternController::class, 'getCommentsByPattern'])
    ->where('videoId', '[A-Za-z0-9_-]{11}');

// Get single comment data with parent and siblings (for modal display)
// Constraint: YouTube comment IDs can be up to 100 characters (base64url + optional suffixes)
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
})->where('commentId', '[A-Za-z0-9_.-]{1,100}');

Route::middleware('auth')->get('/user', function (Request $request) {
    return $request->user();
});

// Admin Management Routes (011-member-system - Admin Module)
// T222: Add admin API routes
// T284: Admin routes with rate limiting (60 requests per minute)
// T286: Admin session timeout middleware
Route::prefix('admin')->middleware(['web', 'auth', 'check.admin', 'check.admin.session', 'throttle:60,1'])->group(function () {
    // User Management (Phase 2)
    Route::get('/users', [\App\Http\Controllers\Admin\UserManagementController::class, 'index']);
    Route::get('/users/{userId}', [\App\Http\Controllers\Admin\UserManagementController::class, 'show']);
    Route::put('/users/{userId}/role', [\App\Http\Controllers\Admin\UserManagementController::class, 'updateRole']);
    Route::post('/users/{userId}/verify-email', [\App\Http\Controllers\Admin\UserManagementController::class, 'verifyEmail']);

    // Identity Verification Management (Phase 4)
    Route::get('/verifications', [\App\Http\Controllers\Admin\UserManagementController::class, 'listVerificationRequests']);
    Route::get('/verifications/{verificationId}', [\App\Http\Controllers\Admin\UserManagementController::class, 'showVerificationRequest']);
    Route::post('/verifications/{verificationId}/review', [\App\Http\Controllers\Admin\UserManagementController::class, 'reviewVerificationRequest']);

    // Analytics & Reporting (Phase 5)
    Route::get('/analytics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'getAnalytics']);
    Route::get('/reports/users/export', [\App\Http\Controllers\Admin\AnalyticsController::class, 'exportUserList']);
    Route::get('/reports/activity', [\App\Http\Controllers\Admin\AnalyticsController::class, 'generateActivityReport']);
    Route::get('/reports/api-usage', [\App\Http\Controllers\Admin\AnalyticsController::class, 'generateApiUsageReport']);

    // Audit Logs (Phase 6 - T282, T288, T289)
    Route::get('/audit-logs', [\App\Http\Controllers\Admin\UserManagementController::class, 'auditLogs']);
    Route::get('/audit-logs/export', [\App\Http\Controllers\Admin\UserManagementController::class, 'exportAuditLogs']);
});

