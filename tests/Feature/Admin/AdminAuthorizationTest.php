<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /** @test */
    public function visitor_cannot_access_admin_panel()
    {
        // No authentication

        $response = $this->getJson('/api/admin/users');

        // Laravel's default auth middleware returns "Unauthenticated."
        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    /** @test */
    public function regular_member_cannot_access_admin_panel()
    {
        // Create regular member
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Act as regular member
        $this->actingAs($user);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    /** @test */
    public function premium_member_cannot_access_admin_panel()
    {
        // Create premium member
        $user = User::factory()->create();
        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole);

        // Act as premium member
        $this->actingAs($user);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    /** @test */
    public function website_editor_cannot_access_admin_panel()
    {
        // Create website editor
        $user = User::factory()->create();
        $editorRole = Role::where('name', 'website_editor')->first();
        $user->roles()->attach($editorRole);

        // Act as website editor
        $this->actingAs($user);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    /** @test */
    public function administrator_can_access_admin_panel()
    {
        // Create administrator
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Act as administrator
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_role_is_checked_on_every_request()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Act as admin
        $this->actingAs($admin);

        // First request succeeds
        $response1 = $this->getJson('/api/admin/users');
        $response1->assertStatus(200);

        // Simulate role removal (admin demoted)
        $admin->roles()->detach($adminRole);

        // Second request should fail
        $response2 = $this->getJson('/api/admin/users');
        $response2->assertStatus(403);
    }

    /** @test */
    public function admin_authorization_works_for_all_admin_endpoints()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create target user
        $user = User::factory()->create();

        // Act as admin
        $this->actingAs($admin);

        // Test list users endpoint
        $response1 = $this->getJson('/api/admin/users');
        $response1->assertStatus(200);

        // Test get user details endpoint
        $response2 = $this->getJson("/api/admin/users/{$user->id}");
        $response2->assertStatus(200);

        // Test update user role endpoint
        $regularRole = Role::where('name', 'regular_member')->first();
        $response3 = $this->putJson("/api/admin/users/{$user->id}/role", [
            'role_id' => $regularRole->id
        ]);
        $response3->assertStatus(200);
    }

    /** @test */
    public function admin_cannot_elevate_to_admin_from_regular_user()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Create target user
        $targetUser = User::factory()->create();

        // Act as regular user
        $this->actingAs($user);

        // Try to change target user's role
        $adminRole = Role::where('name', 'administrator')->first();
        $response = $this->putJson("/api/admin/users/{$targetUser->id}/role", [
            'role_id' => $adminRole->id
        ]);

        // Should be forbidden (no access to endpoint at all)
        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_requests_are_rejected()
    {
        // Create a user
        $user = User::factory()->create();

        // Try to access admin endpoints without authentication

        // List users
        $response1 = $this->getJson('/api/admin/users');
        $response1->assertStatus(401);

        // Get user details
        $response2 = $this->getJson("/api/admin/users/{$user->id}");
        $response2->assertStatus(401);

        // Update user role
        $regularRole = Role::where('name', 'regular_member')->first();
        $response3 = $this->putJson("/api/admin/users/{$user->id}/role", [
            'role_id' => $regularRole->id
        ]);
        $response3->assertStatus(401);
    }

    /** @test */
    public function admin_middleware_returns_json_for_api_requests()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Act as regular user
        $this->actingAs($user);

        // API request should return JSON
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }
}
