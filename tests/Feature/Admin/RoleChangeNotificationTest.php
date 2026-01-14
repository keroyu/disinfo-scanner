<?php

namespace Tests\Feature\Admin;

use App\Mail\RoleChangeNotification;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * T061: Feature tests for role change notification emails (US7)
 */
class RoleChangeNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Role $premiumRole;
    protected Role $regularRole;
    protected Role $suspendedRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Get roles
        $this->premiumRole = Role::where('name', 'premium_member')->first();
        $this->regularRole = Role::where('name', 'regular_member')->first();
        $this->suspendedRole = Role::where('name', 'suspended')->first();
        $adminRole = Role::where('name', 'administrator')->first();

        // Create admin user
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'name' => 'Test Admin',
        ]);
        $this->admin->roles()->attach($adminRole->id);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'email' => 'user@test.com',
            'name' => 'Test User',
        ]);
        $this->regularUser->roles()->attach($this->regularRole->id);
    }

    /**
     * Test notification sent after batch role change to Premium Member
     */
    public function test_notification_sent_after_batch_role_change(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/users/batch-role', [
                'user_ids' => [$this->regularUser->id],
                'role_id' => $this->premiumRole->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.notifications_sent', 1);
        $response->assertJsonPath('data.notifications_failed', 0);

        // Verify email was sent (not queued since mailable doesn't implement ShouldQueue)
        Mail::assertSent(RoleChangeNotification::class, function ($mail) {
            return $mail->hasTo($this->regularUser->email)
                && $mail->newRole->name === 'premium_member';
        });
    }

    /**
     * Test notification sent after individual role change
     */
    public function test_notification_sent_after_individual_role_change(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/users/{$this->regularUser->id}/role", [
                'role_id' => $this->premiumRole->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.notification_sent', true);

        // Verify email was sent
        Mail::assertSent(RoleChangeNotification::class, function ($mail) {
            return $mail->hasTo($this->regularUser->email);
        });
    }

    /**
     * Test notification includes role name in Chinese
     */
    public function test_notification_includes_role_name_in_chinese(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->postJson('/api/admin/users/batch-role', [
                'user_ids' => [$this->regularUser->id],
                'role_id' => $this->premiumRole->id,
            ]);

        Mail::assertSent(RoleChangeNotification::class, function ($mail) {
            return $mail->newRole->display_name === '高級會員';
        });
    }

    /**
     * Test notification includes premium expiry for Premium Member
     */
    public function test_notification_includes_premium_expiry_for_premium_member(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->postJson('/api/admin/users/batch-role', [
                'user_ids' => [$this->regularUser->id],
                'role_id' => $this->premiumRole->id,
            ]);

        Mail::assertSent(RoleChangeNotification::class, function ($mail) {
            return $mail->newRole->name === 'premium_member'
                && $mail->premiumExpiresAt !== null;
        });
    }

    /**
     * Test notification sent to suspended users with suspension message
     */
    public function test_notification_sent_to_suspended_users_with_suspension_message(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/users/batch-role', [
                'user_ids' => [$this->regularUser->id],
                'role_id' => $this->suspendedRole->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.notifications_sent', 1);

        // Verify email was sent with suspended role
        Mail::assertSent(RoleChangeNotification::class, function ($mail) {
            return $mail->hasTo($this->regularUser->email)
                && $mail->isSuspended === true;
        });
    }

    /**
     * Test notification sent to unsuspended users with reactivation message
     */
    public function test_notification_sent_to_unsuspended_users_with_reactivation_message(): void
    {
        Mail::fake();

        // First suspend the user
        $this->regularUser->roles()->sync([$this->suspendedRole->id]);

        // Then unsuspend via role change
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/users/batch-role', [
                'user_ids' => [$this->regularUser->id],
                'role_id' => $this->regularRole->id,
            ]);

        $response->assertOk();

        // Verify email was sent with unsuspended flag
        Mail::assertSent(RoleChangeNotification::class, function ($mail) {
            return $mail->hasTo($this->regularUser->email)
                && $mail->wasUnsuspended === true;
        });
    }

    /**
     * Test notification failure does not block role change
     */
    public function test_notification_failure_does_not_block_role_change(): void
    {
        // Make mail fail by throwing exception
        Mail::shouldReceive('to->send')
            ->andThrow(new \Exception('Email service unavailable'));

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/users/batch-role', [
                'user_ids' => [$this->regularUser->id],
                'role_id' => $this->premiumRole->id,
            ]);

        // Role change should still succeed
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.updated_count', 1);

        // User should have new role
        $this->regularUser->refresh();
        $this->assertTrue($this->regularUser->roles->contains('name', 'premium_member'));
    }

    /**
     * Test notification result included in API response
     */
    public function test_notification_result_included_in_api_response(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/users/batch-role', [
                'user_ids' => [$this->regularUser->id],
                'role_id' => $this->premiumRole->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'total_requested',
                'updated_count',
                'notifications_sent',
                'notifications_failed',
            ],
        ]);
    }

    /**
     * Test multiple users receive notifications
     */
    public function test_multiple_users_receive_notifications(): void
    {
        Mail::fake();

        // Create additional users
        $user2 = User::factory()->create(['email' => 'user2@test.com']);
        $user2->roles()->attach($this->regularRole->id);

        $user3 = User::factory()->create(['email' => 'user3@test.com']);
        $user3->roles()->attach($this->regularRole->id);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/users/batch-role', [
                'user_ids' => [$this->regularUser->id, $user2->id, $user3->id],
                'role_id' => $this->premiumRole->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.notifications_sent', 3);
        $response->assertJsonPath('data.notifications_failed', 0);

        // Verify all emails were sent
        Mail::assertSent(RoleChangeNotification::class, 3);
    }

    /**
     * Test audit log created for notification
     */
    public function test_audit_log_created_for_notification(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->postJson('/api/admin/users/batch-role', [
                'user_ids' => [$this->regularUser->id],
                'role_id' => $this->premiumRole->id,
            ]);

        // Check audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => 'role_change_notification',
            'admin_id' => $this->admin->id,
        ]);
    }
}
