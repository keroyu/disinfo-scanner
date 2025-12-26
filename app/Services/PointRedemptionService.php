<?php

namespace App\Services;

use App\Models\User;
use App\Models\PointLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Point Redemption Service
 * T009: Handles point redemption business logic
 */
class PointRedemptionService
{
    /**
     * Points required for redemption
     */
    public const POINTS_REQUIRED = 10;

    /**
     * Days granted per redemption
     */
    public const DAYS_GRANTED = 3;

    /**
     * Redeem points to extend premium membership.
     *
     * @param User $user The user redeeming points
     * @return array{success: bool, message: string, new_expires_at?: Carbon}
     */
    public function redeem(User $user): array
    {
        // Validate user is premium
        if (!$user->isPremium()) {
            return [
                'success' => false,
                'message' => '只有有效的高級會員才能兌換積分。',
            ];
        }

        // Validate sufficient points
        if ($user->points < self::POINTS_REQUIRED) {
            return [
                'success' => false,
                'message' => '積分不足，需要 ' . self::POINTS_REQUIRED . ' 積分才能兌換。',
            ];
        }

        // Execute atomic transaction
        return DB::transaction(function () use ($user) {
            // Lock the user row for update to prevent concurrent redemption
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

            // Re-check conditions after lock
            if (!$lockedUser->isPremium()) {
                return [
                    'success' => false,
                    'message' => '只有有效的高級會員才能兌換積分。',
                ];
            }

            if ($lockedUser->points < self::POINTS_REQUIRED) {
                return [
                    'success' => false,
                    'message' => '積分不足，需要 ' . self::POINTS_REQUIRED . ' 積分才能兌換。',
                ];
            }

            // Deduct points
            $lockedUser->points -= self::POINTS_REQUIRED;

            // Extend premium expiration
            $newExpiresAt = $lockedUser->premium_expires_at->addDays(self::DAYS_GRANTED);
            $lockedUser->premium_expires_at = $newExpiresAt;
            $lockedUser->save();

            // Log the transaction
            PointLog::create([
                'user_id' => $lockedUser->id,
                'amount' => -self::POINTS_REQUIRED,
                'action' => 'redeem',
            ]);

            return [
                'success' => true,
                'message' => '兌換成功！已扣除 ' . self::POINTS_REQUIRED . ' 積分，高級會員期限延長 ' . self::DAYS_GRANTED . ' 天。',
                'new_expires_at' => $newExpiresAt,
            ];
        });
    }
}
