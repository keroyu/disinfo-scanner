<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use App\Services\ApiQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;
use Carbon\Carbon;

/**
 * Unit tests for API Quota Calculation (T419)
 *
 * Tests the quota calculation logic in isolation.
 */
class ApiQuotaCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected ApiQuotaService $quotaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->quotaService = app(ApiQuotaService::class);
    }

    /**
     * @test
     * T419: Remaining quota calculation is accurate
     */
    public function remaining_quota_calculated_correctly(): void
    {
        $user = User::factory()->create();

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 7,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $result = $this->quotaService->checkQuota($user);

        $this->assertEquals(3, $result['usage']['remaining']);
    }

    /**
     * @test
     * T419: Zero remaining when at limit
     */
    public function zero_remaining_when_at_limit(): void
    {
        $user = User::factory()->create();

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 10,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $result = $this->quotaService->checkQuota($user);

        $this->assertEquals(0, $result['usage']['remaining']);
    }

    /**
     * @test
     * T419: Remaining is null for unlimited users
     */
    public function remaining_is_null_for_unlimited_users(): void
    {
        $user = User::factory()->create();

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 50,
            'monthly_limit' => 10,
            'is_unlimited' => true,
        ]);

        $result = $this->quotaService->checkQuota($user);

        $this->assertNull($result['usage']['remaining']);
    }

    /**
     * @test
     * T419: Usage cannot go negative
     */
    public function usage_cannot_go_negative(): void
    {
        $user = User::factory()->create();

        $quota = ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 0,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        // Remaining should be capped at limit
        $this->assertEquals(10, $quota->getRemainingQuota());
    }

    /**
     * @test
     * T419: Model hasQuotaAvailable method works
     */
    public function model_has_quota_available_works(): void
    {
        $user = User::factory()->create();

        $quota = ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 5,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $this->assertTrue($quota->hasQuotaAvailable());

        $quota->usage_count = 10;
        $quota->save();

        $this->assertFalse($quota->hasQuotaAvailable());
    }

    /**
     * @test
     * T419: Unlimited quota model always has available
     */
    public function unlimited_quota_always_has_available(): void
    {
        $user = User::factory()->create();

        $quota = ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 100,
            'monthly_limit' => 10,
            'is_unlimited' => true,
        ]);

        $this->assertTrue($quota->hasQuotaAvailable());
    }

    /**
     * @test
     * T419: Model getRemainingQuota for unlimited returns max int
     */
    public function unlimited_remaining_returns_max_int(): void
    {
        $user = User::factory()->create();

        $quota = ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 50,
            'monthly_limit' => 10,
            'is_unlimited' => true,
        ]);

        $this->assertEquals(PHP_INT_MAX, $quota->getRemainingQuota());
    }

    /**
     * @test
     * T419: Usage over limit still returns 0 remaining (not negative)
     */
    public function over_limit_usage_returns_zero_remaining(): void
    {
        $user = User::factory()->create();

        // This shouldn't happen normally, but ensure it's handled gracefully
        $quota = ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 15, // Over limit
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $this->assertEquals(0, $quota->getRemainingQuota());
    }

    /**
     * @test
     * T419: Default monthly limit is 10
     */
    public function default_monthly_limit_is_ten(): void
    {
        $this->assertEquals(10, ApiQuotaService::MONTHLY_LIMIT);
    }

    /**
     * @test
     * T419: getOrCreate creates with correct defaults
     */
    public function get_or_create_uses_correct_defaults(): void
    {
        $user = User::factory()->create();

        $quota = ApiQuota::getOrCreateForUser($user->id);

        $this->assertEquals(0, $quota->usage_count);
        $this->assertEquals(10, $quota->monthly_limit);
        $this->assertFalse($quota->is_unlimited);
        $this->assertEquals(now()->format('Y-m'), $quota->current_month);
    }
}
