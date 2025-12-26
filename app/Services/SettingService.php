<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    private const CACHE_PREFIX = 'setting:';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get the point redemption days setting.
     * Returns default of 3 if setting is missing or invalid.
     */
    public function getPointRedemptionDays(): int
    {
        $value = $this->get('point_redemption_days', '3');
        $days = (int) $value;

        // Fallback to default if invalid (must be 1-365)
        if ($days < 1 || $days > 365) {
            return 3;
        }

        return $days;
    }

    /**
     * Set the point redemption days setting.
     */
    public function setPointRedemptionDays(int $days): void
    {
        $this->set('point_redemption_days', (string) $days);
    }

    /**
     * Get a setting value with caching.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember(
            self::CACHE_PREFIX . $key,
            self::CACHE_TTL,
            fn () => Setting::getValue($key, $default)
        );
    }

    /**
     * Set a setting value and invalidate cache.
     */
    public function set(string $key, string $value): void
    {
        Setting::setValue($key, $value);
        Cache::forget(self::CACHE_PREFIX . $key);
    }
}
