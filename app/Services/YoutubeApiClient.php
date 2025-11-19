<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\YouTube as YouTubeService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class YoutubeApiClient
{
    private YouTubeService $youtube;
    private const MAX_RESULTS_PER_PAGE = 100;

    public function __construct()
    {
        $client = new GoogleClient();
        $client->setDeveloperKey(config('services.youtube.api_key'));
        $this->youtube = new YouTubeService($client);
    }

    /**
     * Extract video ID from YouTube URL
     * Supports: youtu.be/{id} and youtube.com/watch?v={id}
     */
    public function extractVideoId(string $url): ?string
    {
        // Pattern 1: youtu.be/VIDEO_ID
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern 2: youtube.com/watch?v=VIDEO_ID
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern 3: Just the video ID itself (11 characters)
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
            return $url;
        }

        return null;
    }

    /**
     * Get video metadata (title, published_at, channel_id)
     */
    public function getVideoMetadata(string $videoId): ?array
    {
        try {
            $response = $this->youtube->videos->listVideos('snippet,statistics', [
                'id' => $videoId,
            ]);

            if (empty($response->getItems())) {
                Log::warning('Video not found', ['video_id' => $videoId]);
                return null;
            }

            $video = $response->getItems()[0];
            $snippet = $video->getSnippet();
            $statistics = $video->getStatistics();

            return [
                'video_id' => $videoId,
                'title' => $snippet->getTitle(),
                'channel_id' => $snippet->getChannelId(),
                'channel_title' => $snippet->getChannelTitle(),
                'published_at' => $this->formatTimestamp($snippet->getPublishedAt()),
                'comment_count' => $statistics->getCommentCount() ?? 0,
            ];
        } catch (\Google\Service\Exception $e) {
            Log::error('YouTube API error fetching video metadata', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        }
    }

    /**
     * Get channel metadata (title)
     */
    public function getChannelMetadata(string $channelId): ?array
    {
        try {
            $response = $this->youtube->channels->listChannels('snippet', [
                'id' => $channelId,
            ]);

            if (empty($response->getItems())) {
                Log::warning('Channel not found', ['channel_id' => $channelId]);
                return null;
            }

            $channel = $response->getItems()[0];
            $snippet = $channel->getSnippet();

            return [
                'channel_id' => $channelId,
                'title' => $snippet->getTitle(),
            ];
        } catch (\Google\Service\Exception $e) {
            Log::error('YouTube API error fetching channel metadata', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get preview comments (latest 5 comments)
     */
    public function getPreviewComments(string $videoId, int $maxResults = 5): array
    {
        try {
            $response = $this->youtube->commentThreads->listCommentThreads('snippet', [
                'videoId' => $videoId,
                'maxResults' => $maxResults,
                'order' => 'time', // Latest first
                'textFormat' => 'plainText',
            ]);

            $comments = [];
            foreach ($response->getItems() as $commentThread) {
                $topLevelComment = $commentThread->getSnippet()->getTopLevelComment();
                $snippet = $topLevelComment->getSnippet();

                $comments[] = [
                    'comment_id' => $topLevelComment->getId(),
                    'author_channel_id' => $snippet->getAuthorChannelId()->getValue(),
                    'author_display_name' => $snippet->getAuthorDisplayName(),
                    'author_profile_image_url' => $snippet->getAuthorProfileImageUrl(),
                    'text_display' => $snippet->getTextDisplay(),
                    'like_count' => $snippet->getLikeCount() ?? 0,
                    'published_at' => $this->formatTimestamp($snippet->getPublishedAt()),
                ];
            }

            return $comments;
        } catch (\Google\Service\Exception $e) {
            Log::error('YouTube API error fetching preview comments', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get all comments for a video (with pagination)
     */
    public function getAllComments(string $videoId): array
    {
        $allComments = [];
        $pageToken = null;

        try {
            do {
                $params = [
                    'videoId' => $videoId,
                    'maxResults' => self::MAX_RESULTS_PER_PAGE,
                    'order' => 'time',
                    'textFormat' => 'plainText',
                    'part' => 'snippet,replies',
                ];

                if ($pageToken) {
                    $params['pageToken'] = $pageToken;
                }

                $response = $this->youtube->commentThreads->listCommentThreads('snippet,replies', $params);

                foreach ($response->getItems() as $commentThread) {
                    $topLevelComment = $commentThread->getSnippet()->getTopLevelComment();
                    $snippet = $topLevelComment->getSnippet();

                    $comment = [
                        'comment_id' => $topLevelComment->getId(),
                        'author_channel_id' => $snippet->getAuthorChannelId()->getValue(),
                        'content' => $snippet->getTextDisplay(),
                        'like_count' => $snippet->getLikeCount() ?? 0,
                        'published_at' => $this->formatTimestamp($snippet->getPublishedAt()),
                        'parent_comment_id' => null,
                    ];

                    $allComments[] = $comment;

                    // Get inline replies (up to 5-20 per thread)
                    if ($commentThread->getSnippet()->getTotalReplyCount() > 0 && $commentThread->getReplies()) {
                        foreach ($commentThread->getReplies()->getComments() as $reply) {
                            $replySnippet = $reply->getSnippet();
                            $allComments[] = [
                                'comment_id' => $reply->getId(),
                                'author_channel_id' => $replySnippet->getAuthorChannelId()->getValue(),
                                'content' => $replySnippet->getTextDisplay(),
                                'like_count' => $replySnippet->getLikeCount() ?? 0,
                                'published_at' => $this->formatTimestamp($replySnippet->getPublishedAt()),
                                'parent_comment_id' => $replySnippet->getParentId(),
                            ];
                        }
                    }

                    // Recursively fetch additional replies if more than 20
                    if ($commentThread->getSnippet()->getTotalReplyCount() > 20) {
                        $additionalReplies = $this->getCommentReplies($topLevelComment->getId());
                        $allComments = array_merge($allComments, $additionalReplies);
                    }
                }

                $pageToken = $response->getNextPageToken();
            } while ($pageToken);

            Log::info('Fetched all comments from YouTube API', [
                'video_id' => $videoId,
                'total_comments' => count($allComments),
            ]);

            return $allComments;
        } catch (\Google\Service\Exception $e) {
            Log::error('YouTube API error fetching all comments', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        }
    }

    /**
     * Get replies for a specific comment
     */
    public function getCommentReplies(string $parentId): array
    {
        try {
            $response = $this->youtube->comments->listComments('snippet', [
                'parentId' => $parentId,
                'maxResults' => 100,
                'textFormat' => 'plainText',
            ]);

            $replies = [];
            foreach ($response->getItems() as $reply) {
                $snippet = $reply->getSnippet();
                $replies[] = [
                    'comment_id' => $reply->getId(),
                    'author_channel_id' => $snippet->getAuthorChannelId()->getValue(),
                    'content' => $snippet->getTextDisplay(),
                    'like_count' => $snippet->getLikeCount() ?? 0,
                    'published_at' => $this->formatTimestamp($snippet->getPublishedAt()),
                    'parent_comment_id' => $parentId,
                ];
            }

            return $replies;
        } catch (\Google\Service\Exception $e) {
            Log::error('YouTube API error fetching comment replies', [
                'parent_id' => $parentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Format ISO 8601 timestamp - keep original ISO format to preserve timezone info
     * YouTube API returns UTC timestamps in ISO 8601 format (e.g., 2025-01-12T01:44:14Z)
     */
    private function formatTimestamp(string $isoTimestamp): string
    {
        Log::debug('YouTube API timestamp', [
            'original_iso' => $isoTimestamp,
            'parsed_utc' => Carbon::parse($isoTimestamp)->setTimezone('UTC')->toDateTimeString(),
            'parsed_taipei' => Carbon::parse($isoTimestamp)->setTimezone('Asia/Taipei')->toDateTimeString(),
        ]);

        // Return original ISO 8601 format to preserve timezone information
        // This ensures proper timezone conversion downstream
        return $isoTimestamp;
    }
}
