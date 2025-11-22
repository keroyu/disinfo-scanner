<?php

namespace Tests\Contract\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ListUsersContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /** @test */
    public function list_users_endpoint_returns_correct_json_structure()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create some regular users
        $users = User::factory()->count(3)->create();

        // Authenticate as admin
        $this->actingAs($admin);

        // Call the endpoint
        $response = $this->getJson('/api/admin/users');

        // Assert response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'email_verified_at',
                        'is_email_verified',
                        'roles' => [
                            '*' => [
                                'id',
                                'name',
                                'display_name',
                            ]
                        ],
                        'created_at',
                        'updated_at',
                    ]
                ],
                'current_page',
                'per_page',
                'total',
                'last_page',
            ]);
    }

    /** @test */
    public function list_users_requires_authentication()
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(401)
            ->assertJson([
                'message' => '請先登入'
            ]);
    }

    /** @test */
    public function list_users_requires_admin_role()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Authenticate as regular user
        $this->actingAs($user);

        // Call the endpoint
        $response = $this->getJson('/api/admin/users');

        // Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    /** @test */
    public function list_users_supports_pagination()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create 25 users
        User::factory()->count(25)->create();

        // Authenticate as admin
        $this->actingAs($admin);

        // Request first page
        $response = $this->getJson('/api/admin/users?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('current_page', 1)
            ->assertJsonCount(10, 'data');
    }

    /** @test */
    public function list_users_includes_role_information()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create user with premium member role
        $premiumUser = User::factory()->create();
        $premiumRole = Role::where('name', 'premium_member')->first();
        $premiumUser->roles()->attach($premiumRole);

        // Authenticate as admin
        $this->actingAs($admin);

        // Call the endpoint
        $response = $this->getJson('/api/admin/users');

        // Should include roles
        $response->assertStatus(200);

        $users = $response->json('data');
        $foundPremiumUser = collect($users)->first(function ($user) use ($premiumUser) {
            return $user['id'] === $premiumUser->id;
        });

        $this->assertNotNull($foundPremiumUser);
        $this->assertNotEmpty($foundPremiumUser['roles']);
        $this->assertEquals('premium_member', $foundPremiumUser['roles'][0]['name']);
    }
}
