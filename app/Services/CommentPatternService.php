<?php

namespace App\Services;

use App\Models\Comment;
use App\ValueObjects\TimeRange;
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
     * @param string|null $timePointsIso Comma-separated ISO timestamps in GMT+8 (optional)
     * @return array
     */
    public function getPatternStatistics(string $videoId, ?string $timePointsIso = null): array
    {
        // If time filtering is active, don't use cache
        if ($timePointsIso !== null && $timePointsIso !== '') {
            return $this->calculateStatistics($videoId, $timePointsIso);
        }

        // Use cache for non-filtered statistics
        $cacheKey = "video:{$videoId}:pattern_statistics";

        return Cache::remember($cacheKey, 300, function () use ($videoId) {
            return $this->calculateStatistics($videoId, null);
        });
    }

    /**
     * Calculate pattern statistics (with or without time filtering)
     *
     * @param string $videoId
     * @param string|null $timePointsIso
     * @return array
     */
    private function calculateStatistics(string $videoId, ?string $timePointsIso = null): array
    {
        $startTime = microtime(true);

        // Build base query
        $query = Comment::where('video_id', $videoId);

        // Apply time range filter if provided
        $timeRanges = [];
        if ($timePointsIso !== null && $timePointsIso !== '') {
            try {
                $timeRanges = TimeRange::createMultiple($timePointsIso);
                $query->byTimeRanges($timeRanges);
            } catch (\InvalidArgumentException $e) {
                Log::warning('Invalid time points in statistics', [
                    'video_id' => $videoId,
                    'time_points_iso' => $timePointsIso,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Get total unique commenters (filtered by time if applicable)
        $totalUniqueCommenters = (clone $query)
            ->distinct('author_channel_id')
            ->count('author_channel_id');

        // Get total comments count (filtered by time if applicable)
        $totalCommentsCount = (clone $query)->count();

        // Calculate each pattern
        $allComments = $this->calculateAllCommentsPattern($videoId, $totalUniqueCommenters, $totalCommentsCount);
        $topLikedComments = $this->calculateTopLikedPattern($videoId, $totalUniqueCommenters);
        $repeatCommenters = $this->calculateRepeatCommenters($videoId, $totalUniqueCommenters, $totalCommentsCount, $timeRanges);
        $nightTimeCommenters = $this->calculateNightTimeCommenters($videoId, $totalUniqueCommenters, $timeRanges);
        $aggressiveCommenters = $this->placeholderPattern('aggressive');
        $simplifiedChineseCommenters = $this->placeholderPattern('simplified_chinese');

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Pattern statistics calculated', [
            'video_id' => $videoId,
            'time_filtered' => !empty($timeRanges),
            'time_points_count' => count($timeRanges),
            'execution_time_ms' => $executionTime,
            'cache_hit' => false
        ]);

        return [
            'all' => $allComments,
            'top_liked' => $topLikedComments,
            'repeat' => $repeatCommenters,
            'night_time' => $nightTimeCommenters,
            'aggressive' => $aggressiveCommenters,
            'simplified_chinese' => $simplifiedChineseCommenters
        ];
    }

    /**
     * Get comments by pattern with pagination
     *
     * @param string $videoId
     * @param string $pattern
     * @param int $offset
     * @param int $limit
     * @param string|null $timePointsIso Comma-separated ISO timestamps in GMT+8 (optional)
     * @return array
     */
    public function getCommentsByPattern(
        string $videoId,
        string $pattern,
        int $offset = 0,
        int $limit = 100,
        ?string $timePointsIso = null
    ): array {
        $startTime = microtime(true);

        $query = Comment::where('video_id', $videoId);

        // Apply time range filter if provided
        $timeRanges = [];
        if ($timePointsIso !== null && $timePointsIso !== '') {
            try {
                $timeRanges = TimeRange::createMultiple($timePointsIso);
                $query->byTimeRanges($timeRanges);
            } catch (\InvalidArgumentException $e) {
                Log::warning('Invalid time points provided', [
                    'video_id' => $videoId,
                    'time_points_iso' => $timePointsIso,
                    'error' => $e->getMessage()
                ]);
                // Continue without time filtering if invalid
            }
        }

        // Apply pattern filter
        switch ($pattern) {
            case 'all':
                // No additional filtering, sort by published_at
                $query->orderBy('published_at', 'DESC');
                break;

            case 'top_liked':
                // Sort by like_count descending, then by published_at
                $query->orderBy('like_count', 'DESC')
                      ->orderBy('published_at', 'DESC');
                break;

            case 'repeat':
                $repeatAuthorIds = $this->getRepeatAuthorIds($videoId, $timeRanges);
                $query->whereIn('author_channel_id', $repeatAuthorIds)
                      ->orderBy('published_at', 'DESC');
                break;

            case 'night_time':
                $nightTimeAuthorIds = $this->getNightTimeAuthorIds();
                $query->whereIn('author_channel_id', $nightTimeAuthorIds)
                      ->where('video_id', $videoId)
                      ->orderBy('published_at', 'DESC');
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
                $query->orderBy('published_at', 'DESC');
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
            'time_points_count' => count($timeRanges),
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
    private function calculateAllCommentsPattern(string $videoId, int $totalUniqueCommenters, int $totalCommentsCount): array
    {
        return [
            'count' => $totalUniqueCommenters,
            'percentage' => 100,
            'total_comments' => $totalCommentsCount
        ];
    }

    /**
     * Calculate "top liked" pattern (all comments, but sorted by like_count)
     */
    private function calculateTopLikedPattern(string $videoId, int $totalUniqueCommenters): array
    {
        return [
            'count' => $totalUniqueCommenters,
            'percentage' => 100
        ];
    }

    /**
     * Calculate repeat commenters (2+ comments on same video)
     *
     * @param string $videoId
     * @param int $totalUniqueCommenters
     * @param int $totalCommentsCount
     * @param array $timeRanges Array of TimeRange objects (optional)
     */
    private function calculateRepeatCommenters(string $videoId, int $totalUniqueCommenters, int $totalCommentsCount, array $timeRanges = []): array
    {
        $query = Comment::where('video_id', $videoId);

        // Apply time filtering if provided
        if (!empty($timeRanges)) {
            $query->byTimeRanges($timeRanges);
        }

        // Get repeat commenter IDs (those with 2+ comments)
        $repeatCommenterIds = (clone $query)
            ->select('author_channel_id')
            ->groupBy('author_channel_id')
            ->havingRaw('COUNT(*) >= 2')
            ->pluck('author_channel_id')
            ->toArray();

        $repeatCommentersCount = count($repeatCommenterIds);

        // Count total comments made by repeat commenters
        $repeatCommentsCount = 0;
        if ($repeatCommentersCount > 0) {
            $repeatCommentsQuery = Comment::where('video_id', $videoId)
                ->whereIn('author_channel_id', $repeatCommenterIds);

            // Apply time filtering if provided
            if (!empty($timeRanges)) {
                $repeatCommentsQuery->byTimeRanges($timeRanges);
            }

            $repeatCommentsCount = $repeatCommentsQuery->count();
        }

        $percentage = $totalUniqueCommenters > 0
            ? round(($repeatCommentersCount / $totalUniqueCommenters) * 100)
            : 0;

        return [
            'count' => $repeatCommentersCount,
            'percentage' => $percentage,
            'total_comments' => $repeatCommentsCount
        ];
    }

    /**
     * Calculate night-time high-frequency commenters (>50% comments during 01:00-05:59 GMT+8)
     *
     * @param string $videoId
     * @param int $totalUniqueCommenters
     * @param array $timeRanges Array of TimeRange objects (optional)
     */
    private function calculateNightTimeCommenters(string $videoId, int $totalUniqueCommenters, array $timeRanges = []): array
    {
        // Get commenters on this video who have >50% night-time comments across ALL channels
        $nightTimeAuthorIds = $this->getNightTimeAuthorIds();

        // Build query for this video
        $query = Comment::where('video_id', $videoId)
            ->whereIn('author_channel_id', $nightTimeAuthorIds);

        // Apply time filtering if provided
        if (!empty($timeRanges)) {
            $query->byTimeRanges($timeRanges);
        }

        // Count how many of these commenters are in the filtered time periods
        $nightTimeCommentersCount = $query
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
     *
     * @param string $videoId
     * @param array $timeRanges Array of TimeRange objects (optional)
     */
    private function getRepeatAuthorIds(string $videoId, array $timeRanges = []): array
    {
        $query = Comment::where('video_id', $videoId);

        // Apply time filtering if provided
        if (!empty($timeRanges)) {
            $query->byTimeRanges($timeRanges);
        }

        return $query
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
