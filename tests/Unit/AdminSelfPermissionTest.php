<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminSelfPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /** @test */
    public function admin_cannot_change_own_role_via_policy()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create policy instance
        $policy = new UserPolicy();

        // Check if admin can update own role
        $canUpdate = $policy->updateRole($admin, $admin);

        $this->assertFalse($canUpdate);
    }

    /** @test */
    public function admin_can_change_other_users_roles_via_policy()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create target user
        $targetUser = User::factory()->create();

        // Create policy instance
        $policy = new UserPolicy();

        // Check if admin can update other user's role
        $canUpdate = $policy->updateRole($admin, $targetUser);

        $this->assertTrue($canUpdate);
    }

    /** @test */
    public function non_admin_cannot_change_any_roles_via_policy()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Create target user
        $targetUser = User::factory()->create();

        // Create policy instance
        $policy = new UserPolicy();

        // Check if regular user can update roles
        $canUpdate = $policy->updateRole($user, $targetUser);

        $this->assertFalse($canUpdate);
    }

    /** @test */
    public function policy_correctly_identifies_admin_users()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create policy instance
        $policy = new UserPolicy();

        $isAdmin = $policy->isAdmin($admin);

        $this->assertTrue($isAdmin);
    }

    /** @test */
    public function policy_correctly_identifies_non_admin_users()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Create policy instance
        $policy = new UserPolicy();

        $isAdmin = $policy->isAdmin($user);

        $this->assertFalse($isAdmin);
    }

    /** @test */
    public function self_permission_change_prevention_is_identity_based()
    {
        // Create two admins
        $admin1 = User::factory()->create(['email' => 'admin1@test.com']);
        $admin2 = User::factory()->create(['email' => 'admin2@test.com']);

        $adminRole = Role::where('name', 'administrator')->first();
        $admin1->roles()->attach($adminRole);
        $admin2->roles()->attach($adminRole);

        // Create policy instance
        $policy = new UserPolicy();

        // Admin1 can change admin2's role
        $canChangeOther = $policy->updateRole($admin1, $admin2);
        $this->assertTrue($canChangeOther);

        // Admin1 cannot change own role
        $canChangeSelf = $policy->updateRole($admin1, $admin1);
        $this->assertFalse($canChangeSelf);

        // Admin2 cannot change own role
        $admin2CanChangeSelf = $policy->updateRole($admin2, $admin2);
        $this->assertFalse($admin2CanChangeSelf);
    }

    /** @test */
    public function admin_with_no_role_relationship_is_not_admin()
    {
        // Create user without any roles
        $user = User::factory()->create();

        // Create policy instance
        $policy = new UserPolicy();

        $isAdmin = $policy->isAdmin($user);

        $this->assertFalse($isAdmin);
    }

    /** @test */
    public function admin_can_view_any_users()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create policy instance
        $policy = new UserPolicy();

        $canViewAny = $policy->viewAny($admin);

        $this->assertTrue($canViewAny);
    }

    /** @test */
    public function non_admin_cannot_view_any_users()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Create policy instance
        $policy = new UserPolicy();

        $canViewAny = $policy->viewAny($user);

        $this->assertFalse($canViewAny);
    }

    /** @test */
    public function admin_cannot_delete_themselves()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create policy instance
        $policy = new UserPolicy();

        $canDelete = $policy->delete($admin, $admin);

        $this->assertFalse($canDelete);
    }

    /** @test */
    public function admin_can_delete_other_users()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create target user
        $targetUser = User::factory()->create();

        // Create policy instance
        $policy = new UserPolicy();

        $canDelete = $policy->delete($admin, $targetUser);

        $this->assertTrue($canDelete);
    }
}
