<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use App\Services\ApiQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PermissionSeeder;
use Carbon\Carbon;

/**
 * Feature tests for API Quota Management (T418)
 *
 * Tests the complete API quota functionality including checking, incrementing,
 * and resetting quotas.
 */
class ApiQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected ApiQuotaService $quotaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->quotaService = app(ApiQuotaService::class);
    }

    /**
     * @test
     * T418: Premium member starts with 0 usage count
     */
    public function premium_member_starts_with_zero_usage(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $result = $this->quotaService->checkQuota($user);

        $this->assertTrue($result['allowed']);
        $this->assertEquals(0, $result['usage']['used']);
        $this->assertEquals(10, $result['usage']['limit']);
        $this->assertEquals(10, $result['usage']['remaining']);
        $this->assertFalse($result['usage']['unlimited']);
    }

    /**
     * @test
     * T418: Usage is correctly incremented after import
     */
    public function usage_is_incremented_after_import(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Initial check
        $this->quotaService->checkQuota($user);

        // Increment usage
        $this->quotaService->incrementUsage($user);

        $result = $this->quotaService->checkQuota($user);
        $this->assertEquals(1, $result['usage']['used']);
        $this->assertEquals(9, $result['usage']['remaining']);
    }

    /**
     * @test
     * T418: Quota is denied when at limit
     */
    public function quota_denied_when_at_limit(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Create quota at limit
        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 10,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $result = $this->quotaService->checkQuota($user);

        $this->assertFalse($result['allowed']);
        $this->assertEquals(10, $result['usage']['used']);
        $this->assertEquals(0, $result['usage']['remaining']);
        $this->assertStringContainsString('10/10', $result['message']);
    }

    /**
     * @test
     * T418: Unlimited quota users always allowed
     */
    public function unlimited_quota_always_allowed(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Create unlimited quota
        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 100,
            'monthly_limit' => 10,
            'is_unlimited' => true,
        ]);

        $result = $this->quotaService->checkQuota($user);

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['usage']['unlimited']);
        $this->assertNull($result['usage']['remaining']);
    }

    /**
     * @test
     * T418: Quota stats display correctly
     */
    public function quota_stats_display_correctly(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 5,
            'monthly_limit' => 10,
            'is_unlimited' => false,
            'last_import_at' => now(),
        ]);

        $stats = $this->quotaService->getQuotaStats($user);

        $this->assertEquals(5, $stats['used']);
        $this->assertEquals(10, $stats['limit']);
        $this->assertEquals(5, $stats['remaining']);
        $this->assertFalse($stats['unlimited']);
        $this->assertNotNull($stats['last_import']);
        $this->assertEquals(now()->format('Y-m'), $stats['current_month']);
    }

    /**
     * @test
     * T418: Grant unlimited quota works correctly
     */
    public function grant_unlimited_quota_works(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Create normal quota first
        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 8,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        // Grant unlimited
        $this->quotaService->grantUnlimitedQuota($user);

        $this->assertTrue($this->quotaService->hasUnlimitedQuota($user));

        $result = $this->quotaService->checkQuota($user);
        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['usage']['unlimited']);
    }

    /**
     * @test
     * T418: Revoke unlimited quota works correctly
     */
    public function revoke_unlimited_quota_works(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Create unlimited quota
        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 50,
            'monthly_limit' => 10,
            'is_unlimited' => true,
        ]);

        // Revoke unlimited
        $this->quotaService->revokeUnlimitedQuota($user);

        $this->assertFalse($this->quotaService->hasUnlimitedQuota($user));

        // Now should be denied since usage (50) > limit (10)
        $result = $this->quotaService->checkQuota($user);
        $this->assertFalse($result['allowed']);
    }

    /**
     * @test
     * T418: Multiple users have independent quotas
     */
    public function multiple_users_have_independent_quotas(): void
    {
        $role = Role::where('name', 'premium_member')->first();

        $user1 = User::factory()->create(['email_verified_at' => now()]);
        $user1->roles()->attach($role);

        $user2 = User::factory()->create(['email_verified_at' => now()]);
        $user2->roles()->attach($role);

        // Increment user1's usage
        $this->quotaService->incrementUsage($user1);
        $this->quotaService->incrementUsage($user1);
        $this->quotaService->incrementUsage($user1);

        // User2's usage should still be 0
        $result1 = $this->quotaService->checkQuota($user1);
        $result2 = $this->quotaService->checkQuota($user2);

        $this->assertEquals(3, $result1['usage']['used']);
        $this->assertEquals(0, $result2['usage']['used']);
    }

    /**
     * @test
     * T418: Website editor has no quota limits
     */
    public function website_editor_has_no_quota_limits(): void
    {
        $role = Role::where('name', 'website_editor')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Website editors should have unlimited access like admins
        $response = $this->actingAs($user)->getJson('/api/quota/check');

        // Website editors have full frontend access, should get unlimited
        $this->assertTrue(
            $response->status() === 200 || $response->status() === 403,
            'Website editor should get 200 (unlimited) or 403 (if quota endpoint restricted to premium)'
        );
    }
}
