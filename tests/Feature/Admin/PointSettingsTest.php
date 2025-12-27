<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * T045-T048, T074: Feature tests for Admin Point Settings
 *
 * Updated 2025-12-27: Changed from point_redemption_days to points_per_day
 * - Old: 10 points = N days (configurable days 1-365)
 * - New: X points = 1 day (configurable points 1-1000)
 */
class PointSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    protected function createAdmin(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $adminRole = Role::where('name', 'administrator')->first();
        $user->roles()->attach($adminRole);
        return $user;
    }

    protected function createRegularUser(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $memberRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($memberRole);
        return $user;
    }

    protected function createPremiumUser(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole);
        return $user;
    }

    // T045, T074: Test for viewing admin settings page
    public function test_admin_can_view_point_settings_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/points/settings');

        $response->assertStatus(200);
        $response->assertSee('積分設定');
        $response->assertSee('每日所需積分');
    }

    public function test_admin_settings_page_shows_current_value(): void
    {
        $admin = $this->createAdmin();
        Setting::setValue('points_per_day', '5');

        $response = $this->actingAs($admin)->get('/admin/points/settings');

        $response->assertStatus(200);
        $response->assertSee('5');
    }

    // T046, T074: Test for updating points per day
    public function test_admin_can_update_points_per_day(): void
    {
        $admin = $this->createAdmin();
        Setting::setValue('points_per_day', '10');

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'points_per_day' => 7,
        ]);

        $response->assertRedirect('/admin/points/settings');
        $response->assertSessionHas('success');
        $this->assertEquals('7', Setting::getValue('points_per_day'));
    }

    public function test_updating_settings_clears_cache(): void
    {
        $admin = $this->createAdmin();
        Setting::setValue('points_per_day', '10');

        // Pre-populate cache
        Cache::put('setting:points_per_day', '10', 3600);

        $this->actingAs($admin)->post('/admin/points/settings', [
            'points_per_day' => 5,
        ]);

        // Cache should be cleared, so next get should return new value
        $this->assertNull(Cache::get('setting:points_per_day'));
    }

    // T047, T074: Test for validation errors
    public function test_validation_rejects_zero_points(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'points_per_day' => 0,
        ]);

        $response->assertSessionHasErrors('points_per_day');
    }

    public function test_validation_rejects_negative_points(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'points_per_day' => -5,
        ]);

        $response->assertSessionHasErrors('points_per_day');
    }

    public function test_validation_rejects_points_over_1000(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'points_per_day' => 1001,
        ]);

        $response->assertSessionHasErrors('points_per_day');
    }

    public function test_validation_rejects_non_integer(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'points_per_day' => 'abc',
        ]);

        $response->assertSessionHasErrors('points_per_day');
    }

    public function test_validation_rejects_empty_value(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'points_per_day' => '',
        ]);

        $response->assertSessionHasErrors('points_per_day');
    }

    public function test_validation_accepts_minimum_value(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'points_per_day' => 1,
        ]);

        $response->assertRedirect('/admin/points/settings');
        $response->assertSessionHas('success');
        $this->assertEquals('1', Setting::getValue('points_per_day'));
    }

    public function test_validation_accepts_maximum_value(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'points_per_day' => 1000,
        ]);

        $response->assertRedirect('/admin/points/settings');
        $response->assertSessionHas('success');
        $this->assertEquals('1000', Setting::getValue('points_per_day'));
    }

    // T048: Test for non-admin access rejection
    public function test_regular_user_cannot_access_settings_page(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->get('/admin/points/settings');

        $response->assertStatus(403);
    }

    public function test_premium_user_cannot_access_settings_page(): void
    {
        $user = $this->createPremiumUser();

        $response = $this->actingAs($user)->get('/admin/points/settings');

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_update_settings(): void
    {
        $user = $this->createRegularUser();
        Setting::setValue('points_per_day', '10');

        $response = $this->actingAs($user)->post('/admin/points/settings', [
            'points_per_day' => 5,
        ]);

        $response->assertStatus(403);
        $this->assertEquals('10', Setting::getValue('points_per_day'));
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/admin/points/settings');

        $response->assertRedirect('/auth/login');
    }

    public function test_unauthenticated_user_cannot_update_settings(): void
    {
        $response = $this->post('/admin/points/settings', [
            'points_per_day' => 5,
        ]);

        $response->assertRedirect('/auth/login');
    }
}
