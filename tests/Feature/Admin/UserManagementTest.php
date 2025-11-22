<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test complete user management flow
     *
     * @test
     */
    public function admin_can_manage_users_end_to_end(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->roles()->attach($adminRole);

        // Create sample user
        $regularRole = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email' => 'user@test.com', 'name' => 'Test User']);
        $user->roles()->attach($regularRole);

        // Step 1: List all users
        $listResponse = $this->actingAs($admin)->getJson('/api/admin/users');
        $listResponse->assertStatus(200);
        $this->assertCount(2, $listResponse->json('data')); // admin + user

        // Step 2: View user details
        $detailsResponse = $this->actingAs($admin)->getJson("/api/admin/users/{$user->id}");
        $detailsResponse->assertStatus(200)
            ->assertJsonPath('data.email', 'user@test.com')
            ->assertJsonPath('data.roles.0.name', 'regular_member');

        // Step 3: Update user role to premium_member
        $premiumRole = Role::where('name', 'premium_member')->first();
        $updateResponse = $this->actingAs($admin)->putJson("/api/admin/users/{$user->id}/role", [
            'role_id' => $premiumRole->id,
        ]);
        $updateResponse->assertStatus(200);

        // Step 4: Verify role change took effect
        $this->assertTrue($user->fresh()->roles->contains('name', 'premium_member'));
        $this->assertFalse($user->fresh()->roles->contains('name', 'regular_member'));

        // Step 5: Verify API quota created for premium member
        $apiQuota = ApiQuota::where('user_id', $user->id)->first();
        $this->assertNotNull($apiQuota);
        $this->assertEquals(10, $apiQuota->monthly_limit);
        $this->assertFalse($apiQuota->is_unlimited);
    }

    /**
     * Test admin can search users by name
     *
     * @test
     */
    public function admin_can_search_users_by_name(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create users with specific names
        $regularRole = Role::where('name', 'regular_member')->first();
        
        $john = User::factory()->create(['name' => 'John Doe', 'email' => 'john@test.com']);
        $john->roles()->attach($regularRole);

        $jane = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@test.com']);
        $jane->roles()->attach($regularRole);

        $bob = User::factory()->create(['name' => 'Bob Johnson', 'email' => 'bob@test.com']);
        $bob->roles()->attach($regularRole);

        // Act: Search for users with "john" in name
        $response = $this->actingAs($admin)
            ->getJson('/api/admin/users?search=john');

        // Assert: Should return John Doe and Bob Johnson
        $response->assertStatus(200);
        $users = $response->json('data');
        $names = array_column($users, 'name');

        $this->assertContains('John Doe', $names);
        $this->assertContains('Bob Johnson', $names);
        $this->assertNotContains('Jane Smith', $names);
    }

    /**
     * Test admin can search users by email
     *
     * @test
     */
    public function admin_can_search_users_by_email(): void
    {
        // Arrange: Create admin user with specific email
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->roles()->attach($adminRole);

        // Create users
        $regularRole = Role::where('name', 'regular_member')->first();

        $user1 = User::factory()->create(['email' => 'alice@example.com']);
        $user1->roles()->attach($regularRole);

        $user2 = User::factory()->create(['email' => 'bob@different.com']);
        $user2->roles()->attach($regularRole);

        // Act: Search for users with "alice" in email
        $response = $this->actingAs($admin)
            ->getJson('/api/admin/users?search=alice');

        // Assert: Should return only alice@example.com
        $response->assertStatus(200);
        $users = $response->json('data');

        $this->assertCount(1, $users);
        $this->assertEquals('alice@example.com', $users[0]['email']);
    }

    /**
     * Test admin can filter users by role
     *
     * @test
     */
    public function admin_can_filter_users_by_role(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create users with different roles
        $regularRole = Role::where('name', 'regular_member')->first();
        $premiumRole = Role::where('name', 'premium_member')->first();
        $editorRole = Role::where('name', 'website_editor')->first();

        $regular1 = User::factory()->create();
        $regular1->roles()->attach($regularRole);

        $regular2 = User::factory()->create();
        $regular2->roles()->attach($regularRole);

        $premium1 = User::factory()->create();
        $premium1->roles()->attach($premiumRole);

        $editor1 = User::factory()->create();
        $editor1->roles()->attach($editorRole);

        // Act: Filter by regular_member role
        $response = $this->actingAs($admin)
            ->getJson('/api/admin/users?role=regular_member');

        // Assert: Should return only regular members
        $response->assertStatus(200);
        $users = $response->json('data');

        $this->assertGreaterThanOrEqual(2, count($users)); // At least 2 regular members
        
        foreach ($users as $user) {
            $roleNames = array_column($user['roles'], 'name');
            $this->assertContains('regular_member', $roleNames);
        }
    }

    /**
     * Test admin can paginate user list
     *
     * @test
     */
    public function admin_can_paginate_user_list(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create 30 users
        $regularRole = Role::where('name', 'regular_member')->first();
        User::factory()->count(30)->create()->each(function ($user) use ($regularRole) {
            $user->roles()->attach($regularRole);
        });

        // Act: Request page 1 with 10 per page
        $page1 = $this->actingAs($admin)
            ->getJson('/api/admin/users?page=1&per_page=10');

        // Act: Request page 2
        $page2 = $this->actingAs($admin)
            ->getJson('/api/admin/users?page=2&per_page=10');

        // Assert: Page 1 has 10 users
        $page1->assertStatus(200);
        $this->assertCount(10, $page1->json('data'));
        $this->assertEquals(1, $page1->json('meta.current_page'));

        // Assert: Page 2 has 10 users
        $page2->assertStatus(200);
        $this->assertCount(10, $page2->json('data'));
        $this->assertEquals(2, $page2->json('meta.current_page'));

        // Assert: Page 1 and Page 2 have different users
        $page1Ids = array_column($page1->json('data'), 'id');
        $page2Ids = array_column($page2->json('data'), 'id');
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    /**
     * Test role change updates API quota for premium members
     *
     * @test
     */
    public function role_change_updates_api_quota_for_premium_members(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create regular member
        $regularRole = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create();
        $user->roles()->attach($regularRole);

        // Verify no API quota initially
        $this->assertNull(ApiQuota::where('user_id', $user->id)->first());

        // Act: Upgrade to premium_member
        $premiumRole = Role::where('name', 'premium_member')->first();
        $response = $this->actingAs($admin)
            ->putJson("/api/admin/users/{$user->id}/role", [
                'role_id' => $premiumRole->id,
            ]);

        // Assert: API quota created with correct limits
        $response->assertStatus(200);
        
        $apiQuota = ApiQuota::where('user_id', $user->id)->first();
        $this->assertNotNull($apiQuota);
        $this->assertEquals(10, $apiQuota->monthly_limit);
        $this->assertEquals(0, $apiQuota->usage_count);
        $this->assertFalse($apiQuota->is_unlimited);
        $this->assertEquals(now()->format('Y-m'), $apiQuota->current_month);
    }
}
