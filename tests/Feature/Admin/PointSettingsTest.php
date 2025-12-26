<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * T045-T048: Feature tests for Admin Point Settings
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

    // T045: Test for viewing admin settings page
    public function test_admin_can_view_point_settings_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/points/settings');

        $response->assertStatus(200);
        $response->assertSee('積分設定');
        $response->assertSee('兌換天數');
    }

    public function test_admin_settings_page_shows_current_value(): void
    {
        $admin = $this->createAdmin();
        Setting::setValue('point_redemption_days', '5');

        $response = $this->actingAs($admin)->get('/admin/points/settings');

        $response->assertStatus(200);
        $response->assertSee('5');
    }

    // T046: Test for updating redemption days
    public function test_admin_can_update_redemption_days(): void
    {
        $admin = $this->createAdmin();
        Setting::setValue('point_redemption_days', '3');

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'point_redemption_days' => 7,
        ]);

        $response->assertRedirect('/admin/points/settings');
        $response->assertSessionHas('success');
        $this->assertEquals('7', Setting::getValue('point_redemption_days'));
    }

    public function test_updating_settings_clears_cache(): void
    {
        $admin = $this->createAdmin();
        Setting::setValue('point_redemption_days', '3');

        // Pre-populate cache
        Cache::put('setting:point_redemption_days', '3', 3600);

        $this->actingAs($admin)->post('/admin/points/settings', [
            'point_redemption_days' => 10,
        ]);

        // Cache should be cleared, so next get should return new value
        $this->assertNull(Cache::get('setting:point_redemption_days'));
    }

    // T047: Test for validation errors
    public function test_validation_rejects_zero_days(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'point_redemption_days' => 0,
        ]);

        $response->assertSessionHasErrors('point_redemption_days');
    }

    public function test_validation_rejects_negative_days(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'point_redemption_days' => -5,
        ]);

        $response->assertSessionHasErrors('point_redemption_days');
    }

    public function test_validation_rejects_days_over_365(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'point_redemption_days' => 366,
        ]);

        $response->assertSessionHasErrors('point_redemption_days');
    }

    public function test_validation_rejects_non_integer(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'point_redemption_days' => 'abc',
        ]);

        $response->assertSessionHasErrors('point_redemption_days');
    }

    public function test_validation_rejects_empty_value(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'point_redemption_days' => '',
        ]);

        $response->assertSessionHasErrors('point_redemption_days');
    }

    public function test_validation_accepts_minimum_value(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'point_redemption_days' => 1,
        ]);

        $response->assertRedirect('/admin/points/settings');
        $response->assertSessionHas('success');
        $this->assertEquals('1', Setting::getValue('point_redemption_days'));
    }

    public function test_validation_accepts_maximum_value(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/points/settings', [
            'point_redemption_days' => 365,
        ]);

        $response->assertRedirect('/admin/points/settings');
        $response->assertSessionHas('success');
        $this->assertEquals('365', Setting::getValue('point_redemption_days'));
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
        Setting::setValue('point_redemption_days', '3');

        $response = $this->actingAs($user)->post('/admin/points/settings', [
            'point_redemption_days' => 10,
        ]);

        $response->assertStatus(403);
        $this->assertEquals('3', Setting::getValue('point_redemption_days'));
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/admin/points/settings');

        $response->assertRedirect('/auth/login');
    }

    public function test_unauthenticated_user_cannot_update_settings(): void
    {
        $response = $this->post('/admin/points/settings', [
            'point_redemption_days' => 10,
        ]);

        $response->assertRedirect('/auth/login');
    }
}
