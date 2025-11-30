<?php

namespace Tests\Contract;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PermissionSeeder;

/**
 * Contract tests for Page Access Control (T435)
 *
 * Tests the contract for page-level permission checks.
 */
class PageAccessContractTest extends TestCase
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
     * T435: Visitor can access Home page
     */
    public function visitor_can_access_home_page(): void
    {
        $response = $this->get(route('import.index'));

        $response->assertStatus(200);
    }

    /**
     * @test
     * T435: Visitor can access Videos List page
     */
    public function visitor_can_access_videos_list(): void
    {
        $response = $this->get(route('videos.index'));

        $response->assertStatus(200);
    }

    /**
     * @test
     * T435: Visitor redirected to login for Channels List
     */
    public function visitor_redirected_to_login_for_channels_list(): void
    {
        $response = $this->get(route('channels.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * @test
     * T435: Visitor redirected to login for Comments List
     */
    public function visitor_redirected_to_login_for_comments_list(): void
    {
        $response = $this->get(route('comments.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * @test
     * T435: Authenticated user can access Channels List
     */
    public function authenticated_user_can_access_channels_list(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('channels.index'));

        $response->assertStatus(200);
    }

    /**
     * @test
     * T435: Authenticated user can access Comments List
     */
    public function authenticated_user_can_access_comments_list(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('comments.index'));

        $response->assertStatus(200);
    }

    /**
     * @test
     * T435: Non-admin user cannot access admin panel
     */
    public function non_admin_cannot_access_admin_panel(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    /**
     * @test
     * T435: Admin can access admin panel
     */
    public function admin_can_access_admin_panel(): void
    {
        $role = Role::where('name', 'administrator')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }

    /**
     * @test
     * T435: Visitor accessing admin panel redirected to login
     */
    public function visitor_accessing_admin_redirected_to_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    /**
     * @test
     * T435: Premium member cannot access admin panel
     */
    public function premium_member_cannot_access_admin_panel(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    /**
     * @test
     * T435: Website editor cannot access admin panel
     */
    public function website_editor_cannot_access_admin_panel(): void
    {
        $role = Role::where('name', 'website_editor')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }
}
