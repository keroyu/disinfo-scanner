<?php

namespace App\Services;

use App\Models\Comment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CommentPatternService
{
    /**
     * Get pattern statistics for a video
     *
     * @param string $videoId
     * @return array
     */
    public function getPatternStatistics(string $videoId): array
    {
        $cacheKey = "video:{$videoId}:pattern_statistics";

        return Cache::remember($cacheKey, 300, function () use ($videoId) {
            $startTime = microtime(true);

            // Get total unique commenters for this video
            $totalUniqueCommenters = Comment::where('video_id', $videoId)
                ->distinct('author_channel_id')
                ->count('author_channel_id');

            // Calculate each pattern
            $allComments = $this->calculateAllCommentsPattern($videoId, $totalUniqueCommenters);
            $repeatCommenters = $this->calculateRepeatCommenters($videoId, $totalUniqueCommenters);
            $nightTimeCommenters = $this->calculateNightTimeCommenters($videoId, $totalUniqueCommenters);
            $aggressiveCommenters = $this->placeholderPattern('aggressive');
            $simplifiedChineseCommenters = $this->placeholderPattern('simplified_chinese');

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Pattern statistics calculated', [
                'video_id' => $videoId,
                'execution_time_ms' => $executionTime,
                'cache_hit' => false
            ]);

            return [
                'all' => $allComments,
                'repeat' => $repeatCommenters,
                'night_time' => $nightTimeCommenters,
                'aggressive' => $aggressiveCommenters,
                'simplified_chinese' => $simplifiedChineseCommenters
            ];
        });
    }

    /**
     * Get comments by pattern with pagination
     *
     * @param string $videoId
     * @param string $pattern
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getCommentsByPattern(string $videoId, string $pattern, int $offset = 0, int $limit = 100): array
    {
        $startTime = microtime(true);

        $query = Comment::where('video_id', $videoId)
            ->orderBy('published_at', 'DESC');

        // Apply pattern filter
        switch ($pattern) {
            case 'all':
                // No additional filtering
                break;

            case 'repeat':
                $repeatAuthorIds = $this->getRepeatAuthorIds($videoId);
                $query->whereIn('author_channel_id', $repeatAuthorIds);
                break;

            case 'night_time':
                $nightTimeAuthorIds = $this->getNightTimeAuthorIds();
                $query->whereIn('author_channel_id', $nightTimeAuthorIds)
                      ->where('video_id', $videoId);
                break;

            case 'aggressive':
            case 'simplified_chinese':
                // Return empty for placeholders
                return [
                    'comments' => [],
                    'has_more' => false,
                    'total' => 0
                ];

            default:
                // Invalid pattern, return all
                break;
        }

        // Get total count for has_more calculation
        $total = $query->count();

        // Apply pagination
        $comments = $query->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($comment) {
                return [
                    'comment_id' => $comment->comment_id,
                    'author_channel_id' => $comment->author_channel_id,
                    'author_name' => $comment->author_channel_id, // Simply use author_channel_id
                    'text' => $comment->text,
                    'like_count' => $comment->like_count,
                    'published_at' => $comment->published_at
                        ? $comment->published_at->setTimezone('Asia/Taipei')->format('Y/m/d H:i') . ' (GMT+8)'
                        : null
                ];
            });

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Comments fetched by pattern', [
            'video_id' => $videoId,
            'pattern' => $pattern,
            'offset' => $offset,
            'limit' => $limit,
            'returned_count' => $comments->count(),
            'execution_time_ms' => $executionTime
        ]);

        return [
            'comments' => $comments->toArray(),
            'has_more' => ($offset + $limit) < $total,
            'total' => $total
        ];
    }

    /**
     * Calculate "all comments" pattern
     */
    private function calculateAllCommentsPattern(string $videoId, int $totalUniqueCommenters): array
    {
        return [
            'count' => $totalUniqueCommenters,
            'percentage' => 100
        ];
    }

    /**
     * Calculate repeat commenters (2+ comments on same video)
     */
    private function calculateRepeatCommenters(string $videoId, int $totalUniqueCommenters): array
    {
        $repeatCommentersCount = Comment::where('video_id', $videoId)
            ->select('author_channel_id')
            ->groupBy('author_channel_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();

        $percentage = $totalUniqueCommenters > 0
            ? round(($repeatCommentersCount / $totalUniqueCommenters) * 100)
            : 0;

        return [
            'count' => $repeatCommentersCount,
            'percentage' => $percentage
        ];
    }

    /**
     * Calculate night-time high-frequency commenters (>50% comments during 01:00-05:59 GMT+8)
     */
    private function calculateNightTimeCommenters(string $videoId, int $totalUniqueCommenters): array
    {
        // Get commenters on this video who have >50% night-time comments across ALL channels
        $nightTimeAuthorIds = $this->getNightTimeAuthorIds();

        // Count how many of these commenters are on this specific video
        $nightTimeCommentersCount = Comment::where('video_id', $videoId)
            ->whereIn('author_channel_id', $nightTimeAuthorIds)
            ->distinct('author_channel_id')
            ->count('author_channel_id');

        $percentage = $totalUniqueCommenters > 0
            ? round(($nightTimeCommentersCount / $totalUniqueCommenters) * 100)
            : 0;

        return [
            'count' => $nightTimeCommentersCount,
            'percentage' => $percentage
        ];
    }

    /**
     * Get author IDs who are repeat commenters on a video
     */
    private function getRepeatAuthorIds(string $videoId): array
    {
        return Comment::where('video_id', $videoId)
            ->select('author_channel_id')
            ->groupBy('author_channel_id')
            ->havingRaw('COUNT(*) >= 2')
            ->pluck('author_channel_id')
            ->toArray();
    }

    /**
     * Get author IDs who have >50% night-time comments across all channels
     * Cached for 5 minutes since this is an expensive cross-channel query
     */
    private function getNightTimeAuthorIds(): array
    {
        return Cache::remember('night_time_author_ids', 300, function () {
            $startTime = microtime(true);

            // Check database driver to use appropriate timezone functions
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                // SQLite version: use PHP-level processing for timezone conversion
                $nightTimeAuthors = $this->getNightTimeAuthorIdsSqlite();
            } else {
                // MySQL version: use CONVERT_TZ function
                $nightTimeAuthors = DB::table('comments')
                    ->select('author_channel_id')
                    ->selectRaw('COUNT(*) as total_comments')
                    ->selectRaw('SUM(CASE
                        WHEN HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) BETWEEN 1 AND 5
                        THEN 1 ELSE 0 END) as night_comments')
                    ->whereNotNull('published_at')
                    ->groupBy('author_channel_id')
                    ->havingRaw('total_comments >= 2')
                    ->havingRaw('night_comments / total_comments > 0.5')
                    ->pluck('author_channel_id')
                    ->toArray();
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Night-time author IDs calculated', [
                'count' => count($nightTimeAuthors),
                'execution_time_ms' => $executionTime,
                'driver' => $driver
            ]);

            return $nightTimeAuthors;
        });
    }

    /**
     * SQLite-compatible version of getNightTimeAuthorIds
     * Uses PHP-level processing since SQLite doesn't support CONVERT_TZ
     */
    private function getNightTimeAuthorIdsSqlite(): array
    {
        // Get all comments grouped by author
        $commentsByAuthor = DB::table('comments')
            ->select('author_channel_id', 'published_at')
            ->whereNotNull('published_at')
            ->get()
            ->groupBy('author_channel_id');

        $nightTimeAuthors = [];

        foreach ($commentsByAuthor as $authorId => $comments) {
            $totalComments = $comments->count();

            // Need at least 2 comments
            if ($totalComments < 2) {
                continue;
            }

            $nightComments = 0;

            foreach ($comments as $comment) {
                // Convert to GMT+8 and check if hour is between 1-5
                $gmt8Time = Carbon::parse($comment->published_at)->setTimezone('Asia/Taipei');
                $hour = $gmt8Time->hour;

                if ($hour >= 1 && $hour <= 5) {
                    $nightComments++;
                }
            }

            // Check if >50% are night comments
            if ($nightComments / $totalComments > 0.5) {
                $nightTimeAuthors[] = $authorId;
            }
        }

        return $nightTimeAuthors;
    }

    /**
     * Return placeholder pattern (for aggressive and simplified Chinese)
     */
    private function placeholderPattern(string $type): array
    {
        return [
            'count' => 'X',
            'percentage' => 0
        ];
    }
}
