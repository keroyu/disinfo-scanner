<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * T076: SettingService
 *
 * Updated 2025-12-27: Changed from getPointRedemptionDays to getPointsPerDay
 * - Old: 10 points = N days (configurable days, fixed points)
 * - New: X points = 1 day (configurable points, fixed day)
 */
class SettingService
{
    private const CACHE_PREFIX = 'setting:';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get the points required per day of premium extension.
     * Returns default of 10 if setting is missing or invalid.
     */
    public function getPointsPerDay(): int
    {
        $value = $this->get('points_per_day', '10');
        $points = (int) $value;

        // Fallback to default if invalid (must be 1-1000)
        if ($points < 1 || $points > 1000) {
            return 10;
        }

        return $points;
    }

    /**
     * Set the points per day setting.
     */
    public function setPointsPerDay(int $points): void
    {
        $this->set('points_per_day', (string) $points);
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
