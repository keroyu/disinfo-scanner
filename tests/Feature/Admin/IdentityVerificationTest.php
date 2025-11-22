<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\IdentityVerification;
use App\Models\ApiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

class IdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /** @test */
    public function admin_can_view_pending_verification_requests()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create users with pending verifications
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

        // Act as admin
        $this->actingAs($admin);

        // View verification list
        $response = $this->getJson('/api/admin/verifications?status=pending');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function admin_can_approve_verification_and_quota_becomes_unlimited()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create premium member with verification request
        $user = User::factory()->create();
        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole);

        // Create API quota (limited)
        $quota = ApiQuota::create([
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

        // Act as admin
        $this->actingAs($admin);

        // Approve verification
        $response = $this->postJson("/api/admin/verifications/{$verification->id}/review", [
            'action' => 'approve',
            'notes' => 'Identity verified successfully'
        ]);

        $response->assertStatus(200);

        // Verify status changed to approved
        $verification->refresh();
        $this->assertEquals('approved', $verification->verification_status);
        $this->assertNotNull($verification->reviewed_at);

        // Verify quota became unlimited
        $quota->refresh();
        $this->assertTrue($quota->is_unlimited);
    }

    /** @test */
    public function admin_can_reject_verification_with_notes()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create user with verification request
        $user = User::factory()->create();
        $verification = IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'ID card upload',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Act as admin
        $this->actingAs($admin);

        // Reject verification
        $response = $this->postJson("/api/admin/verifications/{$verification->id}/review", [
            'action' => 'reject',
            'notes' => 'Documents are not clear enough'
        ]);

        $response->assertStatus(200);

        // Verify status changed to rejected
        $verification->refresh();
        $this->assertEquals('rejected', $verification->verification_status);
        $this->assertEquals('Documents are not clear enough', $verification->notes);
        $this->assertNotNull($verification->reviewed_at);
    }

    /** @test */
    public function approved_verification_sets_unlimited_quota()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create user
        $user = User::factory()->create();
        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole);

        // Create API quota
        $quota = ApiQuota::create([
            'user_id' => $user->id,
            'usage_count' => 8,
            'monthly_limit' => 10,
            'is_unlimited' => false,
            'current_month' => now()->format('Y-m'),
        ]);

        // Create verification
        $verification = IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'Government ID',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Act as admin
        $this->actingAs($admin);

        // Approve
        $this->postJson("/api/admin/verifications/{$verification->id}/review", [
            'action' => 'approve'
        ]);

        // Check quota is unlimited
        $quota->refresh();
        $this->assertTrue($quota->is_unlimited);
    }

    /** @test */
    public function rejected_verification_keeps_quota_limited()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create user
        $user = User::factory()->create();

        // Create API quota
        $quota = ApiQuota::create([
            'user_id' => $user->id,
            'usage_count' => 3,
            'monthly_limit' => 10,
            'is_unlimited' => false,
            'current_month' => now()->format('Y-m'),
        ]);

        // Create verification
        $verification = IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'ID card',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Act as admin
        $this->actingAs($admin);

        // Reject
        $this->postJson("/api/admin/verifications/{$verification->id}/review", [
            'action' => 'reject',
            'notes' => 'Invalid documents'
        ]);

        // Check quota remains limited
        $quota->refresh();
        $this->assertFalse($quota->is_unlimited);
    }

    /** @test */
    public function non_admin_cannot_approve_verifications()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Create verification
        $targetUser = User::factory()->create();
        $verification = IdentityVerification::create([
            'user_id' => $targetUser->id,
            'verification_method' => 'ID card',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Act as regular user
        $this->actingAs($user);

        // Try to approve
        $response = $this->postJson("/api/admin/verifications/{$verification->id}/review", [
            'action' => 'approve'
        ]);

        // Should be forbidden
        $response->assertStatus(403);

        // Verify status didn't change
        $verification->refresh();
        $this->assertEquals('pending', $verification->verification_status);
    }

    /** @test */
    public function admin_can_filter_verifications_by_status()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create pending verification
        $user1 = User::factory()->create();
        IdentityVerification::create([
            'user_id' => $user1->id,
            'verification_method' => 'ID',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Create approved verification
        $user2 = User::factory()->create();
        IdentityVerification::create([
            'user_id' => $user2->id,
            'verification_method' => 'Passport',
            'verification_status' => 'approved',
            'submitted_at' => now()->subDays(1),
            'reviewed_at' => now(),
        ]);

        // Act as admin
        $this->actingAs($admin);

        // Filter pending
        $response = $this->getJson('/api/admin/verifications?status=pending');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('pending', $response->json('data')[0]['verification_status']);

        // Filter approved
        $response = $this->getJson('/api/admin/verifications?status=approved');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('approved', $response->json('data')[0]['verification_status']);
    }
}
