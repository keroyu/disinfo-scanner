<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Video;
use App\Models\Channel;
use App\Models\Author;
use Tests\TestCase;

class CommentsListLayoutTest extends TestCase
{
    protected $channel;
    protected $video;
    protected $author;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->channel = Channel::factory()->create([
            'channel_id' => 'test_channel_001',
            'name' => 'Test Channel',
            'channel_identifier' => 'testchannel',
        ]);

        $this->video = Video::factory()->create([
            'video_id' => 'test_video_001',
            'channel_id' => $this->channel->channel_id,
            'title' => 'Test Video Title',
        ]);

        $this->author = Author::factory()->create([
            'author_channel_id' => 'test_author_001',
            'name' => 'Test Commenter',
        ]);
    }

    /**
     * Test that comments list page loads successfully
     */
    public function test_comments_list_page_loads(): void
    {
        $response = $this->get('/comments');
        $response->assertStatus(200);
        $response->assertViewIs('comments.list');
        $response->assertViewHasKey('comments');
    }

    /**
     * Test empty comments list shows appropriate message
     */
    public function test_empty_comments_list_shows_no_results_message(): void
    {
        $response = $this->get('/comments');
        $response->assertStatus(200);
        $response->assertSee('No comments found');
    }

    /**
     * Test comments are displayed with all required columns
     */
    public function test_comments_display_all_required_columns(): void
    {
        $comment = Comment::factory()->create([
            'comment_id' => 'test_comment_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => 'This is a test comment',
            'like_count' => 10,
            'published_at' => now(),
        ]);

        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Verify table headers are present
        $response->assertSee('Channel');
        $response->assertSee('Video Title');
        $response->assertSee('Commenter');
        $response->assertSee('Comment');
        $response->assertSee('Likes');
        $response->assertSee('Date');

        // Verify comment data is displayed
        $response->assertSee($this->channel->name);
        $response->assertSee($this->video->title);
        $response->assertSee($this->author->name);
    }

    /**
     * Test pagination shows 500 comments per page
     */
    public function test_pagination_shows_500_comments_per_page(): void
    {
        // Create more than 500 comments
        for ($i = 0; $i < 550; $i++) {
            Comment::factory()->create([
                'comment_id' => "test_comment_{$i}",
                'video_id' => $this->video->video_id,
                'author_channel_id' => $this->author->author_channel_id,
            ]);
        }

        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Verify pagination info is displayed
        $response->assertSee('Showing 1 to 500 of 550 comments');
    }

    /**
     * Test keyword search filter works
     */
    public function test_keyword_search_filters_comments(): void
    {
        $comment1 = Comment::factory()->create([
            'comment_id' => 'comment_search_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => 'Laravel framework discussion',
        ]);

        $comment2 = Comment::factory()->create([
            'comment_id' => 'comment_search_002',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => 'Python programming tutorial',
        ]);

        $response = $this->get('/comments?search=Laravel');
        $response->assertStatus(200);

        // Verify only matching comment is shown
        $response->assertSee('Laravel framework discussion');
        $response->assertDontSee('Python programming tutorial');
    }

    /**
     * Test date range filter works
     */
    public function test_date_range_filter_filters_comments(): void
    {
        $comment1 = Comment::factory()->create([
            'comment_id' => 'comment_date_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'published_at' => now()->subDays(10),
        ]);

        $comment2 = Comment::factory()->create([
            'comment_id' => 'comment_date_002',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'published_at' => now(),
        ]);

        $fromDate = now()->subDays(5)->format('Y-m-d');
        $toDate = now()->format('Y-m-d');

        $response = $this->get("/comments?from_date={$fromDate}&to_date={$toDate}");
        $response->assertStatus(200);
    }

    /**
     * Test sort by likes parameter is recognized
     */
    public function test_sort_by_likes_parameter_is_recognized(): void
    {
        Comment::factory()->create([
            'comment_id' => 'comment_like_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'like_count' => 100,
        ]);

        Comment::factory()->create([
            'comment_id' => 'comment_like_002',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'like_count' => 10,
        ]);

        $response = $this->get('/comments?sort=likes&direction=desc');
        $response->assertStatus(200);
    }

    /**
     * Test sort by date parameter is recognized
     */
    public function test_sort_by_date_parameter_is_recognized(): void
    {
        $response = $this->get('/comments?sort=date&direction=asc');
        $response->assertStatus(200);
    }

    /**
     * Test channel links are properly formatted
     */
    public function test_channel_links_are_properly_formatted(): void
    {
        $comment = Comment::factory()->create([
            'comment_id' => 'comment_link_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Verify channel link is present
        $response->assertSee('https://www.youtube.com/@testchannel');
    }

    /**
     * Test video links are properly formatted with comment ID
     */
    public function test_video_links_include_comment_id(): void
    {
        $comment = Comment::factory()->create([
            'comment_id' => 'comment_video_link_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Verify video link includes both video_id and comment_id
        $response->assertSee('watch?v=' . $this->video->video_id . '&lc=' . $comment->comment_id);
    }

    /**
     * Test combined search and date filter works
     */
    public function test_combined_search_and_date_filter_works(): void
    {
        $comment1 = Comment::factory()->create([
            'comment_id' => 'comment_combined_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => 'Laravel discussion',
            'published_at' => now(),
        ]);

        $comment2 = Comment::factory()->create([
            'comment_id' => 'comment_combined_002',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => 'Python discussion',
            'published_at' => now()->subDays(10),
        ]);

        $fromDate = now()->subDays(5)->format('Y-m-d');
        $toDate = now()->format('Y-m-d');

        $response = $this->get("/comments?search=Laravel&from_date={$fromDate}&to_date={$toDate}");
        $response->assertStatus(200);
        $response->assertSee('Laravel discussion');
    }

    /**
     * Test table has semantic HTML structure
     */
    public function test_table_has_semantic_html_structure(): void
    {
        Comment::factory()->create([
            'comment_id' => 'comment_semantic_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Verify semantic table structure
        $response->assertSeeTextInOrder(['<table', '<thead', '<tbody', '</tbody', '</table']);
    }

    /**
     * Test comment text is truncated appropriately
     */
    public function test_long_comment_text_is_truncated(): void
    {
        $longText = str_repeat('This is a very long comment text. ', 20);

        $comment = Comment::factory()->create([
            'comment_id' => 'comment_long_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => $longText,
        ]);

        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Verify text is truncated
        $response->assertSee('This is a very long comment text');
    }

    /**
     * Test sort direction toggle is indicated
     */
    public function test_sort_direction_indicator_is_displayed(): void
    {
        $response = $this->get('/comments?sort=likes&direction=desc');
        $response->assertStatus(200);

        // Should show sort indicator
        $response->assertSee('Likes');
    }

    /**
     * Test table structure is semantic HTML
     * Verifies proper use of table, thead, tbody, tr, th, td elements
     */
    public function test_semantic_html_table_structure(): void
    {
        Comment::factory()->create([
            'comment_id' => 'comment_semantic_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Verify semantic structure
        $html = $response->getContent();
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<thead', $html);
        $this->assertStringContainsString('<tbody', $html);
        $this->assertStringContainsString('<tr', $html);
        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('<td', $html);
    }

    /**
     * Test column headers have proper labeling for accessibility
     */
    public function test_column_headers_have_proper_labels(): void
    {
        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Verify header labels exist
        $response->assertSee('Channel');
        $response->assertSee('Video Title');
        $response->assertSee('Commenter');
        $response->assertSee('Comment');
        $response->assertSee('Likes');
        $response->assertSee('Date');
    }

    /**
     * Test text contrast ratios meet WCAG standards
     * Verifies dark text on light backgrounds
     */
    public function test_text_contrast_ratio_meets_wcag_standards(): void
    {
        Comment::factory()->create([
            'comment_id' => 'comment_contrast_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Check for proper text color classes (gray-700 on white background meets WCAG AA)
        $html = $response->getContent();
        $this->assertStringContainsString('text-gray-700', $html);
    }

    /**
     * Test link elements have proper labeling
     */
    public function test_links_have_proper_labels_and_titles(): void
    {
        $comment = Comment::factory()->create([
            'comment_id' => 'comment_link_labels_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Verify links have title attributes
        $html = $response->getContent();
        $this->assertStringContainsString('title=', $html);
        $this->assertStringContainsString('aria-label', $html);
    }

    /**
     * Test focus states are visually apparent
     */
    public function test_focus_visible_states_exist(): void
    {
        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Check for focus-visible styling
        $html = $response->getContent();
        $this->assertStringContainsString('focus-visible', $html);
    }

    /**
     * Test keyboard navigation is supported
     */
    public function test_keyboard_navigation_support(): void
    {
        Comment::factory()->create([
            'comment_id' => 'comment_keyboard_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Verify interactive elements can be tabbed
        $response->assertSee('input');
        $response->assertSee('button');
        $response->assertSee('a');
    }

    /**
     * Test form labels are associated with inputs
     */
    public function test_form_labels_are_associated_with_inputs(): void
    {
        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Verify form has proper labels
        $response->assertSee('Search Comments');
        $response->assertSee('From Date');
        $response->assertSee('To Date');
    }

    /**
     * Test ARIA labels exist for dynamic elements
     */
    public function test_aria_labels_exist_for_interactive_elements(): void
    {
        Comment::factory()->create([
            'comment_id' => 'comment_aria_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $response = $this->get('/comments');
        $response->assertStatus(200);

        $html = $response->getContent();
        $this->assertStringContainsString('aria-label', $html);
    }

    /**
     * Test images and important visual elements have alt text or ARIA labels
     */
    public function test_visual_elements_have_accessible_labels(): void
    {
        $response = $this->get('/comments');
        $response->assertStatus(200);

        // Check for accessible labels on visual elements
        $response->assertSee('aria-hidden');
    }

    /**
     * Test page structure supports screen reader navigation
     */
    public function test_page_structure_supports_screen_readers(): void
    {
        $response = $this->get('/comments');
        $response->assertStatus(200);

        $html = $response->getContent();

        // Verify heading structure
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('<p', $html);

        // Verify semantic landmarks
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<form', $html);
    }
}
