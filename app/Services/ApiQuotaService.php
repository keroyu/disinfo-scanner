<?php

namespace App\Services;

use App\Models\User;
use App\Models\ApiQuota;
use Illuminate\Support\Facades\Log;

class ApiQuotaService
{
    /**
     * Monthly quota limit for Premium Members.
     */
    const MONTHLY_LIMIT = 10;

    /**
     * Get or create API quota record for user.
     *
     * @param User $user
     * @return ApiQuota
     */
    public function getOrCreateQuota(User $user): ApiQuota
    {
        $currentMonth = now()->format('Y-m');

        $quota = ApiQuota::firstOrCreate(
            ['user_id' => $user->id],
            [
                'current_month' => $currentMonth,
                'usage_count' => 0,
                'monthly_limit' => self::MONTHLY_LIMIT,
                'is_unlimited' => false,
            ]
        );

        // Reset quota if month has changed
        if ($quota->current_month !== $currentMonth) {
            $quota->usage_count = 0;
            $quota->current_month = $currentMonth;
            $quota->save();

            Log::info('API quota reset for new month', [
                'user_id' => $user->id,
                'month' => $currentMonth,
            ]);
        }

        return $quota;
    }

    /**
     * Check if user can use API import (within quota).
     *
     * @param User $user
     * @return array ['allowed' => bool, 'message' => string, 'usage' => array]
     */
    public function checkQuota(User $user): array
    {
        $quota = $this->getOrCreateQuota($user);

        // Unlimited access (identity verified)
        if ($quota->is_unlimited) {
            return [
                'allowed' => true,
                'message' => '您擁有無限配額',
                'usage' => [
                    'used' => $quota->usage_count,
                    'limit' => null,
                    'remaining' => null,
                    'unlimited' => true,
                ],
            ];
        }

        // Check if within limit
        if ($quota->usage_count < $quota->monthly_limit) {
            return [
                'allowed' => true,
                'message' => '配額充足',
                'usage' => [
                    'used' => $quota->usage_count,
                    'limit' => $quota->monthly_limit,
                    'remaining' => $quota->monthly_limit - $quota->usage_count,
                    'unlimited' => false,
                ],
            ];
        }

        // Quota exceeded
        return [
            'allowed' => false,
            'message' => "您已達到本月配額上限 ({$quota->usage_count}/{$quota->monthly_limit})。請完成身份驗證以獲得無限配額。",
            'usage' => [
                'used' => $quota->usage_count,
                'limit' => $quota->monthly_limit,
                'remaining' => 0,
                'unlimited' => false,
            ],
        ];
    }

    /**
     * Increment usage count after successful import.
     *
     * @param User $user
     * @return void
     */
    public function incrementUsage(User $user): void
    {
        $quota = $this->getOrCreateQuota($user);

        $quota->usage_count++;
        $quota->last_import_at = now();
        $quota->save();

        Log::info('API quota usage incremented', [
            'user_id' => $user->id,
            'usage_count' => $quota->usage_count,
            'monthly_limit' => $quota->monthly_limit,
            'month' => $quota->current_month,
        ]);
    }

    /**
     * Grant unlimited quota (after identity verification).
     *
     * @param User $user
     * @return void
     */
    public function grantUnlimitedQuota(User $user): void
    {
        $quota = $this->getOrCreateQuota($user);

        $quota->is_unlimited = true;
        $quota->save();

        Log::info('SECURITY: Unlimited API quota granted', [
            'user_id' => $user->id,
            'email' => $user->email,
            'granted_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Revoke unlimited quota.
     *
     * @param User $user
     * @return void
     */
    public function revokeUnlimitedQuota(User $user): void
    {
        $quota = $this->getOrCreateQuota($user);

        $quota->is_unlimited = false;
        $quota->save();

        Log::info('SECURITY: Unlimited API quota revoked', [
            'user_id' => $user->id,
            'email' => $user->email,
            'revoked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get quota usage statistics for display.
     *
     * @param User $user
     * @return array
     */
    public function getQuotaStats(User $user): array
    {
        $quota = $this->getOrCreateQuota($user);

        return [
            'used' => $quota->usage_count,
            'limit' => $quota->monthly_limit,
            'remaining' => $quota->is_unlimited ? null : max(0, $quota->monthly_limit - $quota->usage_count),
            'unlimited' => $quota->is_unlimited,
            'last_import' => $quota->last_import_at ? $quota->last_import_at->timezone('Asia/Taipei')->format('Y-m-d H:i (T)') : null,
            'current_month' => $quota->current_month,
        ];
    }

    /**
     * Reset monthly quota for all users (called by scheduled task).
     *
     * @return int Number of quotas reset
     */
    public function resetMonthlyQuota(): int
    {
        $currentMonth = now()->format('Y-m');

        $resetCount = ApiQuota::where('current_month', '!=', $currentMonth)
            ->update([
                'usage_count' => 0,
                'current_month' => $currentMonth,
            ]);

        if ($resetCount > 0) {
            Log::info('Monthly API quotas reset', [
                'reset_count' => $resetCount,
                'month' => $currentMonth,
            ]);
        }

        return $resetCount;
    }

    /**
     * Check if user has unlimited quota.
     *
     * @param User $user
     * @return bool
     */
    public function hasUnlimitedQuota(User $user): bool
    {
        $quota = $this->getOrCreateQuota($user);
        return $quota->is_unlimited;
    }
}
