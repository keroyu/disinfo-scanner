<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * T049: Unit tests for SettingService fallback behavior
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

    public function test_get_point_redemption_days_returns_default_when_setting_missing(): void
    {
        // Ensure no setting exists
        Setting::where('key', 'point_redemption_days')->delete();

        $days = $this->service->getPointRedemptionDays();

        $this->assertEquals(3, $days);
    }

    public function test_get_point_redemption_days_returns_stored_value(): void
    {
        Setting::setValue('point_redemption_days', '7');

        $days = $this->service->getPointRedemptionDays();

        $this->assertEquals(7, $days);
    }

    public function test_get_point_redemption_days_returns_default_for_zero(): void
    {
        Setting::setValue('point_redemption_days', '0');

        $days = $this->service->getPointRedemptionDays();

        $this->assertEquals(3, $days);
    }

    public function test_get_point_redemption_days_returns_default_for_negative(): void
    {
        Setting::setValue('point_redemption_days', '-5');

        $days = $this->service->getPointRedemptionDays();

        $this->assertEquals(3, $days);
    }

    public function test_get_point_redemption_days_returns_default_for_over_365(): void
    {
        Setting::setValue('point_redemption_days', '400');

        $days = $this->service->getPointRedemptionDays();

        $this->assertEquals(3, $days);
    }

    public function test_get_point_redemption_days_returns_default_for_non_numeric(): void
    {
        Setting::setValue('point_redemption_days', 'invalid');

        $days = $this->service->getPointRedemptionDays();

        $this->assertEquals(3, $days);
    }

    public function test_get_point_redemption_days_accepts_minimum_boundary(): void
    {
        Setting::setValue('point_redemption_days', '1');

        $days = $this->service->getPointRedemptionDays();

        $this->assertEquals(1, $days);
    }

    public function test_get_point_redemption_days_accepts_maximum_boundary(): void
    {
        Setting::setValue('point_redemption_days', '365');

        $days = $this->service->getPointRedemptionDays();

        $this->assertEquals(365, $days);
    }

    public function test_set_point_redemption_days_stores_value(): void
    {
        $this->service->setPointRedemptionDays(10);

        $this->assertEquals('10', Setting::getValue('point_redemption_days'));
    }

    public function test_set_point_redemption_days_clears_cache(): void
    {
        // Pre-populate cache
        Cache::put('setting:point_redemption_days', '3', 3600);

        $this->service->setPointRedemptionDays(10);

        $this->assertNull(Cache::get('setting:point_redemption_days'));
    }

    public function test_get_uses_cache(): void
    {
        Setting::setValue('point_redemption_days', '5');

        // First call should populate cache
        $this->service->getPointRedemptionDays();

        // Change database value directly
        Setting::where('key', 'point_redemption_days')->update(['value' => '10']);

        // Second call should return cached value
        $days = $this->service->getPointRedemptionDays();

        $this->assertEquals(5, $days);
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
