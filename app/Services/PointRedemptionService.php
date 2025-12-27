<?php

namespace App\Services;

use App\Models\User;
use App\Models\PointLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * T077: Point Redemption Service
 *
 * Updated 2025-12-27: Changed from "10 points = N days" to "X points = 1 day"
 * Updated 2025-12-27: Batch redemption - redeems ALL available points at once
 * - Points deducted are now configurable via getPointsPerDay()
 * - Redeems maximum possible days based on available points
 */
class PointRedemptionService
{
    /**
     * Default points required per day (fallback if setting unavailable)
     */
    public const DEFAULT_POINTS_REQUIRED = 10;

    protected SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Get the current points required per day from settings.
     */
    public function getPointsPerDay(): int
    {
        return $this->settingService->getPointsPerDay();
    }

    /**
     * Calculate how many days can be redeemed with given points.
     */
    public function calculateRedeemableDays(int $points): int
    {
        $pointsPerDay = $this->getPointsPerDay();
        return intdiv($points, $pointsPerDay);
    }

    /**
     * Calculate points that will be deducted for given points.
     */
    public function calculatePointsToDeduct(int $points): int
    {
        $pointsPerDay = $this->getPointsPerDay();
        $days = $this->calculateRedeemableDays($points);
        return $days * $pointsPerDay;
    }

    /**
     * Redeem ALL available points to extend premium membership.
     * Batch redemption: deducts maximum redeemable points at once.
     *
     * @param User $user The user redeeming points
     * @return array{success: bool, message: string, new_expires_at?: Carbon, points_deducted?: int, days_granted?: int}
     */
    public function redeem(User $user): array
    {
        // Get configurable points before transaction
        $pointsPerDay = $this->getPointsPerDay();

        // Validate user is premium
        if (!$user->isPremium()) {
            return [
                'success' => false,
                'message' => '只有有效的高級會員才能兌換積分。',
            ];
        }

        // Validate sufficient points for at least 1 day
        if ($user->points < $pointsPerDay) {
            return [
                'success' => false,
                'message' => '積分不足，需要至少 ' . $pointsPerDay . ' 積分才能兌換 1 天。',
            ];
        }

        // Execute atomic transaction
        return DB::transaction(function () use ($user, $pointsPerDay) {
            // Lock the user row for update to prevent concurrent redemption
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

            // Re-check conditions after lock
            if (!$lockedUser->isPremium()) {
                return [
                    'success' => false,
                    'message' => '只有有效的高級會員才能兌換積分。',
                ];
            }

            if ($lockedUser->points < $pointsPerDay) {
                return [
                    'success' => false,
                    'message' => '積分不足，需要至少 ' . $pointsPerDay . ' 積分才能兌換 1 天。',
                ];
            }

            // Calculate maximum redeemable days and points to deduct
            $daysToGrant = intdiv($lockedUser->points, $pointsPerDay);
            $pointsToDeduct = $daysToGrant * $pointsPerDay;

            // Deduct points
            $lockedUser->points -= $pointsToDeduct;

            // Extend premium expiration
            $newExpiresAt = $lockedUser->premium_expires_at->addDays($daysToGrant);
            $lockedUser->premium_expires_at = $newExpiresAt;
            $lockedUser->save();

            // Log the transaction
            PointLog::create([
                'user_id' => $lockedUser->id,
                'amount' => -$pointsToDeduct,
                'action' => 'redeem',
            ]);

            return [
                'success' => true,
                'message' => '兌換成功！已扣除 ' . $pointsToDeduct . ' 積分，高級會員期限延長 ' . $daysToGrant . ' 天。',
                'new_expires_at' => $newExpiresAt,
                'points_deducted' => $pointsToDeduct,
                'days_granted' => $daysToGrant,
            ];
        });
    }
}
