<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Services\BatchRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSuspensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /**
     * T048: Test suspended users cannot login
     */
    public function test_suspended_user_cannot_login(): void
    {
        // Create a suspended user with email verified and password set
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => bcrypt('password'),
            'is_email_verified' => true,
            'has_default_password' => false,  // Important: user has set their password
        ]);
        $suspendedRole = Role::where('name', 'suspended')->first();
        $user->roles()->attach($suspendedRole->id);

        // Attempt login
        $response = $this->postJson('/api/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => '您的帳號已被停權，請聯繫管理員',
        ]);
    }

    /**
     * T048: Test isSuspended method works correctly
     */
    public function test_is_suspended_returns_true_for_suspended_users(): void
    {
        $user = User::factory()->create();
        $suspendedRole = Role::where('name', 'suspended')->first();
        $user->roles()->attach($suspendedRole->id);

        $this->assertTrue($user->isSuspended());
    }

    /**
     * T048: Test isSuspended returns false for normal users
     */
    public function test_is_suspended_returns_false_for_normal_users(): void
    {
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        $this->assertFalse($user->isSuspended());
    }

    /**
     * T049: Test admin cannot suspend themselves
     */
    public function test_admin_cannot_suspend_themselves(): void
    {
        $admin = User::factory()->create([
            'is_email_verified' => true,
        ]);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole->id);

        $batchRoleService = new BatchRoleService();
        $suspendedRole = Role::where('name', 'suspended')->first();

        $result = $batchRoleService->changeRoles([$admin->id], $suspendedRole->id, $admin->id);

        $this->assertEquals(0, $result['updated_count']);
        $this->assertEquals(1, $result['skipped_self']);
    }

    /**
     * T049: Test batch suspend via API endpoint
     */
    public function test_batch_suspend_via_api(): void
    {
        // Create admin user
        $admin = User::factory()->create(['is_email_verified' => true]);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole->id);

        // Create regular users to suspend
        $user1 = User::factory()->create();
        $user1->roles()->attach(Role::where('name', 'regular_member')->first()->id);

        $user2 = User::factory()->create();
        $user2->roles()->attach(Role::where('name', 'regular_member')->first()->id);

        // Call batch suspend API
        $response = $this->actingAs($admin)->postJson('/api/admin/users/batch-suspend', [
            'user_ids' => [$user1->id, $user2->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Verify users are suspended
        $user1->refresh();
        $user2->refresh();
        $this->assertTrue($user1->isSuspended());
        $this->assertTrue($user2->isSuspended());
    }

    /**
     * T049: Test already suspended users are skipped
     */
    public function test_already_suspended_users_are_skipped(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole->id);

        // Create already suspended user
        $user = User::factory()->create();
        $suspendedRole = Role::where('name', 'suspended')->first();
        $user->roles()->attach($suspendedRole->id);

        $batchRoleService = new BatchRoleService();
        $result = $batchRoleService->changeRoles([$user->id], $suspendedRole->id, $admin->id);

        $this->assertEquals(0, $result['updated_count']);
        $this->assertEquals(1, $result['skipped_already_suspended']);
    }

    /**
     * T049: Test unsuspension flow
     */
    public function test_unsuspension_changes_role_from_suspended(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole->id);

        // Create suspended user
        $user = User::factory()->create();
        $suspendedRole = Role::where('name', 'suspended')->first();
        $user->roles()->attach($suspendedRole->id);

        // Verify user is suspended
        $this->assertTrue($user->isSuspended());

        // Change to regular member
        $batchRoleService = new BatchRoleService();
        $regularRole = Role::where('name', 'regular_member')->first();
        $result = $batchRoleService->changeRoles([$user->id], $regularRole->id, $admin->id);

        $this->assertEquals(1, $result['updated_count']);
        $this->assertEquals(1, $result['unsuspended_count']);

        // Verify user is no longer suspended
        $user->refresh();
        $this->assertFalse($user->isSuspended());
        $this->assertTrue($user->hasRole('regular_member'));
    }
}
