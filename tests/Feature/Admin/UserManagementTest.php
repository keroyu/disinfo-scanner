<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /** @test */
    public function admin_can_view_user_list()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create some users
        User::factory()->count(5)->create();

        // Act as admin
        $this->actingAs($admin);

        // View user list
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'roles']
                ]
            ]);

        // Should have 6 users total (5 created + 1 admin)
        $this->assertEquals(6, $response->json('total'));
    }

    /** @test */
    public function admin_can_change_user_role_from_regular_to_premium()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create regular member
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Act as admin
        $this->actingAs($admin);

        // Change to premium member
        $premiumRole = Role::where('name', 'premium_member')->first();
        $response = $this->putJson("/api/admin/users/{$user->id}/role", [
            'role_id' => $premiumRole->id
        ]);

        $response->assertStatus(200);

        // Verify role changed
        $user->refresh();
        $this->assertTrue($user->roles->contains('name', 'premium_member'));
        $this->assertFalse($user->roles->contains('name', 'regular_member'));
    }

    /** @test */
    public function admin_cannot_change_own_permission_level()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Act as admin
        $this->actingAs($admin);

        // Try to change own role to regular member
        $regularRole = Role::where('name', 'regular_member')->first();
        $response = $this->putJson("/api/admin/users/{$admin->id}/role", [
            'role_id' => $regularRole->id
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => '不能變更自己的權限等級'
            ]);

        // Verify role didn't change
        $admin->refresh();
        $this->assertTrue($admin->roles->contains('name', 'administrator'));
    }

    /** @test */
    public function admin_can_search_users_by_email()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create users with specific emails
        User::factory()->create(['email' => 'john@example.com', 'name' => 'John Doe']);
        User::factory()->create(['email' => 'jane@example.com', 'name' => 'Jane Smith']);
        User::factory()->create(['email' => 'bob@test.com', 'name' => 'Bob Wilson']);

        // Act as admin
        $this->actingAs($admin);

        // Search for "john@" (more specific to find only email)
        $response = $this->getJson('/api/admin/users?search=john@');

        $response->assertStatus(200);

        $users = $response->json('data');
        $this->assertCount(1, $users);
        $this->assertEquals('john@example.com', $users[0]['email']);
    }

    /** @test */
    public function admin_can_filter_users_by_role()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create premium members
        $premiumRole = Role::where('name', 'premium_member')->first();
        $premiumUser1 = User::factory()->create();
        $premiumUser1->roles()->attach($premiumRole);
        $premiumUser2 = User::factory()->create();
        $premiumUser2->roles()->attach($premiumRole);

        // Create regular members
        $regularRole = Role::where('name', 'regular_member')->first();
        $regularUser = User::factory()->create();
        $regularUser->roles()->attach($regularRole);

        // Act as admin
        $this->actingAs($admin);

        // Filter by premium_member role
        $response = $this->getJson("/api/admin/users?role={$premiumRole->id}");

        $response->assertStatus(200);

        // Should only return premium members
        $users = $response->json('data');
        $this->assertCount(2, $users);
    }

    /** @test */
    public function role_change_takes_effect_immediately()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create regular member
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Act as admin
        $this->actingAs($admin);

        // Change to premium member
        $premiumRole = Role::where('name', 'premium_member')->first();
        $this->putJson("/api/admin/users/{$user->id}/role", [
            'role_id' => $premiumRole->id
        ]);

        // Immediately check user's roles (no re-login required)
        $user->refresh();
        $this->assertTrue($user->roles->contains('name', 'premium_member'));
    }

    /** @test */
    public function admin_can_view_user_details()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create target user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        // Act as admin
        $this->actingAs($admin);

        // View user details
        $response = $this->getJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Test User')
            ->assertJsonPath('email', 'test@example.com')
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'roles',
                'api_quota',
                'identity_verification',
            ]);
    }

    /** @test */
    public function non_admin_cannot_access_user_list()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Act as regular user
        $this->actingAs($user);

        // Try to access user list
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    /** @test */
    public function non_admin_cannot_change_user_roles()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Create target user
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($regularRole);

        // Act as regular user
        $this->actingAs($user);

        // Try to change role
        $premiumRole = Role::where('name', 'premium_member')->first();
        $response = $this->putJson("/api/admin/users/{$targetUser->id}/role", [
            'role_id' => $premiumRole->id
        ]);

        $response->assertStatus(403);

        // Verify role didn't change
        $targetUser->refresh();
        $this->assertTrue($targetUser->roles->contains('name', 'regular_member'));
    }
}
