<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * T011: Feature tests for points display on settings page
 */
class PointsDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test premium member can see points section on settings page.
     */
    public function test_premium_member_can_see_points_section(): void
    {
        $user = User::factory()->create([
            'points' => 15,
            'premium_expires_at' => Carbon::now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertStatus(200);
        $response->assertSee('積分系統');
        $response->assertSee('目前積分');
        $response->assertSee('15');
    }

    /**
     * Test premium member can see expiration date on settings page.
     */
    public function test_premium_member_can_see_expiration_date(): void
    {
        $expiresAt = Carbon::now()->addDays(30);
        $user = User::factory()->create([
            'points' => 10,
            'premium_expires_at' => $expiresAt,
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertStatus(200);
        $response->assertSee('高級會員到期');
        // Check formatted date in GMT+8
        $response->assertSee($expiresAt->timezone('Asia/Taipei')->format('Y-m-d'));
    }

    /**
     * Test non-premium user does not see points section.
     */
    public function test_non_premium_user_does_not_see_points_section(): void
    {
        $user = User::factory()->create([
            'points' => 0,
            'premium_expires_at' => null,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertStatus(200);
        $response->assertDontSee('積分系統');
        $response->assertDontSee('目前積分');
    }

    /**
     * Test expired premium user does not see points section.
     */
    public function test_expired_premium_user_does_not_see_points_section(): void
    {
        $user = User::factory()->create([
            'points' => 10,
            'premium_expires_at' => Carbon::now()->subDays(1),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertStatus(200);
        $response->assertDontSee('積分系統');
    }

    /**
     * Test points display shows 0 when user has no points.
     */
    public function test_points_display_shows_zero(): void
    {
        $user = User::factory()->create([
            'points' => 0,
            'premium_expires_at' => Carbon::now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertStatus(200);
        $response->assertSee('積分系統');
        $response->assertSee('0');
    }
}
