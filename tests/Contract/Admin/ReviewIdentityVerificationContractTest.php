<?php

namespace Tests\Contract\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\IdentityVerification;
use App\Models\ApiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReviewIdentityVerificationContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /** @test */
    public function review_verification_endpoint_returns_correct_json_structure_on_approval()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create premium member with verification request
        $user = User::factory()->create();
        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole);

        // Create API quota
        ApiQuota::create([
            'user_id' => $user->id,
            'usage_count' => 5,
            'monthly_limit' => 10,
            'is_unlimited' => false,
            'current_month' => now()->format('Y-m'),
        ]);

        // Create identity verification
        $verification = IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'ID card upload',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Approve verification
        $response = $this->postJson("/api/admin/verifications/{$verification->id}/review", [
            'action' => 'approve',
            'notes' => 'Identity verified successfully'
        ]);

        // Assert response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'verification' => [
                    'id',
                    'user_id',
                    'verification_method',
                    'verification_status',
                    'reviewed_at',
                    'notes',
                ]
            ])
            ->assertJsonPath('verification.verification_status', 'approved');
    }

    /** @test */
    public function review_verification_endpoint_returns_correct_json_structure_on_rejection()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create premium member with verification request
        $user = User::factory()->create();
        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole);

        // Create identity verification
        $verification = IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'ID card upload',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Reject verification
        $response = $this->postJson("/api/admin/verifications/{$verification->id}/review", [
            'action' => 'reject',
            'notes' => 'Documents are not clear'
        ]);

        // Assert response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'verification' => [
                    'id',
                    'user_id',
                    'verification_method',
                    'verification_status',
                    'reviewed_at',
                    'notes',
                ]
            ])
            ->assertJsonPath('verification.verification_status', 'rejected');
    }

    /** @test */
    public function review_verification_requires_authentication()
    {
        $user = User::factory()->create();
        $verification = IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'ID card upload',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        $response = $this->postJson("/api/admin/verifications/{$verification->id}/review", [
            'action' => 'approve'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function review_verification_requires_admin_role()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Create verification
        $verification = IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'ID card upload',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Authenticate as regular user
        $this->actingAs($user);

        // Try to review
        $response = $this->postJson("/api/admin/verifications/{$verification->id}/review", [
            'action' => 'approve'
        ]);

        // Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    /** @test */
    public function review_verification_validates_required_fields()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        $user = User::factory()->create();
        $verification = IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'ID card upload',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Missing action
        $response = $this->postJson("/api/admin/verifications/{$verification->id}/review", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
    }

    /** @test */
    public function review_verification_validates_action_values()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        $user = User::factory()->create();
        $verification = IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'ID card upload',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Invalid action
        $response = $this->postJson("/api/admin/verifications/{$verification->id}/review", [
            'action' => 'invalid_action'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
    }

    /** @test */
    public function review_verification_returns_404_for_nonexistent_verification()
    {
        // Create admin user
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Authenticate as admin
        $this->actingAs($admin);

        // Request nonexistent verification
        $response = $this->postJson("/api/admin/verifications/99999/review", [
            'action' => 'approve'
        ]);

        $response->assertStatus(404);
    }
}
