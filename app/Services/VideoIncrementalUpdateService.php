<?php

namespace App\Services;

use App\Models\Video;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VideoIncrementalUpdateService
{
    public function __construct(
        private CommentImportService $importService
    ) {}

    /**
     * Get preview of new comments for a video
     *
     * @param string $videoId YouTube video ID
     * @param string|null $userApiKey User's YouTube API key (required for authenticated users)
     * @return array Preview data including count and first 5 comments
     */
    public function getPreview(string $videoId, ?string $userApiKey = null): array
    {
        // Create YouTubeApiService with user's API key
        $youtubeApi = new YouTubeApiService($userApiKey);
        // 1. Get video details
        $video = Video::where('video_id', $videoId)->firstOrFail();

        // 2. Get current comment count in database
        $currentCommentCount = Comment::where('video_id', $videoId)->count();

        // 3. Get latest and earliest comment timestamps (T1 and T2)
        $latestCommentTime = Comment::where('video_id', $videoId)->max('published_at');
        $earliestCommentTime = Comment::where('video_id', $videoId)->min('published_at');

        // 4. Fetch comments outside current range from YouTube API
        // Strategy: Fetch comments published_at > T1 OR published_at < T2
        Log::info('Fetching preview comments', [
            'video_id' => $videoId,
            'latest_comment_time' => $latestCommentTime,
            'earliest_comment_time' => $earliestCommentTime,
        ]);

        $newComments = $youtubeApi->fetchCommentsOutsideRange(
            $videoId,
            $latestCommentTime,
            $earliestCommentTime,
            2500
        );

        // 5. Calculate import details
        $newCommentCount = count($newComments);
        $willImportCount = min($newCommentCount, 2500);

        // 6. Return preview (first 5 + count)
        // Convert preview comments' published_at to Asia/Taipei timezone
        $previewComments = array_slice($newComments, 0, 5);
        foreach ($previewComments as &$comment) {
            if (isset($comment['published_at'])) {
                $comment['published_at'] = \Carbon\Carbon::parse($comment['published_at'])
                    ->setTimezone('Asia/Taipei')
                    ->format('Y-m-d H:i:s');
            }
        }

        return [
            'video_id' => $videoId,
            'video_title' => $video->title,
            'current_comment_count' => $currentCommentCount,
            'latest_comment_time' => $latestCommentTime ? \Carbon\Carbon::parse($latestCommentTime)->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s') : null,
            'earliest_comment_time' => $earliestCommentTime ? \Carbon\Carbon::parse($earliestCommentTime)->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s') : null,
            'new_comment_count' => $newCommentCount,
            'will_import_count' => $willImportCount,
            'preview_comments' => $previewComments,
            'has_more' => $newCommentCount > 2500,
            'import_limit' => 2500,
        ];
    }

    /**
     * Execute incremental import of new comments
     *
     * @param string $videoId YouTube video ID
     * @param string|null $userApiKey User's YouTube API key (required for authenticated users)
     * @return array Import result with counts and status
     */
    public function executeImport(string $videoId, ?string $userApiKey = null): array
    {
        // Create YouTubeApiService with user's API key
        $youtubeApi = new YouTubeApiService($userApiKey);
        // 1. Get video details
        $video = Video::where('video_id', $videoId)->firstOrFail();

        // 2. Get latest and earliest comment timestamps (T1 and T2)
        $latestCommentTime = Comment::where('video_id', $videoId)->max('published_at');
        $earliestCommentTime = Comment::where('video_id', $videoId)->min('published_at');

        // 3. Fetch comments outside current range from YouTube API
        // Strategy: Fetch comments published_at > T1 OR published_at < T2
        Log::info('Starting incremental import', [
            'video_id' => $videoId,
            'latest_comment_time' => $latestCommentTime,
            'earliest_comment_time' => $earliestCommentTime,
        ]);

        $newComments = $youtubeApi->fetchCommentsOutsideRange(
            $videoId,
            $latestCommentTime,
            $earliestCommentTime,
            2500
        );
        $totalAvailable = count($newComments);

        // 4. Import comments with 2500-limit (idempotent)
        $importedCount = $this->importService->importIncrementalComments($videoId, $newComments, 2500);

        // 5. Update video.comment_count by counting actual comments in database
        $actualCommentCount = Comment::where('video_id', $videoId)->count();
        $video->comment_count = $actualCommentCount;

        // 6. Update video.updated_at to now()
        $video->updated_at = now();
        $video->save();

        Log::info('Incremental import completed', [
            'video_id' => $videoId,
            'imported_count' => $importedCount,
            'total_available' => $totalAvailable,
            'updated_comment_count' => $actualCommentCount,
        ]);

        // 7. Calculate remaining and build response message
        $remaining = max(0, $totalAvailable - 2500);
        $hasMore = $totalAvailable > 2500;

        $message = $hasMore
            ? "成功導入 {$importedCount} 則留言。還有約 {$remaining} 則留言可用,請再次點擊更新按鈕繼續導入。"
            : "成功導入 {$importedCount} 則留言";

        return [
            'video_id' => $videoId,
            'imported_count' => $importedCount,
            'total_available' => $totalAvailable,
            'remaining' => $remaining,
            'has_more' => $hasMore,
            'updated_comment_count' => $actualCommentCount,
            'message' => $message,
        ];
    }
}
