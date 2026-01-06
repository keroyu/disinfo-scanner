<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected RolePermissionService $roleService;

    public function __construct(RolePermissionService $roleService)
    {
        $this->roleService = $roleService;
    }

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

            // Clone the Carbon instance to avoid mutating the original
            $startDate = $user->isPremium() && $user->premium_expires_at->isFuture()
                ? $user->premium_expires_at->copy()
                : now();

            $newExpiryDate = $startDate->addDays($days);

            $user->premium_expires_at = $newExpiryDate;
            $user->save();

            // Ensure user has premium_member role using RolePermissionService
            $hadRole = $this->roleService->hasRole($user, 'premium_member');
            Log::info('Checking premium_member role before assignment', [
                'trace_id' => $traceId,
                'user_id' => $user->id,
                'had_role' => $hadRole,
            ]);

            if (!$hadRole) {
                $assigned = $this->roleService->assignRole($user, 'premium_member');
                Log::info('Premium member role assignment result', [
                    'trace_id' => $traceId,
                    'user_id' => $user->id,
                    'assigned' => $assigned,
                ]);
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
