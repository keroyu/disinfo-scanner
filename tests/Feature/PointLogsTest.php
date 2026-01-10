<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\PointLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * T027-T028: Feature tests for viewing point logs
 */
class PointLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * T027: Test premium member can view point logs.
     */
    public function test_premium_member_can_view_point_logs(): void
    {
        $user = User::factory()->create([
            'points' => 5,
            'premium_expires_at' => Carbon::now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        // Create some point logs
        PointLog::create([
            'user_id' => $user->id,
            'amount' => 1,
            'action' => 'report',
        ]);

        PointLog::create([
            'user_id' => $user->id,
            'amount' => -10,
            'action' => 'redeem',
        ]);

        $response = $this->actingAs($user)->get('/settings/points/logs');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('meta.current_points', 5);
    }

    /**
     * T027: Test point logs are ordered by date descending.
     */
    public function test_point_logs_ordered_by_date_descending(): void
    {
        $user = User::factory()->create([
            'points' => 10,
            'premium_expires_at' => Carbon::now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        // Create logs with different timestamps
        $oldLog = PointLog::create([
            'user_id' => $user->id,
            'amount' => 1,
            'action' => 'report',
        ]);

        // Manually set older timestamp
        \DB::table('point_logs')->where('id', $oldLog->id)->update([
            'created_at' => Carbon::now()->subHours(2),
        ]);

        $newLog = PointLog::create([
            'user_id' => $user->id,
            'amount' => 1,
            'action' => 'report',
        ]);

        $response = $this->actingAs($user)->get('/settings/points/logs');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals($newLog->id, $data[0]['id']);
        $this->assertEquals($oldLog->id, $data[1]['id']);
    }

    /**
     * T027: Test point logs include GMT+8 display time.
     */
    public function test_point_logs_include_gmt8_display_time(): void
    {
        $user = User::factory()->create([
            'points' => 10,
            'premium_expires_at' => Carbon::now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        PointLog::create([
            'user_id' => $user->id,
            'amount' => 1,
            'action' => 'report',
        ]);

        $response = $this->actingAs($user)->get('/settings/points/logs');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('created_at_display', $data[0]);
        $this->assertStringContainsString('(GMT+8)', $data[0]['created_at_display']);
    }

    /**
     * T028: Test empty point logs returns correct structure.
     */
    public function test_empty_point_logs(): void
    {
        $user = User::factory()->create([
            'points' => 0,
            'premium_expires_at' => Carbon::now()->addDays(30),
        ]);

        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole->id);

        $response = $this->actingAs($user)->get('/settings/points/logs');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
        $response->assertJsonPath('meta.current_points', 0);
    }

    /**
     * Test non-premium user CAN now view point logs (Phase 9 change).
     * Updated: All logged-in users can view their point logs.
     */
    public function test_non_premium_can_view_point_logs(): void
    {
        $user = User::factory()->create([
            'points' => 10,
            'premium_expires_at' => null,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        $response = $this->actingAs($user)->get('/settings/points/logs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['total', 'current_points'],
        ]);
    }

    /**
     * Test unauthenticated user is redirected.
     */
    public function test_unauthenticated_user_redirected(): void
    {
        $response = $this->get('/settings/points/logs');

        $response->assertRedirect('/auth/login');
    }
}
