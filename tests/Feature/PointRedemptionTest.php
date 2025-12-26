<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\PointLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * T016-T018: Feature tests for point redemption
 */
class PointRedemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * T016: Test successful redemption deducts points and extends membership.
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
        // Premium extended by 3 days
        $this->assertTrue($user->premium_expires_at->gt($expiresAt->addDays(2)));
        $this->assertTrue($user->premium_expires_at->lt($expiresAt->addDays(4)));
    }

    /**
     * T016: Test redemption creates a point log entry.
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
        $this->assertEquals(-10, $log->amount);
        $this->assertEquals('redeem', $log->action);
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
     * T017: Test exactly 10 points allows redemption.
     */
    public function test_exactly_ten_points_allows_redemption(): void
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
     * Test multiple consecutive redemptions work correctly.
     */
    public function test_multiple_consecutive_redemptions(): void
    {
        $expiresAt = Carbon::now()->addDays(10);
        $user = User::factory()->create([
            'points' => 30,
            'premium_expires_at' => $expiresAt,
            'has_default_password' => false,
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        // Use actingAs once and make all requests in sequence
        $this->actingAs($user);

        // First redemption
        $response1 = $this->post('/settings/points/redeem');
        $response1->assertRedirect('/settings');
        $this->assertEquals(20, User::find($user->id)->points);

        // Second redemption
        $response2 = $this->post('/settings/points/redeem');
        $response2->assertRedirect('/settings');
        $this->assertEquals(10, User::find($user->id)->points);

        // Third redemption
        $response3 = $this->post('/settings/points/redeem');
        $response3->assertRedirect('/settings');
        $this->assertEquals(0, User::find($user->id)->points);

        // Verify total extension (9 days from original expiry)
        $finalUser = User::find($user->id);
        $expectedExpiresAt = $expiresAt->copy()->addDays(9);
        $this->assertTrue($finalUser->premium_expires_at->diffInSeconds($expectedExpiresAt) < 5);

        // Verify 3 point logs created
        $this->assertEquals(3, PointLog::where('user_id', $user->id)->count());
    }
}
