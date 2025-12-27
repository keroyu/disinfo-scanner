<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * T049, T072: Unit tests for SettingService
 *
 * Updated 2025-12-27: Changed from getPointRedemptionDays to getPointsPerDay
 * - Old: 10 points = N days (configurable days, fixed points)
 * - New: X points = 1 day (configurable points, fixed day)
 */
class SettingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SettingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SettingService();
        Cache::flush();
    }

    public function test_get_points_per_day_returns_default_when_setting_missing(): void
    {
        // Ensure no setting exists
        Setting::where('key', 'points_per_day')->delete();

        $points = $this->service->getPointsPerDay();

        $this->assertEquals(10, $points);
    }

    public function test_get_points_per_day_returns_stored_value(): void
    {
        Setting::setValue('points_per_day', '5');

        $points = $this->service->getPointsPerDay();

        $this->assertEquals(5, $points);
    }

    public function test_get_points_per_day_returns_default_for_zero(): void
    {
        Setting::setValue('points_per_day', '0');

        $points = $this->service->getPointsPerDay();

        $this->assertEquals(10, $points);
    }

    public function test_get_points_per_day_returns_default_for_negative(): void
    {
        Setting::setValue('points_per_day', '-5');

        $points = $this->service->getPointsPerDay();

        $this->assertEquals(10, $points);
    }

    public function test_get_points_per_day_returns_default_for_over_1000(): void
    {
        Setting::setValue('points_per_day', '1500');

        $points = $this->service->getPointsPerDay();

        $this->assertEquals(10, $points);
    }

    public function test_get_points_per_day_returns_default_for_non_numeric(): void
    {
        Setting::setValue('points_per_day', 'invalid');

        $points = $this->service->getPointsPerDay();

        $this->assertEquals(10, $points);
    }

    public function test_get_points_per_day_accepts_minimum_boundary(): void
    {
        Setting::setValue('points_per_day', '1');

        $points = $this->service->getPointsPerDay();

        $this->assertEquals(1, $points);
    }

    public function test_get_points_per_day_accepts_maximum_boundary(): void
    {
        Setting::setValue('points_per_day', '1000');

        $points = $this->service->getPointsPerDay();

        $this->assertEquals(1000, $points);
    }

    public function test_set_points_per_day_stores_value(): void
    {
        $this->service->setPointsPerDay(15);

        $this->assertEquals('15', Setting::getValue('points_per_day'));
    }

    public function test_set_points_per_day_clears_cache(): void
    {
        // Pre-populate cache
        Cache::put('setting:points_per_day', '10', 3600);

        $this->service->setPointsPerDay(5);

        $this->assertNull(Cache::get('setting:points_per_day'));
    }

    public function test_get_uses_cache(): void
    {
        Setting::setValue('points_per_day', '5');

        // First call should populate cache
        $this->service->getPointsPerDay();

        // Change database value directly
        Setting::where('key', 'points_per_day')->update(['value' => '20']);

        // Second call should return cached value
        $points = $this->service->getPointsPerDay();

        $this->assertEquals(5, $points);
    }

    public function test_generic_get_with_default(): void
    {
        $value = $this->service->get('nonexistent_key', 'default_value');

        $this->assertEquals('default_value', $value);
    }

    public function test_generic_set_creates_new_setting(): void
    {
        $this->service->set('new_key', 'new_value');

        $this->assertEquals('new_value', Setting::getValue('new_key'));
    }

    public function test_generic_set_updates_existing_setting(): void
    {
        Setting::setValue('existing_key', 'old_value');

        $this->service->set('existing_key', 'new_value');

        $this->assertEquals('new_value', Setting::getValue('existing_key'));
    }
}
