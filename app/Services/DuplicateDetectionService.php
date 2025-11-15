<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Author;
use App\Models\Channel;

class DuplicateDetectionService
{
    /**
     * Detect duplicate comments
     */
    public function detectDuplicateComments(array $commentIds): array
    {
        $existingIds = Comment::whereIn('comment_id', $commentIds)
            ->pluck('comment_id')
            ->toArray();

        return [
            'duplicates' => $existingIds,
            'new_count' => count($commentIds) - count($existingIds),
            'duplicate_count' => count($existingIds),
        ];
    }

    /**
     * Check if author exists
     */
    public function detectExistingAuthor($authorChannelId): ?Author
    {
        return Author::find($authorChannelId);
    }

    /**
     * Check if channel exists
     */
    public function detectExistingChannel($channelId): ?Channel
    {
        return Channel::find($channelId);
    }

    /**
     * Check if multiple authors exist
     */
    public function detectExistingAuthors(array $authorChannelIds): array
    {
        $existing = Author::whereIn('author_channel_id', $authorChannelIds)
            ->pluck('author_channel_id')
            ->toArray();

        return [
            'existing' => $existing,
            'new' => array_diff($authorChannelIds, $existing),
        ];
    }
}
