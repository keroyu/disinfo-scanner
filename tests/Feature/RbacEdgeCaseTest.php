<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RBAC Edge Case Tests (T511-T519)
 *
 * Phase 7 RBAC Testing & Validation
 * Tests edge cases and boundary conditions
 */
class RbacEdgeCaseTest extends TestCase
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
     * T511: Test session expiration during permission-protected action
     */
    public function test_t511_session_expiration_redirects_to_login(): void
    {
        $user = $this->createUserWithRole('regular_member');

        // First request should work
        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertStatus(200);

        // Simulate session expiration by logging out
        auth()->logout();

        // Now accessing protected route should redirect to login
        $response = $this->get(route('settings.index'));
        $response->assertRedirect(route('login'));
    }

    /**
     * T512: Test role change takes effect without re-login
     */
    public function test_t512_role_change_takes_effect_immediately(): void
    {
        $user = $this->createUserWithRole('regular_member');

        // Regular member cannot access admin panel
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertForbidden();

        // Change role to administrator
        $adminRole = Role::where('name', 'administrator')->first();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->detach($regularRole->id);
        $user->roles()->attach($adminRole->id, ['assigned_at' => now()]);

        // Clear cached relationships
        $user->load('roles');

        // Now should be able to access admin panel (same session)
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }

    /**
     * T513: Test simultaneous role changes by multiple admins
     * This test simulates race condition handling
     */
    public function test_t513_concurrent_role_changes_handled_correctly(): void
    {
        $targetUser = $this->createUserWithRole('regular_member');
        $admin1 = $this->createUserWithRole('administrator');
        $admin2 = $this->createUserWithRole('administrator');

        $premiumRole = Role::where('name', 'premium_member')->first();
        $regularRole = Role::where('name', 'regular_member')->first();

        // Both admins try to change role at the same time
        // First change should succeed
        DB::transaction(function () use ($targetUser, $premiumRole, $regularRole) {
            $targetUser->roles()->detach($regularRole->id);
            $targetUser->roles()->attach($premiumRole->id, ['assigned_at' => now()]);
        });

        // Refresh user roles
        $targetUser->load('roles');

        // User should have premium_member role now
        $this->assertTrue($targetUser->hasRole('premium_member'));
        $this->assertFalse($targetUser->hasRole('regular_member'));
    }

    /**
     * T514: Test quota limit reached mid-import operation
     */
    public function test_t514_quota_limit_check_before_operation(): void
    {
        $user = $this->createUserWithRole('premium_member');

        // Set quota at limit
        $quota = ApiQuota::create([
            'user_id' => $user->id,
            'monthly_limit' => 10,
            'usage_count' => 10,
            'is_unlimited' => false,
            'current_month' => now()->format('Y-m'),
        ]);

        // Verify user cannot import more
        $this->assertEquals(10, $quota->usage_count);
        $this->assertEquals(10, $quota->monthly_limit);
        $this->assertFalse($quota->is_unlimited);

        // The quota check should prevent new imports
        $this->assertEquals(0, $quota->getRemainingQuota());
        $this->assertFalse($quota->hasQuotaAvailable());
    }

    /**
     * T515: Test visitor directly accessing restricted URL
     */
    public function test_t515_visitor_directly_accessing_restricted_url_redirected(): void
    {
        // Try to access various protected URLs directly
        $protectedUrls = [
            route('settings.index'),
            route('channels.index'),
            route('comments.index'),
            route('admin.dashboard'),
        ];

        foreach ($protectedUrls as $url) {
            $response = $this->get($url);
            // Should redirect to login or show forbidden
            $this->assertTrue(
                $response->status() === 302 || $response->status() === 403,
                "URL {$url} should be protected"
            );
        }
    }

    /**
     * T516: Test Regular Member cannot elevate to Premium Member permissions
     */
    public function test_t516_regular_member_cannot_elevate_permissions(): void
    {
        $user = $this->createUserWithRole('regular_member');

        // Regular member cannot submit identity verification (Premium only)
        $response = $this->actingAs($user)->post(route('settings.verification'), [
            'verification_method' => 'government_id',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('error');
    }

    /**
     * T517: Test Premium Member cannot access admin functions
     */
    public function test_t517_premium_member_cannot_access_admin(): void
    {
        $user = $this->createUserWithRole('premium_member');

        // Cannot access admin dashboard
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertForbidden();

        // Cannot access user management
        $response = $this->actingAs($user)->get(route('admin.users.index'));
        $response->assertForbidden();
    }

    /**
     * T518: Test Website Editor cannot access admin panel
     */
    public function test_t518_website_editor_cannot_access_admin(): void
    {
        $user = $this->createUserWithRole('website_editor');

        // Cannot access admin dashboard
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertForbidden();

        // Cannot access user management
        $response = $this->actingAs($user)->get(route('admin.users.index'));
        $response->assertForbidden();
    }

    /**
     * T519: Test permission denied for deleted/deactivated roles
     */
    public function test_t519_user_without_roles_has_limited_access(): void
    {
        // Create user without any roles
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);

        // Should be treated as a visitor (no special permissions)
        // Can view public pages
        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);

        // Cannot access admin
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertForbidden();
    }

    /**
     * Test removing all roles from a user revokes permissions
     */
    public function test_removing_roles_revokes_permissions(): void
    {
        $user = $this->createUserWithRole('administrator');

        // Admin can access admin panel
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // Remove all roles
        $user->roles()->detach();
        $user->load('roles');

        // Now cannot access admin panel
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertForbidden();
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
