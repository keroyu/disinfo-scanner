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
    private ?YouTube $youtube = null;

    private function getYoutube(): YouTube
    {
        if ($this->youtube === null) {
            $apiKey = auth()->user()?->youtube_api_key ?? config('services.youtube.api_key');
            if (!$apiKey) {
                throw new AuthenticationException('YouTube API key not configured');
            }
            $client = new Client();
            $client->setApplicationName('DISINFO_SCANNER');
            $client->setDeveloperKey($apiKey);
            $this->youtube = new YouTube($client);
        }
        return $this->youtube;
    }

    /**
     * Fetch video metadata from YouTube API
     * Returns: title, channel_name, published_at
     */
    public function fetchVideoMetadata(string $videoId): array
    {
        $this->validateVideoId($videoId);

        try {
            $response = $this->getYoutube()->videos->listVideos('snippet', [
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
            $response = $this->getYoutube()->commentThreads->listCommentThreads('snippet,replies', [
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
     * Fetch comments outside the current range (for incremental updates)
     * Returns comments where: published_at > latestTime OR published_at < earliestTime
     * This allows both updating new comments AND backfilling old comments
     *
     * @param string $videoId The YouTube video ID
     * @param string|null $latestTime Latest comment time in DB (T1)
     * @param string|null $earliestTime Earliest comment time in DB (T2)
     * @param int $maxResults Maximum number of comments to return (default 2500)
     * @return array Flattened array of comments outside the range (newest first from API)
     */
    public function fetchCommentsOutsideRange(
        string $videoId,
        ?string $latestTime = null,
        ?string $earliestTime = null,
        int $maxResults = 2500
    ): array {
        $this->validateVideoId($videoId);

        try {
            $allComments = [];
            $filteredComments = [];
            $nextPageToken = null;
            $pageCount = 0;
            $maxPages = 100; // Safety limit: max 100 pages (10,000 comments)

            // Convert times to Carbon for comparison
            $t1 = $latestTime ? \Carbon\Carbon::parse($latestTime) : null;
            $t2 = $earliestTime ? \Carbon\Carbon::parse($earliestTime) : null;

            Log::info('Fetching comments outside range', [
                'video_id' => $videoId,
                'latest_time' => $t1?->toDateTimeString(),
                'earliest_time' => $t2?->toDateTimeString(),
                'max_results' => $maxResults,
            ]);

            // Fetch comments in pages, ordered by time (newest first from API)
            do {
                $pageCount++;
                $params = [
                    'videoId' => $videoId,
                    'maxResults' => 100,
                    'order' => 'time', // YouTube API returns newest first
                    'textFormat' => 'plainText',
                ];

                if ($nextPageToken) {
                    $params['pageToken'] = $nextPageToken;
                }

                $response = $this->getYoutube()->commentThreads->listCommentThreads('snippet,replies', $params);

                // Process each comment thread (including recursive replies)
                foreach ($response->getItems() as $thread) {
                    $topLevel = $thread->getSnippet()->getTopLevelComment();
                    $topLevelData = $this->parseCommentData($topLevel);
                    $commentTime = \Carbon\Carbon::parse($topLevelData['published_at']);

                    // Check if comment is outside current range: > T1 OR < T2
                    $isOutsideRange = (!$t1 || $commentTime->greaterThan($t1)) ||
                                     (!$t2 || $commentTime->lessThan($t2));

                    if ($isOutsideRange) {
                        $filteredComments[] = $topLevelData;
                    }

                    $allComments[] = $topLevelData;

                    // Process inline replies (up to 20 per thread)
                    if ($thread->getSnippet()->getTotalReplyCount() > 0 && $thread->getReplies()) {
                        foreach ($thread->getReplies()->getComments() as $reply) {
                            $replyData = $this->parseCommentData($reply, $topLevel->getId());
                            $replyTime = \Carbon\Carbon::parse($replyData['published_at']);

                            $isOutsideRange = (!$t1 || $replyTime->greaterThan($t1)) ||
                                             (!$t2 || $replyTime->lessThan($t2));

                            if ($isOutsideRange) {
                                $filteredComments[] = $replyData;
                            }

                            $allComments[] = $replyData;
                        }
                    }

                    // Recursively fetch additional replies if more than 20
                    if ($thread->getSnippet()->getTotalReplyCount() > 20) {
                        $additionalReplies = $this->fetchRepliesRecursive(
                            $videoId,
                            $topLevel->getId()
                        );

                        foreach ($additionalReplies as $replyData) {
                            $replyTime = \Carbon\Carbon::parse($replyData['published_at']);

                            $isOutsideRange = (!$t1 || $replyTime->greaterThan($t1)) ||
                                             (!$t2 || $replyTime->lessThan($t2));

                            if ($isOutsideRange) {
                                $filteredComments[] = $replyData;
                            }

                            $allComments[] = $replyData;
                        }
                    }
                }

                $nextPageToken = $response->getNextPageToken();

                // Continue fetching until we run out of pages or hit the page limit
            } while ($nextPageToken && $pageCount < $maxPages);

            // Take only the first maxResults comments (newest first from API)
            $totalFiltered = count($filteredComments);
            $filteredComments = array_slice($filteredComments, 0, $maxResults);

            Log::info('Comments fetched and filtered (outside range)', [
                'video_id' => $videoId,
                'pages_fetched' => $pageCount,
                'total_fetched' => count($allComments),
                'filtered_count' => $totalFiltered,
                'will_import' => count($filteredComments),
                'latest_time' => $t1?->toDateTimeString(),
                'earliest_time' => $t2?->toDateTimeString(),
                'order' => 'newest-first (from API)',
            ]);

            return $filteredComments;
        } catch (\Exception $e) {
            Log::error('YouTube API error in fetchCommentsOutsideRange', [
                'video_id' => $videoId,
                'latest_time' => $latestTime,
                'earliest_time' => $earliestTime,
                'error' => $e->getMessage(),
            ]);
            throw new YouTubeApiException('Failed to fetch comments outside range: ' . $e->getMessage());
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
            $pageCount = 0;
            $maxPages = 100; // Safety limit: max 100 pages (10,000 comments)

            // Convert publishedAfter to Carbon for comparison
            $cutoffTime = $publishedAfter ? \Carbon\Carbon::parse($publishedAfter) : null;

            Log::info('Fetching comments for incremental update', [
                'video_id' => $videoId,
                'cutoff_time' => $cutoffTime?->toDateTimeString(),
                'max_results' => $maxResults,
            ]);

            // Fetch comments in pages, ordered by time (newest first from API)
            // We'll collect all new comments then reverse to get oldest-first order
            do {
                $pageCount++;
                $params = [
                    'videoId' => $videoId,
                    'maxResults' => 100,
                    'order' => 'time', // YouTube API returns newest first
                    'textFormat' => 'plainText',
                ];

                if ($nextPageToken) {
                    $params['pageToken'] = $nextPageToken;
                }

                $response = $this->getYoutube()->commentThreads->listCommentThreads('snippet,replies', $params);

                // Process each comment thread (including recursive replies)
                foreach ($response->getItems() as $thread) {
                    $topLevel = $thread->getSnippet()->getTopLevelComment();
                    $topLevelData = $this->parseCommentData($topLevel);

                    // Check if top-level comment is new
                    $commentTime = \Carbon\Carbon::parse($topLevelData['published_at']);
                    $isNew = !$cutoffTime || $commentTime->greaterThan($cutoffTime);

                    if ($isNew) {
                        $newComments[] = $topLevelData;
                    }

                    $allComments[] = $topLevelData;

                    // Process inline replies (up to 5-20 per thread)
                    if ($thread->getSnippet()->getTotalReplyCount() > 0 && $thread->getReplies()) {
                        foreach ($thread->getReplies()->getComments() as $reply) {
                            $replyData = $this->parseCommentData($reply, $topLevel->getId());
                            $replyTime = \Carbon\Carbon::parse($replyData['published_at']);

                            if (!$cutoffTime || $replyTime->greaterThan($cutoffTime)) {
                                $newComments[] = $replyData;
                            }

                            $allComments[] = $replyData;
                        }
                    }

                    // Recursively fetch additional replies if more than 20
                    if ($thread->getSnippet()->getTotalReplyCount() > 20) {
                        $additionalReplies = $this->fetchRepliesRecursive(
                            $videoId,
                            $topLevel->getId()
                        );

                        // Filter additional replies
                        foreach ($additionalReplies as $replyData) {
                            $replyTime = \Carbon\Carbon::parse($replyData['published_at']);

                            if (!$cutoffTime || $replyTime->greaterThan($cutoffTime)) {
                                $newComments[] = $replyData;
                            }

                            $allComments[] = $replyData;
                        }
                    }
                }

                $nextPageToken = $response->getNextPageToken();

                // Continue fetching until we run out of pages or hit the page limit
            } while ($nextPageToken && $pageCount < $maxPages);

            // Reverse to get oldest-first order, then take only the oldest maxResults comments
            // This ensures incremental updates import in chronological order
            $totalNewComments = count($newComments);
            $newComments = array_reverse($newComments);
            $newComments = array_slice($newComments, 0, $maxResults);

            Log::info('Comments fetched and filtered', [
                'video_id' => $videoId,
                'pages_fetched' => $pageCount,
                'total_fetched' => count($allComments),
                'new_comments_found' => $totalNewComments,
                'will_import' => count($newComments),
                'cutoff_time' => $cutoffTime?->toDateTimeString(),
                'order' => 'oldest-first (reversed)',
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
     * @param bool $isNewVideo Whether this is a new video (first import)
     * @return array Flattened array of all comments and replies with parent_comment_id set
     */
    public function fetchAllComments(string $videoId, ?callable $progressCallback = null, bool $isNewVideo = false): array
    {
        $this->validateVideoId($videoId);

        try {
            $allComments = [];
            $nextPageToken = null;
            $maxCommentsLimit = $isNewVideo ? 2500 : PHP_INT_MAX;

            do {
                $params = [
                    'videoId' => $videoId,
                    'maxResults' => 100,
                    // For new videos: order=time returns NEWEST FIRST (最新→最舊)
                    // Strategy: Import latest 2500 comments first, use incremental update for older ones later
                    'order' => $isNewVideo ? 'time' : 'relevance',
                    'textFormat' => 'plainText',
                ];

                if ($nextPageToken) {
                    $params['pageToken'] = $nextPageToken;
                }

                $response = $this->getYoutube()->commentThreads->listCommentThreads('snippet,replies', $params);

                foreach ($response->getItems() as $thread) {
                    // Stop if we've reached the limit for new videos
                    if ($isNewVideo && count($allComments) >= $maxCommentsLimit) {
                        break 2;
                    }

                    $topLevel = $thread->getSnippet()->getTopLevelComment();
                    $commentData = $this->parseCommentData($topLevel);
                    $allComments[] = $commentData;

                    // Process inline replies (up to 20 per thread)
                    if ($thread->getSnippet()->getTotalReplyCount() > 0 && $thread->getReplies()) {
                        foreach ($thread->getReplies()->getComments() as $reply) {
                            $replyData = $this->parseCommentData($reply, $topLevel->getId());
                            $allComments[] = $replyData;

                            // Stop if we've reached the limit for new videos
                            if ($isNewVideo && count($allComments) >= $maxCommentsLimit) {
                                break 3;
                            }
                        }
                    }

                    // Recursively fetch additional replies if more than 20
                    if ($thread->getSnippet()->getTotalReplyCount() > 20) {
                        $additionalReplies = $this->fetchRepliesRecursive(
                            $videoId,
                            $topLevel->getId()
                        );

                        foreach ($additionalReplies as $replyData) {
                            if ($isNewVideo && count($allComments) >= $maxCommentsLimit) {
                                break 3;
                            }
                            $allComments[] = $replyData;
                        }
                    }
                }

                if ($progressCallback) {
                    $progressCallback(count($allComments));
                }

                $nextPageToken = $response->getNextPageToken();

                // Stop pagination if we've reached the limit for new videos
            } while ($nextPageToken && (!$isNewVideo || count($allComments) < $maxCommentsLimit));

            if ($isNewVideo) {
                Log::info('New video: comment import completed', [
                    'video_id' => $videoId,
                    'comments_fetched' => count($allComments),
                    'limit' => $maxCommentsLimit,
                    'order' => 'NEWEST FIRST (最新→最舊)',
                    'strategy' => 'Import latest comments first, older comments can be added via incremental update',
                    'first_comment_date' => $allComments[0]['published_at'] ?? null,
                    'last_comment_date' => $allComments[count($allComments) - 1]['published_at'] ?? null,
                ]);
            }

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

                $response = $this->getYoutube()->comments->listComments('snippet', $params);

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
