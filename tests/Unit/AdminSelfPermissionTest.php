<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;

class AdminSelfPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test UserPolicy prevents admin from updating own role
     *
     * @test
     */
    public function user_policy_prevents_admin_from_updating_own_role(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $policy = new UserPolicy();

        // Act: Check if admin can update own role
        $canUpdate = $policy->updateRole($admin, $admin);

        // Assert: Permission denied
        $this->assertFalse($canUpdate);
    }

    /**
     * Test UserPolicy allows admin to update other users' roles
     *
     * @test
     */
    public function user_policy_allows_admin_to_update_other_users_roles(): void
    {
        // Arrange: Create admin user and target user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $regularRole = Role::where('name', 'regular_member')->first();
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($regularRole);

        $policy = new UserPolicy();

        // Act: Check if admin can update target user's role
        $canUpdate = $policy->updateRole($admin, $targetUser);

        // Assert: Permission granted
        $this->assertTrue($canUpdate);
    }

    /**
     * Test UserPolicy denies non-admin from updating any role
     *
     * @test
     */
    public function user_policy_denies_non_admin_from_updating_any_role(): void
    {
        // Arrange: Create two regular users
        $regularRole = Role::where('name', 'regular_member')->first();
        
        $user1 = User::factory()->create();
        $user1->roles()->attach($regularRole);

        $user2 = User::factory()->create();
        $user2->roles()->attach($regularRole);

        $policy = new UserPolicy();

        // Act: Check if user1 can update user2's role
        $canUpdate = $policy->updateRole($user1, $user2);

        // Assert: Permission denied
        $this->assertFalse($canUpdate);
    }

    /**
     * Test isAdmin method correctly identifies administrators
     *
     * @test
     */
    public function is_admin_method_correctly_identifies_administrators(): void
    {
        // Arrange: Create users with different roles
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $regularRole = Role::where('name', 'regular_member')->first();
        $regularUser = User::factory()->create();
        $regularUser->roles()->attach($regularRole);

        $editorRole = Role::where('name', 'website_editor')->first();
        $editor = User::factory()->create();
        $editor->roles()->attach($editorRole);

        $policy = new UserPolicy();

        // Act & Assert: Only admin returns true
        $this->assertTrue($policy->isAdmin($admin));
        $this->assertFalse($policy->isAdmin($regularUser));
        $this->assertFalse($policy->isAdmin($editor));
    }

    /**
     * Test admin can delete other users but not self
     *
     * @test
     */
    public function admin_can_delete_other_users_but_not_self(): void
    {
        // Arrange: Create admin user and target user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $regularRole = Role::where('name', 'regular_member')->first();
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($regularRole);

        $policy = new UserPolicy();

        // Act & Assert: Cannot delete self
        $this->assertFalse($policy->delete($admin, $admin));

        // Act & Assert: Can delete other users
        $this->assertTrue($policy->delete($admin, $targetUser));
    }

    /**
     * Test viewAny permission requires admin role
     *
     * @test
     */
    public function view_any_permission_requires_admin_role(): void
    {
        // Arrange: Create admin and regular user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $regularRole = Role::where('name', 'regular_member')->first();
        $regularUser = User::factory()->create();
        $regularUser->roles()->attach($regularRole);

        $policy = new UserPolicy();

        // Act & Assert: Only admin can view any
        $this->assertTrue($policy->viewAny($admin));
        $this->assertFalse($policy->viewAny($regularUser));
    }
}
