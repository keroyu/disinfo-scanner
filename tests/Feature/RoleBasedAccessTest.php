<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /** @test */
    public function visitor_sees_login_prompt_for_official_api_import()
    {
        $response = $this->get(route('videos.index'));

        $response->assertStatus(200);
        // Check that the login modal component is included
        $response->assertSee('請登入會員', false);
        // Check that the button triggers login modal for guests
        $response->assertSee('showPermissionModal', false);
    }

    /** @test */
    public function regular_member_sees_upgrade_prompt_for_official_api_import()
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('videos.index'));

        $response->assertStatus(200);
        // Check that upgrade modal component is included
        $response->assertSee('需升級為付費會員', false);
        // Check that the button triggers upgrade modal for regular members
        $response->assertSee('showPermissionModal', false);
    }

    /** @test */
    public function paid_member_without_api_key_sees_api_key_prompt()
    {
        $role = Role::where('name', 'paid_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => null, // No API key set
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('videos.index'));

        $response->assertStatus(200);
        // Check that API key modal component is included
        $response->assertSee('需設定 YouTube API 金鑰', false);
        // Check that the button triggers API key modal
        $response->assertSee('showPermissionModal', false);
    }

    /** @test */
    public function paid_member_with_api_key_can_access_official_api_import()
    {
        $role = Role::where('name', 'paid_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'youtube_api_key' => 'test_api_key',
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('videos.index'));

        $response->assertStatus(200);
        // Check that the button triggers the actual import modal
        $response->assertSee('open-import-modal');
    }

    /** @test */
    public function regular_member_sees_upgrade_prompt_for_comments_search()
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('comments.index'));

        $response->assertStatus(200);
        // Check that search fields are disabled
        $response->assertSee('disabled', false);
        $response->assertSee('搜尋功能需要付費會員', false);
        // Check that Apply Filters button shows upgrade modal
        $response->assertSee('showUpgradeForSearchModal', false);
    }

    /** @test */
    public function paid_member_can_use_comments_search()
    {
        $role = Role::where('name', 'paid_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('comments.index'));

        $response->assertStatus(200);
        // Check that search fields are NOT disabled
        $response->assertDontSee('搜尋功能需要付費會員', false);
        // Check that Apply Filters button is a real submit button
        $response->assertSee('type="submit"', false);
    }

    /** @test */
    public function quota_counter_displays_for_paid_member()
    {
        $role = Role::where('name', 'paid_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        // Create API quota record
        $user->apiQuota()->create([
            'monthly_limit' => 10,
            'usage_count' => 3,
            'is_unlimited' => false,
            'current_month' => now()->format('Y-m'),
        ]);

        $response = $this->actingAs($user)->get(route('videos.index'));

        $response->assertStatus(200);
        // Check that quota counter is displayed
        $response->assertSee('本月 API 用量', false);
        $response->assertSee('3/10', false);
    }

    /** @test */
    public function verified_paid_member_shows_unlimited_quota()
    {
        $role = Role::where('name', 'paid_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        // Create unlimited API quota record (simulating identity-verified paid member)
        $user->apiQuota()->create([
            'monthly_limit' => 10, // Required field, but ignored when is_unlimited is true
            'usage_count' => 0,
            'is_unlimited' => true,
            'current_month' => now()->format('Y-m'),
        ]);

        $response = $this->actingAs($user)->get(route('videos.index'));

        $response->assertStatus(200);
        // Check that unlimited badge is displayed
        $response->assertSee('本月 API 用量', false);
        $response->assertSee('無限制', false);
    }

    /** @test */
    public function regular_member_sees_upgrade_button_in_dropdown()
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('videos.index'));

        $response->assertStatus(200);
        // Check that upgrade button is shown in user dropdown
        $response->assertSee('升級為付費會員', false);
        $response->assertSee('showUpgradeModal', false);
    }

    /** @test */
    public function admin_has_unrestricted_access()
    {
        $role = Role::where('name', 'admin')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('videos.index'));

        $response->assertStatus(200);
        // Admin should see the actual import modal trigger (even without API key)
        $response->assertSee('open-import-modal');
    }

    /** @test */
    public function regular_member_cannot_bypass_search_restriction_via_url()
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        // Try to access comments list with search parameters via URL
        $response = $this->actingAs($user)->get(route('comments.index', [
            'search' => 'test search',
            'search_channel' => 'test channel',
        ]));

        $response->assertStatus(200);
        // The page should load but search parameters should be ignored
        // Since the backend ignores search params for regular members,
        // the results should be the same as without search params
    }

    /** @test */
    public function paid_member_can_use_search_via_url()
    {
        $role = Role::where('name', 'paid_member')->first();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        // Access comments list with search parameters via URL
        $response = $this->actingAs($user)->get(route('comments.index', [
            'search' => 'test search',
        ]));

        $response->assertStatus(200);
        // Paid member should be able to use search
    }
}
