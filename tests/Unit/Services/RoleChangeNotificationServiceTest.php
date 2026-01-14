<?php

namespace Tests\Unit\Services;

use App\Mail\RoleChangeNotification;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleChangeNotificationService;
use App\Services\Results\NotificationResult;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * T062: Unit tests for RoleChangeNotificationService (US7)
 */
class RoleChangeNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RoleChangeNotificationService $service;
    protected Role $premiumRole;
    protected Role $regularRole;
    protected Role $suspendedRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RoleChangeNotificationService();

        // Seed roles
        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Get roles
        $this->premiumRole = Role::where('name', 'premium_member')->first();
        $this->regularRole = Role::where('name', 'regular_member')->first();
        $this->suspendedRole = Role::where('name', 'suspended')->first();
    }

    /**
     * Test notify sends email to all users
     */
    public function test_notify_sends_email_to_all_users(): void
    {
        Mail::fake();

        $users = User::factory()->count(3)->create();

        $result = $this->service->notify(
            $users,
            $this->regularRole,
            null,
            [],
            1
        );

        $this->assertEquals(3, $result->sentCount);
        $this->assertEquals(0, $result->failedCount);
        $this->assertTrue($result->allSuccessful());

        // Use assertSent since mailable is not queued
        Mail::assertSent(RoleChangeNotification::class, 3);
    }

    /**
     * Test notify handles partial email failures
     */
    public function test_notify_handles_partial_email_failures(): void
    {
        $users = User::factory()->count(3)->create();

        // Mock Mail to fail on second user
        $callCount = 0;
        Mail::shouldReceive('to')
            ->andReturnUsing(function ($email) use ($users, &$callCount) {
                $callCount++;
                $mock = \Mockery::mock();

                if ($email === $users[1]->email) {
                    $mock->shouldReceive('send')
                        ->andThrow(new \Exception('SMTP error'));
                } else {
                    $mock->shouldReceive('send')
                        ->andReturn(null);
                }

                return $mock;
            });

        $result = $this->service->notify(
            $users,
            $this->regularRole,
            null,
            [],
            1
        );

        $this->assertEquals(2, $result->sentCount);
        $this->assertEquals(1, $result->failedCount);
        $this->assertFalse($result->allSuccessful());
        $this->assertTrue($result->anySuccessful());
        $this->assertContains($users[1]->id, $result->failedUserIds);
    }

    /**
     * Test notify returns sent and failed counts
     */
    public function test_notify_returns_sent_and_failed_counts(): void
    {
        Mail::fake();

        $users = User::factory()->count(5)->create();

        $result = $this->service->notify(
            $users,
            $this->premiumRole,
            Carbon::now()->addDays(30),
            [],
            1
        );

        $this->assertInstanceOf(NotificationResult::class, $result);
        $this->assertEquals(5, $result->sentCount);
        $this->assertEquals(0, $result->failedCount);
        $this->assertEquals(5, $result->totalAttempted());
    }

    /**
     * Test notifySingle sends email to single user
     */
    public function test_notify_single_sends_email_to_single_user(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $result = $this->service->notifySingle(
            $user,
            $this->premiumRole,
            Carbon::now()->addDays(30),
            false,
            1
        );

        $this->assertTrue($result);

        // Use assertSent since mailable is not queued
        Mail::assertSent(RoleChangeNotification::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * Test notifySingle returns false on failure
     */
    public function test_notify_single_returns_false_on_failure(): void
    {
        Mail::shouldReceive('to->send')
            ->andThrow(new \Exception('SMTP error'));

        $user = User::factory()->create();

        $result = $this->service->notifySingle(
            $user,
            $this->regularRole,
            null,
            false,
            1
        );

        $this->assertFalse($result);
    }

    /**
     * Test notification includes wasUnsuspended flag
     */
    public function test_notification_includes_was_unsuspended_flag(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->roles()->attach($this->suspendedRole->id);

        // Simulate unsuspension
        $previousRoles = [$user->id => 'suspended'];

        $this->service->notify(
            collect([$user]),
            $this->regularRole,
            null,
            $previousRoles,
            1
        );

        // Use assertSent since mailable is not queued
        Mail::assertSent(RoleChangeNotification::class, function ($mail) {
            return $mail->wasUnsuspended === true;
        });
    }

    /**
     * Test notification for suspended users
     */
    public function test_notification_for_suspended_users(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->service->notifySingle(
            $user,
            $this->suspendedRole,
            null,
            false,
            1
        );

        // Use assertSent since mailable is not queued
        Mail::assertSent(RoleChangeNotification::class, function ($mail) {
            return $mail->isSuspended === true;
        });
    }

    /**
     * Test notification with premium expiry date
     */
    public function test_notification_with_premium_expiry_date(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $expiryDate = Carbon::now()->addDays(30);

        $this->service->notifySingle(
            $user,
            $this->premiumRole,
            $expiryDate,
            false,
            1
        );

        // Use assertSent since mailable is not queued
        Mail::assertSent(RoleChangeNotification::class, function ($mail) use ($expiryDate) {
            return $mail->premiumExpiresAt !== null
                && $mail->premiumExpiresAt->format('Y-m-d') === $expiryDate->format('Y-m-d');
        });
    }

    /**
     * Test empty user collection returns empty result
     */
    public function test_empty_user_collection_returns_empty_result(): void
    {
        $result = $this->service->notify(
            collect(),
            $this->regularRole,
            null,
            [],
            1
        );

        $this->assertEquals(0, $result->sentCount);
        $this->assertEquals(0, $result->failedCount);
        $this->assertEquals(0, $result->totalAttempted());
    }
}
