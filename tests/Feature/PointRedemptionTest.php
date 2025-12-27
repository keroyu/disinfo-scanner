<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\PointLog;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * T016-T018, T073: Feature tests for point redemption
 *
 * Updated 2025-12-27: Batch redemption - redeems ALL available points at once
 * - Points deducted are now configurable (default: 10)
 * - Redeems maximum possible days based on available points
 */
class PointRedemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        // Set default points_per_day
        Setting::setValue('points_per_day', '10');
    }

    /**
     * T016, T073: Test successful redemption deducts configurable points and extends membership by 1 day.
     */
    public function test_successful_redemption_deducts_points_and_extends_membership(): void
    {
        $expiresAt = Carbon::now()->addDays(10);
        $user = User::factory()->create([
            'points' => 15,
            'premium_expires_at' => $expiresAt,
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $response = $this->actingAs($user)->post('/settings/points/redeem');

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals(5, $user->points);
        // Premium extended by 1 day (fixed)
        $expectedExpiry = $expiresAt->copy()->addDay();
        $this->assertTrue($user->premium_expires_at->diffInSeconds($expectedExpiry) < 5);
    }

    /**
     * T073: Test batch redemption with custom points_per_day setting.
     * 12 points with 5 points/day = 2 days (deduct 10, leave 2)
     */
    public function test_redemption_uses_configurable_points_per_day(): void
    {
        // Set custom points per day
        Setting::setValue('points_per_day', '5');

        $expiresAt = Carbon::now()->addDays(10);
        $user = User::factory()->create([
            'points' => 12,
            'premium_expires_at' => $expiresAt,
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $response = $this->actingAs($user)->post('/settings/points/redeem');

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');

        $user->refresh();
        // Batch redemption: 12 / 5 = 2 days, deduct 10, leave 2
        $this->assertEquals(2, $user->points);
        // Premium extended by 2 days
        $expectedExpiry = $expiresAt->copy()->addDays(2);
        $this->assertTrue($user->premium_expires_at->diffInSeconds($expectedExpiry) < 5);
    }

    /**
     * T016: Test batch redemption creates a point log entry with correct amount.
     * 20 points with 10 points/day = 2 days (deduct 20)
     */
    public function test_redemption_creates_point_log(): void
    {
        $user = User::factory()->create([
            'points' => 20,
            'premium_expires_at' => Carbon::now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $this->actingAs($user)->post('/settings/points/redeem');

        $log = PointLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        // Batch: 20 / 10 = 2 days, deduct all 20
        $this->assertEquals(-20, $log->amount);
        $this->assertEquals('redeem', $log->action);
    }

    /**
     * T073: Test point log uses configurable points amount with batch.
     * 20 points with 7 points/day = 2 days (deduct 14, leave 6)
     */
    public function test_point_log_uses_configurable_amount(): void
    {
        Setting::setValue('points_per_day', '7');

        $user = User::factory()->create([
            'points' => 20,
            'premium_expires_at' => Carbon::now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $this->actingAs($user)->post('/settings/points/redeem');

        $log = PointLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        // Batch: 20 / 7 = 2 days, deduct 14
        $this->assertEquals(-14, $log->amount);
    }

    /**
     * T017: Test insufficient points rejection.
     */
    public function test_insufficient_points_rejection(): void
    {
        $user = User::factory()->create([
            'points' => 5,
            'premium_expires_at' => Carbon::now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $response = $this->actingAs($user)->post('/settings/points/redeem');

        $response->assertRedirect('/settings');
        $response->assertSessionHas('error');

        $user->refresh();
        $this->assertEquals(5, $user->points); // Points unchanged
    }

    /**
     * T017: Test exactly enough points allows redemption.
     */
    public function test_exactly_enough_points_allows_redemption(): void
    {
        $user = User::factory()->create([
            'points' => 10,
            'premium_expires_at' => Carbon::now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $response = $this->actingAs($user)->post('/settings/points/redeem');

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals(0, $user->points);
    }

    /**
     * T018: Test non-premium rejection.
     */
    public function test_non_premium_rejection(): void
    {
        $user = User::factory()->create([
            'points' => 20,
            'premium_expires_at' => null,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        $response = $this->actingAs($user)->post('/settings/points/redeem');

        $response->assertRedirect('/settings');
        $response->assertSessionHas('error');

        $user->refresh();
        $this->assertEquals(20, $user->points); // Points unchanged
    }

    /**
     * T018: Test expired premium rejection.
     */
    public function test_expired_premium_rejection(): void
    {
        $user = User::factory()->create([
            'points' => 20,
            'premium_expires_at' => Carbon::now()->subDays(1),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $response = $this->actingAs($user)->post('/settings/points/redeem');

        $response->assertRedirect('/settings');
        $response->assertSessionHas('error');

        $user->refresh();
        $this->assertEquals(20, $user->points); // Points unchanged
    }

    /**
     * Test unauthenticated user is redirected to login.
     */
    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->post('/settings/points/redeem');

        $response->assertRedirect('/auth/login');
    }

    /**
     * T073: Test batch redemption takes all points at once.
     * 30 points with 10 points/day = 3 days (deduct 30, leave 0)
     */
    public function test_batch_redemption_takes_all_points(): void
    {
        $expiresAt = Carbon::now()->addDays(10);
        $user = User::factory()->create([
            'points' => 30,
            'premium_expires_at' => $expiresAt,
            'has_default_password' => false,
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $this->actingAs($user);

        // Single batch redemption takes all 30 points for 3 days
        $response = $this->post('/settings/points/redeem');
        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');

        $this->assertEquals(0, User::find($user->id)->points);

        // Verify total extension (3 days from original expiry)
        $finalUser = User::find($user->id);
        $expectedExpiresAt = $expiresAt->copy()->addDays(3);
        $this->assertTrue($finalUser->premium_expires_at->diffInSeconds($expectedExpiresAt) < 5);

        // Verify 1 point log created (single batch)
        $this->assertEquals(1, PointLog::where('user_id', $user->id)->count());
        $this->assertEquals(-30, PointLog::where('user_id', $user->id)->first()->amount);
    }

    /**
     * T073: Test batch redemption with custom points_per_day leaves remainder.
     * 12 points with 5 points/day = 2 days (deduct 10, leave 2)
     */
    public function test_batch_redemption_with_custom_points(): void
    {
        Setting::setValue('points_per_day', '5');

        $expiresAt = Carbon::now()->addDays(10);
        $user = User::factory()->create([
            'points' => 12,
            'premium_expires_at' => $expiresAt,
            'has_default_password' => false,
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $this->actingAs($user);

        // Batch redemption: 12 / 5 = 2 days, deduct 10, leave 2
        $response = $this->post('/settings/points/redeem');
        $response->assertSessionHas('success');
        $this->assertEquals(2, User::find($user->id)->points);

        // Second redemption should fail (2 < 5)
        $response2 = $this->post('/settings/points/redeem');
        $response2->assertSessionHas('error');
        $this->assertEquals(2, User::find($user->id)->points);

        // Verify 1 point log (second failed)
        $this->assertEquals(1, PointLog::where('user_id', $user->id)->count());
        $this->assertEquals(-10, PointLog::where('user_id', $user->id)->first()->amount);

        // Verify total extension (2 days)
        $finalUser = User::find($user->id);
        $expectedExpiresAt = $expiresAt->copy()->addDays(2);
        $this->assertTrue($finalUser->premium_expires_at->diffInSeconds($expectedExpiresAt) < 5);
    }
}
