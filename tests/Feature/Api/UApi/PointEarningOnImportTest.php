<?php

namespace Tests\Feature\Api\UApi;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\PointLog;
use App\Services\PointEarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * T104-T105: Feature tests for U-API import granting points
 */
class PointEarningOnImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * T104: Test U-API import grants +1 point to user.
     */
    public function test_uapi_import_grants_point_to_user(): void
    {
        $user = User::factory()->create([
            'points' => 5,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        $service = new PointEarningService();
        $result = $service->grantUapiImportPoint($user, 'test_video_123');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['points_earned']);
        $this->assertEquals(6, $result['new_balance']);

        // Verify database was updated
        $user->refresh();
        $this->assertEquals(6, $user->points);

        // Verify point log was created with correct action
        $log = PointLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(1, $log->amount);
        $this->assertEquals('uapi_import', $log->action);
    }

    /**
     * Test regular member can earn points via U-API import.
     */
    public function test_regular_member_can_earn_points_via_uapi(): void
    {
        $user = User::factory()->create([
            'points' => 0,
            'premium_expires_at' => null,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        $service = new PointEarningService();
        $result = $service->grantUapiImportPoint($user, 'video_abc');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['new_balance']);
    }

    /**
     * Test premium member can earn points via U-API import.
     */
    public function test_premium_member_can_earn_points_via_uapi(): void
    {
        $user = User::factory()->create([
            'points' => 10,
            'premium_expires_at' => now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $service = new PointEarningService();
        $result = $service->grantUapiImportPoint($user, 'video_xyz');

        $this->assertTrue($result['success']);
        $this->assertEquals(11, $result['new_balance']);
    }

    /**
     * Test point log shows correct action label for uapi_import.
     */
    public function test_point_log_shows_correct_action_label(): void
    {
        $user = User::factory()->create([
            'points' => 0,
        ]);

        $service = new PointEarningService();
        $service->grantUapiImportPoint($user, 'video_test');

        $log = PointLog::where('user_id', $user->id)->first();
        $this->assertEquals('U-API 導入', $log->action_display);
    }

    /**
     * Test multiple imports create multiple point logs.
     */
    public function test_multiple_imports_create_multiple_point_logs(): void
    {
        $user = User::factory()->create([
            'points' => 0,
        ]);

        $service = new PointEarningService();
        $service->grantUapiImportPoint($user, 'video_1');
        $service->grantUapiImportPoint($user, 'video_2');
        $service->grantUapiImportPoint($user, 'video_3');

        $user->refresh();
        $this->assertEquals(3, $user->points);
        $this->assertEquals(3, PointLog::where('user_id', $user->id)->count());
    }
}
