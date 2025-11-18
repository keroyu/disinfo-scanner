<?php

namespace App\Services;

use Google\Client;
use Google\Service\YouTube;
use App\Exceptions\YouTubeApiException;
use App\Exceptions\InvalidVideoIdException;
use App\Exceptions\AuthenticationException;
use Illuminate\Support\Facades\Log;

class YouTubeApiService
{
    private YouTube $youtube;

    public function __construct()
    {
        $apiKey = config('services.youtube.api_key');

        if (!$apiKey) {
            throw new AuthenticationException('YouTube API key not configured');
        }

        $client = new Client();
        $client->setApplicationName('DISINFO_SCANNER');
        $client->setDeveloperKey($apiKey);

        $this->youtube = new YouTube($client);
    }

    /**
     * Fetch video metadata from YouTube API
     * Returns: title, channel_name, published_at
     */
    public function fetchVideoMetadata(string $videoId): array
    {
        $this->validateVideoId($videoId);

        try {
            $response = $this->youtube->videos->listVideos('snippet', [
                'id' => $videoId,
            ]);

            $items = $response->getItems();
            if (empty($items)) {
                throw new YouTubeApiException("Video not found: {$videoId}");
            }

            $video = $items[0];
            $snippet = $video->getSnippet();

            return [
                'title' => $snippet->getTitle(),
                'channel_name' => $snippet->getChannelTitle(),
                'channel_id' => $snippet->getChannelId(),
                'published_at' => $snippet->getPublishedAt(),
            ];
        } catch (\Exception $e) {
            Log::error('YouTube API error in fetchVideoMetadata', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            throw new YouTubeApiException('Failed to fetch video metadata: ' . $e->getMessage());
        }
    }

    /**
     * Fetch preview comments (up to 5) for a video without persisting
     */
    public function fetchPreviewComments(string $videoId): array
    {
        $this->validateVideoId($videoId);

        try {
            $response = $this->youtube->commentThreads->listCommentThreads('snippet,replies', [
                'videoId' => $videoId,
                'maxResults' => 5,
                'order' => 'time',
                'textFormat' => 'plainText',
            ]);

            return $this->flattenCommentThreads($response->getItems());
        } catch (\Exception $e) {
            Log::error('YouTube API error in fetchPreviewComments', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            throw new YouTubeApiException('Failed to fetch preview comments: ' . $e->getMessage());
        }
    }

    /**
     * Fetch comments published after a specific timestamp (for incremental updates)
     * NOTE: YouTube API doesn't support publishedAfter parameter, so we fetch and filter client-side
     *
     * @param string $videoId The YouTube video ID
     * @param string|null $publishedAfter ISO 8601 timestamp (e.g., "2025-11-05 15:00:00")
     * @param int $maxResults Maximum number of NEW comments to return (default 500)
     * @return array Flattened array of comments published after the timestamp
     */
    public function fetchCommentsAfter(string $videoId, ?string $publishedAfter = null, int $maxResults = 500): array
    {
        $this->validateVideoId($videoId);

        try {
            $allComments = [];
            $newComments = [];
            $nextPageToken = null;

            // Convert publishedAfter to Carbon for comparison
            $cutoffTime = $publishedAfter ? \Carbon\Carbon::parse($publishedAfter) : null;

            Log::info('Fetching comments for incremental update', [
                'video_id' => $videoId,
                'cutoff_time' => $cutoffTime?->toDateTimeString(),
                'max_results' => $maxResults,
            ]);

            // Fetch comments in pages, ordered by time (newest first)
            do {
                $params = [
                    'videoId' => $videoId,
                    'maxResults' => 100,
                    'order' => 'time',
                    'textFormat' => 'plainText',
                ];

                if ($nextPageToken) {
                    $params['pageToken'] = $nextPageToken;
                }

                $response = $this->youtube->commentThreads->listCommentThreads('snippet,replies', $params);

                $fetchedComments = $this->flattenCommentThreads($response->getItems());

                // Filter comments published after cutoff time
                foreach ($fetchedComments as $comment) {
                    $commentTime = \Carbon\Carbon::parse($comment['published_at']);

                    // Only include comments AFTER the cutoff time
                    if (!$cutoffTime || $commentTime->greaterThan($cutoffTime)) {
                        $newComments[] = $comment;

                        // Stop if we've collected enough new comments
                        if (count($newComments) >= $maxResults) {
                            break 2; // Break both foreach and do-while
                        }
                    }
                }

                $allComments = array_merge($allComments, $fetchedComments);
                $nextPageToken = $response->getNextPageToken();

                // Stop if no more pages or we have enough new comments
            } while ($nextPageToken && count($newComments) < $maxResults);

            Log::info('Comments fetched and filtered', [
                'video_id' => $videoId,
                'total_fetched' => count($allComments),
                'new_comments' => count($newComments),
                'cutoff_time' => $cutoffTime?->toDateTimeString(),
            ]);

            return $newComments;
        } catch (\Exception $e) {
            Log::error('YouTube API error in fetchCommentsAfter', [
                'video_id' => $videoId,
                'published_after' => $publishedAfter,
                'error' => $e->getMessage(),
            ]);
            throw new YouTubeApiException('Failed to fetch comments after timestamp: ' . $e->getMessage());
        }
    }

    /**
     * Fetch all comments for a video
     * Recursively fetches replies at all levels
     *
     * @param string $videoId The YouTube video ID
     * @param ?callable $progressCallback Optional: callback for progress updates
     * @return array Flattened array of all comments and replies with parent_comment_id set
     */
    public function fetchAllComments(string $videoId, ?callable $progressCallback = null): array
    {
        $this->validateVideoId($videoId);

        try {
            $allComments = [];
            $nextPageToken = null;

            do {
                $params = [
                    'videoId' => $videoId,
                    'maxResults' => 100,
                    'order' => 'relevance',
                    'textFormat' => 'plainText',
                ];

                if ($nextPageToken) {
                    $params['pageToken'] = $nextPageToken;
                }

                $response = $this->youtube->commentThreads->listCommentThreads('snippet,replies', $params);

                foreach ($response->getItems() as $thread) {
                    $topLevel = $thread->getSnippet()->getTopLevelComment();
                    $commentData = $this->parseCommentData($topLevel);
                    $allComments[] = $commentData;

                    // Process inline replies (up to 20 per thread)
                    if ($thread->getSnippet()->getTotalReplyCount() > 0 && $thread->getReplies()) {
                        foreach ($thread->getReplies()->getComments() as $reply) {
                            $replyData = $this->parseCommentData($reply, $topLevel->getId());
                            $allComments[] = $replyData;
                        }
                    }

                    // Recursively fetch additional replies if more than 20
                    if ($thread->getSnippet()->getTotalReplyCount() > 20) {
                        $additionalReplies = $this->fetchRepliesRecursive(
                            $videoId,
                            $topLevel->getId()
                        );
                        $allComments = array_merge($allComments, $additionalReplies);
                    }
                }

                if ($progressCallback) {
                    $progressCallback(count($allComments));
                }

                $nextPageToken = $response->getNextPageToken();
            } while ($nextPageToken);

            return $allComments;
        } catch (\Exception $e) {
            Log::error('YouTube API error in fetchAllComments', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            throw new YouTubeApiException('Failed to fetch all comments: ' . $e->getMessage());
        }
    }

    /**
     * Recursively fetch all replies to a comment
     *
     * @param string $videoId The YouTube video ID
     * @param string $parentCommentId The parent comment ID
     * @return array Flattened array of all reply comments with parent_comment_id set
     */
    private function fetchRepliesRecursive(string $videoId, string $parentCommentId): array
    {
        $replies = [];
        $nextPageToken = null;

        try {
            do {
                $params = [
                    'parentId' => $parentCommentId,
                    'maxResults' => 100,
                    'textFormat' => 'plainText',
                ];

                if ($nextPageToken) {
                    $params['pageToken'] = $nextPageToken;
                }

                $response = $this->youtube->comments->listComments('snippet', $params);

                foreach ($response->getItems() as $comment) {
                    $commentData = $this->parseCommentData($comment, $parentCommentId);
                    $replies[] = $commentData;
                }

                $nextPageToken = $response->getNextPageToken();
            } while ($nextPageToken);
        } catch (\Exception $e) {
            Log::warning('Error fetching recursive replies', [
                'parent_id' => $parentCommentId,
                'error' => $e->getMessage(),
            ]);
        }

        return $replies;
    }

    /**
     * Parse comment data from YouTube API response
     */
    private function parseCommentData($comment, ?string $parentId = null): array
    {
        $snippet = $comment->getSnippet();

        return [
            'comment_id' => $comment->getId(),
            'video_id' => $snippet->getVideoId(),
            'author_channel_id' => $snippet->getAuthorChannelId()->getValue() ?? null,
            'text' => $snippet->getTextDisplay(),
            'like_count' => $snippet->getLikeCount() ?? 0,
            'published_at' => $snippet->getPublishedAt(),
            'parent_comment_id' => $parentId,
        ];
    }

    /**
     * Flatten comment threads to array of comments
     */
    private function flattenCommentThreads(array $threads): array
    {
        $comments = [];

        foreach ($threads as $thread) {
            $topLevel = $thread->getSnippet()->getTopLevelComment();
            $comments[] = $this->parseCommentData($topLevel);

            // Add inline replies
            if ($thread->getReplies()) {
                foreach ($thread->getReplies()->getComments() as $reply) {
                    $comments[] = $this->parseCommentData($reply, $topLevel->getId());
                }
            }
        }

        return $comments;
    }

    /**
     * Validate video ID format
     */
    public function validateVideoId(string $videoId): bool
    {
        // YouTube video IDs are 11 characters long and contain alphanumeric, dash, and underscore
        if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId)) {
            throw new InvalidVideoIdException("Invalid video ID format: {$videoId}");
        }

        return true;
    }

    /**
     * Log import operation
     */
    public function logOperation(string $traceId, string $operation, int $commentCount, string $status, ?string $error = null): void
    {
        Log::info('YouTube API operation', [
            'trace_id' => $traceId,
            'operation' => $operation,
            'comment_count' => $commentCount,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
