<?php

namespace Tests\Contract\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\IdentityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ListVerificationRequestsContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /** @test */
    public function list_verifications_endpoint_returns_correct_json_structure()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create users with verification requests
        $user1 = User::factory()->create();
        IdentityVerification::create([
            'user_id' => $user1->id,
            'verification_method' => 'ID card upload',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        $user2 = User::factory()->create();
        IdentityVerification::create([
            'user_id' => $user2->id,
            'verification_method' => 'Passport scan',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Call the endpoint
        $response = $this->getJson('/api/admin/verifications');

        // Assert response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'user' => [
                            'id',
                            'name',
                            'email',
                        ],
                        'verification_method',
                        'verification_status',
                        'submitted_at',
                        'reviewed_at',
                        'notes',
                    ]
                ],
                'current_page',
                'per_page',
                'total',
                'last_page',
            ]);
    }

    /** @test */
    public function list_verifications_requires_authentication()
    {
        $response = $this->getJson('/api/admin/verifications');

        $response->assertStatus(401);
    }

    /** @test */
    public function list_verifications_requires_admin_role()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Authenticate as regular user
        $this->actingAs($user);

        // Call the endpoint
        $response = $this->getJson('/api/admin/verifications');

        // Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    /** @test */
    public function list_verifications_supports_pagination()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create 25 verification requests
        for ($i = 0; $i < 25; $i++) {
            $user = User::factory()->create();
            IdentityVerification::create([
                'user_id' => $user->id,
                'verification_method' => 'ID card upload',
                'verification_status' => 'pending',
                'submitted_at' => now(),
            ]);
        }

        // Authenticate as admin
        $this->actingAs($admin);

        // Request first page
        $response = $this->getJson('/api/admin/verifications?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('current_page', 1)
            ->assertJsonCount(10, 'data');
    }

    /** @test */
    public function list_verifications_can_filter_by_status()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create pending verification
        $user1 = User::factory()->create();
        IdentityVerification::create([
            'user_id' => $user1->id,
            'verification_method' => 'ID card upload',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Create approved verification
        $user2 = User::factory()->create();
        IdentityVerification::create([
            'user_id' => $user2->id,
            'verification_method' => 'Passport scan',
            'verification_status' => 'approved',
            'submitted_at' => now()->subDays(1),
            'reviewed_at' => now(),
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Filter by pending status
        $response = $this->getJson('/api/admin/verifications?status=pending');

        $response->assertStatus(200);

        $verifications = $response->json('data');
        foreach ($verifications as $verification) {
            $this->assertEquals('pending', $verification['verification_status']);
        }
    }

    /** @test */
    public function list_verifications_includes_user_information()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create user with verification
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'ID card upload',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Call the endpoint
        $response = $this->getJson('/api/admin/verifications');

        $response->assertStatus(200);

        $verifications = $response->json('data');
        $this->assertNotEmpty($verifications);
        $this->assertEquals('Test User', $verifications[0]['user']['name']);
        $this->assertEquals('test@example.com', $verifications[0]['user']['email']);
    }

    /** @test */
    public function list_verifications_orders_by_submitted_date_descending()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create older verification
        $user1 = User::factory()->create();
        $older = IdentityVerification::create([
            'user_id' => $user1->id,
            'verification_method' => 'ID card upload',
            'verification_status' => 'pending',
            'submitted_at' => now()->subDays(5),
        ]);

        // Create newer verification
        $user2 = User::factory()->create();
        $newer = IdentityVerification::create([
            'user_id' => $user2->id,
            'verification_method' => 'Passport scan',
            'verification_status' => 'pending',
            'submitted_at' => now()->subDays(1),
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Call the endpoint
        $response = $this->getJson('/api/admin/verifications');

        $response->assertStatus(200);

        $verifications = $response->json('data');
        // Newer should come first
        $this->assertEquals($newer->id, $verifications[0]['id']);
        $this->assertEquals($older->id, $verifications[1]['id']);
    }
}
