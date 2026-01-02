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
     * Test non-premium user CAN now see points section (Phase 9 change).
     * Updated: Regular members now see points section to track their earnings.
     */
    public function test_non_premium_user_can_see_points_section(): void
    {
        $user = User::factory()->create([
            'points' => 0,
            'premium_expires_at' => null,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertStatus(200);
        $response->assertSee('積分系統');
        $response->assertSee('目前積分');
        $response->assertSee('需升級為高級會員才能兌換積分');
    }

    /**
     * Test expired premium user CAN now see points section (Phase 9 change).
     * Updated: Expired premium users now see points section but with upgrade prompt.
     */
    public function test_expired_premium_user_can_see_points_section(): void
    {
        $user = User::factory()->create([
            'points' => 10,
            'premium_expires_at' => Carbon::now()->subDays(1),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertStatus(200);
        $response->assertSee('積分系統');
        $response->assertSee('10'); // Can still see their points
        $response->assertSee('需升級為高級會員才能兌換積分');
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

    // =========================================================================
    // Phase 9 Tests: Regular member points visibility (T100-T102)
    // =========================================================================

    /**
     * T100: Test regular member can see points balance on settings page.
     */
    public function test_regular_member_can_see_points_balance(): void
    {
        $user = User::factory()->create([
            'points' => 10,
            'premium_expires_at' => null,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertStatus(200);
        $response->assertSee('積分系統');
        $response->assertSee('目前積分');
        $response->assertSee('10');
    }

    /**
     * T101: Test regular member sees upgrade prompt on settings page.
     */
    public function test_regular_member_sees_upgrade_prompt(): void
    {
        $user = User::factory()->create([
            'points' => 15,
            'premium_expires_at' => null,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertStatus(200);
        $response->assertSee('需升級為高級會員才能兌換積分');
    }

    /**
     * T102: Test regular member sees points earning info on settings page.
     */
    public function test_regular_member_sees_points_earning_info(): void
    {
        $user = User::factory()->create([
            'points' => 5,
            'premium_expires_at' => null,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertStatus(200);
        $response->assertSee('透過 U-API 導入影片可獲得積分');
    }

    /**
     * Test regular member can view point logs.
     */
    public function test_regular_member_can_view_point_logs(): void
    {
        $user = User::factory()->create([
            'points' => 5,
            'premium_expires_at' => null,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        // Create a point log for the user
        \App\Models\PointLog::create([
            'user_id' => $user->id,
            'amount' => 1,
            'action' => 'uapi_import',
        ]);

        $response = $this->actingAs($user)->get('/settings/points/logs');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                [
                    'amount' => 1,
                    'action' => 'uapi_import',
                    'action_display' => 'U-API 導入',
                ],
            ],
        ]);
    }
}
