<?php

namespace Tests\Contract\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UpdateUserRoleContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /** @test */
    public function update_user_role_endpoint_returns_correct_json_structure()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create target user
        $targetUser = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $targetUser->roles()->attach($regularRole);

        // Get premium role ID
        $premiumRole = Role::where('name', 'premium_member')->first();

        // Authenticate as admin
        $this->actingAs($admin);

        // Update user role
        $response = $this->putJson("/api/admin/users/{$targetUser->id}/role", [
            'role_id' => $premiumRole->id
        ]);

        // Assert response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'roles' => [
                        '*' => [
                            'id',
                            'name',
                            'display_name',
                        ]
                    ],
                ]
            ])
            ->assertJsonPath('user.id', $targetUser->id);
    }

    /** @test */
    public function update_user_role_requires_authentication()
    {
        $user = User::factory()->create();
        $premiumRole = Role::where('name', 'premium_member')->first();

        $response = $this->putJson("/api/admin/users/{$user->id}/role", [
            'role_id' => $premiumRole->id
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => '請先登入'
            ]);
    }

    /** @test */
    public function update_user_role_requires_admin_role()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Target user
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($regularRole);

        // Premium role
        $premiumRole = Role::where('name', 'premium_member')->first();

        // Authenticate as regular user
        $this->actingAs($user);

        // Try to update role
        $response = $this->putJson("/api/admin/users/{$targetUser->id}/role", [
            'role_id' => $premiumRole->id
        ]);

        // Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    /** @test */
    public function update_user_role_prevents_self_permission_change()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Get regular role
        $regularRole = Role::where('name', 'regular_member')->first();

        // Authenticate as admin
        $this->actingAs($admin);

        // Try to change own role
        $response = $this->putJson("/api/admin/users/{$admin->id}/role", [
            'role_id' => $regularRole->id
        ]);

        // Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'message' => '不能變更自己的權限等級'
            ]);
    }

    /** @test */
    public function update_user_role_validates_required_fields()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Target user
        $targetUser = User::factory()->create();

        // Authenticate as admin
        $this->actingAs($admin);

        // Missing role_id
        $response = $this->putJson("/api/admin/users/{$targetUser->id}/role", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    /** @test */
    public function update_user_role_validates_role_exists()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Target user
        $targetUser = User::factory()->create();

        // Authenticate as admin
        $this->actingAs($admin);

        // Invalid role ID
        $response = $this->putJson("/api/admin/users/{$targetUser->id}/role", [
            'role_id' => 99999
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    /** @test */
    public function update_user_role_replaces_existing_roles()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create target user with regular role
        $targetUser = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $targetUser->roles()->attach($regularRole);

        // Get premium role
        $premiumRole = Role::where('name', 'premium_member')->first();

        // Authenticate as admin
        $this->actingAs($admin);

        // Update to premium role
        $response = $this->putJson("/api/admin/users/{$targetUser->id}/role", [
            'role_id' => $premiumRole->id
        ]);

        $response->assertStatus(200);

        // Refresh user
        $targetUser->refresh();

        // Should only have premium role now
        $this->assertCount(1, $targetUser->roles);
        $this->assertEquals('premium_member', $targetUser->roles->first()->name);
    }
}
