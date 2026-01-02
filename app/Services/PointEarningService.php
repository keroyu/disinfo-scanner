<?php

namespace App\Services;

use App\Models\PointLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * T107-T108: Service for handling point acquisition
 *
 * This service handles earning points from various sources (U-API import, etc.)
 * Separated from PointRedemptionService which handles spending points.
 */
class PointEarningService
{
    /**
     * Grant points for U-API video import.
     *
     * @param User $user The user earning points
     * @param string $videoId The imported video ID (for logging reference)
     * @return array{success: bool, points_earned: int, new_balance: int}
     */
    public function grantUapiImportPoint(User $user, string $videoId): array
    {
        return DB::transaction(function () use ($user, $videoId) {
            // Lock user row for update to prevent race conditions
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

            // Increment points
            $lockedUser->points += 1;
            $lockedUser->save();

            // Log the point earning
            PointLog::create([
                'user_id' => $lockedUser->id,
                'amount' => 1,
                'action' => 'uapi_import',
            ]);

            return [
                'success' => true,
                'points_earned' => 1,
                'new_balance' => $lockedUser->points,
            ];
        });
    }
}
