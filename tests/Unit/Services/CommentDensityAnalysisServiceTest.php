<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CommentDensityAnalysisService;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CommentDensityAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CommentDensityAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommentDensityAnalysisService();
    }

    /** @test */
    public function it_aggregates_hourly_data_correctly()
    {
        $videoId = 'test_video_123';
        $start = Carbon::parse('2025-11-10 15:00:00', 'UTC');
        $end = Carbon::parse('2025-11-12 15:00:00', 'UTC');

        $result = $this->service->aggregateHourlyData($videoId, $start, $end);

        $this->assertIsArray($result);
        // Test will FAIL initially because method not implemented
    }

    /** @test */
    public function it_aggregates_daily_data_correctly()
    {
        $videoId = 'test_video_123';
        $start = Carbon::parse('2025-11-01 00:00:00', 'UTC');
        $end = Carbon::parse('2025-11-19 23:59:59', 'UTC');

        $result = $this->service->aggregateDailyData($videoId, $start, $end);

        $this->assertIsArray($result);
        // Test will FAIL initially because method not implemented
    }

    /** @test */
    public function it_fills_missing_buckets_for_hourly_data()
    {
        $sparseData = [
            ['time_bucket' => '2025-11-10 15:00:00', 'comment_count' => 10],
            ['time_bucket' => '2025-11-10 18:00:00', 'comment_count' => 5],
        ];

        $start = Carbon::parse('2025-11-10 15:00:00', 'Asia/Taipei');
        $end = Carbon::parse('2025-11-10 18:00:00', 'Asia/Taipei');

        $result = $this->service->fillMissingBuckets($sparseData, $start, $end, 'hourly');

        // Should have 4 buckets: 15:00, 16:00, 17:00, 18:00
        $this->assertCount(4, $result);
        $this->assertEquals(10, $result[0]['comment_count']);
        $this->assertEquals(0, $result[1]['comment_count']); // 16:00 missing -> filled with 0
        $this->assertEquals(0, $result[2]['comment_count']); // 17:00 missing -> filled with 0
        $this->assertEquals(5, $result[3]['comment_count']);
    }

    /** @test */
    public function it_fills_missing_buckets_for_daily_data()
    {
        $sparseData = [
            ['time_bucket' => '2025-11-10', 'comment_count' => 100],
            ['time_bucket' => '2025-11-13', 'comment_count' => 50],
        ];

        $start = Carbon::parse('2025-11-10', 'Asia/Taipei');
        $end = Carbon::parse('2025-11-13', 'Asia/Taipei');

        $result = $this->service->fillMissingBuckets($sparseData, $start, $end, 'daily');

        // Should have 4 buckets: 11-10, 11-11, 11-12, 11-13
        $this->assertCount(4, $result);
        $this->assertEquals(100, $result[0]['comment_count']);
        $this->assertEquals(0, $result[1]['comment_count']); // 11-11 missing
        $this->assertEquals(0, $result[2]['comment_count']); // 11-12 missing
        $this->assertEquals(50, $result[3]['comment_count']);
    }

    /** @test */
    public function it_converts_to_data_points_with_gmt8_format()
    {
        $denseBuckets = [
            ['time_bucket' => '2025-11-10 15:00:00', 'comment_count' => 10],
            ['time_bucket' => '2025-11-10 16:00:00', 'comment_count' => 0],
        ];

        $result = $this->service->convertToDataPoints($denseBuckets, 'hourly');

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('timestamp', $result[0]);
        $this->assertArrayHasKey('display_time', $result[0]);
        $this->assertArrayHasKey('count', $result[0]);
        $this->assertArrayHasKey('bucket_size', $result[0]);

        // Check GMT+8 label
        $this->assertStringContainsString('(GMT+8)', $result[0]['display_time']);
        $this->assertEquals('hour', $result[0]['bucket_size']);
        $this->assertEquals(10, $result[0]['count']);
    }

    /** @test */
    public function it_orchestrates_complete_data_retrieval()
    {
        $videoId = 'test_video_123';
        $videoPublishedAt = Carbon::parse('2025-11-10 15:00:00', 'UTC');

        $result = $this->service->getCommentDensityData($videoId, $videoPublishedAt);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('hourly_data', $result);
        $this->assertArrayHasKey('daily_data', $result);
        $this->assertArrayHasKey('metadata', $result);

        // Validate hourly_data structure
        $this->assertArrayHasKey('range', $result['hourly_data']);
        $this->assertArrayHasKey('data', $result['hourly_data']);

        // Validate daily_data structure
        $this->assertArrayHasKey('range', $result['daily_data']);
        $this->assertArrayHasKey('data', $result['daily_data']);

        // Validate metadata
        $this->assertArrayHasKey('query_time_ms', $result['metadata']);
        $this->assertArrayHasKey('trace_id', $result['metadata']);
    }
}
