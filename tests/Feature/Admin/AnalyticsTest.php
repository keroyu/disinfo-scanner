<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use App\Models\IdentityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    /** @test */
    public function admin_can_view_analytics_dashboard()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create some test data
        $user1 = User::factory()->create(['created_at' => now()->subDays(5)]);
        $user2 = User::factory()->create(['created_at' => now()->subDays(3)]);

        // Act as admin
        $this->actingAs($admin);

        // Access analytics endpoint
        $response = $this->getJson('/api/admin/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'totalUsers',
                    'verifiedUsers',
                    'premiumMembers',
                    'totalApiCalls',
                ],
                'registrations' => [
                    'labels',
                    'data',
                ],
                'usersByRole' => [
                    'labels',
                    'data',
                ],
                'apiQuotaUsage' => [
                    'labels',
                    'data',
                ],
            ]);
    }

    /** @test */
    public function analytics_displays_correct_statistics()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create test users
        $regularUser = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $regularUser->roles()->attach($regularRole);

        $premiumUser = User::factory()->create();
        $premiumRole = Role::where('name', 'premium_member')->first();
        $premiumUser->roles()->attach($premiumRole);

        // Create verified user
        IdentityVerification::create([
            'user_id' => $premiumUser->id,
            'verification_method' => 'ID card',
            'verification_status' => 'approved',
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        // Create API quota
        ApiQuota::create([
            'user_id' => $regularUser->id,
            'usage_count' => 50,
            'monthly_limit' => 100,
            'is_unlimited' => false,
            'current_month' => now()->format('Y-m'),
        ]);

        // Act as admin
        $this->actingAs($admin);

        // Get analytics
        $response = $this->getJson('/api/admin/analytics');

        $response->assertStatus(200);

        $stats = $response->json('stats');
        $this->assertEquals(3, $stats['totalUsers']); // admin, regular, premium
        $this->assertEquals(1, $stats['verifiedUsers']);
        $this->assertEquals(1, $stats['premiumMembers']);
        $this->assertEquals(50, $stats['totalApiCalls']);
    }

    /** @test */
    public function admin_can_export_user_list()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create test users
        $user = User::factory()->create();

        // Act as admin
        $this->actingAs($admin);

        // Export user list
        $response = $this->get('/api/admin/reports/users/export');

        // Verify export is successful with correct headers
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /** @test */
    public function admin_can_export_activity_report()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Act as admin
        $this->actingAs($admin);

        // Export activity report
        $response = $this->get('/api/admin/reports/activity?start_date=2025-01-01&end_date=2025-12-31');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /** @test */
    public function admin_can_export_api_usage_report()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create user with API quota
        $user = User::factory()->create();
        ApiQuota::create([
            'user_id' => $user->id,
            'usage_count' => 100,
            'monthly_limit' => 1000,
            'is_unlimited' => false,
            'current_month' => now()->format('Y-m'),
        ]);

        // Act as admin
        $this->actingAs($admin);

        // Export API usage report
        $response = $this->get('/api/admin/reports/api-usage');

        // Verify export is successful with correct headers
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /** @test */
    public function non_admin_cannot_access_analytics()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Act as regular user
        $this->actingAs($user);

        // Try to access analytics
        $response = $this->getJson('/api/admin/analytics');

        $response->assertStatus(403)
            ->assertJson([
                'message' => '無權限訪問此功能'
            ]);
    }

    /** @test */
    public function non_admin_cannot_export_reports()
    {
        // Create regular user
        $user = User::factory()->create();
        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole);

        // Act as regular user
        $this->actingAs($user);

        // Try to export user list
        $response = $this->get('/api/admin/reports/users/export');
        $response->assertStatus(403);

        // Try to export activity report
        $response = $this->get('/api/admin/reports/activity');
        $response->assertStatus(403);

        // Try to export API usage report
        $response = $this->get('/api/admin/reports/api-usage');
        $response->assertStatus(403);
    }

    /** @test */
    public function analytics_filters_by_date_range()
    {
        // Create admin
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'administrator')->first();
        $admin->roles()->attach($adminRole);

        // Create users on different dates
        User::factory()->create(['created_at' => now()->subDays(10)]);
        User::factory()->create(['created_at' => now()->subDays(5)]);
        User::factory()->create(['created_at' => now()->subDays(2)]);

        // Act as admin
        $this->actingAs($admin);

        // Get analytics with date range
        $response = $this->getJson('/api/admin/analytics?start_date=' . now()->subDays(7)->format('Y-m-d') . '&end_date=' . now()->format('Y-m-d'));

        $response->assertStatus(200);

        // Should only include users from last 7 days (2 users)
        $registrations = $response->json('registrations');
        $totalRegistrations = array_sum($registrations['data']);
        $this->assertEquals(2, $totalRegistrations);
    }
}
