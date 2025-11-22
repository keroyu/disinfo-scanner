<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test only administrators can access admin panel
     *
     * @test
     */
    public function only_administrators_can_access_admin_panel(): void
    {
        // Arrange: Create users with different roles
        $roles = [
            'regular_member',
            'premium_member',
            'website_editor',
            'administrator',
        ];

        $users = [];
        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            $user = User::factory()->create();
            $user->roles()->attach($role);
            $users[$roleName] = $user;
        }

        // Act & Assert: Only administrator can access
        foreach ($users as $roleName => $user) {
            $response = $this->actingAs($user)->getJson('/admin/users');

            if ($roleName === 'administrator') {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(403)
                    ->assertJson(['message' => '無權限訪問此功能']);
            }
        }
    }

    /**
     * Test unauthenticated users cannot access admin panel
     *
     * @test
     */
    public function unauthenticated_users_cannot_access_admin_panel(): void
    {
        // Act: Access admin panel without authentication
        $response = $this->getJson('/admin/users');

        // Assert: Unauthorized (Laravel's default message for unauthenticated API requests)
        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * Test admin cannot change own permission level
     *
     * @test
     */
    public function admin_cannot_change_own_permission_level(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Act: Try to demote self to regular member
        $regularRole = Role::where('name', 'regular_member')->first();
        $response = $this->actingAs($admin)
            ->putJson("/api/admin/users/{$admin->id}/role", [
                'role_id' => $regularRole->id,
            ]);

        // Assert: Request denied
        $response->assertStatus(403)
            ->assertJson(['message' => '您無法變更自己的權限等級']);

        // Verify role unchanged
        $this->assertTrue($admin->fresh()->roles->contains('name', 'administrator'));
        $this->assertFalse($admin->fresh()->roles->contains('name', 'regular_member'));
    }

    /**
     * Test admin can change other users' roles
     *
     * @test
     */
    public function admin_can_change_other_users_roles(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create target user
        $regularRole = Role::where('name', 'regular_member')->first();
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($regularRole);

        // Act: Change target user's role to premium_member
        $premiumRole = Role::where('name', 'premium_member')->first();
        $response = $this->actingAs($admin)
            ->putJson("/api/admin/users/{$targetUser->id}/role", [
                'role_id' => $premiumRole->id,
            ]);

        // Assert: Request successful
        $response->assertStatus(200);

        // Verify role changed
        $this->assertTrue($targetUser->fresh()->roles->contains('name', 'premium_member'));
        $this->assertFalse($targetUser->fresh()->roles->contains('name', 'regular_member'));
    }

    /**
     * Test non-admin cannot view other users' details
     *
     * @test
     */
    public function non_admin_cannot_view_other_users_details(): void
    {
        // Arrange: Create two regular members
        $regularRole = Role::where('name', 'regular_member')->first();
        
        $user1 = User::factory()->create();
        $user1->roles()->attach($regularRole);

        $user2 = User::factory()->create();
        $user2->roles()->attach($regularRole);

        // Act: User1 tries to view User2's details
        $response = $this->actingAs($user1)
            ->getJson("/api/admin/users/{$user2->id}");

        // Assert: Request denied
        $response->assertStatus(403)
            ->assertJson(['message' => '無權限訪問此功能']);
    }

    /**
     * Test non-admin cannot change other users' roles
     *
     * @test
     */
    public function non_admin_cannot_change_other_users_roles(): void
    {
        // Arrange: Create two regular members
        $regularRole = Role::where('name', 'regular_member')->first();
        
        $user1 = User::factory()->create();
        $user1->roles()->attach($regularRole);

        $user2 = User::factory()->create();
        $user2->roles()->attach($regularRole);

        // Act: User1 tries to change User2's role
        $premiumRole = Role::where('name', 'premium_member')->first();
        $response = $this->actingAs($user1)
            ->putJson("/api/admin/users/{$user2->id}/role", [
                'role_id' => $premiumRole->id,
            ]);

        // Assert: Request denied
        $response->assertStatus(403)
            ->assertJson(['message' => '無權限訪問此功能']);

        // Verify role unchanged
        $this->assertTrue($user2->fresh()->roles->contains('name', 'regular_member'));
    }

    /**
     * Test website editor cannot access admin panel
     *
     * @test
     */
    public function website_editor_cannot_access_admin_panel(): void
    {
        // Arrange: Create website editor
        $editorRole = Role::where('name', 'website_editor')->first();
        $editor = User::factory()->create();
        $editor->roles()->attach($editorRole);

        // Act: Editor tries to access admin panel
        $response = $this->actingAs($editor)
            ->getJson('/admin/users');

        // Assert: Request denied
        $response->assertStatus(403)
            ->assertJson(['message' => '無權限訪問此功能']);
    }

    /**
     * Test admin can elevate user to administrator role
     *
     * @test
     */
    public function admin_can_elevate_user_to_administrator_role(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create regular user
        $regularRole = Role::where('name', 'regular_member')->first();
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($regularRole);

        // Act: Elevate target user to administrator
        $response = $this->actingAs($admin)
            ->putJson("/api/admin/users/{$targetUser->id}/role", [
                'role_id' => $adminRole->id,
            ]);

        // Assert: Request successful
        $response->assertStatus(200);

        // Verify user is now administrator
        $freshUser = $targetUser->fresh();
        $this->assertTrue($freshUser->roles->contains('name', 'administrator'));

        // Verify no longer has old role
        $this->assertFalse($freshUser->roles->contains('name', 'regular_member'));
    }
}
