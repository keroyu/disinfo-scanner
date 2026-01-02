<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Extend premium membership for a user.
     *
     * If the user is already a premium member with remaining days,
     * the new days are added to the current expiry date.
     * If the user is not a premium member or their membership has expired,
     * the new days are added from today.
     */
    public function extendPremium(User $user, int $days, ?string $traceId = null): void
    {
        DB::transaction(function () use ($user, $days, $traceId) {
            // Lock the user row to prevent race conditions
            $user = User::lockForUpdate()->find($user->id);

            $startDate = $user->isPremium() && $user->premium_expires_at->isFuture()
                ? $user->premium_expires_at
                : now();

            $newExpiryDate = $startDate->addDays($days);

            $user->premium_expires_at = $newExpiryDate;
            $user->save();

            // Ensure user has premium_member role
            if (!$user->hasRole('premium_member')) {
                $user->assignRole('premium_member');
            }

            Log::info('Premium membership extended', [
                'trace_id' => $traceId,
                'user_id' => $user->id,
                'days_added' => $days,
                'new_expiry' => $newExpiryDate->toIso8601String(),
            ]);
        });
    }
}
