<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommentDensityAnalysisService
{
    /**
     * Get complete comment density data (dual-dataset approach)
     * Returns both hourly and daily datasets for client-side filtering
     */
    public function getCommentDensityData(string $videoId, Carbon $videoPublishedAt): array
    {
        $startTime = microtime(true);
        $traceId = Str::uuid()->toString();

        Log::info('Comment density aggregation started', [
            'trace_id' => $traceId,
            'video_id' => $videoId,
            'video_published_at' => $videoPublishedAt->toDateTimeString()
        ]);

        try {
            // Calculate time ranges
            $hourlyStart = $videoPublishedAt->copy();
            $hourlyEnd = min($videoPublishedAt->copy()->addDays(14), Carbon::now('UTC'));

            $dailyStart = $videoPublishedAt->copy()->startOfDay();
            $dailyEnd = Carbon::now('UTC');

            // Query 1: Hourly data (first 14 days)
            $hourlyResults = $this->aggregateHourlyData($videoId, $hourlyStart, $hourlyEnd);

            // Query 2: Daily data (publication to current)
            $dailyResults = $this->aggregateDailyData($videoId, $dailyStart, $dailyEnd);

            // Fill missing buckets
            $hourlyStartGMT8 = $hourlyStart->copy()->setTimezone('Asia/Taipei');
            $hourlyEndGMT8 = $hourlyEnd->copy()->setTimezone('Asia/Taipei');
            $dailyStartGMT8 = $dailyStart->copy()->setTimezone('Asia/Taipei');
            $dailyEndGMT8 = $dailyEnd->copy()->setTimezone('Asia/Taipei');

            $denseHourlyBuckets = $this->fillMissingBuckets($hourlyResults, $hourlyStartGMT8, $hourlyEndGMT8, 'hourly');
            $denseDailyBuckets = $this->fillMissingBuckets($dailyResults, $dailyStartGMT8, $dailyEndGMT8, 'daily');

            // Convert to data points with GMT+8
            $hourlyDataPoints = $this->convertToDataPoints($denseHourlyBuckets, 'hourly');
            $dailyDataPoints = $this->convertToDataPoints($denseDailyBuckets, 'daily');

            $queryTime = round((microtime(true) - $startTime) * 1000);

            Log::info('Comment density aggregation completed', [
                'trace_id' => $traceId,
                'query_time_ms' => $queryTime,
                'hourly_data_points' => count($hourlyDataPoints),
                'daily_data_points' => count($dailyDataPoints)
            ]);

            return [
                'hourly_data' => [
                    'range' => [
                        'start' => $hourlyStartGMT8->toIso8601String(),
                        'end' => $hourlyEndGMT8->toIso8601String(),
                        'total_hours' => $hourlyStartGMT8->diffInHours($hourlyEndGMT8)
                    ],
                    'data' => $hourlyDataPoints
                ],
                'daily_data' => [
                    'range' => [
                        'start' => $dailyStartGMT8->toIso8601String(),
                        'end' => $dailyEndGMT8->toIso8601String(),
                        'total_days' => $dailyStartGMT8->diffInDays($dailyEndGMT8) + 1
                    ],
                    'data' => $dailyDataPoints
                ],
                'metadata' => [
                    'query_time_ms' => $queryTime,
                    'hourly_data_points' => count($hourlyDataPoints),
                    'daily_data_points' => count($dailyDataPoints),
                    'trace_id' => $traceId
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Comment density query failed', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
                'video_id' => $videoId
            ]);

            throw $e;
        }
    }

    /**
     * Aggregate hourly comment data (first 14 days after publication)
     */
    public function aggregateHourlyData(string $videoId, Carbon $start, Carbon $end): array
    {
        $results = DB::table('comments')
            ->select(DB::raw("
                DATE_FORMAT(
                    CONVERT_TZ(published_at, '+00:00', '+08:00'),
                    '%Y-%m-%d %H:00:00'
                ) as time_bucket,
                COUNT(*) as comment_count
            "))
            ->where('video_id', $videoId)
            ->where('published_at', '>=', $start->toDateTimeString())
            ->where('published_at', '<=', $end->toDateTimeString())
            ->groupBy('time_bucket')
            ->orderBy('time_bucket', 'ASC')
            ->get()
            ->map(fn($row) => [
                'time_bucket' => $row->time_bucket,
                'comment_count' => $row->comment_count
            ])
            ->toArray();

        return $results;
    }

    /**
     * Aggregate daily comment data (publication to current date)
     */
    public function aggregateDailyData(string $videoId, Carbon $start, Carbon $end): array
    {
        $results = DB::table('comments')
            ->select(DB::raw("
                DATE(
                    CONVERT_TZ(published_at, '+00:00', '+08:00')
                ) as time_bucket,
                COUNT(*) as comment_count
            "))
            ->where('video_id', $videoId)
            ->where('published_at', '>=', $start->toDateTimeString())
            ->where('published_at', '<=', $end->toDateTimeString())
            ->groupBy('time_bucket')
            ->orderBy('time_bucket', 'ASC')
            ->get()
            ->map(fn($row) => [
                'time_bucket' => $row->time_bucket,
                'comment_count' => $row->comment_count
            ])
            ->toArray();

        return $results;
    }

    /**
     * Fill missing time buckets with zero counts (sparse -> dense)
     */
    public function fillMissingBuckets(array $sparseData, Carbon $start, Carbon $end, string $granularity): array
    {
        $denseBuckets = [];
        $existingBuckets = collect($sparseData)->keyBy('time_bucket');

        $current = $start->copy();
        $increment = ($granularity === 'hourly') ? 'addHour' : 'addDay';
        $format = ($granularity === 'hourly') ? 'Y-m-d H:00:00' : 'Y-m-d';

        while ($current->lessThanOrEqualTo($end)) {
            $bucketKey = $current->format($format);

            if ($existingBuckets->has($bucketKey)) {
                $denseBuckets[] = $existingBuckets[$bucketKey];
            } else {
                $denseBuckets[] = [
                    'time_bucket' => $bucketKey,
                    'comment_count' => 0
                ];
            }

            $current->$increment();
        }

        return $denseBuckets;
    }

    /**
     * Convert dense buckets to data points with GMT+8 timestamps
     */
    public function convertToDataPoints(array $denseBuckets, string $granularity): array
    {
        return array_map(function($bucket) use ($granularity) {
            $taipeiTime = Carbon::parse($bucket['time_bucket'], 'Asia/Taipei');

            return [
                'timestamp' => $taipeiTime->toIso8601String(),
                'display_time' => $taipeiTime->format('Y-m-d H:i') . ' (GMT+8)',
                'count' => (int) $bucket['comment_count'],
                'bucket_size' => $granularity === 'hourly' ? 'hour' : 'day'
            ];
        }, $denseBuckets);
    }

    /**
     * Get comments pattern data by time of day (Taipei timezone)
     * Daytime: 06:00-17:59
     * Night: 18:00-00:59
     * Late Night: 01:00-05:59
     */
    public function getCommentsPattern(string $videoId): array
    {
        $results = DB::table('comments')
            ->select(DB::raw("
                CASE
                    WHEN HOUR(CONVERT_TZ(published_at, '+00:00', '+08:00')) BETWEEN 6 AND 17 THEN 'daytime'
                    WHEN HOUR(CONVERT_TZ(published_at, '+00:00', '+08:00')) BETWEEN 18 AND 23 THEN 'night'
                    WHEN HOUR(CONVERT_TZ(published_at, '+00:00', '+08:00')) = 0 THEN 'night'
                    ELSE 'late_night'
                END as period,
                COUNT(*) as count
            "))
            ->where('video_id', $videoId)
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        // Initialize counts
        $daytime = (int) ($results->get('daytime')->count ?? 0);
        $night = (int) ($results->get('night')->count ?? 0);
        $lateNight = (int) ($results->get('late_night')->count ?? 0);

        $total = $daytime + $night + $lateNight;

        // Calculate percentages (rounded to 1 decimal place)
        $daytimePercentage = $total > 0 ? round(($daytime / $total) * 100, 1) : 0;
        $nightPercentage = $total > 0 ? round(($night / $total) * 100, 1) : 0;
        $lateNightPercentage = $total > 0 ? round(($lateNight / $total) * 100, 1) : 0;

        // Format ratio as percentages (e.g., "30.0% : 45.0% : 25.0%")
        $ratio = sprintf('%s%% : %s%% : %s%%', $daytimePercentage, $nightPercentage, $lateNightPercentage);

        return [
            'daytime' => [
                'count' => $daytime,
                'percentage' => $daytimePercentage,
                'hours' => '06:00-17:59'
            ],
            'night' => [
                'count' => $night,
                'percentage' => $nightPercentage,
                'hours' => '18:00-00:59'
            ],
            'late_night' => [
                'count' => $lateNight,
                'percentage' => $lateNightPercentage,
                'hours' => '01:00-05:59'
            ],
            'ratio' => $ratio,
            'total' => $total
        ];
    }
}
