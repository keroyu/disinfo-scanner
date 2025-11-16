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
                'moderationStatus' => 'heldForReview,likelySpam,published',
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
     * Fetch all comments for a video, optionally filtered by date
     * Recursively fetches replies at all levels
     */
    public function fetchAllComments(string $videoId, ?string $afterDate = null, ?callable $progressCallback = null): array
    {
        $this->validateVideoId($videoId);

        try {
            $allComments = [];
            $nextPageToken = null;

            do {
                $params = [
                    'videoId' => $videoId,
                    'maxResults' => 100,
                    'order' => 'time',
                    'textFormat' => 'plainText',
                    'moderationStatus' => 'heldForReview,likelySpam,published',
                ];

                if ($nextPageToken) {
                    $params['pageToken'] = $nextPageToken;
                }

                $response = $this->youtube->commentThreads->listCommentThreads('snippet,replies', $params);

                foreach ($response->getItems() as $thread) {
                    $topLevel = $thread->getSnippet()->getTopLevelComment();
                    $commentData = $this->parseCommentData($topLevel);

                    // Filter by date if provided
                    if ($afterDate && strtotime($commentData['published_at']) <= strtotime($afterDate)) {
                        break 2; // Break both foreach and do-while
                    }

                    $allComments[] = $commentData;

                    // Process inline replies
                    if ($thread->getSnippet()->getTotalReplyCount() > 0 && $thread->getReplies()) {
                        foreach ($thread->getReplies()->getComments() as $reply) {
                            $replyData = $this->parseCommentData($reply, $topLevel->getId());
                            $allComments[] = $replyData;
                        }
                    }

                    // Recursively fetch additional replies if more than 20
                    if ($thread->getSnippet()->getTotalReplyCount() > 20) {
                        $additionalReplies = $this->fetchRepliesRecursive($videoId, $topLevel->getId());
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
     */
    private function fetchRepliesRecursive(string $videoId, string $parentCommentId, ?string $afterDate = null): array
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

                    // Filter by date if provided
                    if ($afterDate && strtotime($commentData['published_at']) <= strtotime($afterDate)) {
                        break 2; // Break both foreach and do-while
                    }

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
