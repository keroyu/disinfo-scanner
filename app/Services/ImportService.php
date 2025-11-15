<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Video;
use App\Models\Comment;
use App\Models\Author;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportService
{
    protected $urlParsingService;
    protected $youTubePageService;
    protected $urtubeapiService;
    protected $dataTransformService;
    protected $duplicateDetectionService;
    protected $channelTaggingService;

    public function __construct()
    {
        $this->urlParsingService = new UrlParsingService();
        $this->youTubePageService = new YouTubePageService();
        $this->urtubeapiService = new UrtubeapiService();
        $this->dataTransformService = new DataTransformService();
        $this->duplicateDetectionService = new DuplicateDetectionService();
        $this->channelTaggingService = new ChannelTaggingService();
    }

    /**
     * Main import orchestration method
     */
    public function import($url): object
    {
        $traceId = (string) Str::uuid();
        $stats = [
            'trace_id' => $traceId,
            'import_id' => null,
            'video_id' => null,
            'channel_id' => null,
            'channel_name' => null,
            'requires_tags' => false,
            'newly_added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'total_processed' => 0,
        ];

        try {
            // Step 1: Identify and validate URL
            $urlType = $this->urlParsingService->identify($url);

            // Step 2: Extract video and channel IDs
            if ($urlType === 'youtube') {
                $videoId = $this->urlParsingService->extractVideoIdFromUrl($url);
                $watchUrl = $this->youTubePageService->getWatchUrl($videoId);
                $pageHtml = $this->youTubePageService->fetchPageSource($watchUrl);
                $channelId = $this->youTubePageService->extractChannelIdFromSource($pageHtml);
                $channelName = null; // Will be extracted from urtubeapi
            } else {
                // urtubeapi format
                $extracted = $this->urlParsingService->extractFromUrtubeapiUrl($url);
                $videoId = $extracted['videoId'];
                $channelId = $extracted['channelId'];
                $channelName = null;
            }

            // Step 3: Fetch comment data from urtubeapi
            $apiData = $this->urtubeapiService->fetchCommentData($videoId, $channelId);

            $stats['video_id'] = $videoId;
            $stats['channel_id'] = $channelId;
            $stats['channel_name'] = $apiData['channelTitle'] ?? null;
            $stats['total_processed'] = count($apiData['comments'] ?? []);

            // Step 4: Check if channel is new
            $isNewChannel = $this->channelTaggingService->isNewChannel($channelId);

            if ($isNewChannel) {
                // Step 4a: Create pending import and ask for tags
                $importId = $this->channelTaggingService->createPendingImport(
                    $videoId,
                    $channelId,
                    $apiData['channelTitle'] ?? null
                );

                $stats['import_id'] = $importId;
                $stats['requires_tags'] = true;

                // Log this import start
                Log::info('Import paused for tagging', [
                    'trace_id' => $traceId,
                    'import_id' => $importId,
                    'channel_id' => $channelId,
                    'channel_name' => $stats['channel_name'],
                ]);

                return (object) $stats;
            }

            // Step 5: Transform data
            $models = $this->dataTransformService->transformToModels($apiData);

            // Step 6: Detect duplicates
            $commentIds = array_map(fn($c) => $c['commentId'], $apiData['comments']);
            $dupStats = $this->duplicateDetectionService->detectDuplicateComments($commentIds);
            $stats['skipped'] = $dupStats['duplicate_count'];

            // Step 7: Insert data into database
            DB::transaction(function () use ($models, $apiData, $channelId, &$stats) {
                // Ensure channel exists
                $channel = Channel::firstOrCreate(
                    ['channel_id' => $channelId],
                    [
                        'channel_name' => $apiData['channelTitle'] ?? null,
                        'first_import_at' => now(),
                    ]
                );

                // Insert or update video
                $video = Video::updateOrCreate(
                    ['video_id' => $models->video->video_id],
                    $models->video->toArray()
                );

                // Insert authors
                foreach ($models->authors as $author) {
                    Author::updateOrCreate(
                        ['author_channel_id' => $author->author_channel_id],
                        $author->toArray()
                    );
                }

                // Insert comments (skip duplicates)
                $newComments = 0;
                foreach ($models->comments as $comment) {
                    $created = Comment::firstOrCreate(
                        ['comment_id' => $comment->comment_id],
                        $comment->toArray()
                    )->wasRecentlyCreated;

                    if ($created) {
                        $newComments++;
                    }
                }

                $stats['newly_added'] = $newComments;

                // Update channel timestamps
                $channel->update([
                    'last_import_at' => now(),
                    'comment_count' => Comment::where('video_id', $models->video->video_id)->count(),
                ]);
            });

            // Log successful import
            Log::info('Import completed', [
                'trace_id' => $traceId,
                'channel_id' => $channelId,
                'newly_added' => $stats['newly_added'],
                'skipped' => $stats['skipped'],
            ]);

            return (object) $stats;
        } catch (\Exception $e) {
            $stats['errors'] = 1;
            Log::error('Import failed', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Resume import after tagging
     */
    public function resumeImport($importId): object
    {
        $pendingImport = $this->channelTaggingService->getPendingImport($importId);

        if (!$pendingImport) {
            throw new \Exception('匯入不存在或已過期');
        }

        // Fetch the pending import data again
        $videoId = $pendingImport['video_id'];
        $channelId = $pendingImport['channel_id'];

        $apiData = $this->urtubeapiService->fetchCommentData($videoId, $channelId);
        $models = $this->dataTransformService->transformToModels($apiData);

        $stats = [
            'newly_added' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        // Insert data
        DB::transaction(function () use ($models, $apiData, $channelId, &$stats) {
            $video = Video::updateOrCreate(
                ['video_id' => $models->video->video_id],
                $models->video->toArray()
            );

            foreach ($models->authors as $author) {
                Author::updateOrCreate(
                    ['author_channel_id' => $author->author_channel_id],
                    $author->toArray()
                );
            }

            $newComments = 0;
            foreach ($models->comments as $comment) {
                $created = Comment::firstOrCreate(
                    ['comment_id' => $comment->comment_id],
                    $comment->toArray()
                )->wasRecentlyCreated;

                if ($created) {
                    $newComments++;
                }
            }

            $stats['newly_added'] = $newComments;

            // Update channel
            $channel = Channel::find($channelId);
            if ($channel) {
                $channel->update([
                    'last_import_at' => now(),
                    'comment_count' => Comment::where('video_id', $models->video->video_id)->count(),
                ]);
            }
        });

        // Clear pending import
        $this->channelTaggingService->clearPendingImport($importId);

        return (object) $stats;
    }
}
