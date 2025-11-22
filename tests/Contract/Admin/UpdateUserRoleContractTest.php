<?php

namespace Tests\Contract\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;

class UpdateUserRoleContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test update user role endpoint returns correct JSON structure
     *
     * @test
     */
    public function update_user_role_returns_correct_json_structure(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create target user
        $regularRole = Role::where('name', 'regular_member')->first();
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($regularRole);

        // Act: Update user role to premium_member
        $premiumRole = Role::where('name', 'premium_member')->first();
        $response = $this->actingAs($admin)
            ->putJson("/admin/users/{$targetUser->id}/role", [
                'role_id' => $premiumRole->id,
            ]);

        // Assert: Verify response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
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
            ]);

        // Verify role was updated
        $this->assertTrue($targetUser->fresh()->roles->contains('name', 'premium_member'));
    }

    /**
     * Test admin cannot change own role
     *
     * @test
     */
    public function admin_cannot_change_own_role(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Act: Try to change own role
        $regularRole = Role::where('name', 'regular_member')->first();
        $response = $this->actingAs($admin)
            ->putJson("/admin/users/{$admin->id}/role", [
                'role_id' => $regularRole->id,
            ]);

        // Assert: Request denied
        $response->assertStatus(403)
            ->assertJson([
                'message' => '您無法變更自己的權限等級'
            ]);

        // Verify role unchanged
        $this->assertTrue($admin->fresh()->roles->contains('name', 'administrator'));
    }

    /**
     * Test update user role requires admin authentication
     *
     * @test
     */
    public function update_user_role_requires_admin_authentication(): void
    {
        // Arrange: Create regular users
        $regularRole = Role::where('name', 'regular_member')->first();
        
        $user1 = User::factory()->create();
        $user1->roles()->attach($regularRole);

        $user2 = User::factory()->create();
        $user2->roles()->attach($regularRole);

        // Act: Regular user tries to change another user's role
        $premiumRole = Role::where('name', 'premium_member')->first();
        $response = $this->actingAs($user1)
            ->putJson("/admin/users/{$user2->id}/role", [
                'role_id' => $premiumRole->id,
            ]);

        // Assert: Request denied
        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);

        // Verify role unchanged
        $this->assertTrue($user2->fresh()->roles->contains('name', 'regular_member'));
    }

    /**
     * Test update user role validates role_id
     *
     * @test
     */
    public function update_user_role_validates_role_id(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create target user
        $regularRole = Role::where('name', 'regular_member')->first();
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($regularRole);

        // Act: Try to update with invalid role_id
        $response = $this->actingAs($admin)
            ->putJson("/admin/users/{$targetUser->id}/role", [
                'role_id' => 999, // Non-existent role
            ]);

        // Assert: Validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    /**
     * Test update user role validates user exists
     *
     * @test
     */
    public function update_user_role_validates_user_exists(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Act: Try to update non-existent user
        $premiumRole = Role::where('name', 'premium_member')->first();
        $response = $this->actingAs($admin)
            ->putJson("/admin/users/99999/role", [
                'role_id' => $premiumRole->id,
            ]);

        // Assert: Not found
        $response->assertStatus(404)
            ->assertJson([
                'message' => '找不到指定的使用者'
            ]);
    }

    /**
     * Test role change takes effect immediately
     *
     * @test
     */
    public function role_change_takes_effect_immediately(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create target user
        $regularRole = Role::where('name', 'regular_member')->first();
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($regularRole);

        // Act: Change role to premium_member
        $premiumRole = Role::where('name', 'premium_member')->first();
        $response = $this->actingAs($admin)
            ->putJson("/admin/users/{$targetUser->id}/role", [
                'role_id' => $premiumRole->id,
            ]);

        $response->assertStatus(200);

        // Assert: Check user has new role immediately
        $freshUser = $targetUser->fresh();
        $this->assertFalse($freshUser->roles->contains('name', 'regular_member'));
        $this->assertTrue($freshUser->roles->contains('name', 'premium_member'));
    }
}
