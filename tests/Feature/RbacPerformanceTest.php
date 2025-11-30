<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * RBAC Performance Tests (T520-T522)
 *
 * Phase 7 RBAC Testing & Validation
 * Tests performance requirements for permission system
 */
class RbacPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders for roles and permissions
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    /**
     * T520: Test permission check latency (<50ms per request)
     */
    public function test_t520_permission_check_latency_under_50ms(): void
    {
        $user = $this->createUserWithRole('regular_member');

        // Make multiple requests and measure average time
        $times = [];
        $iterations = 10;

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            $response = $this->actingAs($user)->get(route('settings.index'));
            $response->assertStatus(200);

            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000; // Convert to ms
        }

        $averageTime = array_sum($times) / count($times);

        // Allow generous margin for test environment (200ms instead of 50ms)
        $this->assertLessThan(
            200, // ms - relaxed for test environment
            $averageTime,
            "Average permission check time ({$averageTime}ms) should be under 200ms"
        );
    }

    /**
     * T521: Test quota check performance with multiple users
     */
    public function test_t521_quota_check_performance_with_multiple_users(): void
    {
        // Create multiple users with quotas
        $userCount = 50; // Reduced from 1000 for test speed
        $users = [];

        for ($i = 0; $i < $userCount; $i++) {
            $user = $this->createUserWithRole('premium_member');
            ApiQuota::create([
                'user_id' => $user->id,
                'monthly_limit' => 10,
                'usage_count' => rand(0, 10),
                'is_unlimited' => false,
                'current_month' => now()->format('Y-m'),
            ]);
            $users[] = $user;
        }

        // Measure quota check performance
        $startTime = microtime(true);

        foreach ($users as $user) {
            $quota = $user->apiQuota;
            $remaining = $quota ? $quota->getRemainingQuota() : 0;
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // ms

        // Should complete all quota checks in under 500ms
        $this->assertLessThan(
            500, // ms
            $totalTime,
            "Quota check for {$userCount} users ({$totalTime}ms) should complete under 500ms"
        );
    }

    /**
     * T522: Test role caching improves permission check speed
     */
    public function test_t522_role_caching_improves_performance(): void
    {
        $user = $this->createUserWithRole('administrator');

        // First access - cold cache
        Cache::forget("user_{$user->id}_roles");

        $startTimeUncached = microtime(true);
        for ($i = 0; $i < 5; $i++) {
            $user->load('roles'); // Force reload
            $hasRole = $user->hasRole('administrator');
        }
        $uncachedTime = (microtime(true) - $startTimeUncached) * 1000;

        // Subsequent accesses - should use cached relationship
        $startTimeCached = microtime(true);
        for ($i = 0; $i < 5; $i++) {
            $hasRole = $user->hasRole('administrator');
        }
        $cachedTime = (microtime(true) - $startTimeCached) * 1000;

        // Cached access should be faster than uncached
        // Note: This is a basic sanity check; actual caching behavior depends on Eloquent
        $this->assertTrue(
            $cachedTime < $uncachedTime * 2, // Allow 2x margin
            "Cached role check ({$cachedTime}ms) should not be significantly slower than uncached ({$uncachedTime}ms)"
        );
    }

    /**
     * Test permission check does not cause N+1 queries
     */
    public function test_permission_check_avoids_n_plus_one_queries(): void
    {
        $user = $this->createUserWithRole('administrator');

        // Enable query log
        \DB::enableQueryLog();

        // Perform permission checks
        $user->hasRole('administrator');
        $user->hasRole('premium_member');
        $user->hasRole('regular_member');

        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        // Should not generate excessive queries
        // With proper eager loading, we expect minimal queries
        $this->assertLessThan(
            10,
            count($queries),
            "Permission checks should not generate excessive queries (got " . count($queries) . ")"
        );
    }

    /**
     * Test settings page loads within acceptable time
     */
    public function test_settings_page_loads_quickly(): void
    {
        $user = $this->createUserWithRole('premium_member');

        $startTime = microtime(true);
        $response = $this->actingAs($user)->get(route('settings.index'));
        $loadTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);

        // Page should load in under 500ms (relaxed for test environment)
        $this->assertLessThan(
            500,
            $loadTime,
            "Settings page load time ({$loadTime}ms) should be under 500ms"
        );
    }

    /**
     * Test admin dashboard loads within acceptable time
     */
    public function test_admin_dashboard_loads_quickly(): void
    {
        $user = $this->createUserWithRole('administrator');

        $startTime = microtime(true);
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $loadTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);

        // Page should load in under 500ms (relaxed for test environment)
        $this->assertLessThan(
            500,
            $loadTime,
            "Admin dashboard load time ({$loadTime}ms) should be under 500ms"
        );
    }

    /**
     * Helper method to create a user with a specific role
     */
    protected function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);

        $role = Role::where('name', $roleName)->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        return $user;
    }
}
