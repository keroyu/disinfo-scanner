<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Tag;
use Illuminate\Support\Facades\Log;

class ChannelTagManager
{
    /**
     * Get all tags for a channel
     */
    public function getChannelTags(Channel $channel): array
    {
        $tagIds = $channel->getTagIdsArray();
        if (empty($tagIds)) {
            return [];
        }

        return Tag::whereIn('tag_id', $tagIds)->get()->map(function ($tag) {
            return [
                'id' => $tag->tag_id,
                'name' => $tag->name,
                'color' => $tag->color ?? '#3B82F6',
            ];
        })->toArray();
    }

    /**
     * Get all available tags in the system
     */
    public function getAllTags(): array
    {
        return Tag::all()->map(function ($tag) {
            return [
                'id' => $tag->tag_id,
                'name' => $tag->name,
                'color' => $tag->color ?? '#3B82F6',
            ];
        })->toArray();
    }

    /**
     * Sync channel tags (update tag_ids field)
     *
     * @param Channel $channel
     * @param array $tagIds Array of tag IDs
     * @return void
     */
    public function syncChannelTags(Channel $channel, array $tagIds): void
    {
        // Validate that all tag IDs exist
        $validTags = Tag::whereIn('tag_id', $tagIds)->pluck('tag_id')->toArray();

        if (count($validTags) !== count($tagIds)) {
            $invalid = array_diff($tagIds, $validTags);
            throw new \InvalidArgumentException('Invalid tag IDs: ' . implode(', ', $invalid));
        }

        // Update tag_ids field with comma-separated string
        $channel->update([
            'tag_ids' => empty($tagIds) ? null : implode(',', $tagIds)
        ]);

        Log::info('Channel tags synced', [
            'channel_id' => $channel->channel_id,
            'tag_ids' => $tagIds,
        ]);
    }

    /**
     * Validate that at least one tag is provided for new channels
     */
    public function validateNewChannelTags(array $tagIds): bool
    {
        return !empty($tagIds) && count($tagIds) > 0;
    }
}
