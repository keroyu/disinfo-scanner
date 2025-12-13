<?php

namespace Tests\Contract\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Video;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Author;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;

/**
 * Contract Tests for Admin Video Management API (012-admin-video-management)
 *
 * Tests T008-T012, T023-T026, T038-T040, T051-T053
 */
class VideoManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Channel $channel;
    protected Author $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $this->admin = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $this->admin->roles()->attach($adminRole);

        // Create regular user (non-admin)
        $regularRole = Role::where('name', 'regular_member')->first();
        $this->regularUser = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'is_email_verified' => true,
        ]);
        $this->regularUser->roles()->attach($regularRole);

        // Create a channel for videos
        $this->channel = Channel::create([
            'channel_id' => 'UC_test_chan',
            'channel_name' => 'Test Channel',
        ]);

        // Create an author for comments
        $this->author = Author::create([
            'author_channel_id' => 'UC_test_author',
            'name' => 'Test Author',
        ]);
    }

    /**
     * Helper to create a video with optional comments
     */
    protected function createVideoWithComments(string $videoId, string $title, int $commentCount = 0): Video
    {
        $video = Video::create([
            'video_id' => $videoId,
            'channel_id' => $this->channel->channel_id,
            'title' => $title,
            'published_at' => now()->subDays(rand(1, 30)),
        ]);

        for ($i = 0; $i < $commentCount; $i++) {
            Comment::create([
                'comment_id' => "comment_{$videoId}_{$i}",
                'video_id' => $video->video_id,
                'author_channel_id' => $this->author->author_channel_id,
                'text' => "Comment $i on video $videoId",
                'published_at' => now()->subHours(rand(1, 100)),
            ]);
        }

        return $video;
    }

    // =====================================================
    // T008-T012: User Story 1 - List Videos Contract Tests
    // =====================================================

    /**
     * T008 [US1]: Contract test for GET /api/admin/videos (list endpoint)
     * @test
     */
    public function list_videos_returns_correct_json_structure(): void
    {
        // Arrange
        $this->createVideoWithComments('dQw4w9WgXcQ', 'Test Video 1', 5);
        $this->createVideoWithComments('xvFZjo5PgG0', 'Test Video 2', 3);

        // Act
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/videos');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'video_id',
                        'title',
                        'channel_id',
                        'channel_name',
                        'published_at',
                        'actual_comment_count',
                        'created_at',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    /**
     * T009 [US1]: Contract test for pagination parameters
     * @test
     */
    public function list_videos_supports_pagination(): void
    {
        // Arrange: Create 25 videos
        for ($i = 0; $i < 25; $i++) {
            $videoId = sprintf('vid%08d', $i);
            $this->createVideoWithComments($videoId, "Video $i");
        }

        // Act: Request first page with 10 per page
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/videos?page=1&per_page=10');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 25);

        $this->assertCount(10, $response->json('data'));

        // Act: Request second page
        $response2 = $this->actingAs($this->admin)
            ->getJson('/api/admin/videos?page=2&per_page=10');

        $response2->assertStatus(200)
            ->assertJsonPath('meta.current_page', 2);

        $this->assertCount(10, $response2->json('data'));
    }

    /**
     * T010 [US1]: Contract test for search filter
     * @test
     */
    public function list_videos_supports_search(): void
    {
        // Arrange
        $this->createVideoWithComments('dQw4w9WgXcQ', 'Funny Cat Video');
        $this->createVideoWithComments('xvFZjo5PgG0', 'Dog Training Tutorial');

        // Also create a channel with searchable name
        $searchChannel = Channel::create([
            'channel_id' => 'UC_cat_chan',
            'channel_name' => 'Cat Lovers Channel',
        ]);
        $video3 = Video::create([
            'video_id' => 'abc12345678',
            'channel_id' => $searchChannel->channel_id,
            'title' => 'Random Video',
            'published_at' => now(),
        ]);

        // Act: Search for "cat"
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/videos?search=cat');

        // Assert: Should find video with "Cat" in title and video from "Cat Lovers Channel"
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);

        $titles = collect($data)->pluck('title')->toArray();
        $this->assertContains('Funny Cat Video', $titles);
        $this->assertContains('Random Video', $titles);
    }

    /**
     * T011 [US1]: Contract test for sort parameters
     * @test
     */
    public function list_videos_supports_sorting(): void
    {
        // Arrange
        $video1 = $this->createVideoWithComments('dQw4w9WgXcQ', 'A First Video', 10);
        $video2 = $this->createVideoWithComments('xvFZjo5PgG0', 'Z Last Video', 5);
        $video3 = $this->createVideoWithComments('abc12345678', 'M Middle Video', 15);

        // Act: Sort by title ascending
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/videos?sort_by=title&sort_dir=asc');

        // Assert
        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertEquals(['A First Video', 'M Middle Video', 'Z Last Video'], $titles);

        // Act: Sort by actual_comment_count descending
        $response2 = $this->actingAs($this->admin)
            ->getJson('/api/admin/videos?sort_by=actual_comment_count&sort_dir=desc');

        $response2->assertStatus(200);
        $titles2 = collect($response2->json('data'))->pluck('title')->toArray();
        $this->assertEquals(['M Middle Video', 'A First Video', 'Z Last Video'], $titles2);
    }

    /**
     * T012 [US1]: Contract test for 401/403 unauthorized access
     * @test
     */
    public function list_videos_requires_admin_authentication(): void
    {
        // Act: Unauthenticated request
        $response = $this->getJson('/api/admin/videos');
        $response->assertStatus(401);

        // Act: Non-admin user request
        $response2 = $this->actingAs($this->regularUser)
            ->getJson('/api/admin/videos');

        $response2->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    // =====================================================
    // T023-T026: User Story 2 - Edit Video Contract Tests
    // =====================================================

    /**
     * T023 [US2]: Contract test for GET /api/admin/videos/{id} (get single video)
     * @test
     */
    public function get_video_returns_correct_json_structure(): void
    {
        // Arrange
        $video = $this->createVideoWithComments('dQw4w9WgXcQ', 'Test Video', 5);

        // Act
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/videos/dQw4w9WgXcQ');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'video_id',
                    'title',
                    'channel_id',
                    'channel_name',
                    'published_at',
                    'actual_comment_count',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJsonPath('data.video_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('data.title', 'Test Video')
            ->assertJsonPath('data.actual_comment_count', 5);
    }

    /**
     * T024 [US2]: Contract test for PUT /api/admin/videos/{id} (update video)
     * @test
     */
    public function update_video_returns_success(): void
    {
        // Arrange
        $video = $this->createVideoWithComments('dQw4w9WgXcQ', 'Original Title');

        // Act
        $response = $this->actingAs($this->admin)
            ->putJson('/api/admin/videos/dQw4w9WgXcQ', [
                'title' => 'Updated Title',
                'published_at' => '2025-01-15T08:30:00Z',
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => '影片資料已更新',
            ])
            ->assertJsonPath('data.title', 'Updated Title');

        // Verify database was updated
        $this->assertDatabaseHas('videos', [
            'video_id' => 'dQw4w9WgXcQ',
            'title' => 'Updated Title',
        ]);
    }

    /**
     * T025 [US2]: Contract test for validation errors (empty title, invalid date)
     * @test
     */
    public function update_video_validates_input(): void
    {
        // Arrange
        $video = $this->createVideoWithComments('dQw4w9WgXcQ', 'Test Video');

        // Act: Empty title
        $response = $this->actingAs($this->admin)
            ->putJson('/api/admin/videos/dQw4w9WgXcQ', [
                'title' => '',
                'published_at' => '2025-01-15T08:30:00Z',
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'message' => '驗證失敗',
            ])
            ->assertJsonPath('errors.title.0', '標題不能為空');

        // Act: Invalid date
        $response2 = $this->actingAs($this->admin)
            ->putJson('/api/admin/videos/dQw4w9WgXcQ', [
                'title' => 'Valid Title',
                'published_at' => 'not-a-date',
            ]);

        $response2->assertStatus(422)
            ->assertJsonPath('errors.published_at.0', '日期格式無效');
    }

    /**
     * T026 [US2]: Contract test for 404 when video not found
     * @test
     */
    public function get_video_returns_404_when_not_found(): void
    {
        // Act - use 11-character video ID to match route pattern
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/videos/nonexisten1');

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'message' => '找不到此影片，可能已被刪除'
            ]);
    }

    // =====================================================
    // T038-T040: User Story 3 - Delete Video Contract Tests
    // =====================================================

    /**
     * T038 [US3]: Contract test for GET /api/admin/videos/{id}/comment-count
     * @test
     */
    public function get_comment_count_returns_count(): void
    {
        // Arrange
        $video = $this->createVideoWithComments('dQw4w9WgXcQ', 'Test Video', 25);

        // Act
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/videos/dQw4w9WgXcQ/comment-count');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'video_id' => 'dQw4w9WgXcQ',
                'comment_count' => 25,
            ]);
    }

    /**
     * T039 [US3]: Contract test for DELETE /api/admin/videos/{id}
     * @test
     */
    public function delete_video_returns_success(): void
    {
        // Arrange
        $video = $this->createVideoWithComments('dQw4w9WgXcQ', 'Test Video', 10);

        // Act
        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/admin/videos/dQw4w9WgXcQ');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => '影片及相關留言已刪除',
                'deleted_comments' => 10,
            ]);

        // Verify video was deleted
        $this->assertDatabaseMissing('videos', ['video_id' => 'dQw4w9WgXcQ']);
    }

    /**
     * T040 [US3]: Contract test for cascade deletion (verify comments deleted)
     * @test
     */
    public function delete_video_cascades_to_comments(): void
    {
        // Arrange
        $video = $this->createVideoWithComments('dQw4w9WgXcQ', 'Test Video', 5);

        // Verify comments exist
        $this->assertDatabaseCount('comments', 5);

        // Act
        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/admin/videos/dQw4w9WgXcQ');

        // Assert
        $response->assertStatus(200);

        // Verify all comments were deleted (cascade)
        $this->assertDatabaseCount('comments', 0);
    }

    // =====================================================
    // T051-T053: User Story 4 - Batch Delete Contract Tests
    // =====================================================

    /**
     * T051 [US4]: Contract test for POST /api/admin/videos/batch-delete
     * @test
     */
    public function batch_delete_returns_success(): void
    {
        // Arrange
        $this->createVideoWithComments('dQw4w9WgXcQ', 'Video 1', 5);
        $this->createVideoWithComments('xvFZjo5PgG0', 'Video 2', 3);
        $this->createVideoWithComments('abc12345678', 'Video 3', 7);

        // Act
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/videos/batch-delete', [
                'video_ids' => ['dQw4w9WgXcQ', 'xvFZjo5PgG0'],
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => '已刪除 2 部影片及相關留言',
                'deleted_videos' => 2,
                'deleted_comments' => 8, // 5 + 3
            ]);

        // Verify correct videos were deleted
        $this->assertDatabaseMissing('videos', ['video_id' => 'dQw4w9WgXcQ']);
        $this->assertDatabaseMissing('videos', ['video_id' => 'xvFZjo5PgG0']);
        $this->assertDatabaseHas('videos', ['video_id' => 'abc12345678']);
    }

    /**
     * T052 [US4]: Contract test for batch delete validation (empty array, >50 videos)
     * @test
     */
    public function batch_delete_validates_input(): void
    {
        // Act: Empty array
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/videos/batch-delete', [
                'video_ids' => [],
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'message' => '驗證失敗',
            ])
            ->assertJsonPath('errors.video_ids.0', '請選擇要刪除的影片');

        // Act: Too many videos (>50)
        $tooManyIds = [];
        for ($i = 0; $i < 51; $i++) {
            $tooManyIds[] = sprintf('vid%08d', $i);
        }

        $response2 = $this->actingAs($this->admin)
            ->postJson('/api/admin/videos/batch-delete', [
                'video_ids' => $tooManyIds,
            ]);

        $response2->assertStatus(422)
            ->assertJsonPath('errors.video_ids.0', '一次最多刪除50部影片');
    }

    /**
     * T053 [US4]: Contract test for batch cascade deletion
     * @test
     */
    public function batch_delete_cascades_to_comments(): void
    {
        // Arrange
        $this->createVideoWithComments('dQw4w9WgXcQ', 'Video 1', 10);
        $this->createVideoWithComments('xvFZjo5PgG0', 'Video 2', 15);

        // Verify comments exist
        $this->assertDatabaseCount('comments', 25);

        // Act
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/videos/batch-delete', [
                'video_ids' => ['dQw4w9WgXcQ', 'xvFZjo5PgG0'],
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('deleted_comments', 25);

        // Verify all comments were deleted (cascade)
        $this->assertDatabaseCount('comments', 0);
    }

    /**
     * Verify audit log is created for video edit
     * @test
     */
    public function update_video_creates_audit_log(): void
    {
        // Arrange
        $video = $this->createVideoWithComments('dQw4w9WgXcQ', 'Original Title');

        // Act
        $this->actingAs($this->admin)
            ->putJson('/api/admin/videos/dQw4w9WgXcQ', [
                'title' => 'Updated Title',
                'published_at' => '2025-01-15T08:30:00Z',
            ]);

        // Assert
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => 'video_edit',
            'admin_id' => $this->admin->id,
            'resource_type' => 'video',
        ]);
    }

    /**
     * Verify audit log is created for video delete
     * @test
     */
    public function delete_video_creates_audit_log(): void
    {
        // Arrange
        $video = $this->createVideoWithComments('dQw4w9WgXcQ', 'Test Video', 5);

        // Act
        $this->actingAs($this->admin)
            ->deleteJson('/api/admin/videos/dQw4w9WgXcQ');

        // Assert
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => 'video_delete',
            'admin_id' => $this->admin->id,
            'resource_type' => 'video',
        ]);
    }

    /**
     * Verify audit log is created for batch delete
     * @test
     */
    public function batch_delete_creates_audit_log(): void
    {
        // Arrange
        $this->createVideoWithComments('dQw4w9WgXcQ', 'Video 1', 5);
        $this->createVideoWithComments('xvFZjo5PgG0', 'Video 2', 3);

        // Act
        $this->actingAs($this->admin)
            ->postJson('/api/admin/videos/batch-delete', [
                'video_ids' => ['dQw4w9WgXcQ', 'xvFZjo5PgG0'],
            ]);

        // Assert
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => 'video_batch_delete',
            'admin_id' => $this->admin->id,
            'resource_type' => 'video',
        ]);
    }
}
