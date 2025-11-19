<?php

namespace App\Services;

use App\Models\Video;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VideoIncrementalUpdateService
{
    public function __construct(
        private YouTubeApiService $youtubeApi,
        private CommentImportService $importService
    ) {}

    /**
     * Get preview of new comments for a video
     *
     * @param string $videoId YouTube video ID
     * @return array Preview data including count and first 5 comments
     */
    public function getPreview(string $videoId): array
    {
        // 1. Get video details
        $video = Video::where('video_id', $videoId)->firstOrFail();

        // 2. Get current comment count in database
        $currentCommentCount = Comment::where('video_id', $videoId)->count();

        // 3. Get last comment timestamp for this video
        $lastCommentTime = Comment::where('video_id', $videoId)
            ->max('published_at');

        // 4. Fetch new comments from YouTube API (client-side filtering)
        Log::info('Fetching preview comments', [
            'video_id' => $videoId,
            'last_comment_time' => $lastCommentTime,
        ]);

        $newComments = $this->youtubeApi->fetchCommentsAfter($videoId, $lastCommentTime, 500);

        // 5. Calculate import details
        $newCommentCount = count($newComments);
        $willImportCount = min($newCommentCount, 500);

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
            'last_comment_time' => $lastCommentTime ? \Carbon\Carbon::parse($lastCommentTime)->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s') : null,
            'new_comment_count' => $newCommentCount,
            'will_import_count' => $willImportCount,
            'preview_comments' => $previewComments,
            'has_more' => $newCommentCount > 500,
            'import_limit' => 500,
        ];
    }

    /**
     * Execute incremental import of new comments
     *
     * @param string $videoId YouTube video ID
     * @return array Import result with counts and status
     */
    public function executeImport(string $videoId): array
    {
        // 1. Get video details
        $video = Video::where('video_id', $videoId)->firstOrFail();

        // 2. Get last comment timestamp for this video
        $lastCommentTime = Comment::where('video_id', $videoId)
            ->max('published_at');

        // 3. Fetch new comments from YouTube API (client-side filtering)
        Log::info('Starting incremental import', [
            'video_id' => $videoId,
            'last_comment_time' => $lastCommentTime,
        ]);

        $newComments = $this->youtubeApi->fetchCommentsAfter($videoId, $lastCommentTime, 500);
        $totalAvailable = count($newComments);

        // 4. Import comments with 500-limit (idempotent)
        $importedCount = $this->importService->importIncrementalComments($videoId, $newComments, 500);

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
        $remaining = max(0, $totalAvailable - 500);
        $hasMore = $totalAvailable > 500;

        $message = $hasMore
            ? "成功導入 {$importedCount} 則留言。還有 {$remaining} 則新留言可用,請再次點擊更新按鈕繼續導入。"
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
