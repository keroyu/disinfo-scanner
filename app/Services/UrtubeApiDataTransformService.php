<?php

namespace App\Services;

use App\Models\Video;
use App\Models\Comment;
use App\Models\Author;

class UrtubeApiDataTransformService
{
    /**
     * Transform urtubeapi JSON to Eloquent models
     *
     * @param array $apiJson API response data (may be camelCase or snake_case)
     * @param string|null $channelId Channel ID from URL token or web scraper (NOT from API response)
     * @return object {video, comments, authors}
     */
    public function transformToModels(array $apiJson, ?string $channelId = null): object
    {
        // channelId comes from request parameter (token) or web scraper, NEVER from API response
        if (!$channelId) {
            $channelId = $apiJson['channelId'] ?? $apiJson['channel_id'] ?? null;
        }

        $videoId = $apiJson['videoId'] ?? $apiJson['video_id'];

        $video = new Video([
            'video_id' => $videoId,
            'channel_id' => $channelId,
            'title' => $apiJson['videoTitle'] ?? null,
            'published_at' => null,
        ]);

        $comments = [];
        $authors = [];
        $authorChannelIds = [];

        // Comments from urtubeapi can be either:
        // 1. Array format: array of comment objects (has 'comments' array structure)
        // 2. Object format: object where keys are comment IDs (needs iteration by key)
        $commentsData = $apiJson['comments'] ?? [];

        foreach ($commentsData as $commentId => $commentData) {
            // commentId is the key when API returns object format
            // Skip if commentData is not an array (shouldn't happen but be safe)
            if (!is_array($commentData)) {
                continue;
            }

            // Extract fields from comment data
            // Support multiple possible field names
            $authorChannelId = $commentData['authorChannelId'] ?? $commentData['author_channel_id'] ?? null;
            $authorName = $commentData['author'] ?? $commentData['authorDisplayName'] ?? null;
            // Text can be in textDisplay or text or text field
            $text = $commentData['textOriginal'] ?? $commentData['textDisplay'] ?? $commentData['text'] ?? '';
            $likeCount = $commentData['likeCount'] ?? $commentData['like_count'] ?? 0;
            $publishedAt = $commentData['publishedAt'] ?? $commentData['published_at'] ?? null;

            // Collect unique authors
            if ($authorChannelId && !isset($authorChannelIds[$authorChannelId])) {
                $authorChannelIds[$authorChannelId] = true;
                $authors[] = new Author([
                    'author_channel_id' => $authorChannelId,
                    'name' => $authorName,
                    'profile_url' => null,
                ]);
            }

            // Create comment (skip if no comment ID or text)
            if ($commentId && $text) {
                $comments[] = new Comment([
                    'comment_id' => $commentId,
                    'video_id' => $videoId,
                    'author_channel_id' => $authorChannelId,
                    'text' => $text,
                    'like_count' => $likeCount,
                    'published_at' => $publishedAt ?
                        \Carbon\Carbon::parse($publishedAt) : null,
                ]);
            }
        }

        return (object)[
            'video' => $video,
            'comments' => $comments,
            'authors' => $authors,
        ];
    }
}
