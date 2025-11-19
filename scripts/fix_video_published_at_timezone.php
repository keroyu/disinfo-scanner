#!/usr/bin/env php
<?php

/**
 * Fix video published_at timezone
 *
 * Problem: YouTube returns datetime with timezone (e.g., 2025-07-20T04:01:05-07:00)
 * but Laravel stored only the time value (04:01:05) as UTC, ignoring timezone offset.
 *
 * Solution: Re-scrape published_at from YouTube and store correctly as UTC.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Video;
use App\Services\UrtubeApiMetadataService;
use Illuminate\Support\Facades\DB;

$metadataService = new UrtubeApiMetadataService();

echo "開始修正影片發布時間時區...\n\n";

// Get all videos with published_at
$videos = Video::whereNotNull('published_at')->get();

echo "找到 {$videos->count()} 個影片\n";
echo "是否要繼續修正？(y/n): ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) !== 'y') {
    echo "已取消\n";
    exit(0);
}

$fixed = 0;
$failed = 0;
$skipped = 0;

foreach ($videos as $video) {
    echo "\n處理影片: {$video->video_id}\n";
    echo "  當前值: {$video->getRawOriginal('published_at')}\n";

    try {
        // Re-scrape from YouTube
        $metadata = $metadataService->scrapeMetadata($video->video_id);

        if (!$metadata['publishedAt']) {
            echo "  ⚠ 無法從 YouTube 取得發布時間，跳過\n";
            $skipped++;
            continue;
        }

        // Parse and convert to UTC
        $publishedAtUtc = \Carbon\Carbon::parse($metadata['publishedAt'])
            ->setTimezone('UTC')
            ->toDateTimeString();

        echo "  YouTube 返回: {$metadata['publishedAt']}\n";
        echo "  轉換為 UTC: {$publishedAtUtc}\n";

        // Update database
        DB::table('videos')
            ->where('video_id', $video->video_id)
            ->update(['published_at' => $publishedAtUtc]);

        echo "  ✓ 已更新\n";
        $fixed++;

        // Sleep to avoid rate limiting
        usleep(500000); // 0.5 second

    } catch (\Exception $e) {
        echo "  ✗ 錯誤: {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\n\n=== 修正完成 ===\n";
echo "成功: {$fixed}\n";
echo "失敗: {$failed}\n";
echo "跳過: {$skipped}\n";
echo "總計: {$videos->count()}\n";
