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
 * Contract tests for API Quota endpoints (T416)
 *
 * Tests the contract for checking and managing API import quotas.
 */
class ApiQuotaContractTest extends TestCase
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
     * T416: Contract test for check quota endpoint response structure
     */
    public function check_quota_returns_valid_structure_for_premium_member(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 3,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/quota/check');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'allowed',
                'message',
                'usage' => [
                    'used',
                    'limit',
                    'remaining',
                    'unlimited',
                ],
            ])
            ->assertJson([
                'allowed' => true,
                'usage' => [
                    'used' => 3,
                    'limit' => 10,
                    'remaining' => 7,
                    'unlimited' => false,
                ],
            ]);
    }

    /**
     * @test
     * T416: Contract test for quota exceeded response
     */
    public function check_quota_returns_exceeded_response_when_at_limit(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 10,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/quota/check');

        $response->assertStatus(200)
            ->assertJson([
                'allowed' => false,
                'usage' => [
                    'used' => 10,
                    'limit' => 10,
                    'remaining' => 0,
                    'unlimited' => false,
                ],
            ]);
    }

    /**
     * @test
     * T416: Contract test for unlimited quota response
     */
    public function check_quota_returns_unlimited_for_verified_premium_member(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 50,
            'monthly_limit' => 10,
            'is_unlimited' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/quota/check');

        $response->assertStatus(200)
            ->assertJson([
                'allowed' => true,
                'usage' => [
                    'unlimited' => true,
                ],
            ]);
    }

    /**
     * @test
     * T416: Contract test - unauthenticated users cannot check quota
     */
    public function check_quota_requires_authentication(): void
    {
        $response = $this->getJson('/api/quota/check');

        $response->assertStatus(401);
    }

    /**
     * @test
     * T416: Contract test - regular members get 401 for quota check (not applicable)
     */
    public function regular_member_cannot_access_quota_endpoint(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->getJson('/api/quota/check');

        // Regular members don't have quota - they can't use Official API
        $response->assertStatus(403);
    }

    /**
     * @test
     * T416: Contract test - administrators have unlimited quota
     */
    public function administrator_has_unlimited_quota(): void
    {
        $role = Role::where('name', 'administrator')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->getJson('/api/quota/check');

        $response->assertStatus(200)
            ->assertJson([
                'allowed' => true,
                'usage' => [
                    'unlimited' => true,
                ],
            ]);
    }
}
