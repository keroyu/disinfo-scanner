<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Services\PaymentService;
use App\Services\RolePermissionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * T013: Unit tests for PaymentService extendPremium logic
 */
class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->service = app(PaymentService::class);
    }

    public function test_extend_premium_for_new_member_starts_from_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        $user = User::factory()->create([
            'premium_expires_at' => null,
        ]);

        $this->service->extendPremium($user, 30);

        $user->refresh();
        $expectedExpiry = Carbon::parse('2025-01-31 12:00:00');
        $this->assertTrue($user->premium_expires_at->diffInSeconds($expectedExpiry) < 5);

        Carbon::setTestNow();
    }

    public function test_extend_premium_for_expired_member_starts_from_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

        $user = User::factory()->create([
            'premium_expires_at' => Carbon::parse('2025-01-01 12:00:00'), // Expired
        ]);

        $this->service->extendPremium($user, 30);

        $user->refresh();
        $expectedExpiry = Carbon::parse('2025-02-14 12:00:00');
        $this->assertTrue($user->premium_expires_at->diffInSeconds($expectedExpiry) < 5);

        Carbon::setTestNow();
    }

    public function test_extend_premium_for_active_member_adds_to_current_expiry(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        $user = User::factory()->create([
            'premium_expires_at' => Carbon::parse('2025-01-15 12:00:00'), // 14 days remaining
        ]);

        $this->service->extendPremium($user, 30);

        $user->refresh();
        // Should add 30 days to Jan 15, not from Jan 1
        $expectedExpiry = Carbon::parse('2025-02-14 12:00:00');
        $this->assertTrue($user->premium_expires_at->diffInSeconds($expectedExpiry) < 5);

        Carbon::setTestNow();
    }

    public function test_extend_premium_assigns_premium_member_role(): void
    {
        $user = User::factory()->create([
            'premium_expires_at' => null,
        ]);

        $this->assertFalse($user->hasRole('premium_member'));

        $this->service->extendPremium($user, 30);

        $user->refresh();
        $this->assertTrue($user->hasRole('premium_member'));
    }

    public function test_extend_premium_does_not_duplicate_role(): void
    {
        $user = User::factory()->create();
        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $roleCountBefore = $user->roles()->count();

        $this->service->extendPremium($user, 30);

        $user->refresh();
        $roleCountAfter = $user->roles()->count();
        $this->assertEquals($roleCountBefore, $roleCountAfter);
    }

    public function test_extend_premium_with_different_durations(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        // 365 days (annual)
        $user = User::factory()->create(['premium_expires_at' => null]);
        $this->service->extendPremium($user, 365);
        $user->refresh();

        $expectedExpiry = Carbon::parse('2026-01-01 12:00:00');
        $this->assertTrue($user->premium_expires_at->diffInSeconds($expectedExpiry) < 5);

        Carbon::setTestNow();
    }
}
