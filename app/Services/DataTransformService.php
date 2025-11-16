<?php

namespace App\Services;

use App\Models\Video;
use App\Models\Comment;
use App\Models\Author;

class DataTransformService
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
            'youtube_url' => "https://www.youtube.com/watch?v={$videoId}",
            'published_at' => null,
        ]);

        $comments = [];
        $authors = [];
        $authorChannelIds = [];

        foreach ($apiJson['comments'] as $commentData) {
            // Support both camelCase (legacy) and snake_case (API) field names
            $commentId = $commentData['commentId'] ?? $commentData['comment_id'] ?? null;
            $authorChannelId = $commentData['authorChannelId'] ?? $commentData['author_channel_id'] ?? null;
            $authorName = $commentData['author'] ?? null;
            $text = $commentData['text'] ?? '';
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

            // Create comment
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

        return (object)[
            'video' => $video,
            'comments' => $comments,
            'authors' => $authors,
        ];
    }
}
