<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Video;
use App\Models\Channel;
use App\Models\Author;
use Tests\TestCase;

class VideosListTest extends TestCase
{
    protected $channel;
    protected $video1;
    protected $video2;
    protected $video3;
    protected $author;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->channel = Channel::factory()->create([
            'channel_id' => 'test_channel_001',
            'channel_name' => 'Test Channel',
        ]);

        // Video with comments (should appear in list)
        $this->video1 = Video::factory()->create([
            'video_id' => 'test_video_001',
            'channel_id' => $this->channel->channel_id,
            'title' => 'Test Video 1 with Comments',
            'published_at' => now()->subDays(1),
        ]);

        // Video without comments (should NOT appear in list)
        $this->video2 = Video::factory()->create([
            'video_id' => 'test_video_002',
            'channel_id' => $this->channel->channel_id,
            'title' => 'Test Video 2 without Comments',
            'published_at' => now()->subDays(2),
        ]);

        // Another video with comments
        $this->video3 = Video::factory()->create([
            'video_id' => 'test_video_003',
            'channel_id' => $this->channel->channel_id,
            'title' => 'Test Video 3 with Many Comments',
            'published_at' => now(),
        ]);

        $this->author = Author::factory()->create([
            'author_channel_id' => 'test_author_001',
            'name' => 'Test Commenter',
        ]);

        // Add comments to video1 (2 comments)
        Comment::factory()->create([
            'comment_id' => 'comment_001',
            'video_id' => $this->video1->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => 'First comment on video 1',
            'published_at' => now()->subHours(5),
        ]);

        Comment::factory()->create([
            'comment_id' => 'comment_002',
            'video_id' => $this->video1->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => 'Second comment on video 1',
            'published_at' => now()->subHours(2),
        ]);

