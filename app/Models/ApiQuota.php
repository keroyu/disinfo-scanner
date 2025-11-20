<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiQuota extends Model
{
    protected $fillable = [
        'user_id',
        'current_month',
        'usage_count',
        'monthly_limit',
        'is_unlimited',
        'last_import_at',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'monthly_limit' => 'integer',
        'is_unlimited' => 'boolean',
        'last_import_at' => 'datetime',
    ];

    /**
     * Relationships
     */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if quota is available.
     */
    public function hasQuotaAvailable(): bool
    {
        if ($this->is_unlimited) {
            return true;
        }

        return $this->usage_count < $this->monthly_limit;
    }

    /**
     * Get remaining quota.
     */
    public function getRemainingQuota(): int
    {
        if ($this->is_unlimited) {
            return PHP_INT_MAX;
        }

        return max(0, $this->monthly_limit - $this->usage_count);
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_import_at' => now()]);
    }

    /**
     * Reset monthly quota.
     */
    public function resetMonthly(): void
    {
        $this->update([
            'usage_count' => 0,
            'current_month' => now()->format('Y-m'),
        ]);
    }

    /**
     * Check if current month matches quota month.
     */
    public function isCurrentMonth(): bool
    {
        return $this->current_month === now()->format('Y-m');
    }

    /**
     * Get or create quota for user.
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'current_month' => now()->format('Y-m'),
                'usage_count' => 0,
                'monthly_limit' => 10,
                'is_unlimited' => false,
            ]
        );
    }
}
