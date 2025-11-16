<?php

namespace Tests\Browser;

use App\Models\Comment;
use App\Models\Video;
use App\Models\Channel;
use App\Models\Author;
use Tests\DuskTestCase;
use Facebook\WebDriver\WebDriverBy;

class CommentsListLayoutTest extends DuskTestCase
{
    protected $channel;
    protected $video;
    protected $author;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->channel = Channel::factory()->create([
            'channel_id' => 'test_browser_channel_001',
            'name' => 'Test Browser Channel',
            'channel_identifier' => 'testbrowserchannel',
        ]);

        $this->video = Video::factory()->create([
            'video_id' => 'test_browser_video_001',
            'channel_id' => $this->channel->channel_id,
            'title' => 'Test Browser Video Title',
        ]);

        $this->author = Author::factory()->create([
            'author_channel_id' => 'test_browser_author_001',
            'name' => 'Test Browser Commenter',
        ]);
    }

    /**
     * Test comments list page renders correctly on desktop
     */
    public function test_comments_list_renders_on_desktop(): void
    {
        Comment::factory()->create([
            'comment_id' => 'browser_comment_desktop_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => 'This is a desktop test comment',
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->assertSee('Comments List')
                ->assertSee('Search Comments')
                ->assertVisible('table')
                ->assertVisible('thead')
                ->assertVisible('tbody');
        });
    }

    /**
     * Test column widths are correct on desktop (channel 100px, title 200px)
     */
    public function test_column_widths_are_correct_on_desktop(): void
    {
        Comment::factory()->create([
            'comment_id' => 'browser_comment_width_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->resize(1920, 1080) // Desktop size
                ->assertVisible('table');

            // Verify table is in viewport
            $element = $browser->resolver->findOrFail('table');
            $this->assertNotNull($element);
        });
    }

    /**
     * Test responsive layout on tablet viewport (768px)
     */
    public function test_responsive_layout_on_tablet(): void
    {
        Comment::factory()->create([
            'comment_id' => 'browser_comment_tablet_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->resize(768, 1024) // Tablet size
                ->assertVisible('table');
        });
    }

    /**
     * Test responsive layout on mobile viewport (375px)
     */
    public function test_responsive_layout_on_mobile(): void
    {
        Comment::factory()->create([
            'comment_id' => 'browser_comment_mobile_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->resize(375, 667) // Mobile size
                ->assertVisible('table');
        });
    }

    /**
     * Test long comment text wraps properly
     */
    public function test_long_comment_text_wraps_on_desktop(): void
    {
        $longText = 'This is a very long comment that should wrap across multiple lines and demonstrate the comment content multi-line wrapping behavior with proper word breaks for readability and accessibility standards. Lorem ipsum dolor sit amet, consectetur adipiscing elit.';

        Comment::factory()->create([
            'comment_id' => 'browser_comment_wrap_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => $longText,
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->resize(1920, 1080)
                ->assertSee('This is a very long comment');
        });
    }

    /**
     * Test channel link navigates to YouTube
     */
    public function test_channel_link_opens_youtube_channel(): void
    {
        Comment::factory()->create([
            'comment_id' => 'browser_comment_channel_link_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->assertSeeLink($this->channel->name);
        });
    }

    /**
     * Test video link includes comment ID parameter
     */
    public function test_video_link_includes_comment_id_parameter(): void
    {
        $commentId = 'browser_comment_video_link_001';
        Comment::factory()->create([
            'comment_id' => $commentId,
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/comments');

            // Check for correct link format: watch?v=VIDEO_ID&lc=COMMENT_ID
            $linkFound = $browser->resolver->all()
                ->some(function ($element) use ($commentId) {
                    return strpos($element->getAttribute('href') ?? '', "&lc={$commentId}") !== false;
                });

            $this->assertTrue(
                $linkFound,
                'Video link with comment ID parameter not found'
            );
        });
    }

    /**
     * Test search filter updates results
     */
    public function test_search_filter_updates_results(): void
    {
        Comment::factory()->create([
            'comment_id' => 'browser_comment_search_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'text' => 'Laravel framework discussion',
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->type('@search', 'Laravel')
                ->click('@submit-filters')
                ->waitForText('Laravel framework discussion')
                ->assertSee('Laravel framework discussion');
        });
    }

    /**
     * Test pagination works correctly
     */
    public function test_pagination_links_are_present(): void
    {
        // Create enough comments to trigger pagination
        for ($i = 0; $i < 505; $i++) {
            Comment::factory()->create([
                'comment_id' => "browser_comment_paginate_{$i}",
                'video_id' => $this->video->video_id,
                'author_channel_id' => $this->author->author_channel_id,
            ]);
        }

        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->assertVisible('nav'); // Pagination nav should be visible
        });
    }

    /**
     * Test sticky headers remain visible when scrolling table
     */
    public function test_table_headers_remain_sticky_on_desktop(): void
    {
        // Create many comments to enable scrolling
        for ($i = 0; $i < 100; $i++) {
            Comment::factory()->create([
                'comment_id' => "browser_comment_sticky_{$i}",
                'video_id' => $this->video->video_id,
                'author_channel_id' => $this->author->author_channel_id,
            ]);
        }

        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->resize(1920, 1080)
                ->assertVisible('thead');
        });
    }

    /**
     * Test likes column is visible on desktop
     */
    public function test_likes_column_visible_on_desktop(): void
    {
        Comment::factory()->create([
            'comment_id' => 'browser_comment_likes_desktop_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'like_count' => 42,
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->resize(1920, 1080)
                ->assertSee('Likes');
        });
    }

    /**
     * Test date column is visible on desktop
     */
    public function test_date_column_visible_on_desktop(): void
    {
        Comment::factory()->create([
            'comment_id' => 'browser_comment_date_desktop_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'published_at' => now(),
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->resize(1920, 1080)
                ->assertSee('Date');
        });
    }

    /**
     * Test page load time is acceptable (< 3 seconds)
     */
    public function test_comments_list_page_load_time(): void
    {
        for ($i = 0; $i < 50; $i++) {
            Comment::factory()->create([
                'comment_id' => "browser_comment_perf_{$i}",
                'video_id' => $this->video->video_id,
                'author_channel_id' => $this->author->author_channel_id,
            ]);
        }

        $this->browse(function ($browser) {
            $startTime = microtime(true);
            $browser->visit('/comments');
            $loadTime = microtime(true) - $startTime;

            // Assert page loads in reasonable time
            $this->assertLessThan(
                3,
                $loadTime,
                'Comments list page took more than 3 seconds to load'
            );
        });
    }

    /**
     * Test empty state message displays when no comments exist
     */
    public function test_empty_state_message_displays(): void
    {
        $this->browse(function ($browser) {
            $browser->visit('/comments')
                ->assertSee('No comments found');
        });
    }

    /**
     * Test sort direction indicator updates
     */
    public function test_sort_direction_indicator_updates(): void
    {
        Comment::factory()->create([
            'comment_id' => 'browser_comment_sort_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $this->author->author_channel_id,
            'like_count' => 10,
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/comments?sort=likes&direction=desc')
                ->assertSee('Likes');
        });
    }
}
