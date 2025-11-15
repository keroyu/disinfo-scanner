<?php

namespace App\Services;

use App\Models\Video;
use App\Models\Comment;
use App\Models\Author;

class DataTransformService
{
    /**
     * Transform urtubeapi JSON to Eloquent models
     */
    public function transformToModels(array $apiJson): object
    {
        $video = new Video([
            'video_id' => $apiJson['videoId'],
            'channel_id' => $apiJson['channelId'],
            'title' => $apiJson['videoTitle'] ?? null,
            'youtube_url' => "https://www.youtube.com/watch?v={$apiJson['videoId']}",
            'published_at' => null,
        ]);

        $comments = [];
        $authors = [];
        $authorChannelIds = [];

        foreach ($apiJson['comments'] as $commentData) {
            $authorChannelId = $commentData['authorChannelId'] ?? null;

            // Collect unique authors
            if ($authorChannelId && !isset($authorChannelIds[$authorChannelId])) {
                $authorChannelIds[$authorChannelId] = true;
                $authors[] = new Author([
                    'author_channel_id' => $authorChannelId,
                    'name' => $commentData['author'] ?? null,
                    'profile_url' => null,
                ]);
            }

            // Create comment
            $comments[] = new Comment([
                'comment_id' => $commentData['commentId'],
                'video_id' => $apiJson['videoId'],
                'author_channel_id' => $authorChannelId,
                'text' => $commentData['text'] ?? '',
                'like_count' => $commentData['likeCount'] ?? 0,
                'published_at' => isset($commentData['publishedAt']) ?
                    \Carbon\Carbon::parse($commentData['publishedAt']) : null,
            ]);
        }

        return (object)[
            'video' => $video,
            'comments' => $comments,
            'authors' => $authors,
        ];
    }
}
