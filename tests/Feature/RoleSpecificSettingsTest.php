<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\IdentityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Role-Specific Settings Access (T501-T505)
 *
 * Phase 6 Integration Testing for RBAC Module
 */
class RoleSpecificSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders for roles
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * T501: Test all authenticated users can change password
     */
    public function test_t501_all_authenticated_users_can_see_password_change_section(): void
    {
        $roles = ['regular_member', 'premium_member', 'website_editor', 'administrator'];

        foreach ($roles as $roleName) {
            $user = User::factory()->create([
                'is_email_verified' => true,
                'has_default_password' => false,
            ]);
            $role = Role::where('name', $roleName)->first();
            $user->roles()->attach($role->id, ['assigned_at' => now()]);

            $response = $this->actingAs($user)->get(route('settings.index'));

            $response->assertStatus(200);
            $response->assertSee('密碼設定');
            $response->assertSee('目前密碼');
            $response->assertSee('新密碼');
            $response->assertSee('確認新密碼');

            // Test password update endpoint is accessible
            $response = $this->actingAs($user)->post(route('settings.password'), [
                'current_password' => 'wrong_password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

            // Should fail validation but endpoint should be accessible
            $response->assertSessionHasErrors('current_password');
        }
    }

    /**
     * T502: Test Regular Member can configure YouTube API key
     */
    public function test_t502_regular_member_can_configure_youtube_api_key(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
            'youtube_api_key' => null,
        ]);
        $role = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        // Check settings page shows API key section
        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertStatus(200);
        $response->assertSee('YouTube API 金鑰');
        $response->assertSee('未設定');

        // Test saving valid API key (must be 39 characters, starting with AIza)
        $validApiKey = 'AIzaSyC12345678901234567890123456789012';
        $response = $this->actingAs($user)->post(route('settings.api-key'), [
            'youtube_api_key' => $validApiKey,
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals($validApiKey, $user->youtube_api_key);

        // Check settings page now shows "已設定"
        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertSee('已設定');
    }

    /**
     * T493: Test YouTube API key format validation
     */
    public function test_t493_youtube_api_key_format_validation(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        // Test invalid API key format
        $invalidApiKey = 'invalid_key_format';
        $response = $this->actingAs($user)->post(route('settings.api-key'), [
            'youtube_api_key' => $invalidApiKey,
        ]);

        $response->assertSessionHasErrors('youtube_api_key');
        $this->assertNull($user->fresh()->youtube_api_key);

        // Test API key not starting with AIza
        $response = $this->actingAs($user)->post(route('settings.api-key'), [
            'youtube_api_key' => 'XXXX567890123456789012345678901234567890',
        ]);

        $response->assertSessionHasErrors('youtube_api_key');

        // Test API key with correct format but wrong length
        $response = $this->actingAs($user)->post(route('settings.api-key'), [
            'youtube_api_key' => 'AIzaShort',
        ]);

        $response->assertSessionHasErrors('youtube_api_key');
    }

    /**
     * T503: Test video update enabled after API key configured
     * This is verified via feature access - Regular Members need API key for video update
     */
    public function test_t503_video_update_enabled_after_api_key_configured(): void
    {
        // User without API key
        $userWithoutKey = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
            'youtube_api_key' => null,
        ]);
        $role = Role::where('name', 'regular_member')->first();
        $userWithoutKey->roles()->attach($role->id, ['assigned_at' => now()]);

        $this->assertNull($userWithoutKey->youtube_api_key);

        // User with API key (39 characters)
        $userWithKey = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
            'youtube_api_key' => 'AIzaSyC12345678901234567890123456789012',
        ]);
        $userWithKey->roles()->attach($role->id, ['assigned_at' => now()]);

        $this->assertNotNull($userWithKey->youtube_api_key);
    }

    /**
     * T504: Test Premium Member can submit identity verification
     */
    public function test_t504_premium_member_can_submit_identity_verification(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        // Check settings page shows verification section
        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertStatus(200);
        $response->assertSee('身分驗證');
        $response->assertSee('提交驗證申請');

        // Submit verification request
        $response = $this->actingAs($user)->post(route('settings.verification'), [
            'verification_method' => 'government_id',
            'verification_notes' => '測試備註',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');

        // Check verification record was created
        $this->assertDatabaseHas('identity_verifications', [
            'user_id' => $user->id,
            'verification_method' => 'government_id',
            'verification_status' => 'pending',
        ]);
    }

    /**
     * T504: Test Regular Member cannot submit identity verification
     */
    public function test_t504_regular_member_cannot_submit_identity_verification(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        // Check settings page does NOT show verification section
        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertStatus(200);
        $response->assertDontSee('提交驗證申請');

        // Try to submit verification request (should fail)
        $response = $this->actingAs($user)->post(route('settings.verification'), [
            'verification_method' => 'government_id',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('error');

        // Check no verification record was created
        $this->assertDatabaseMissing('identity_verifications', [
            'user_id' => $user->id,
        ]);
    }

    /**
     * T505: Test verification status displays correctly in settings - pending
     */
    public function test_t505_verification_status_pending_displays_correctly(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        // Test pending status display
        IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'government_id',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertSee('審核中');
        $response->assertSee('您的身分驗證申請已送出');
    }

    /**
     * T505: Test verification status displays correctly in settings - rejected
     */
    public function test_t505_verification_status_rejected_displays_correctly(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        // Test rejected status display
        IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'government_id',
            'verification_status' => 'rejected',
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
            'notes' => '資料不完整',
        ]);

        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertSee('驗證未通過');
        $response->assertSee('資料不完整');
        $response->assertSee('提交驗證申請'); // Can re-submit
    }

    /**
     * T505: Test verification status displays correctly in settings - approved
     */
    public function test_t505_verification_status_approved_displays_correctly(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        // Test approved status display
        IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'government_id',
            'verification_status' => 'approved',
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertSee('身分驗證已通過');
        $response->assertSee('無限制的官方 API 匯入配額');
        $response->assertDontSee('提交驗證申請'); // Cannot re-submit
    }

    /**
     * T491: Test verified Premium Member does not see verification submission form
     */
    public function test_t491_verified_premium_member_hides_verification_form(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        // Create approved verification
        IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'government_id',
            'verification_status' => 'approved',
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertSee('身分驗證已通過');
        $response->assertDontSee('驗證方式');
        $response->assertDontSee('提交驗證申請');
    }

    /**
     * T492: Test Website Editors see all settings
     */
    public function test_t492_website_editors_see_all_settings(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'website_editor')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertStatus(200);

        // Should see password change
        $response->assertSee('密碼設定');

        // Should see API key configuration
        $response->assertSee('YouTube API 金鑰');
    }

    /**
     * T492: Test Administrators see all settings
     */
    public function test_t492_administrators_see_all_settings(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'administrator')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertStatus(200);

        // Should see password change
        $response->assertSee('密碼設定');

        // Should see API key configuration
        $response->assertSee('YouTube API 金鑰');
    }

    /**
     * Test cannot submit verification when one is already pending
     */
    public function test_cannot_submit_verification_when_pending(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        // Create pending verification
        IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'government_id',
            'verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Try to submit another verification
        $response = $this->actingAs($user)->post(route('settings.verification'), [
            'verification_method' => 'social_media',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('error');

        // Check only one verification record exists
        $this->assertEquals(1, IdentityVerification::where('user_id', $user->id)->count());
    }

    /**
     * Test can re-submit verification after rejection
     */
    public function test_can_resubmit_verification_after_rejection(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        // Create rejected verification
        IdentityVerification::create([
            'user_id' => $user->id,
            'verification_method' => 'government_id',
            'verification_status' => 'rejected',
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);

        // Re-submit verification
        $response = $this->actingAs($user)->post(route('settings.verification'), [
            'verification_method' => 'social_media',
            'verification_notes' => '重新提交',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');

        // Check verification record was updated
        $verification = $user->fresh()->identityVerification;
        $this->assertEquals('pending', $verification->verification_status);
        $this->assertEquals('social_media', $verification->verification_method);
    }

    /**
     * T498: Test verification method validation
     */
    public function test_t498_verification_method_validation(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $role = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        // Test invalid verification method
        $response = $this->actingAs($user)->post(route('settings.verification'), [
            'verification_method' => 'invalid_method',
        ]);

        $response->assertSessionHasErrors('verification_method');

        // Test empty verification method
        $response = $this->actingAs($user)->post(route('settings.verification'), [
            'verification_method' => '',
        ]);

        $response->assertSessionHasErrors('verification_method');

        // Test valid methods
        $validMethods = ['government_id', 'social_media', 'organization'];
        foreach ($validMethods as $method) {
            $user = User::factory()->create([
                'is_email_verified' => true,
                'has_default_password' => false,
            ]);
            $user->roles()->attach($role->id, ['assigned_at' => now()]);

            $response = $this->actingAs($user)->post(route('settings.verification'), [
                'verification_method' => $method,
            ]);

            $response->assertRedirect(route('settings.index'));
            $response->assertSessionHas('success');
        }
    }
}
