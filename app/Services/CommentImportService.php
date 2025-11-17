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
    private YoutubeApiClient $youtubeClient;
    private ChannelTagManager $tagManager;

    public function __construct(
        YouTubeApiService $youtubeApiService,
        YoutubeApiClient $youtubeClient,
        ChannelTagManager $tagManager
    ) {
        $this->youtubeApiService = $youtubeApiService;
        $this->youtubeClient = $youtubeClient;
        $this->tagManager = $tagManager;
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

    // ========== NEW METHODS FOR 005-API-IMPORT-COMMENTS ========== //

    /**
     * Check if video exists in database
     */
    public function checkVideoExists(string $videoId): bool
    {
        return Video::where('video_id', $videoId)->exists();
    }

    /**
     * Check if channel exists in database
     */
    public function checkChannelExists(string $channelId): bool
    {
        return Channel::where('channel_id', $channelId)->exists();
    }

    /**
     * Import or update channel (for 005 feature)
     */
    public function importChannel(string $channelId, string $channelName): Channel
    {
        $channel = Channel::firstOrCreate(
            ['channel_id' => $channelId],
            ['channel_name' => $channelName]
        );

        Log::info('Channel imported/updated (005)', [
            'channel_id' => $channelId,
            'channel_name' => $channelName,
            'was_new' => $channel->wasRecentlyCreated,
        ]);

        return $channel;
    }

    /**
     * Import video (for 005 feature)
     */
    public function importVideo(array $videoData): Video
    {
        $video = Video::create([
            'video_id' => $videoData['video_id'],
            'channel_id' => $videoData['channel_id'],
            'title' => $videoData['title'],
            'published_at' => $videoData['published_at'],
            'comment_count' => null, // Will be calculated after comment import
        ]);

        Log::info('Video imported (005)', [
            'video_id' => $videoData['video_id'],
            'title' => $videoData['title'],
        ]);

        return $video;
    }

    /**
     * Import all comments and replies for a video (for 005 feature)
     */
    public function importComments(string $videoId, array $comments): int
    {
        $imported = 0;

        foreach ($comments as $commentData) {
            try {
                // Create or update author first
                if (isset($commentData['author_channel_id'])) {
                    Author::firstOrCreate(
                        ['author_channel_id' => $commentData['author_channel_id']],
                        ['name' => $commentData['author_channel_id']]
                    );
                }

                Comment::create([
                    'comment_id' => $commentData['comment_id'],
                    'video_id' => $videoId,
                    'author_channel_id' => $commentData['author_channel_id'],
                    'text' => $commentData['content'],
                    'like_count' => $commentData['like_count'],
                    'published_at' => $commentData['published_at'],
                    'parent_comment_id' => $commentData['parent_comment_id'],
                ]);
                $imported++;
            } catch (\Exception $e) {
                Log::warning('Failed to import comment (005)', [
                    'comment_id' => $commentData['comment_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Comments imported (005)', [
            'video_id' => $videoId,
            'total_imported' => $imported,
        ]);

        return $imported;
    }

    /**
     * Calculate and update comment count for a video (for 005 feature)
     */
    public function calculateCommentCount(string $videoId): int
    {
        $count = Comment::where('video_id', $videoId)->count();

        Video::where('video_id', $videoId)->update(['comment_count' => $count]);

        Log::info('Comment count calculated (005)', [
            'video_id' => $videoId,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Perform full import with transaction (for 005 feature)
     *
     * @param string $videoUrl YouTube video URL
     * @param string $scenario 'new_video_existing_channel' or 'new_video_new_channel'
     * @param array $channelTags Tag IDs to assign to channel
     * @param bool $importReplies Whether to import replies
     * @return array Import result with statistics
     */
    public function performFullImport(
        string $videoUrl,
        string $scenario,
        array $channelTags = [],
        bool $importReplies = true
    ): array {
        $videoId = $this->youtubeClient->extractVideoId($videoUrl);

        if (!$videoId) {
            throw new \InvalidArgumentException('Invalid YouTube URL');
        }

        return DB::transaction(function () use ($videoId, $scenario, $channelTags, $importReplies) {
            // Stage 1: Get video metadata from YouTube API
            $videoMetadata = $this->youtubeClient->getVideoMetadata($videoId);

            if (!$videoMetadata) {
                throw new \RuntimeException('Video not found on YouTube');
            }

            // Stage 2: Import/update channel
            $channel = $this->importChannel(
                $videoMetadata['channel_id'],
                $videoMetadata['channel_title']
            );

            // Stage 3: Sync channel tags
            if (!empty($channelTags)) {
                $this->tagManager->syncChannelTags($channel, $channelTags);
            }

            // Stage 4: Import video
            $video = $this->importVideo([
                'video_id' => $videoMetadata['video_id'],
                'channel_id' => $videoMetadata['channel_id'],
                'title' => $videoMetadata['title'],
                'published_at' => $videoMetadata['published_at'],
            ]);

            // Stage 5: Import all comments
            $allComments = $this->youtubeClient->getAllComments($videoId);
            $importedCount = $this->importComments($videoId, $allComments);

            // Stage 6: Calculate and update comment count
            $totalCount = $this->calculateCommentCount($videoId);

            // Stage 7: Update channel's last_import_at
            $channel->update(['last_import_at' => now()->format('Y-m-d H:i:s')]);

            Log::info('Full import completed successfully (005)', [
                'video_id' => $videoId,
                'channel_id' => $channel->channel_id,
                'imported_comments' => $importedCount,
                'total_comments' => $totalCount,
                'scenario' => $scenario,
            ]);

            // Calculate reply count
            $replyCount = Comment::where('video_id', $videoId)
                ->whereNotNull('parent_comment_id')
                ->count();

            return [
                'status' => 'success',
                'message' => "成功導入 {$totalCount} 則留言",
                'imported_comment_count' => $totalCount - $replyCount,
                'imported_reply_count' => $replyCount,
                'total_imported' => $totalCount,
                'channel_id' => $channel->channel_id,
                'channel_name' => $channel->channel_name,
                'video_id' => $videoId,
                'video_title' => $video->title,
                'video_published_at' => $video->published_at->format('Y-m-d H:i:s'),
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ];
        });
    }
}
