<?php

namespace Tests\Contract\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;

class ListUsersContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test list users endpoint returns correct JSON structure
     *
     * @test
     */
    public function list_users_returns_correct_json_structure(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $admin->roles()->attach($adminRole);

        // Create sample users
        $regularRole = Role::where('name', 'regular_member')->first();
        $user1 = User::factory()->create(['email' => 'user1@test.com']);
        $user1->roles()->attach($regularRole);

        $user2 = User::factory()->create(['email' => 'user2@test.com']);
        $user2->roles()->attach($regularRole);

        // Act: Request list users endpoint
        $response = $this->actingAs($admin)
            ->getJson('/admin/users');

        // Assert: Verify response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'is_email_verified',
                        'roles' => [
                            '*' => [
                                'id',
                                'name',
                                'display_name',
                            ]
                        ],
                        'created_at',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ]
            ]);

        // Verify pagination meta is correct
        $response->assertJsonPath('meta.total', 3); // admin + 2 users
    }

    /**
     * Test list users endpoint requires admin authentication
     *
     * @test
     */
    public function list_users_requires_admin_authentication(): void
    {
        // Arrange: Create regular user (not admin)
        $regularRole = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create();
        $user->roles()->attach($regularRole);

        // Act & Assert: Non-admin cannot access
        $response = $this->actingAs($user)
            ->getJson('/admin/users');

        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    /**
     * Test list users endpoint supports pagination
     *
     * @test
     */
    public function list_users_supports_pagination(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create 25 users
        $regularRole = Role::where('name', 'regular_member')->first();
        User::factory()->count(25)->create()->each(function ($user) use ($regularRole) {
            $user->roles()->attach($regularRole);
        });

        // Act: Request first page (15 per page)
        $response = $this->actingAs($admin)
            ->getJson('/admin/users?page=1&per_page=15');

        // Assert: Verify pagination
        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1);

        $this->assertCount(15, $response->json('data'));
    }

    /**
     * Test list users endpoint supports search filtering
     *
     * @test
     */
    public function list_users_supports_search_filtering(): void
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

        // Act: Search for "john"
        $response = $this->actingAs($admin)
            ->getJson('/admin/users?search=john');

        // Assert: Only John Doe returned
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('John Doe', $data[0]['name']);
    }

    /**
     * Test list users endpoint supports role filtering
     *
     * @test
     */
    public function list_users_supports_role_filtering(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create users with different roles
        $regularRole = Role::where('name', 'regular_member')->first();
        $premiumRole = Role::where('name', 'premium_member')->first();

        $regular1 = User::factory()->create();
        $regular1->roles()->attach($regularRole);

        $premium1 = User::factory()->create();
        $premium1->roles()->attach($premiumRole);

        // Act: Filter by regular_member role
        $response = $this->actingAs($admin)
            ->getJson('/admin/users?role=regular_member');

        // Assert: Only regular members returned
        $response->assertStatus(200);
        $data = $response->json('data');

        foreach ($data as $user) {
            $this->assertEquals('regular_member', $user['roles'][0]['name']);
        }
    }
}
