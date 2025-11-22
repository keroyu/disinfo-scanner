<?php

namespace Tests\Contract\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use App\Models\IdentityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GetUserDetailsContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /** @test */
    public function get_user_details_endpoint_returns_correct_json_structure()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create target user
        $targetUser = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $targetUser->roles()->attach($regularRole);

        // Create API quota for user
        ApiQuota::create([
            'user_id' => $targetUser->id,
            'usage_count' => 5,
            'monthly_limit' => 10,
            'is_unlimited' => false,
            'current_month' => now()->format('Y-m'),
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Get user details
        $response = $this->getJson("/api/admin/users/{$targetUser->id}");

        // Assert response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'email_verified_at',
                'is_email_verified',
                'has_default_password',
                'last_password_change_at',
                'roles' => [
                    '*' => [
                        'id',
                        'name',
                        'display_name',
                    ]
                ],
                'api_quota' => [
                    'usage_count',
                    'monthly_limit',
                    'is_unlimited',
                    'current_month',
                ],
                'identity_verification' => [
                    'status',
                    'method',
                    'submitted_at',
                    'reviewed_at',
                    'reviewer_notes',
                ],
                'created_at',
                'updated_at',
            ])
            ->assertJsonPath('id', $targetUser->id);
    }

    /** @test */
    public function get_user_details_requires_authentication()
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/admin/users/{$user->id}");

        $response->assertStatus(401)
            ->assertJson([
                'message' => '請先登入'
            ]);
    }

    /** @test */
    public function get_user_details_requires_admin_role()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Target user
        $targetUser = User::factory()->create();

        // Authenticate as regular user
        $this->actingAs($user);

        // Try to get user details
        $response = $this->getJson("/api/admin/users/{$targetUser->id}");

        // Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    /** @test */
    public function get_user_details_returns_404_for_nonexistent_user()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Authenticate as admin
        $this->actingAs($admin);

        // Request nonexistent user
        $response = $this->getJson("/api/admin/users/99999");

        $response->assertStatus(404);
    }

    /** @test */
    public function get_user_details_includes_api_quota_information()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create target user with premium role
        $targetUser = User::factory()->create();
        $premiumRole = Role::where('name', 'premium_member')->first();
        $targetUser->roles()->attach($premiumRole);

        // Create API quota
        ApiQuota::create([
            'user_id' => $targetUser->id,
            'usage_count' => 7,
            'monthly_limit' => 10,
            'is_unlimited' => false,
            'current_month' => now()->format('Y-m'),
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Get user details
        $response = $this->getJson("/api/admin/users/{$targetUser->id}");

        $response->assertStatus(200)
            ->assertJsonPath('api_quota.usage_count', 7)
            ->assertJsonPath('api_quota.monthly_limit', 10)
            ->assertJsonPath('api_quota.is_unlimited', false);
    }

    /** @test */
    public function get_user_details_includes_identity_verification_status()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create target user
        $targetUser = User::factory()->create();
        $premiumRole = Role::where('name', 'premium_member')->first();
        $targetUser->roles()->attach($premiumRole);

        // Create identity verification
        IdentityVerification::create([
            'user_id' => $targetUser->id,
            'method' => 'ID card upload',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Get user details
        $response = $this->getJson("/api/admin/users/{$targetUser->id}");

        $response->assertStatus(200)
            ->assertJsonPath('identity_verification.status', 'pending')
            ->assertJsonPath('identity_verification.method', 'ID card upload');
    }

    /** @test */
    public function get_user_details_shows_null_if_no_identity_verification()
    {
        // Create admin user
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create target user without identity verification
        $targetUser = User::factory()->create();

        // Authenticate as admin
        $this->actingAs($admin);

        // Get user details
        $response = $this->getJson("/api/admin/users/{$targetUser->id}");

        $response->assertStatus(200)
            ->assertJsonPath('identity_verification.status', null);
    }
}
