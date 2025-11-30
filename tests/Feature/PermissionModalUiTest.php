<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PermissionSeeder;

/**
 * Feature tests for Permission Modal UI Integration (T483-T487)
 *
 * Tests the permission modal UI feedback for different user roles.
 */
class PermissionModalUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    /**
     * @test
     * T483: Visitor sees "請登入會員" modal when clicking Comments List
     */
    public function visitor_sees_login_modal_content_on_comments_list(): void
    {
        // Visitor viewing the main layout should see navigation
        // with Comments List as a button (not link) that triggers login modal
        $response = $this->get(route('videos.index'));
        $response->assertStatus(200);

        // The nav should contain button with onclick for login modal
        $response->assertSee("onclick=\"showPermissionModal('login', '留言列表')\"", false);

        // Also verify that visitors are redirected when directly accessing Comments List
        $response = $this->get(route('comments.index'));
        $response->assertRedirect(route('login'));
    }

    /**
     * @test
     * T483: Visitor sees login modal button for Channels List
     */
    public function visitor_sees_login_modal_for_channels_list(): void
    {
        $response = $this->get(route('videos.index'));
        $response->assertStatus(200);

        // The nav should contain button with onclick for login modal for channels
        $response->assertSee("onclick=\"showPermissionModal('login', '頻道列表')\"", false);
    }

    /**
     * @test
     * T484: Regular Member sees "需升級為高級會員" modal on Official API import
     */
    public function regular_member_sees_upgrade_modal_on_official_api_import(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);

        // The Official API import button should show upgrade modal
        $response->assertSee("onclick=\"showPermissionModal('upgrade', '官方API導入')\"", false);
    }

    /**
     * @test
     * T484: Regular Member cannot use search on Comments List
     */
    public function regular_member_sees_upgrade_message_on_comments_search(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('comments.index'));
        $response->assertStatus(200);

        // Should see the upgrade message for search
        $response->assertSee('搜尋功能需要高級會員', false);
        $response->assertSee('升級為高級會員即可使用留言搜尋與篩選功能', false);
    }

    /**
     * @test
     * T485: Premium Member sees quota counter
     */
    public function premium_member_sees_quota_counter(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Create API quota with usage
        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 7,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);

        // Should see the quota display in dropdown
        $response->assertSee('本月 API 用量:', false);
        $response->assertSee('7/10', false);
    }

    /**
     * @test
     * T486: Quota exceeded modal shows correct usage (10/10)
     */
    public function quota_exceeded_shows_correct_usage(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Create API quota at maximum
        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 10,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);

        // Should see the quota display showing 10/10
        $response->assertSee('10/10', false);
    }

    /**
     * @test
     * T487: Verified Premium Member sees "Unlimited" instead of quota
     */
    public function verified_premium_member_sees_unlimited(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Create API quota with unlimited flag
        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 50, // Has used many, but unlimited
            'monthly_limit' => 10,
            'is_unlimited' => true, // Identity verified
        ]);

        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);

        // Should see "無限制" instead of quota counter
        $response->assertSee('無限制', false);
    }

    /**
     * @test
     * T470: Visitor sees login modal for Videos List search
     */
    public function visitor_sees_login_modal_for_videos_search(): void
    {
        $response = $this->get(route('videos.index'));
        $response->assertStatus(200);

        // Should see the login message for search
        $response->assertSee('搜尋功能需要登入會員', false);

        // Search fields should be disabled
        $response->assertSee('disabled', false);

        // Apply Filters button should trigger login modal
        $response->assertSee("onclick=\"showPermissionModal('login', '影片搜尋')\"", false);
    }

    /**
     * @test
     * Authenticated user can use Videos List search
     */
    public function authenticated_user_can_use_videos_search(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);

        // Should NOT see the login message for search (user is authenticated)
        $response->assertDontSee('搜尋功能需要登入會員', false);

        // Apply Filters should be a submit button, not modal trigger
        $response->assertSee('type="submit"', false);
    }

    /**
     * @test
     * Premium Member can use Comments List search
     */
    public function premium_member_can_use_comments_search(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('comments.index'));
        $response->assertStatus(200);

        // Should NOT see the upgrade message
        $response->assertDontSee('搜尋功能需要高級會員', false);
    }

    /**
     * @test
     * Administrator has access to all features without modals
     */
    public function administrator_has_full_access(): void
    {
        $role = Role::where('name', 'administrator')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Videos page - full access
        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);
        $response->assertDontSee('搜尋功能需要登入會員', false);

        // Comments page - full access
        $response = $this->actingAs($user)->get(route('comments.index'));
        $response->assertStatus(200);
        $response->assertDontSee('搜尋功能需要高級會員', false);

        // Admin panel - has access
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }

    /**
     * @test
     * User without API key sees API key modal (when api_key modal is included in layout)
     */
    public function user_without_api_key_sees_api_key_modal_in_layout(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => null,
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);

        // The api_key modal should be included in the layout for users without API key
        // It contains the message "需設定 YouTube API 金鑰"
        $response->assertSee('需設定 YouTube API 金鑰', false);
    }

    /**
     * @test
     * User with API key does not see API key requirement modal
     */
    public function user_with_api_key_does_not_see_api_key_modal(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'AIzaSyTestApiKey123',
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);

        // Should NOT see the API key requirement modal (it's not included for users with API key)
        $response->assertDontSee('需設定 YouTube API 金鑰', false);
    }
}