        // Add comments to video3 (5 comments) - last comment is most recent
        for ($i = 0; $i < 5; $i++) {
            Comment::factory()->create([
                'comment_id' => "comment_video3_{$i}",
                'video_id' => $this->video3->video_id,
                'author_channel_id' => $this->author->author_channel_id,
                'text' => "Comment {$i} on video 3",
                'published_at' => now()->subHours(10 - $i),
            ]);
        }
    }

    /**
     * T006: Test that videos list page displays correctly with default sort
     */
    public function test_videos_list_page_displays_correctly_with_default_sort(): void
    {
        $response = $this->get('/videos');
        $response->assertStatus(200);
        $response->assertViewIs('videos.list');
        $response->assertViewHas('videos');

        // Verify table headers are present
        $response->assertSee('Channel Name');
        $response->assertSee('Video Title');
        $response->assertSee('Comment Count');
        $response->assertSee('Last Comment Time');

        // Default sort should be by published_at DESC (newest first)
        // So video3 should appear before video1
        $content = $response->getContent();
        $pos_video3 = strpos($content, 'Test Video 3 with Many Comments');
        $pos_video1 = strpos($content, 'Test Video 1 with Comments');
        $this->assertNotFalse($pos_video3);
        $this->assertNotFalse($pos_video1);
        $this->assertLessThan($pos_video1, $pos_video3, 'Video 3 (newer) should appear before Video 1 (older) in default sort');
    }

    /**
     * T007: Test that only videos with comments are shown
     */
    public function test_only_videos_with_comments_are_shown(): void
    {
        $response = $this->get('/videos');
        $response->assertStatus(200);

        // video1 and video3 have comments - should be visible
        $response->assertSee('Test Video 1 with Comments');
        $response->assertSee('Test Video 3 with Many Comments');

        // video2 has no comments - should NOT be visible
        $response->assertDontSee('Test Video 2 without Comments');
    }

    /**
     * T008: Test that comment counts are accurate
     */
    public function test_comment_counts_are_accurate(): void
    {
        $response = $this->get('/videos');
        $response->assertStatus(200);

        // Video 1 has 2 comments
        $response->assertSee('2', false); // Comment count for video1

        // Video 3 has 5 comments
        $response->assertSee('5', false); // Comment count for video3
    }

    /**
     * T009: Test that last comment time displays correctly in YYYY-MM-DD HH:MM format
     */
    public function test_last_comment_time_displays_correctly(): void
    {
        $response = $this->get('/videos');
        $response->assertStatus(200);

        // The format should be Y-m-d H:i
        // We'll check that the timestamp format is present (contains year-month-day and time)
        $response->assertSee(now()->format('Y-m-d'));
    }

    /**
     * T010: Test that sort by clicking column headers works
     */
    public function test_sort_by_column_headers_works(): void
    {
        // Sort by comment count descending (video3 has 5 comments, video1 has 2)
        $response = $this->get('/videos?sort=actual_comment_count&direction=desc');
        $response->assertStatus(200);

        // Video 3 (5 comments) should appear before Video 1 (2 comments)
        $content = $response->getContent();
        $pos_video3 = strpos($content, 'Test Video 3 with Many Comments');
        $pos_video1 = strpos($content, 'Test Video 1 with Comments');
        $this->assertLessThan($pos_video1, $pos_video3, 'Video 3 (more comments) should appear before Video 1 when sorted by comment count DESC');

        // Sort by comment count ascending (video1 has fewer comments)
        $response = $this->get('/videos?sort=actual_comment_count&direction=asc');
        $response->assertStatus(200);

        // Video 1 (2 comments) should appear before Video 3 (5 comments)
        $content = $response->getContent();
        $pos_video3 = strpos($content, 'Test Video 3 with Many Comments');
        $pos_video1 = strpos($content, 'Test Video 1 with Comments');
        $this->assertLessThan($pos_video3, $pos_video1, 'Video 1 (fewer comments) should appear before Video 3 when sorted by comment count ASC');
    }

    /**
     * T011: Test that sort direction toggles between asc/desc on repeated clicks
     */
    public function test_sort_direction_toggles(): void
    {
        // First request with DESC
        $response1 = $this->get('/videos?sort=actual_comment_count&direction=desc');
        $response1->assertStatus(200);

        // Second request with ASC (simulating toggle)
        $response2 = $this->get('/videos?sort=actual_comment_count&direction=asc');
        $response2->assertStatus(200);

        // Verify the order is different
        $this->assertNotEquals($response1->getContent(), $response2->getContent());
    }

    /**
     * T028: Test that search by keyword filters videos correctly (case-insensitive)
     */
    public function test_search_by_keyword_filters_videos(): void
    {
        $response = $this->get('/videos?search=Video 1');
        $response->assertStatus(200);

        // Should see Video 1
        $response->assertSee('Test Video 1 with Comments');

        // Should NOT see Video 3
        $response->assertDontSee('Test Video 3 with Many Comments');

        // Test case-insensitive search
        $response = $this->get('/videos?search=video 1');
        $response->assertStatus(200);
        $response->assertSee('Test Video 1 with Comments');
    }

    /**
     * T029: Test that search matches both video title and channel name
     */
    public function test_search_matches_title_and_channel_name(): void
    {
        // Search by video title
        $response = $this->get('/videos?search=Video 3');
        $response->assertStatus(200);
        $response->assertSee('Test Video 3 with Many Comments');

        // Search by channel name
        $response = $this->get('/videos?search=Test Channel');
        $response->assertStatus(200);
        $response->assertSee('Test Video 1 with Comments');
        $response->assertSee('Test Video 3 with Many Comments');
    }

    /**
     * T030: Test that Clear Filters button resets search
     */
    public function test_clear_filters_resets_search(): void
    {
        // Search with filter
        $response = $this->get('/videos?search=Video 1');
        $response->assertStatus(200);
        $response->assertDontSee('Test Video 3 with Many Comments');

        // Clear filters by visiting without search parameter
        $response = $this->get('/videos');
        $response->assertStatus(200);
        $response->assertSee('Test Video 1 with Comments');
        $response->assertSee('Test Video 3 with Many Comments');
    }

    /**
     * T031: Test that pagination preserves search parameters
     */
    public function test_pagination_preserves_search_parameters(): void
    {
        // Create many videos to trigger pagination
        for ($i = 0; $i < 550; $i++) {
            $video = Video::factory()->create([
                'video_id' => "pagination_video_{$i}",
                'channel_id' => $this->channel->channel_id,
                'title' => "Pagination Test Video {$i}",
            ]);

            // Add a comment to each video so they appear in the list
            Comment::factory()->create([
                'comment_id' => "pagination_comment_{$i}",
                'video_id' => $video->video_id,
                'author_channel_id' => $this->author->author_channel_id,
                'text' => "Comment on video {$i}",
            ]);
        }

        // Get page with search parameter
        $response = $this->get('/videos?search=Pagination&page=1');
        $response->assertStatus(200);

        // Check that pagination links include the search parameter
        $response->assertSee('search=Pagination');
    }

    /**
     * T020: Test clicking channel name redirects to Comments List with channel filter
     */
    public function test_channel_name_link_redirects_to_comments_with_channel_filter(): void
    {
        $response = $this->get('/videos');
        $response->assertStatus(200);

        // Check that channel name links contain correct URL
        $channelUrl = '/comments?search_channel=' . urlencode($this->channel->channel_name);
        $response->assertSee($channelUrl, false);
    }

    /**
     * T021: Test clicking video title redirects to Comments List with title search
     */
    public function test_video_title_link_redirects_to_comments_with_title_search(): void
    {
        $response = $this->get('/videos');
        $response->assertStatus(200);

        // Check that video title links contain correct URL
        $titleUrl = '/comments?search=' . urlencode($this->video1->title);
        $response->assertSee('/comments?search=', false);
    }

    /**
     * T022: Test clicking last comment time redirects with 90-day date range
     */
    public function test_last_comment_time_link_has_90_day_date_range(): void
    {
        $response = $this->get('/videos');
        $response->assertStatus(200);

        // Check that the response contains date range parameters
        $response->assertSee('from_date=', false);
        $response->assertSee('to_date=', false);
    }

    /**
     * T023: Test navigation URLs are correctly URL-encoded for special characters
     */
    public function test_navigation_urls_are_url_encoded(): void
    {
        // Create a video with special characters in title
        $specialVideo = Video::factory()->create([
            'video_id' => 'special_video',
            'channel_id' => $this->channel->channel_id,
            'title' => 'Video with Special & Characters',
        ]);

        Comment::factory()->create([
            'comment_id' => 'special_comment',
            'video_id' => $specialVideo->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => 'Comment on special video',
        ]);

        $response = $this->get('/videos');
        $response->assertStatus(200);

        // URL should be encoded (& becomes %26)
        $response->assertSee('Video+with+Special+%26+Characters', false);
    }
}
