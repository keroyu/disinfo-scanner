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
 * Contract tests for Quota Enforcement (T417)
 *
 * Tests the contract for how quotas are enforced during API import operations.
 */
class QuotaEnforcementTest extends TestCase
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
     * T417: Contract test - quota enforcement response structure on success
     */
    public function quota_enforcement_returns_success_when_within_limit(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_api_key',
        ]);
        $user->roles()->attach($role);

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 5,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        // This tests the contract that API import respects quota
        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        // We expect either success or validation error (not quota error)
        // The quota check should pass since usage (5) < limit (10)
        $this->assertNotEquals(429, $response->status());
    }

    /**
     * @test
     * T417: Contract test - quota enforcement returns 429 when exceeded
     */
    public function quota_enforcement_returns_429_when_quota_exceeded(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_api_key',
        ]);
        $user->roles()->attach($role);

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

        $response->assertStatus(429)
            ->assertJsonStructure([
                'error' => [
                    'type',
                    'message',
                    'details' => [
                        'current_usage',
                        'limit',
                    ],
                ],
            ])
            ->assertJson([
                'error' => [
                    'type' => 'QuotaExceeded',
                    'details' => [
                        'current_usage' => 10,
                        'limit' => 10,
                    ],
                ],
            ]);
    }

    /**
     * @test
     * T417: Contract test - quota enforcement allows unlimited users
     */
    public function quota_enforcement_allows_unlimited_quota_users(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_api_key',
        ]);
        $user->roles()->attach($role);

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 100, // Way over normal limit
            'monthly_limit' => 10,
            'is_unlimited' => true, // But has unlimited access
        ]);

        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        // Should NOT get quota exceeded error
        $this->assertNotEquals(429, $response->status());
    }

    /**
     * @test
     * T417: Contract test - administrators bypass quota enforcement
     */
    public function quota_enforcement_bypassed_for_administrators(): void
    {
        $role = Role::where('name', 'administrator')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        // Even without quota record, admin should pass
        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        // Should NOT get quota exceeded error
        $this->assertNotEquals(429, $response->status());
    }

    /**
     * @test
     * T417: Contract test - regular members cannot access Official API import
     */
    public function regular_member_cannot_use_official_api_import(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_api_key',
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->postJson('/api/videos/import/official', [
            'video_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'type' => 'PermissionDenied',
                    'message' => '需升級為高級會員',
                ],
            ]);
    }

    /**
     * @test
     * T417: Contract test - quota exceeded message includes verification suggestion
     */
    public function quota_exceeded_message_includes_verification_suggestion(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_api_key',
        ]);
        $user->roles()->attach($role);

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

        $response->assertStatus(429)
            ->assertJsonPath('error.details.suggestion', '請完成身份驗證以獲得無限配額');
    }
}
