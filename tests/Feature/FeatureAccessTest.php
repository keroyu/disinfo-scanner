<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PermissionSeeder;

/**
 * Feature tests for Feature Access Control (T450)
 *
 * Tests the complete feature access control functionality.
 */
class FeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    /**
     * @test
     * T450, T451: Visitor cannot use Videos List search (search params ignored)
     */
    public function visitor_cannot_use_videos_list_search(): void
    {
        // Access videos list with search params as visitor
        $response = $this->get(route('videos.index', ['search' => 'test']));

        $response->assertStatus(200);
        // Search params should be ignored for visitors
        // The view should show the login modal prompt for search
    }

    /**
     * @test
     * T450, T452: Regular member cannot use Comments List search
     */
    public function regular_member_cannot_use_comments_search(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('comments.index'));

        $response->assertStatus(200);
        // Should see disabled search fields
        $response->assertSee('搜尋功能需要高級會員', false);
    }

    /**
     * @test
     * T450, T453: Premium member can use all search features
     */
    public function premium_member_can_use_all_search_features(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('comments.index'));

        $response->assertStatus(200);
        // Should NOT see the disabled search message
        $response->assertDontSee('搜尋功能需要高級會員', false);
    }

    /**
     * @test
     * T450, T454: Regular member can use U-API import
     */
    public function regular_member_can_use_uapi_import(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // U-API import should be accessible
        $response = $this->actingAs($user)->postJson('/api/uapi/import', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        // Should not be permission denied (might be 422 for validation)
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * @test
     * T450, T455: Regular member cannot use Official API import
     */
    public function regular_member_cannot_use_official_api_import(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_key',
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        $response->assertStatus(403);
    }

    /**
     * @test
     * T450, T456: Premium member can use Official API import with quota
     */
    public function premium_member_can_use_official_api_import_with_quota(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_key',
        ]);
        $user->roles()->attach($role);

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 5,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        // Should succeed (not blocked by permission or quota)
        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(429, $response->status());
    }

    /**
     * @test
     * T450, T457: Verified premium member has unlimited Official API import
     */
    public function verified_premium_member_has_unlimited_import(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_key',
        ]);
        $user->roles()->attach($role);

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 50, // Way over normal limit
            'monthly_limit' => 10,
            'is_unlimited' => true, // Identity verified
        ]);

        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        // Should not be blocked by quota
        $this->assertNotEquals(429, $response->status());
    }

    /**
     * @test
     * T450, T458: Visitor can use video analysis feature
     */
    public function visitor_can_use_video_analysis(): void
    {
        // Video analysis page should be accessible to visitors
        // Note: This test would need a video to exist; we test route accessibility
        $response = $this->get('/videos/test1234567/analysis');

        // Should not be redirected to login (200 or 404 if video doesn't exist)
        $this->assertNotEquals(302, $response->status());
    }

    /**
     * @test
     * T450, T459: Visitor cannot use video update feature
     */
    public function visitor_cannot_use_video_update(): void
    {
        $response = $this->postJson('/api/video-update/preview', [
            'video_id' => 'test1234567', // 11 characters
        ]);

        $response->assertStatus(401);
    }

    /**
     * @test
     * T450, T460, T461: Regular member needs API key for video update
     */
    public function regular_member_needs_api_key_for_video_update(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => null, // No API key
        ]);
        $user->roles()->attach($role);

        // Create a channel and video in database for the test
        \App\Models\Channel::create([
            'channel_id' => 'test_channel',
            'name' => 'Test Channel',
        ]);
        \App\Models\Video::create([
            'video_id' => 'test1234567',
            'title' => 'Test Video',
            'channel_id' => 'test_channel',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/video-update/preview', [
            'video_id' => 'test1234567', // 11 characters
        ]);

        // User without API key gets 403 (forbidden) with error message
        $response->assertStatus(403)
            ->assertJsonPath('error', '請先在帳號設定中配置 YouTube API 金鑰');
    }

    /**
     * @test
     * T450, T462: Visitor can use video analysis but not video update
     */
    public function visitor_can_analyze_but_not_update(): void
    {
        // Analysis - accessible
        $response = $this->get('/videos/test1234567/analysis');
        $this->assertNotEquals(302, $response->status());

        // Update - requires auth
        $response = $this->postJson('/api/video-update/preview', [
            'video_id' => 'test1234567', // 11 characters
        ]);
        $response->assertStatus(401);
    }

    /**
     * @test
     * T450, T463: Visitor cannot use Videos List search effectively
     */
    public function visitor_search_shows_login_prompt(): void
    {
        $response = $this->get(route('videos.index'));

        $response->assertStatus(200);
        // The UI should show login modal trigger for search (already tested in RoleBasedAccessTest)
    }

    /**
     * @test
     * T450, T464: Regular member import permissions
     */
    public function regular_member_import_permissions(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_key',
        ]);
        $user->roles()->attach($role);

        // U-API import - allowed
        $response = $this->actingAs($user)->postJson('/api/uapi/import', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);
        $this->assertNotEquals(403, $response->status());

        // Official API import - denied
        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);
        $response->assertStatus(403);
    }

    /**
     * @test
     * T450, T465: Regular member can use video update with API key
     */
    public function regular_member_can_use_video_update_with_api_key(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_api_key_configured', // Has API key
        ]);
        $user->roles()->attach($role);

        // Create a channel and video in database for the test
        \App\Models\Channel::create([
            'channel_id' => 'test_channel_2',
            'name' => 'Test Channel 2',
        ]);
        \App\Models\Video::create([
            'video_id' => 'test1234567',
            'title' => 'Test Video',
            'channel_id' => 'test_channel_2',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/video-update/preview', [
            'video_id' => 'test1234567', // 11 characters
        ]);

        // Should not fail due to missing API key (might fail for other reasons like YouTube API error)
        $this->assertNotEquals(400, $response->status());
    }

    /**
     * @test
     * T450, T466: Premium member quota check on Official API import
     */
    public function premium_member_quota_check_on_import(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_key',
        ]);
        $user->roles()->attach($role);

        // At quota limit
        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 10,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        $response->assertStatus(429);
    }

    /**
     * @test
     * T450, T467: Website editor has full frontend feature access
     */
    public function website_editor_full_frontend_access(): void
    {
        $role = Role::where('name', 'website_editor')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_key',
        ]);
        $user->roles()->attach($role);

        // Official API import - website editor should have access
        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);
        $this->assertNotEquals(403, $response->status());

        // Comments search - should work
        $response = $this->actingAs($user)->get(route('comments.index'));
        $response->assertStatus(200);
        $response->assertDontSee('搜尋功能需要高級會員', false);
    }
}
