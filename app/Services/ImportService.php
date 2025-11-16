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
    protected $youTubeMetadataService;
    protected $urtubeapiService;
    protected $dataTransformService;
    protected $duplicateDetectionService;
    protected $channelTaggingService;

    public function __construct()
    {
        $this->urlParsingService = new UrlParsingService();
        $this->youTubePageService = new YouTubePageService();
        $this->youTubeMetadataService = new YouTubeMetadataService();
        $this->urtubeapiService = new UrtubeapiService();
        $this->dataTransformService = new DataTransformService();
        $this->duplicateDetectionService = new DuplicateDetectionService();
        $this->channelTaggingService = new ChannelTaggingService();
    }

    /**
     * Prepare import: Parse URL, scrape metadata, fetch API data, cache for confirmation
     * NO database writes - read-only operation
     *
     * @param string $url YouTube or urtubeapi URL
     * @return object {import_id, video_id, channel_id, video_title, channel_name, comment_count, requires_tags}
     */
    public function prepareImport(string $url): object
    {
        $traceId = (string) Str::uuid();
        $metadata = [
            'trace_id' => $traceId,
            'import_id' => null,
            'video_id' => null,
            'channel_id' => null,
            'video_title' => null,
            'channel_name' => null,
            'comment_count' => 0,
            'requires_tags' => false,
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
            } else {
                // urtubeapi format
                $extracted = $this->urlParsingService->extractFromUrtubeapiUrl($url);
                $videoId = $extracted['videoId'];
                $channelId = $extracted['channelId'];
            }

            $metadata['video_id'] = $videoId;
            $metadata['channel_id'] = $channelId;

            // Step 3: Fetch comment data from urtubeapi (determines comment count)
            $apiData = $this->urtubeapiService->fetchCommentData($videoId, $channelId);
            $metadata['channel_name'] = $apiData['channelTitle'] ?? null;
            $metadata['comment_count'] = count($apiData['comments'] ?? []);

            // Step 4: Scrape video metadata (title, channel name, published date) from YouTube
            // This is optional and gracefully degrades if it fails
            // Works for both YouTube URLs and urtubeapi (since API provides videoId)
            $scrapedMetadata = $this->youTubeMetadataService->scrapeMetadata($videoId);
            $metadata['video_title'] = $scrapedMetadata['videoTitle'];
            $metadata['published_at'] = $scrapedMetadata['publishedAt'];

            // If we couldn't get channel name from API, try scraped version
            if (!$metadata['channel_name'] && $scrapedMetadata['channelName']) {
                $metadata['channel_name'] = $scrapedMetadata['channelName'];
            }

            Log::info('Metadata scraping completed', [
                'trace_id' => $traceId,
                'video_id' => $videoId,
                'scraping_status' => $scrapedMetadata['scrapingStatus'],
                'has_title' => !is_null($scrapedMetadata['videoTitle']),
                'has_channel' => !is_null($scrapedMetadata['channelName']),
                'has_published_at' => !is_null($scrapedMetadata['publishedAt']),
                'url_type' => $urlType,
            ]);

            // Step 5: Check if channel is new
            $isNewChannel = $this->channelTaggingService->isNewChannel($channelId);
            $metadata['requires_tags'] = $isNewChannel;

            // Step 6: Create pending import for later confirmation
            $importId = $this->channelTaggingService->createPendingImport(
                $videoId,
                $channelId,
                $metadata['channel_name'],
                $metadata['video_title'],
                $metadata['comment_count'],
                $metadata['published_at']
            );

            $metadata['import_id'] = $importId;

            Log::info('Import preparation completed', [
                'trace_id' => $traceId,
                'import_id' => $importId,
                'channel_id' => $channelId,
                'requires_tags' => $isNewChannel,
            ]);

            return (object) $metadata;
        } catch (\Exception $e) {
            Log::error('Import preparation failed', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Confirm import: Write cached import data to database atomically
     * Wraps all writes in a database transaction
     *
     * @param string $importId UUID from prepareImport
     * @param array|null $tags Optional tag codes for new channels
     * @return object {newly_added, updated, skipped, total_processed}
     */
    public function confirmImport(string $importId, ?array $tags = null): object
    {
        $traceId = (string) Str::uuid();
        $stats = [
            'newly_added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'total_processed' => 0,
        ];

        try {
            // Step 1: Retrieve pending import from cache
            $pendingImport = $this->channelTaggingService->getPendingImport($importId);

            if (!$pendingImport) {
                throw new \Exception('匯入不存在或已過期');
            }

            $videoId = $pendingImport['video_id'];
            $channelId = $pendingImport['channel_id'];

            // Step 2: Validate tags for new channels
            $isNewChannel = $this->channelTaggingService->isNewChannel($channelId);
            if ($isNewChannel && (empty($tags) || !is_array($tags))) {
                throw new \Exception('請至少選擇一個標籤');
            }

            // Step 3: Fetch fresh API data
            $apiData = $this->urtubeapiService->fetchCommentData($videoId, $channelId);
            // IMPORTANT: Pass channelId explicitly - API does NOT return it (it's a request parameter only)
            $models = $this->dataTransformService->transformToModels($apiData, $channelId);

            // Step 4: Detect duplicates
            // commentIds are the keys in the comments object (not a field within each comment)
            $commentIds = array_keys($apiData['comments'] ?? []);
            $dupStats = $this->duplicateDetectionService->detectDuplicateComments($commentIds);
            $stats['skipped'] = $dupStats['duplicate_count'];
            $stats['total_processed'] = count($apiData['comments']);

            // Step 5: Execute database write in transaction
            // IMPORTANT: Add $importId to use clause - it's needed for selectTagsForChannel()
            // Use cached metadata from pendingImport instead of API (API doesn't return title/name)
            DB::transaction(function () use ($models, $apiData, $channelId, $isNewChannel, $tags, $importId, $pendingImport, &$stats) {
                // Ensure channel exists
                // Use cached channel_name from pendingImport (from web scraping), not API
                $channel = Channel::firstOrCreate(
                    ['channel_id' => $channelId],
                    [
                        'channel_name' => $pendingImport['channel_name'] ?? null,
                        'first_import_at' => now(),
                    ]
                );

                // Insert or update video
                // Use cached video_title and published_at from pendingImport (from web scraping), not API
                $videoData = $models->video->toArray();
                if ($pendingImport['video_title']) {
                    $videoData['title'] = $pendingImport['video_title'];
                }
                if ($pendingImport['published_at']) {
                    $videoData['published_at'] = $pendingImport['published_at'];
                }
                $videoCreated = false;
                $video = Video::updateOrCreate(
                    ['video_id' => $models->video->video_id],
                    $videoData
                );
                // Check if this is a new video (wasn't in database before)
                if ($video->wasRecentlyCreated) {
                    $videoCreated = true;
                }

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

                // Attach tags to channel if new channel
                if ($isNewChannel && !empty($tags)) {
                    $this->channelTaggingService->selectTagsForChannel($importId, $channelId, $tags);
                }

                // Update channel timestamps and increment video_count if new video
                $updateData = [
                    'last_import_at' => now(),
                    'comment_count' => Comment::where('video_id', $models->video->video_id)->count(),
                ];
                if ($videoCreated) {
                    $updateData['video_count'] = $channel->video_count + 1;
                }
                $channel->update($updateData);
            });

            // Step 6: Clear pending import from cache
            $this->channelTaggingService->clearPendingImport($importId);

            Log::info('Import confirmed and completed', [
                'trace_id' => $traceId,
                'import_id' => $importId,
                'channel_id' => $channelId,
                'newly_added' => $stats['newly_added'],
                'skipped' => $stats['skipped'],
            ]);

            return (object) $stats;
        } catch (\Exception $e) {
            Log::error('Import confirmation failed', [
                'trace_id' => $traceId,
                'import_id' => $importId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Main import orchestration method
     * For backward compatibility: delegates to prepareImport() + confirmImport()
     * For new channels, requires tags before confirmation
     */
    public function import($url): object
    {
        // Step 1: Prepare import (read-only)
        $prepared = $this->prepareImport($url);

        // Step 2: If new channel, return early - caller must provide tags
        if ($prepared->requires_tags) {
            return $prepared;
        }

        // Step 3: If existing channel, auto-confirm without tags
        return $this->confirmImport($prepared->import_id, null);
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
        // IMPORTANT: Pass channelId explicitly - API does NOT return it
        $models = $this->dataTransformService->transformToModels($apiData, $channelId);

        $stats = [
            'newly_added' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        // Insert data
        DB::transaction(function () use ($models, $apiData, $channelId, $pendingImport, &$stats) {
            // Use cached video_title and published_at from pendingImport (from web scraping), not API
            $videoData = $models->video->toArray();
            if ($pendingImport['video_title']) {
                $videoData['title'] = $pendingImport['video_title'];
            }
            if ($pendingImport['published_at']) {
                $videoData['published_at'] = $pendingImport['published_at'];
            }
            $videoCreated = false;
            $video = Video::updateOrCreate(
                ['video_id' => $models->video->video_id],
                $videoData
            );
            // Check if this is a new video (wasn't in database before)
            if ($video->wasRecentlyCreated) {
                $videoCreated = true;
            }

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

            // Update channel with cached metadata and increment video_count if new video
            $channel = Channel::find($channelId);
            if ($channel) {
                $updateData = [
                    'channel_name' => $pendingImport['channel_name'] ?? $channel->channel_name,
                    'last_import_at' => now(),
                    'comment_count' => Comment::where('video_id', $models->video->video_id)->count(),
                ];
                if ($videoCreated) {
                    $updateData['video_count'] = $channel->video_count + 1;
                }
                $channel->update($updateData);
            }
        });

        // Clear pending import
        $this->channelTaggingService->clearPendingImport($importId);

        return (object) $stats;
    }
}
