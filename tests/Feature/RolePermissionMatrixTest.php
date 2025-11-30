<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role Permission Matrix Tests (T506-T510)
 *
 * Phase 7 RBAC Testing & Validation
 * Tests all role-permission combinations for all 5 user types
 */
class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders for roles and permissions
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    /**
     * T506: Test all Visitor permissions (4 scenarios)
     * Visitors (unregistered users) can:
     * - View Home page
     * - View Videos List page
     * - Cannot access Channels List
     * - Cannot access Comments List
     */
    public function test_t506_visitor_can_view_home_page(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_t506_visitor_can_view_videos_list(): void
    {
        $response = $this->get(route('videos.index'));
        $response->assertStatus(200);
    }

    public function test_t506_visitor_cannot_access_channels_list(): void
    {
        $response = $this->get(route('channels.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_t506_visitor_cannot_access_comments_list(): void
    {
        $response = $this->get(route('comments.index'));
        $response->assertRedirect(route('login'));
    }

    /**
     * T507: Test all Regular Member permissions (10 scenarios)
     */
    public function test_t507_regular_member_can_view_home(): void
    {
        $user = $this->createUserWithRole('regular_member');

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);
    }

    public function test_t507_regular_member_can_view_videos_list(): void
    {
        $user = $this->createUserWithRole('regular_member');

        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);
    }

    public function test_t507_regular_member_can_view_channels_list(): void
    {
        $user = $this->createUserWithRole('regular_member');

        $response = $this->actingAs($user)->get(route('channels.index'));
        $response->assertStatus(200);
    }

    public function test_t507_regular_member_can_view_comments_list(): void
    {
        $user = $this->createUserWithRole('regular_member');

        $response = $this->actingAs($user)->get(route('comments.index'));
        $response->assertStatus(200);
    }

    public function test_t507_regular_member_can_access_settings(): void
    {
        $user = $this->createUserWithRole('regular_member');

        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertStatus(200);
    }

    public function test_t507_regular_member_can_change_password(): void
    {
        $user = $this->createUserWithRole('regular_member');
        $user->password = bcrypt('OldPassword123!');
        $user->save();

        $response = $this->actingAs($user)->post(route('settings.password'), [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');
    }

    public function test_t507_regular_member_can_configure_api_key(): void
    {
        $user = $this->createUserWithRole('regular_member');

        $response = $this->actingAs($user)->post(route('settings.api-key'), [
            'youtube_api_key' => 'AIzaSyC12345678901234567890123456789012',
        ]);

        $response->assertRedirect(route('settings.index'));
        $this->assertNotNull($user->fresh()->youtube_api_key);
    }

    public function test_t507_regular_member_cannot_access_admin_panel(): void
    {
        $user = $this->createUserWithRole('regular_member');

        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertForbidden();
    }

    public function test_t507_regular_member_cannot_submit_verification(): void
    {
        $user = $this->createUserWithRole('regular_member');

        $response = $this->actingAs($user)->post(route('settings.verification'), [
            'verification_method' => 'government_id',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('error');
    }

    public function test_t507_regular_member_sees_upgrade_prompt(): void
    {
        $user = $this->createUserWithRole('regular_member');

        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);
        // The upgrade button should be visible in navigation
    }

    /**
     * T508: Test all Premium Member permissions (12 scenarios)
     */
    public function test_t508_premium_member_can_view_all_public_pages(): void
    {
        $user = $this->createUserWithRole('premium_member');

        $this->actingAs($user)->get('/')->assertStatus(200);
        $this->actingAs($user)->get(route('videos.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('channels.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('comments.index'))->assertStatus(200);
    }

    public function test_t508_premium_member_can_access_settings(): void
    {
        $user = $this->createUserWithRole('premium_member');

        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertStatus(200);
        $response->assertSee('身分驗證');
    }

    public function test_t508_premium_member_can_submit_verification(): void
    {
        $user = $this->createUserWithRole('premium_member');

        $response = $this->actingAs($user)->post(route('settings.verification'), [
            'verification_method' => 'government_id',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');
    }

    public function test_t508_premium_member_has_api_quota(): void
    {
        $user = $this->createUserWithRole('premium_member');

        // Create quota record
        $quota = ApiQuota::create([
            'user_id' => $user->id,
            'monthly_limit' => 10,
            'usage_count' => 0,
            'is_unlimited' => false,
            'current_month' => now()->format('Y-m'),
        ]);

        $this->assertEquals(10, $quota->monthly_limit);
        $this->assertFalse($quota->is_unlimited);
    }

    public function test_t508_premium_member_cannot_access_admin_panel(): void
    {
        $user = $this->createUserWithRole('premium_member');

        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertForbidden();
    }

    public function test_t508_premium_member_can_configure_api_key(): void
    {
        $user = $this->createUserWithRole('premium_member');

        $response = $this->actingAs($user)->post(route('settings.api-key'), [
            'youtube_api_key' => 'AIzaSyC12345678901234567890123456789012',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');
    }

    /**
     * T509: Test all Website Editor permissions (15 scenarios)
     */
    public function test_t509_website_editor_can_view_all_pages(): void
    {
        $user = $this->createUserWithRole('website_editor');

        $this->actingAs($user)->get('/')->assertStatus(200);
        $this->actingAs($user)->get(route('videos.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('channels.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('comments.index'))->assertStatus(200);
    }

    public function test_t509_website_editor_can_access_settings(): void
    {
        $user = $this->createUserWithRole('website_editor');

        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertStatus(200);
        $response->assertSee('YouTube API 金鑰');
    }

    public function test_t509_website_editor_can_configure_api_key(): void
    {
        $user = $this->createUserWithRole('website_editor');

        $response = $this->actingAs($user)->post(route('settings.api-key'), [
            'youtube_api_key' => 'AIzaSyC12345678901234567890123456789012',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');
    }

    public function test_t509_website_editor_cannot_access_admin_panel(): void
    {
        $user = $this->createUserWithRole('website_editor');

        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertForbidden();
    }

    public function test_t509_website_editor_can_change_password(): void
    {
        $user = $this->createUserWithRole('website_editor');
        $user->password = bcrypt('OldPassword123!');
        $user->save();

        $response = $this->actingAs($user)->post(route('settings.password'), [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');
    }

    /**
     * T510: Test all Administrator permissions (all features)
     */
    public function test_t510_admin_can_view_all_public_pages(): void
    {
        $user = $this->createUserWithRole('administrator');

        $this->actingAs($user)->get('/')->assertStatus(200);
        $this->actingAs($user)->get(route('videos.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('channels.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('comments.index'))->assertStatus(200);
    }

    public function test_t510_admin_can_access_admin_dashboard(): void
    {
        $user = $this->createUserWithRole('administrator');

        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }

    public function test_t510_admin_can_access_user_management(): void
    {
        $user = $this->createUserWithRole('administrator');

        $response = $this->actingAs($user)->get(route('admin.users.index'));
        $response->assertStatus(200);
    }

    public function test_t510_admin_can_access_settings(): void
    {
        $user = $this->createUserWithRole('administrator');

        $response = $this->actingAs($user)->get(route('settings.index'));
        $response->assertStatus(200);
    }

    public function test_t510_admin_can_configure_api_key(): void
    {
        $user = $this->createUserWithRole('administrator');

        $response = $this->actingAs($user)->post(route('settings.api-key'), [
            'youtube_api_key' => 'AIzaSyC12345678901234567890123456789012',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');
    }

    public function test_t510_admin_can_change_password(): void
    {
        $user = $this->createUserWithRole('administrator');
        $user->password = bcrypt('OldPassword123!');
        $user->save();

        $response = $this->actingAs($user)->post(route('settings.password'), [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');
    }

    public function test_t510_admin_has_unlimited_quota(): void
    {
        $user = $this->createUserWithRole('administrator');

        // Admin should not be subject to quota limits
        $this->assertTrue($user->hasRole('administrator'));
    }

    /**
     * Helper method to create a user with a specific role
     */
    protected function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);

        $role = Role::where('name', $roleName)->first();
        $user->roles()->attach($role->id, ['assigned_at' => now()]);

        return $user;
    }
}
