<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Video;
use App\Models\Comment;
use App\Models\Author;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommentImportService
{
    private YouTubeApiService $youtubeApiService;

    public function __construct(YouTubeApiService $youtubeApiService)
    {
        $this->youtubeApiService = $youtubeApiService;
    }

    /**
     * Execute new video import workflow
     * 1. Fetch metadata from YouTube API
     * 2. Display metadata for confirmation
     * 3. Fetch preview comments
     * 4. Upon confirmation, fetch all comments and store to database
     *
     * @param string $videoId YouTube video ID
     * @return array {success, status, data}
     */
    public function importNewVideo(string $videoId): array
    {
        $traceId = (string) Str::uuid();

        try {
            // Step 1: Fetch metadata from YouTube API
            $metadata = $this->youtubeApiService->fetchVideoMetadata($videoId);

            Log::info('New video metadata fetched', [
                'trace_id' => $traceId,
                'video_id' => $videoId,
                'channel_id' => $metadata['channel_id'],
                'title' => $metadata['title'],
            ]);

            return [
                'success' => true,
                'status' => 'metadata_ready',
                'trace_id' => $traceId,
                'data' => [
                    'video_id' => $videoId,
                    'title' => $metadata['title'],
                    'channel_name' => $metadata['channel_name'],
                    'channel_id' => $metadata['channel_id'],
                    'published_at' => $metadata['published_at'],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch new video metadata', [
                'trace_id' => $traceId,
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'metadata_fetch_failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch preview comments for a video (no persistence)
     * Returns up to 5 comments
     *
     * @param string $videoId YouTube video ID
     * @return array {success, data: {preview_comments, total_count}}
     */
    public function fetchPreview(string $videoId): array
    {
        $traceId = (string) Str::uuid();

        try {
            $previewComments = $this->youtubeApiService->fetchPreviewComments($videoId);

            Log::info('Preview comments fetched', [
                'trace_id' => $traceId,
                'video_id' => $videoId,
                'count' => count($previewComments),
            ]);

            return [
                'success' => true,
                'status' => 'preview_ready',
                'data' => [
                    'preview_comments' => $previewComments,
                    'total_preview_count' => count($previewComments),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch preview comments', [
                'trace_id' => $traceId,
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'preview_fetch_failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute full import for new or existing video
     * Wraps entire workflow in database transaction
     * NO data persists until ALL comments successfully fetched
     *
     * @param string $videoId YouTube video ID
     * @param array $videoMetadata {title, channel_name, channel_id, published_at}
     * @return array {success, status, data: {comments_imported, replies_imported, ...}}
     */
    public function executeFullImport(string $videoId, ?array $videoMetadata = null): array
    {
        $traceId = (string) Str::uuid();
        $startTime = microtime(true);

        try {
            // Check if video exists
            $existingVideo = Video::where('video_id', $videoId)->first();
            $isNewVideo = !$existingVideo;

            // For new videos, fetch metadata if not provided
            if ($isNewVideo && !$videoMetadata) {
                $videoMetadata = $this->youtubeApiService->fetchVideoMetadata($videoId);
            }

            // Determine import mode and get existing comment timestamps
            $maxPublishedAt = null;
            $existingCommentIds = [];

            if (!$isNewVideo) {
                // Get max timestamp from existing comments for incremental import
                $maxComment = Comment::where('video_id', $videoId)
                    ->orderBy('published_at', 'desc')
                    ->first();

                if ($maxComment) {
                    $maxPublishedAt = $maxComment->published_at->toDateTimeString();
                }

                // Get existing comment IDs for duplicate detection
                $existingCommentIds = Comment::where('video_id', $videoId)
                    ->pluck('comment_id')
                    ->toArray();
            }

            // Fetch ALL comments and replies from YouTube API
            // This happens BEFORE database transaction to maximize failure recovery
            Log::info('Starting comment fetch', [
                'trace_id' => $traceId,
                'video_id' => $videoId,
                'import_mode' => $isNewVideo ? 'new' : 'incremental',
            ]);

            $allComments = $this->youtubeApiService->fetchAllComments(
                $videoId,
                $maxPublishedAt,
                $existingCommentIds,
                function ($count) use ($traceId) {
                    Log::debug('Comment fetch progress', [
                        'trace_id' => $traceId,
                        'fetched_count' => $count,
                    ]);
                }
            );

            // No comments to import
            if (empty($allComments)) {
                Log::info('No new comments found', [
                    'trace_id' => $traceId,
                    'video_id' => $videoId,
                ]);

                return [
                    'success' => true,
                    'status' => 'import_complete_no_changes',
                    'data' => [
                        'video_id' => $videoId,
                        'comments_imported' => 0,
                        'replies_imported' => 0,
                        'total_imported' => 0,
                        'import_mode' => $isNewVideo ? 'new' : 'incremental',
                        'import_duration_seconds' => round(microtime(true) - $startTime, 2),
                    ],
                ];
            }

            // ATOMIC DATABASE TRANSACTION
            // Either all data persists or none
            $result = DB::transaction(function () use (
                $videoId,
                $videoMetadata,
                $allComments,
                $isNewVideo,
                $traceId
            ) {
                // Step 1: Handle channel
                $channelId = $videoMetadata['channel_id'] ?? null;
                $channel = null;

                if ($channelId) {
                    $channel = Channel::firstOrCreate(
                        ['channel_id' => $channelId],
                        [
                            'channel_name' => $videoMetadata['channel_name'] ?? $channelId,
                            'video_count' => 0,
                            'comment_count' => 0,
                            'first_import_at' => now(),
                            'last_import_at' => now(),
                        ]
                    );
                }

                // Step 2: Handle video
                $video = $isNewVideo
                    ? Video::create([
                        'video_id' => $videoId,
                        'channel_id' => $channelId,
                        'title' => $videoMetadata['title'] ?? 'Imported from YouTube API',
                        'published_at' => $videoMetadata['published_at'] ?? null,
                    ])
                    : $this->updateVideoTimestamp($videoId);

                // Step 3: Insert comments and replies
                $topLevelCount = 0;
                $replyCount = 0;

                foreach ($allComments as $commentData) {
                    // Skip duplicates (should not happen due to API query, but be safe)
                    if (Comment::where('comment_id', $commentData['comment_id'])->exists()) {
                        continue;
                    }

                    // Get or create author
                    $authorChannelId = $commentData['author_channel_id'];
                    if ($authorChannelId) {
                        Author::firstOrCreate(
                            ['author_channel_id' => $authorChannelId],
                            ['name' => $authorChannelId]
                        );
                    }

                    // Create comment
                    Comment::create([
                        'comment_id' => $commentData['comment_id'],
                        'video_id' => $videoId,
                        'author_channel_id' => $authorChannelId,
                        'text' => $commentData['text'],
                        'like_count' => $commentData['like_count'],
                        'published_at' => $commentData['published_at'],
                        'parent_comment_id' => $commentData['parent_comment_id'],
                    ]);

                    if ($commentData['parent_comment_id']) {
                        $replyCount++;
                    } else {
                        $topLevelCount++;
                    }
                }

                // Step 4: Update channel with new counts
                if ($channel) {
                    // Recalculate comment count for this channel
                    $commentCount = Comment::whereHas('video', function ($q) use ($channel) {
                        $q->where('channel_id', $channel->channel_id);
                    })->count();

                    // For new videos, increment video count
                    $newVideoCount = $channel->video_count;
                    if ($isNewVideo) {
                        $newVideoCount++;
                    }

                    $channel->update([
                        'video_count' => $newVideoCount,
                        'comment_count' => $commentCount,
                        'last_import_at' => now(),
                    ]);
                }

                Log::info('Comments imported successfully', [
                    'trace_id' => $traceId,
                    'video_id' => $videoId,
                    'top_level_count' => $topLevelCount,
                    'reply_count' => $replyCount,
                ]);

                return [
                    'top_level_count' => $topLevelCount,
                    'reply_count' => $replyCount,
                ];
            });

            $duration = round(microtime(true) - $startTime, 2);

            return [
                'success' => true,
                'status' => 'import_complete',
                'data' => [
                    'video_id' => $videoId,
                    'comments_imported' => $result['top_level_count'],
                    'replies_imported' => $result['reply_count'],
                    'total_imported' => $result['top_level_count'] + $result['reply_count'],
                    'import_mode' => $isNewVideo ? 'new' : 'incremental',
                    'import_duration_seconds' => $duration,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Full import failed - transaction rolled back', [
                'trace_id' => $traceId,
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'import_failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update video's updated_at timestamp
     */
    private function updateVideoTimestamp(string $videoId): Video
    {
        $video = Video::where('video_id', $videoId)->first();
        if ($video) {
            $video->update(['updated_at' => now()]);
        }
        return $video;
    }
}
