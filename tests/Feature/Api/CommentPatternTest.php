<?php

namespace Tests\Feature\Api;

use App\Models\Comment;
use App\Models\Video;
use App\Models\Author;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class CommentPatternTest extends TestCase
{
    use RefreshDatabase;

    protected $video;
    protected $channel;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test channel
        $this->channel = Channel::create([
            'channel_id' => 'test_channel_001',
            'channel_name' => 'Test Channel',
            'url' => 'https://youtube.com/channel/test_channel_001'
        ]);

        // Create test video
        $this->video = Video::create([
            'video_id' => 'test_video_001',
            'title' => 'Test Video',
            'channel_id' => $this->channel->channel_id,
            'published_at' => Carbon::now(),
            'tags' => []
        ]);
    }

    /** @test */
    public function test_pattern_statistics_returns_correct_structure()
    {
        // Create some test comments
        $this->createTestComments();

        $response = $this->getJson("/api/videos/{$this->video->video_id}/pattern-statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'video_id',
                'patterns' => [
                    'all' => ['count', 'percentage'],
                    'top_liked' => ['count', 'percentage'],
                    'repeat' => ['count', 'percentage'],
                    'night_time' => ['count', 'percentage'],
                    'aggressive' => ['count', 'percentage'],
                    'simplified_chinese' => ['count', 'percentage']
                ]
            ]);
    }

    /** @test */
    public function test_paginated_comments_returns_correct_format()
    {
        $this->createTestComments();

        $response = $this->getJson("/api/videos/{$this->video->video_id}/comments?pattern=all&offset=0&limit=10");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'video_id',
                'pattern',
                'offset',
                'limit',
                'comments' => [
                    '*' => [
                        'comment_id',
                        'author_channel_id',
                        'author_name',
                        'text',
                        'like_count',
                        'published_at'
                    ]
                ],
                'has_more',
                'total'
            ]);
    }

    /** @test */
    public function test_repeat_commenters_count_accurate()
    {
        // Create 2 authors with repeat comments
        $author1 = Author::create(['author_channel_id' => 'author_repeat_1', 'name' => 'Repeat Author 1']);
        $author2 = Author::create(['author_channel_id' => 'author_repeat_2', 'name' => 'Repeat Author 2']);
        $author3 = Author::create(['author_channel_id' => 'author_single_1', 'name' => 'Single Author']);

        // Author 1: 2 comments
        Comment::create([
            'comment_id' => 'comment_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author1->author_channel_id,
            'text' => 'First comment',
            'published_at' => Carbon::now()
        ]);
        Comment::create([
            'comment_id' => 'comment_002',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author1->author_channel_id,
            'text' => 'Second comment',
            'published_at' => Carbon::now()
        ]);

        // Author 2: 3 comments
        Comment::create([
            'comment_id' => 'comment_003',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author2->author_channel_id,
            'text' => 'First comment',
            'published_at' => Carbon::now()
        ]);
        Comment::create([
            'comment_id' => 'comment_004',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author2->author_channel_id,
            'text' => 'Second comment',
            'published_at' => Carbon::now()
        ]);
        Comment::create([
            'comment_id' => 'comment_005',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author2->author_channel_id,
            'text' => 'Third comment',
            'published_at' => Carbon::now()
        ]);

        // Author 3: 1 comment (not repeat)
        Comment::create([
            'comment_id' => 'comment_006',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author3->author_channel_id,
            'text' => 'Single comment',
            'published_at' => Carbon::now()
        ]);

        $response = $this->getJson("/api/videos/{$this->video->video_id}/pattern-statistics");

        $response->assertStatus(200);

        $data = $response->json();

        // Should have 2 repeat commenters out of 3 total
        $this->assertEquals(2, $data['patterns']['repeat']['count']);
        $this->assertEquals(67, $data['patterns']['repeat']['percentage']); // 2/3 = 66.67 rounded to 67
    }

    /** @test */
    public function test_repeat_filter_shows_only_repeat_comments()
    {
        // Create repeat and single commenters
        $author1 = Author::create(['author_channel_id' => 'author_repeat_1', 'name' => 'Repeat Author']);
        $author2 = Author::create(['author_channel_id' => 'author_single_1', 'name' => 'Single Author']);

        Comment::create([
            'comment_id' => 'comment_001',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author1->author_channel_id,
            'text' => 'First comment',
            'published_at' => Carbon::now()
        ]);
        Comment::create([
            'comment_id' => 'comment_002',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author1->author_channel_id,
            'text' => 'Second comment',
            'published_at' => Carbon::now()
        ]);
        Comment::create([
            'comment_id' => 'comment_003',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author2->author_channel_id,
            'text' => 'Single comment',
            'published_at' => Carbon::now()
        ]);

        $response = $this->getJson("/api/videos/{$this->video->video_id}/comments?pattern=repeat&offset=0&limit=100");

        $response->assertStatus(200);

        $data = $response->json();

        // Should only return comments from repeat author (2 comments)
        $this->assertEquals(2, $data['total']);
        $this->assertCount(2, $data['comments']);
    }

    /** @test */
    public function test_aggressive_shows_placeholder_x()
    {
        $response = $this->getJson("/api/videos/{$this->video->video_id}/pattern-statistics");

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals('X', $data['patterns']['aggressive']['count']);
        $this->assertEquals(0, $data['patterns']['aggressive']['percentage']);
    }

    /** @test */
    public function test_aggressive_filter_returns_empty()
    {
        $this->createTestComments();

        $response = $this->getJson("/api/videos/{$this->video->video_id}/comments?pattern=aggressive&offset=0&limit=100");

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(0, $data['total']);
        $this->assertCount(0, $data['comments']);
        $this->assertFalse($data['has_more']);
    }

    /** @test */
    public function test_video_not_found_returns_404()
    {
        $response = $this->getJson("/api/videos/nonexistent_video/pattern-statistics");

        $response->assertStatus(404)
            ->assertJsonStructure([
                'error' => [
                    'type',
                    'message',
                    'details'
                ]
            ]);
    }

    /** @test */
    public function test_invalid_pattern_validation()
    {
        $response = $this->getJson("/api/videos/{$this->video->video_id}/comments?pattern=invalid_pattern&offset=0&limit=100");

        $response->assertStatus(422);
    }

    /** @test */
    public function test_top_liked_pattern_sorts_by_like_count()
    {
        // Create author
        $author = Author::create([
            'author_channel_id' => 'test_author_likes',
            'name' => 'Test Author'
        ]);

        // Create comments with different like counts
        Comment::create([
            'comment_id' => 'comment_low_likes',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author->author_channel_id,
            'text' => 'Low likes comment',
            'like_count' => 5,
            'published_at' => Carbon::now()
        ]);

        Comment::create([
            'comment_id' => 'comment_high_likes',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author->author_channel_id,
            'text' => 'High likes comment',
            'like_count' => 100,
            'published_at' => Carbon::now()->subHours(1)
        ]);

        Comment::create([
            'comment_id' => 'comment_medium_likes',
            'video_id' => $this->video->video_id,
            'author_channel_id' => $author->author_channel_id,
            'text' => 'Medium likes comment',
            'like_count' => 50,
            'published_at' => Carbon::now()->subHours(2)
        ]);

        $response = $this->getJson("/api/videos/{$this->video->video_id}/comments?pattern=top_liked&offset=0&limit=100");

        $response->assertStatus(200);

        $data = $response->json();

        // Verify comments are sorted by like_count DESC
        $this->assertCount(3, $data['comments']);
        $this->assertEquals(100, $data['comments'][0]['like_count']); // Highest first
        $this->assertEquals(50, $data['comments'][1]['like_count']);  // Medium second
        $this->assertEquals(5, $data['comments'][2]['like_count']);   // Lowest last
    }

    /** @test */
    public function test_top_liked_pattern_statistics_same_as_all()
    {
        $this->createTestComments();

        $response = $this->getJson("/api/videos/{$this->video->video_id}/pattern-statistics");

        $response->assertStatus(200);

        $data = $response->json();

        // top_liked should have same count and percentage as 'all' (100%)
        $this->assertEquals($data['patterns']['all']['count'], $data['patterns']['top_liked']['count']);
        $this->assertEquals($data['patterns']['all']['percentage'], $data['patterns']['top_liked']['percentage']);
        $this->assertEquals(100, $data['patterns']['top_liked']['percentage']);
    }

    /**
     * Create some test comments for general testing
     */
    private function createTestComments()
    {
        $author = Author::create([
            'author_channel_id' => 'test_author_001',
            'name' => 'Test Author'
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Comment::create([
                'comment_id' => "comment_00{$i}",
                'video_id' => $this->video->video_id,
                'author_channel_id' => $author->author_channel_id,
                'text' => "Test comment {$i}",
                'like_count' => $i,
                'published_at' => Carbon::now()->subHours($i)
            ]);
        }
    }
}
