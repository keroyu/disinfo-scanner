<?php

namespace Tests\Contract\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use App\Models\IdentityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;

class GetUserDetailsContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test get user details endpoint returns correct JSON structure
     *
     * @test
     */
    public function get_user_details_returns_correct_json_structure(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create target user with API quota
        $premiumRole = Role::where('name', 'premium_member')->first();
        $targetUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_email_verified' => true,
        ]);
        $targetUser->roles()->attach($premiumRole);

        // Create API quota
        ApiQuota::create([
            'user_id' => $targetUser->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 5,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        // Act: Request user details
        $response = $this->actingAs($admin)
            ->getJson("/admin/users/{$targetUser->id}");

        // Assert: Verify response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'is_email_verified',
                    'has_default_password',
                    'youtube_api_key',
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
                    'identity_verification',
                    'created_at',
                    'updated_at',
                ]
            ]);

        // Verify data values
        $response->assertJsonPath('data.email', 'test@example.com')
            ->assertJsonPath('data.api_quota.usage_count', 5)
            ->assertJsonPath('data.api_quota.monthly_limit', 10);
    }

    /**
     * Test get user details requires admin authentication
     *
     * @test
     */
    public function get_user_details_requires_admin_authentication(): void
    {
        // Arrange: Create regular users
        $regularRole = Role::where('name', 'regular_member')->first();
        
        $user1 = User::factory()->create();
        $user1->roles()->attach($regularRole);

        $user2 = User::factory()->create();
        $user2->roles()->attach($regularRole);

        // Act: Regular user tries to view another user's details
        $response = $this->actingAs($user1)
            ->getJson("/admin/users/{$user2->id}");

        // Assert: Request denied
        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    /**
     * Test get user details returns 404 for non-existent user
     *
     * @test
     */
    public function get_user_details_returns_404_for_non_existent_user(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Act: Request details for non-existent user
        $response = $this->actingAs($admin)
            ->getJson("/admin/users/99999");

        // Assert: Not found
        $response->assertStatus(404)
            ->assertJson([
                'message' => '找不到指定的使用者'
            ]);
    }

    /**
     * Test get user details includes identity verification status
     *
     * @test
     */
    public function get_user_details_includes_identity_verification_status(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create user with pending verification
        $premiumRole = Role::where('name', 'premium_member')->first();
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach($premiumRole);

        // Create identity verification
        IdentityVerification::create([
            'user_id' => $targetUser->id,
            'verification_method' => 'email',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Act: Request user details
        $response = $this->actingAs($admin)
            ->getJson("/admin/users/{$targetUser->id}");

        // Assert: Verification included
        $response->assertStatus(200)
            ->assertJsonPath('data.identity_verification.verification_status', 'pending')
            ->assertJsonPath('data.identity_verification.verification_method', 'email');
    }

    /**
     * Test get user details includes all roles
     *
     * @test
     */
    public function get_user_details_includes_all_roles(): void
    {
        // Arrange: Create admin user
        $adminRole = Role::where('name', 'administrator')->first();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // Create user with multiple roles (edge case)
        $regularRole = Role::where('name', 'regular_member')->first();
        $editorRole = Role::where('name', 'website_editor')->first();
        
        $targetUser = User::factory()->create();
        $targetUser->roles()->attach([$regularRole->id, $editorRole->id]);

        // Act: Request user details
        $response = $this->actingAs($admin)
            ->getJson("/admin/users/{$targetUser->id}");

        // Assert: All roles included
        $response->assertStatus(200);
        
        $roles = $response->json('data.roles');
        $this->assertCount(2, $roles);
        
        $roleNames = array_column($roles, 'name');
        $this->assertContains('regular_member', $roleNames);
        $this->assertContains('website_editor', $roleNames);
    }
}
