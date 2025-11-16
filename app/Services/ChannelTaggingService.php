<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\ChannelTag;
use App\Exceptions\ValidationException;
use Illuminate\Support\Str;

class ChannelTaggingService
{
    /**
     * Check if channel is new
     */
    public function isNewChannel($channelId): bool
    {
        return !Channel::where('channel_id', $channelId)->exists();
    }

    /**
     * Create pending import record
     * Extended to accept optional metadata fields (video_title, comment_count)
     */
    public function createPendingImport($videoId, $channelId, $channelName, ?string $videoTitle = null, ?int $commentCount = null, ?string $publishedAt = null): string
    {
        $importId = (string) Str::uuid();

        // Store in cache/session for 10 minutes
        cache()->put("import_{$importId}", [
            'import_id' => $importId,
            'video_id' => $videoId,
            'channel_id' => $channelId,
            'channel_name' => $channelName,
            'video_title' => $videoTitle,
            'published_at' => $publishedAt,
            'comment_count' => $commentCount,
            'status' => 'pending_tags',
            'created_at' => now(),
        ], now()->addMinutes(10));

        return $importId;
    }

    /**
     * Select tags for channel and resume import
     */
    public function selectTagsForChannel($importId, $channelId, $tagCodes): bool
    {
        if (empty($tagCodes)) {
            throw new ValidationException('請至少選擇一個標籤');
        }

        if (!is_array($tagCodes)) {
            throw new ValidationException('標籤格式錯誤');
        }

        // Get tag IDs from codes
        $tags = \App\Models\Tag::whereIn('code', $tagCodes)->pluck('tag_id')->toArray();

        if (count($tags) !== count($tagCodes)) {
            throw new ValidationException('包含無效的標籤');
        }

        // Ensure channel exists
        if (!Channel::where('channel_id', $channelId)->exists()) {
            throw new ValidationException('頻道不存在');
        }

        // Attach tags to channel
        foreach ($tags as $tagId) {
            ChannelTag::firstOrCreate([
                'channel_id' => $channelId,
                'tag_id' => $tagId,
            ]);
        }

        return true;
    }

    /**
     * Get pending import
     */
    public function getPendingImport($importId): ?array
    {
        return cache()->get("import_{$importId}");
    }

    /**
     * Clear pending import
     */
    public function clearPendingImport($importId): void
    {
        cache()->forget("import_{$importId}");
    }
}
