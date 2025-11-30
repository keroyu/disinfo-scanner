<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\ApiQuota;
use App\Services\ApiQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * Unit tests for API Quota Reset (T420)
 *
 * Tests the monthly quota reset functionality.
 */
class ApiQuotaResetTest extends TestCase
{
    use RefreshDatabase;

    protected ApiQuotaService $quotaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quotaService = app(ApiQuotaService::class);
    }

    /**
     * @test
     * T420: Quota resets when month changes
     */
    public function quota_resets_when_month_changes(): void
    {
        $user = User::factory()->create();

        // Create quota for last month
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');
        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => $lastMonth,
            'usage_count' => 8,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        // Check quota should trigger reset
        $result = $this->quotaService->checkQuota($user);

        $this->assertEquals(0, $result['usage']['used']);
        $this->assertEquals(10, $result['usage']['remaining']);
    }

    /**
     * @test
     * T420: Quota does not reset within same month
     */
    public function quota_does_not_reset_within_same_month(): void
    {
        $user = User::factory()->create();

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 5,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $result = $this->quotaService->checkQuota($user);

        $this->assertEquals(5, $result['usage']['used']);
    }

    /**
     * @test
     * T420: Batch reset updates correct records
     */
    public function batch_reset_updates_correct_records(): void
    {
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');

        // Create users with quotas from last month
        $user1 = User::factory()->create();
        ApiQuota::create([
            'user_id' => $user1->id,
            'current_month' => $lastMonth,
            'usage_count' => 5,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $user2 = User::factory()->create();
        ApiQuota::create([
            'user_id' => $user2->id,
            'current_month' => $lastMonth,
            'usage_count' => 10,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        // User with current month - should NOT be reset
        $user3 = User::factory()->create();
        ApiQuota::create([
            'user_id' => $user3->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 7,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        // Run batch reset
        $resetCount = $this->quotaService->resetMonthlyQuota();

        $this->assertEquals(2, $resetCount);

        // Verify user3's quota was not reset
        $user3Quota = ApiQuota::where('user_id', $user3->id)->first();
        $this->assertEquals(7, $user3Quota->usage_count);
    }

    /**
     * @test
     * T420: Model resetMonthly method works
     */
    public function model_reset_monthly_works(): void
    {
        $user = User::factory()->create();

        $quota = ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => Carbon::now()->subMonth()->format('Y-m'),
            'usage_count' => 8,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $quota->resetMonthly();

        $this->assertEquals(0, $quota->usage_count);
        $this->assertEquals(now()->format('Y-m'), $quota->current_month);
    }

    /**
     * @test
     * T420: isCurrentMonth method returns correct value
     */
    public function is_current_month_returns_correct_value(): void
    {
        $user = User::factory()->create();

        $quotaCurrent = ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 0,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $this->assertTrue($quotaCurrent->isCurrentMonth());

        $user2 = User::factory()->create();
        $quotaPast = ApiQuota::create([
            'user_id' => $user2->id,
            'current_month' => Carbon::now()->subMonth()->format('Y-m'),
            'usage_count' => 0,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $this->assertFalse($quotaPast->isCurrentMonth());
    }

    /**
     * @test
     * T420: Reset preserves unlimited status
     */
    public function reset_preserves_unlimited_status(): void
    {
        $user = User::factory()->create();

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => Carbon::now()->subMonth()->format('Y-m'),
            'usage_count' => 50,
            'monthly_limit' => 10,
            'is_unlimited' => true,
        ]);

        // Check quota (triggers auto-reset if month changed)
        $result = $this->quotaService->checkQuota($user);

        // Unlimited status should be preserved
        $this->assertTrue($result['usage']['unlimited']);
    }

    /**
     * @test
     * T420: Batch reset returns 0 when no records need updating
     */
    public function batch_reset_returns_zero_when_no_updates_needed(): void
    {
        $user = User::factory()->create();

        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 5,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $resetCount = $this->quotaService->resetMonthlyQuota();

        $this->assertEquals(0, $resetCount);
    }

    /**
     * @test
     * T420: Reset on 1st of month scenario
     */
    public function reset_on_first_of_month(): void
    {
        // Freeze time to 1st of current month
        Carbon::setTestNow(Carbon::now()->startOfMonth());

        $user = User::factory()->create();

        // Create quota for last month
        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => Carbon::now()->subMonth()->format('Y-m'),
            'usage_count' => 10, // Maxed out
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        $result = $this->quotaService->checkQuota($user);

        $this->assertTrue($result['allowed']);
        $this->assertEquals(0, $result['usage']['used']);
        $this->assertEquals(10, $result['usage']['remaining']);

        Carbon::setTestNow(); // Reset time
    }
}
