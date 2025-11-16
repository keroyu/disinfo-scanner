<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\YouTubeApiService;
use App\Models\Video;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Author;
use App\Exceptions\YouTubeApiException;
use App\Exceptions\InvalidVideoIdException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class YouTubeApiImportController extends Controller
{
    private YouTubeApiService $youtubeApiService;

    public function __construct(YouTubeApiService $youtubeApiService)
    {
        $this->youtubeApiService = $youtubeApiService;
    }

    /**
     * Preview endpoint: Fetch preview comments for a video
     * Returns 5 comments without persisting to DB
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'video_url' => 'required|string',
        ]);

        $traceId = Str::uuid();
        $videoUrl = $request->input('video_url');

        try {
            // Extract video ID from URL
            $videoId = $this->extractVideoId($videoUrl);

            // Check if video exists in database
            $video = Video::where('video_id', $videoId)->first();

            if (!$video) {
                // New video detected - route to import dialog
                Log::info('New video detected', [
                    'trace_id' => $traceId,
                    'video_id' => $videoId,
                ]);

                return response()->json([
                    'success' => true,
                    'status' => 'new_video_detected',
                    'data' => [
                        'video_id' => $videoId,
                        'import_mode' => 'full',
                        'action_required' => 'invoke_import_dialog',
                    ],
                ]);
            }

            // Existing video - fetch preview comments
            $previewComments = $this->youtubeApiService->fetchPreviewComments($videoId);

            $this->youtubeApiService->logOperation(
                $traceId,
                'preview_fetch',
                count($previewComments),
                'success'
            );

            return response()->json([
                'success' => true,
                'status' => 'preview_ready',
                'data' => [
                    'video_id' => $videoId,
                    'import_mode' => 'incremental',
                    'preview_comments' => $previewComments,
                    'total_preview_count' => count($previewComments),
                ],
            ]);
        } catch (InvalidVideoIdException $e) {
            Log::warning('Invalid video URL', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Invalid YouTube URL format',
            ], 422);
        } catch (YouTubeApiException $e) {
            Log::error('YouTube API error in preview', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch preview: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error in preview', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Confirm endpoint: Perform full import after user confirms
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'video_url' => 'required|string',
        ]);

        $traceId = Str::uuid();
        $videoUrl = $request->input('video_url');
        $startTime = microtime(true);

        try {
            // Extract video ID
            $videoId = $this->extractVideoId($videoUrl);

            // Check if video exists
            $video = Video::where('video_id', $videoId)->first();
            $isNewVideo = !$video;

            // Determine import mode
            $importMode = $isNewVideo ? 'full' : 'incremental';
            $afterDate = null;

            if (!$isNewVideo) {
                // Get the max published_at from existing comments for this video
                $maxComment = Comment::where('video_id', $videoId)
                    ->orderBy('published_at', 'desc')
                    ->first();

                if ($maxComment) {
                    $afterDate = $maxComment->published_at->toDateTimeString();
                }
            }

            // Fetch all comments
            $allComments = $this->youtubeApiService->fetchAllComments(
                $videoId,
                $afterDate,
                function ($count) use ($traceId) {
                    Log::debug('Import progress', [
                        'trace_id' => $traceId,
                        'comment_count' => $count,
                    ]);
                }
            );

            // If incremental and found nothing new, return early
            if (!$isNewVideo && empty($allComments)) {
                Log::info('No new comments found in incremental import', [
                    'trace_id' => $traceId,
                    'video_id' => $videoId,
                ]);

                return response()->json([
                    'success' => true,
                    'status' => 'import_complete',
                    'data' => [
                        'video_id' => $videoId,
                        'comments_imported' => 0,
                        'replies_imported' => 0,
                        'total_imported' => 0,
                        'import_mode' => $importMode,
                        'import_duration_seconds' => round(microtime(true) - $startTime, 2),
                    ],
                ]);
            }

            // Get or create video and channel
            $channel = $video?->channel;
            $newCommentCount = 0;
            $replyCount = 0;
            $duplicateCount = 0;

            // Process comments
            foreach ($allComments as $commentData) {
                // Check for duplicates
                $exists = Comment::where('comment_id', $commentData['comment_id'])->exists();

                if ($exists) {
                    $duplicateCount++;
                    // In incremental mode, stop at first duplicate
                    if ($importMode === 'incremental') {
                        break;
                    }
                    continue;
                }

                // Get or create author
                $authorChannelId = $commentData['author_channel_id'];
                if ($authorChannelId) {
                    $author = Author::firstOrCreate(
                        ['author_channel_id' => $authorChannelId],
                        ['name' => $authorChannelId] // Placeholder name
                    );
                }

                // Create or update video first if new
                if ($isNewVideo && !$video) {
                    $video = Video::create([
                        'video_id' => $videoId,
                        'title' => 'Imported from YouTube API',
                        'published_at' => $commentData['published_at'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $isNewVideo = false; // Mark as no longer new after first insert
                }

                // Create comment
                $comment = Comment::create([
                    'comment_id' => $commentData['comment_id'],
                    'video_id' => $videoId,
                    'author_channel_id' => $authorChannelId,
                    'text' => $commentData['text'],
                    'like_count' => $commentData['like_count'],
                    'published_at' => $commentData['published_at'],
                    'parent_comment_id' => $commentData['parent_comment_id'],
                ]);

                $newCommentCount++;

                if ($commentData['parent_comment_id']) {
                    $replyCount++;
                }
            }

            // Update video
            if ($video) {
                $video->update(['updated_at' => now()]);
            }

            // Get channel from video if available
            if ($video && !$channel) {
                $channel = $video->channel;
            }

            // Update or create channel metadata
            if (!$channel && $video) {
                // No channel yet, we would need to get it from the video
                // For now, skip channel creation
            } elseif ($channel) {
                // Recalculate comment count for this channel
                $commentCount = Comment::whereHas('video', function ($query) use ($channel) {
                    $query->where('channel_id', $channel->channel_id);
                })->count();

                $channel->update([
                    'comment_count' => $commentCount,
                    'last_import_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->youtubeApiService->logOperation(
                $traceId,
                'full_import',
                $newCommentCount + $replyCount,
                'success'
            );

            $duration = round(microtime(true) - $startTime, 2);

            return response()->json([
                'success' => true,
                'status' => 'import_complete',
                'data' => [
                    'video_id' => $videoId,
                    'comments_imported' => $newCommentCount - $replyCount,
                    'replies_imported' => $replyCount,
                    'total_imported' => $newCommentCount,
                    'duplicate_skipped' => $duplicateCount,
                    'import_mode' => $importMode,
                    'import_duration_seconds' => $duration,
                ],
            ]);
        } catch (InvalidVideoIdException $e) {
            Log::warning('Invalid video URL in confirm', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Invalid YouTube URL format',
            ], 422);
        } catch (YouTubeApiException $e) {
            Log::error('YouTube API error in confirm', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
            ]);

            $this->youtubeApiService->logOperation(
                $traceId,
                'full_import',
                0,
                'error',
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'error' => 'Failed to import comments: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error in confirm', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Extract video ID from YouTube URL
     */
    private function extractVideoId(string $url): string
    {
        // Match various YouTube URL formats
        $patterns = [
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/^([a-zA-Z0-9_-]{11})$/', // Direct video ID
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        throw new InvalidVideoIdException("Could not extract video ID from URL: {$url}");
    }
}
