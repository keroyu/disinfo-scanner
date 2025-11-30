<?php

namespace Tests\Contract;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PermissionSeeder;

/**
 * Contract tests for Feature Access Control (T449)
 *
 * Tests the contract for feature-level permission checks.
 */
class FeatureAccessContractTest extends TestCase
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
     * T449, T455: Regular member cannot use Official API import
     */
    public function regular_member_denied_official_api_import(): void
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

        $response->assertStatus(403)
            ->assertJsonPath('error.type', 'PermissionDenied')
            ->assertJsonPath('error.message', '需升級為高級會員');
    }

    /**
     * @test
     * T449, T456: Premium member can use Official API import (within quota)
     */
    public function premium_member_can_use_official_api_import(): void
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
            'usage_count' => 0,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        // Should succeed (not be denied by permission or quota)
        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(429, $response->status());
    }

    /**
     * @test
     * T449, T457: Verified premium member has unlimited import
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
            'usage_count' => 100, // Way over normal limit
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
     * T449, T454: Regular member can use U-API import
     */
    public function regular_member_can_use_uapi_import(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->postJson('/api/uapi/import', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        // Should not be denied (might be 422 for validation, but not 403)
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * @test
     * T449, T459: Visitor cannot use video update feature
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
     * T449, T460: Regular member needs API key for video update
     */
    public function regular_member_without_api_key_denied_video_update(): void
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
            'video_id' => 'test1234567', // 11 characters (valid YouTube ID format)
        ]);

        // Should get 403 (forbidden) error about missing API key
        $response->assertStatus(403);
    }

    /**
     * @test
     * T449: Administrator has unrestricted feature access
     */
    public function administrator_has_unrestricted_feature_access(): void
    {
        $role = Role::where('name', 'administrator')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Official API import - should pass (admin doesn't need API key or quota)
        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(429, $response->status());
    }

    /**
     * @test
     * T449, T467: Website editor has full frontend feature access
     */
    public function website_editor_has_full_frontend_access(): void
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
    }
}
