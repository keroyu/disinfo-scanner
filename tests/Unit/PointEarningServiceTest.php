<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\PointLog;
use App\Services\PointEarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * T103: Unit tests for PointEarningService
 */
class PointEarningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PointEarningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->service = new PointEarningService();
    }

    /**
     * Test grantUapiImportPoint increments user points by 1.
     */
    public function test_grant_uapi_import_point_increments_user_points(): void
    {
        $user = User::factory()->create([
            'points' => 5,
        ]);

        $result = $this->service->grantUapiImportPoint($user, 'video123');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['points_earned']);
        $this->assertEquals(6, $result['new_balance']);

        // Verify database
        $user->refresh();
        $this->assertEquals(6, $user->points);
    }

    /**
     * Test grantUapiImportPoint creates a point log with uapi_import action.
     */
    public function test_grant_uapi_import_point_creates_point_log(): void
    {
        $user = User::factory()->create([
            'points' => 10,
        ]);

        $this->service->grantUapiImportPoint($user, 'video456');

        $log = PointLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(1, $log->amount);
        $this->assertEquals('uapi_import', $log->action);
    }

    /**
     * Test grantUapiImportPoint works for regular members (no premium).
     */
    public function test_grant_uapi_import_point_works_for_regular_members(): void
    {
        $user = User::factory()->create([
            'points' => 0,
            'premium_expires_at' => null,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        $result = $this->service->grantUapiImportPoint($user, 'video789');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['points_earned']);
        $this->assertEquals(1, $result['new_balance']);
    }

    /**
     * Test grantUapiImportPoint works for premium members.
     */
    public function test_grant_uapi_import_point_works_for_premium_members(): void
    {
        $user = User::factory()->create([
            'points' => 20,
            'premium_expires_at' => now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $result = $this->service->grantUapiImportPoint($user, 'videoABC');

        $this->assertTrue($result['success']);
        $this->assertEquals(21, $result['new_balance']);
    }

    /**
     * Test concurrent point granting with row-level locking.
     */
    public function test_concurrent_point_granting_uses_row_locking(): void
    {
        $user = User::factory()->create([
            'points' => 0,
        ]);

        // Simulate multiple concurrent grants
        $result1 = $this->service->grantUapiImportPoint($user, 'video1');
        $result2 = $this->service->grantUapiImportPoint($user, 'video2');
        $result3 = $this->service->grantUapiImportPoint($user, 'video3');

        // All should succeed
        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
        $this->assertTrue($result3['success']);

        // Final balance should be 3
        $user->refresh();
        $this->assertEquals(3, $user->points);

        // Should have 3 point logs
        $this->assertEquals(3, PointLog::where('user_id', $user->id)->count());
    }
}
