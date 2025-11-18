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

        // urtubeapi uses NESTED STRUCTURE for replies:
        // Top-level comments are in first level of 'comments' object
        // Replies are nested inside parent's 'replies' object
        // There is NO parentId field - parent-child relationship is expressed through nesting
        $commentsData = $apiJson['comments'] ?? [];

        foreach ($commentsData as $topLevelCommentId => $topLevelCommentData) {
            // Skip if commentData is not an array (shouldn't happen but be safe)
            if (!is_array($topLevelCommentData)) {
                continue;
            }

            // === Process top-level comment ===
            $authorChannelId = $topLevelCommentData['authorChannelId'] ?? $topLevelCommentData['author_channel_id'] ?? null;
            $authorName = $topLevelCommentData['author'] ?? $topLevelCommentData['authorDisplayName'] ?? null;
            $text = $topLevelCommentData['textOriginal'] ?? $topLevelCommentData['textDisplay'] ?? $topLevelCommentData['text'] ?? '';
            $likeCount = $topLevelCommentData['likeCount'] ?? $topLevelCommentData['like_count'] ?? 0;
            $publishedAt = $topLevelCommentData['publishedAt'] ?? $topLevelCommentData['published_at'] ?? null;

            // Collect unique authors
            if ($authorChannelId && !isset($authorChannelIds[$authorChannelId])) {
                $authorChannelIds[$authorChannelId] = true;
                $authors[] = new Author([
                    'author_channel_id' => $authorChannelId,
                    'name' => $authorName,
                    'profile_url' => null,
                ]);
            }

            // Create top-level comment (parent_comment_id = null)
            if ($topLevelCommentId && $text) {
                $comments[] = new Comment([
                    'comment_id' => $topLevelCommentId,
                    'video_id' => $videoId,
                    'author_channel_id' => $authorChannelId,
                    'text' => $text,
                    'like_count' => $likeCount,
                    'published_at' => $publishedAt ?
                        \Carbon\Carbon::parse($publishedAt) : null,
                    'parent_comment_id' => null, // Top-level comment has no parent
                ]);
            }

            // === Process replies (nested in 'replies' object) ===
            if (isset($topLevelCommentData['replies']) && is_array($topLevelCommentData['replies'])) {
                foreach ($topLevelCommentData['replies'] as $replyId => $replyData) {
                    if (!is_array($replyData)) {
                        continue;
                    }

                    // Extract reply fields
                    $replyAuthorChannelId = $replyData['authorChannelId'] ?? $replyData['author_channel_id'] ?? null;
                    $replyAuthorName = $replyData['author'] ?? $replyData['authorDisplayName'] ?? null;
                    $replyText = $replyData['textOriginal'] ?? $replyData['textDisplay'] ?? $replyData['text'] ?? '';
                    $replyLikeCount = $replyData['likeCount'] ?? $replyData['like_count'] ?? 0;
                    $replyPublishedAt = $replyData['publishedAt'] ?? $replyData['published_at'] ?? null;

                    // Collect unique reply authors
                    if ($replyAuthorChannelId && !isset($authorChannelIds[$replyAuthorChannelId])) {
                        $authorChannelIds[$replyAuthorChannelId] = true;
                        $authors[] = new Author([
                            'author_channel_id' => $replyAuthorChannelId,
                            'name' => $replyAuthorName,
                            'profile_url' => null,
                        ]);
                    }

                    // Create reply comment (parent_comment_id = top-level comment ID)
                    if ($replyId && $replyText) {
                        $comments[] = new Comment([
                            'comment_id' => $replyId,
                            'video_id' => $videoId,
                            'author_channel_id' => $replyAuthorChannelId,
                            'text' => $replyText,
                            'like_count' => $replyLikeCount,
                            'published_at' => $replyPublishedAt ?
                                \Carbon\Carbon::parse($replyPublishedAt) : null,
                            'parent_comment_id' => $topLevelCommentId, // Set parent to top-level comment
                        ]);
                    }
                }
            }
        }

        return (object)[
            'video' => $video,
            'comments' => $comments,
            'authors' => $authors,
        ];
    }
}
