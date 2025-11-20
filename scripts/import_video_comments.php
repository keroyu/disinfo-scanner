<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\YoutubeApiClient;
use App\Models\Video;
use App\Models\Comment;
use App\Models\Author;
use Carbon\Carbon;

// Configuration
$videoId = 'hbTsNpCg5N4';
$fromDate = '2025-08-24 06:54:49'; // GMT+8
$toDate = '2025-09-05 05:14:52';   // GMT+8

echo "=== YouTube Comments Import Script ===\n";
echo "Video ID: {$videoId}\n";
echo "Date Range: {$fromDate} to {$toDate} (GMT+8)\n\n";

// Convert to Carbon objects for comparison
$fromCarbon = Carbon::parse($fromDate, 'Asia/Taipei');
$toCarbon = Carbon::parse($toDate, 'Asia/Taipei');

echo "From (UTC): " . $fromCarbon->copy()->setTimezone('UTC')->toDateTimeString() . "\n";
echo "To (UTC): " . $toCarbon->copy()->setTimezone('UTC')->toDateTimeString() . "\n\n";

// Get video from database
$video = Video::where('video_id', $videoId)->first();
if (!$video) {
    echo "Error: Video not found in database\n";
    exit(1);
}

echo "Video: {$video->title}\n";
echo "Current comment count in DB: {$video->actual_comment_count}\n\n";

// Initialize YouTube API client
$youtubeClient = new YoutubeApiClient();

echo "Fetching comments from YouTube API...\n";
echo "(Note: YouTube API doesn't support date filtering, so we'll fetch and filter locally)\n\n";

// Fetch comments with higher limit to get more data
// Since we already have 985 comments, let's try fetching 2000 to see if there are more
$allComments = $youtubeClient->getAllComments($videoId, 2000);

echo "Total comments fetched from API: " . count($allComments) . "\n\n";

// Filter comments by date range
$filteredComments = array_filter($allComments, function($comment) use ($fromCarbon, $toCarbon) {
    $commentDate = Carbon::parse($comment['published_at']);
    return $commentDate->between($fromCarbon, $toCarbon);
});

echo "Comments in specified date range: " . count($filteredComments) . "\n\n";

if (empty($filteredComments)) {
    echo "No comments found in the specified date range.\n";
    exit(0);
}

// Show sample of comments
echo "Sample comments:\n";
echo "----------------\n";
$sampleCount = min(5, count($filteredComments));
$sampleComments = array_slice($filteredComments, 0, $sampleCount);
foreach ($sampleComments as $comment) {
    $publishedAt = Carbon::parse($comment['published_at'])->setTimezone('Asia/Taipei');
    echo "- {$publishedAt->format('Y-m-d H:i:s')}: " . mb_substr($comment['content'], 0, 50) . "...\n";
}
echo "\n";

// Ask for confirmation
echo "Do you want to import these " . count($filteredComments) . " comments? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));

if (strtolower($line) !== 'yes' && strtolower($line) !== 'y') {
    echo "Import cancelled.\n";
    exit(0);
}

echo "\nStarting import...\n";

// Separate top-level comments and replies
$topLevelComments = [];
$replyComments = [];

foreach ($filteredComments as $comment) {
    if (empty($comment['parent_comment_id'])) {
        $topLevelComments[] = $comment;
    } else {
        $replyComments[] = $comment;
    }
}

echo "Top-level comments: " . count($topLevelComments) . "\n";
echo "Reply comments: " . count($replyComments) . "\n\n";

// Import comments
$imported = 0;
$skipped = 0;
$errors = 0;

// First, import all top-level comments
echo "Importing top-level comments...\n";
foreach ($topLevelComments as $commentData) {
    try {
        // Check if comment already exists
        $existingComment = Comment::where('comment_id', $commentData['comment_id'])->first();

        if ($existingComment) {
            $skipped++;
            continue;
        }

        // Create or get author
        Author::firstOrCreate(
            ['author_channel_id' => $commentData['author_channel_id']],
            ['name' => $commentData['author_channel_id']]
        );

        // Create comment
        Comment::create([
            'comment_id' => $commentData['comment_id'],
            'video_id' => $videoId,
            'author_channel_id' => $commentData['author_channel_id'],
            'text' => $commentData['content'],
            'like_count' => $commentData['like_count'],
            'published_at' => Carbon::parse($commentData['published_at']),
            'parent_comment_id' => null,
        ]);

        $imported++;

        if ($imported % 10 == 0) {
            echo ".";
        }

    } catch (\Exception $e) {
        $errors++;
        echo "\nError importing comment {$commentData['comment_id']}: " . $e->getMessage() . "\n";
    }
}

echo "\n\nImporting reply comments...\n";
// Then, import reply comments
foreach ($replyComments as $commentData) {
    try {
        // Check if comment already exists
        $existingComment = Comment::where('comment_id', $commentData['comment_id'])->first();

        if ($existingComment) {
            $skipped++;
            continue;
        }

        // Create or get author
        Author::firstOrCreate(
            ['author_channel_id' => $commentData['author_channel_id']],
            ['name' => $commentData['author_channel_id']]
        );

        // Create comment
        Comment::create([
            'comment_id' => $commentData['comment_id'],
            'video_id' => $videoId,
            'author_channel_id' => $commentData['author_channel_id'],
            'text' => $commentData['content'],
            'like_count' => $commentData['like_count'],
            'published_at' => Carbon::parse($commentData['published_at']),
            'parent_comment_id' => $commentData['parent_comment_id'],
        ]);

        $imported++;

        if ($imported % 10 == 0) {
            echo ".";
        }

    } catch (\Exception $e) {
        $errors++;
        echo "\nError importing reply comment {$commentData['comment_id']}: " . $e->getMessage() . "\n";
    }
}

echo "\n\n=== Import Complete ===\n";
echo "Imported: {$imported}\n";
echo "Skipped (already exists): {$skipped}\n";
echo "Errors: {$errors}\n";

// Update video comment_count (not actual_comment_count which is computed)
if ($imported > 0) {
    $totalComments = Comment::where('video_id', $videoId)->count();
    echo "\nTotal comments in database: {$totalComments}\n";
}

echo "\nDone!\n";
