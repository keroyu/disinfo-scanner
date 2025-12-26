<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;

/**
 * T010: Unit tests for User points methods
 */
class UserPointsTest extends TestCase
{
    /**
     * Test isPremium() returns true when premium_expires_at is in the future.
     */
    public function test_is_premium_returns_true_when_not_expired(): void
    {
        $user = new User();
        $user->premium_expires_at = Carbon::now()->addDays(30);

        $this->assertTrue($user->isPremium());
    }

    /**
     * Test isPremium() returns false when premium_expires_at is in the past.
     */
    public function test_is_premium_returns_false_when_expired(): void
    {
        $user = new User();
        $user->premium_expires_at = Carbon::now()->subDays(1);

        $this->assertFalse($user->isPremium());
    }

    /**
     * Test isPremium() returns false when premium_expires_at is null.
     */
    public function test_is_premium_returns_false_when_null(): void
    {
        $user = new User();
        $user->premium_expires_at = null;

        $this->assertFalse($user->isPremium());
    }

    /**
     * Test isPremium() returns false when premium_expires_at is exactly now.
     */
    public function test_is_premium_returns_false_when_exactly_now(): void
    {
        $user = new User();
        $user->premium_expires_at = Carbon::now();

        $this->assertFalse($user->isPremium());
    }

    /**
     * Test canRedeemPoints() returns true when premium and has enough points.
     */
    public function test_can_redeem_points_returns_true_when_eligible(): void
    {
        $user = new User();
        $user->premium_expires_at = Carbon::now()->addDays(30);
        $user->points = 15;

        $this->assertTrue($user->canRedeemPoints(10));
    }

    /**
     * Test canRedeemPoints() returns false when not premium.
     */
    public function test_can_redeem_points_returns_false_when_not_premium(): void
    {
        $user = new User();
        $user->premium_expires_at = null;
        $user->points = 15;

        $this->assertFalse($user->canRedeemPoints(10));
    }

    /**
     * Test canRedeemPoints() returns false when insufficient points.
     */
    public function test_can_redeem_points_returns_false_when_insufficient_points(): void
    {
        $user = new User();
        $user->premium_expires_at = Carbon::now()->addDays(30);
        $user->points = 5;

        $this->assertFalse($user->canRedeemPoints(10));
    }

    /**
     * Test canRedeemPoints() returns true when exactly enough points.
     */
    public function test_can_redeem_points_returns_true_when_exactly_enough(): void
    {
        $user = new User();
        $user->premium_expires_at = Carbon::now()->addDays(30);
        $user->points = 10;

        $this->assertTrue($user->canRedeemPoints(10));
    }

    /**
     * Test canRedeemPoints() uses default value of 10.
     */
    public function test_can_redeem_points_uses_default_value(): void
    {
        $user = new User();
        $user->premium_expires_at = Carbon::now()->addDays(30);
        $user->points = 10;

        $this->assertTrue($user->canRedeemPoints());
    }
}
