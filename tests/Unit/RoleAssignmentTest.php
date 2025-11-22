<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /** @test */
    public function user_can_be_assigned_a_role()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'regular_member')->first();

        $user->roles()->attach($role);

        $this->assertTrue($user->roles->contains($role));
    }

    /** @test */
    public function user_can_have_multiple_roles()
    {
        $user = User::factory()->create();
        $role1 = Role::where('name', 'regular_member')->first();
        $role2 = Role::where('name', 'website_editor')->first();

        $user->roles()->attach([$role1->id, $role2->id]);

        $this->assertCount(2, $user->roles);
        $this->assertTrue($user->roles->contains($role1));
        $this->assertTrue($user->roles->contains($role2));
    }

    /** @test */
    public function user_role_can_be_changed()
    {
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $premiumRole = Role::where('name', 'premium_member')->first();

        // Assign initial role
        $user->roles()->attach($regularRole);
        $this->assertTrue($user->roles->contains($regularRole));

        // Change role
        $user->roles()->sync([$premiumRole->id]);
        $user->refresh();

        $this->assertCount(1, $user->roles);
        $this->assertTrue($user->roles->contains($premiumRole));
        $this->assertFalse($user->roles->contains($regularRole));
    }

    /** @test */
    public function user_role_can_be_removed()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'regular_member')->first();

        $user->roles()->attach($role);
        $this->assertTrue($user->roles->contains($role));

        $user->roles()->detach($role);
        $user->refresh();

        $this->assertCount(0, $user->roles);
        $this->assertFalse($user->roles->contains($role));
    }

    /** @test */
    public function role_has_correct_attributes()
    {
        $adminRole = Role::where('name', 'administrator')->first();

        $this->assertNotNull($adminRole);
        $this->assertEquals('administrator', $adminRole->name);
        $this->assertEquals('管理員', $adminRole->display_name);
        $this->assertNotNull($adminRole->description);
    }

    /** @test */
    public function all_five_roles_exist()
    {
        $roles = Role::all();

        $this->assertCount(5, $roles);

        $roleNames = $roles->pluck('name')->toArray();
        $this->assertContains('visitor', $roleNames);
        $this->assertContains('regular_member', $roleNames);
        $this->assertContains('premium_member', $roleNames);
        $this->assertContains('website_editor', $roleNames);
        $this->assertContains('administrator', $roleNames);
    }

    /** @test */
    public function role_sync_replaces_all_existing_roles()
    {
        $user = User::factory()->create();
        $role1 = Role::where('name', 'regular_member')->first();
        $role2 = Role::where('name', 'premium_member')->first();
        $role3 = Role::where('name', 'website_editor')->first();

        // Assign multiple roles
        $user->roles()->attach([$role1->id, $role2->id]);
        $this->assertCount(2, $user->roles);

        // Sync to single role (should replace all)
        $user->roles()->sync([$role3->id]);
        $user->refresh();

        $this->assertCount(1, $user->roles);
        $this->assertTrue($user->roles->contains($role3));
        $this->assertFalse($user->roles->contains($role1));
        $this->assertFalse($user->roles->contains($role2));
    }

    /** @test */
    public function user_without_roles_has_empty_roles_collection()
    {
        $user = User::factory()->create();

        $this->assertCount(0, $user->roles);
        $this->assertTrue($user->roles->isEmpty());
    }

    /** @test */
    public function role_assignment_persists_to_database()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'premium_member')->first();

        $user->roles()->attach($role);

        // Create fresh instance from database
        $freshUser = User::find($user->id);

        $this->assertTrue($freshUser->roles->contains($role));
    }

    /** @test */
    public function role_relationships_are_correctly_defined()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'administrator')->first();

        $user->roles()->attach($role);

        // Test user->roles relationship
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->roles);

        // Test role->users relationship
        $this->assertTrue($role->users->contains($user));
    }

    /** @test */
    public function duplicate_role_assignment_is_prevented()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'regular_member')->first();

        // Attach same role twice
        $user->roles()->attach($role);
        $user->roles()->attach($role); // This should not create duplicate

        $user->refresh();

        // Should only have one instance of the role
        $this->assertCount(1, $user->roles);
    }

    /** @test */
    public function role_can_be_assigned_during_user_creation()
    {
        $role = Role::where('name', 'regular_member')->first();

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->assertCount(1, $user->roles);
        $this->assertEquals($role->id, $user->roles->first()->id);
    }
}
