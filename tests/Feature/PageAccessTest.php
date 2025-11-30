<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PermissionSeeder;

/**
 * Feature tests for Page Access Control (T436)
 *
 * Tests the complete page access control functionality.
 */
class PageAccessTest extends TestCase
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
     * T436, T444: Visitor can access Home and Videos List
     */
    public function visitor_can_access_public_pages(): void
    {
        // Home page
        $response = $this->get(route('import.index'));
        $response->assertStatus(200);

        // Videos List
        $response = $this->get(route('videos.index'));
        $response->assertStatus(200);
    }

    /**
     * @test
     * T436, T445: Visitor redirected to login when accessing Channels List
     */
    public function visitor_redirected_to_login_for_protected_pages(): void
    {
        $response = $this->get(route('channels.index'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('comments.index'));
        $response->assertRedirect(route('login'));
    }

    /**
     * @test
     * T436, T446: Regular Member can access Channels List and Comments List
     */
    public function regular_member_can_access_member_pages(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Channels List
        $response = $this->actingAs($user)->get(route('channels.index'));
        $response->assertStatus(200);

        // Comments List
        $response = $this->actingAs($user)->get(route('comments.index'));
        $response->assertStatus(200);
    }

    /**
     * @test
     * T436, T447: Regular Member cannot access admin panel
     */
    public function regular_member_cannot_access_admin_panel(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(403);
    }

    /**
     * @test
     * T436, T448: Administrator can access all pages
     */
    public function administrator_can_access_all_pages(): void
    {
        $role = Role::where('name', 'administrator')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Public pages
        $response = $this->actingAs($user)->get(route('import.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);

        // Member pages
        $response = $this->actingAs($user)->get(route('channels.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($user)->get(route('comments.index'));
        $response->assertStatus(200);

        // Admin pages
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }

    /**
     * @test
     * T436: Premium Member can access all frontend pages but not admin
     */
    public function premium_member_can_access_frontend_but_not_admin(): void
    {
        $role = Role::where('name', 'premium_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Frontend pages - should work
        $response = $this->actingAs($user)->get(route('import.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($user)->get(route('videos.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($user)->get(route('channels.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($user)->get(route('comments.index'));
        $response->assertStatus(200);

        // Admin panel - should be denied
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(403);
    }

    /**
     * @test
     * T436: Website Editor can access all frontend pages but not admin
     */
    public function website_editor_can_access_frontend_but_not_admin(): void
    {
        $role = Role::where('name', 'website_editor')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        // Frontend pages - should work
        $response = $this->actingAs($user)->get(route('channels.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($user)->get(route('comments.index'));
        $response->assertStatus(200);

        // Admin panel - should be denied
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(403);
    }

    /**
     * @test
     * T436, T441: Visitor accessing protected page via AJAX gets 401
     */
    public function visitor_accessing_protected_page_via_ajax_gets_401(): void
    {
        $response = $this->getJson(route('channels.index'));

        // Laravel's auth middleware returns 401 for unauthenticated JSON requests
        $response->assertStatus(401);
    }

    /**
     * @test
     * T436, T442: Authenticated user without permission sees 403
     */
    public function authenticated_user_without_permission_sees_403(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    /**
     * @test
     * T436: Settings page requires authentication
     */
    public function settings_page_requires_authentication(): void
    {
        $response = $this->get(route('settings.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * @test
     * T436: Authenticated user can access settings
     */
    public function authenticated_user_can_access_settings(): void
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('settings.index'));

        $response->assertStatus(200);
    }
}
