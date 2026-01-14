<?php

namespace App\Services;

use App\Mail\RoleChangeNotification;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Results\NotificationResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * T063: Service for sending role change notification emails
 *
 * Sends notification emails to users when their role is changed by an administrator.
 * Implements fire-and-forget pattern - email failures don't affect role changes.
 */
class RoleChangeNotificationService
{
    /**
     * Send notification emails to multiple users after role change.
     *
     * @param Collection $users Collection of User models that had their role changed
     * @param Role $newRole The new role assigned to the users
     * @param Carbon|null $premiumExpiresAt Premium expiry date (for Premium Members)
     * @param array $previousRoles Mapping of user_id => previous_role_name
     * @param int|null $adminId Admin who performed the change (for audit logging)
     * @return NotificationResult Result containing sent/failed counts
     */
    public function notify(
        Collection $users,
        Role $newRole,
        ?Carbon $premiumExpiresAt = null,
        array $previousRoles = [],
        ?int $adminId = null
    ): NotificationResult {
        $result = new NotificationResult();

        if ($users->isEmpty()) {
            return $result;
        }

        foreach ($users as $user) {
            $wasUnsuspended = ($previousRoles[$user->id] ?? '') === 'suspended';

            try {
                $this->sendToUser($user, $newRole, $premiumExpiresAt, $wasUnsuspended);
                $result->addSuccess();
            } catch (\Exception $e) {
                $result->addFailure($user->id, $user->email);

                Log::error('Role change notification failed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'new_role' => $newRole->name,
                    'error' => $e->getMessage(),
                ]);

                // T070: Log notification failure to audit_logs
                if ($adminId) {
                    AuditLog::log(
                        actionType: 'role_notification_failed',
                        description: sprintf(
                            '角色變更通知郵件發送失敗: %s (%s)',
                            $user->name,
                            $user->email
                        ),
                        userId: $user->id,
                        adminId: $adminId,
                        resourceType: 'user',
                        resourceId: $user->id,
                        changes: [
                            'error' => $e->getMessage(),
                            'email' => $user->email,
                            'new_role' => $newRole->name,
                        ]
                    );
                }
            }
        }

        // T070: Log successful batch notification
        if ($result->sentCount > 0 && $adminId) {
            AuditLog::log(
                actionType: 'role_change_notification',
                description: sprintf(
                    '發送角色變更通知郵件給 %d 位用戶',
                    $result->sentCount
                ),
                userId: null,
                adminId: $adminId,
                resourceType: 'batch',
                resourceId: null,
                changes: [
                    'recipient_count' => $result->sentCount,
                    'failed_count' => $result->failedCount,
                    'new_role' => $newRole->display_name,
                    'failed_emails' => $result->failedEmails,
                ]
            );
        }

        return $result;
    }

    /**
     * Send notification email to a single user after role change.
     *
     * @param User $user The user whose role was changed
     * @param Role $newRole The new role assigned
     * @param Carbon|null $premiumExpiresAt Premium expiry date (for Premium Members)
     * @param bool $wasUnsuspended True if user was previously suspended
     * @param int|null $adminId Admin who performed the change (for audit logging)
     * @return bool True if email was sent successfully
     */
    public function notifySingle(
        User $user,
        Role $newRole,
        ?Carbon $premiumExpiresAt = null,
        bool $wasUnsuspended = false,
        ?int $adminId = null
    ): bool {
        try {
            $this->sendToUser($user, $newRole, $premiumExpiresAt, $wasUnsuspended);

            // Log successful single notification
            if ($adminId) {
                AuditLog::log(
                    actionType: 'role_change_notification',
                    description: sprintf(
                        '發送角色變更通知郵件給 %s (%s)',
                        $user->name,
                        $user->email
                    ),
                    userId: $user->id,
                    adminId: $adminId,
                    resourceType: 'user',
                    resourceId: $user->id,
                    changes: [
                        'new_role' => $newRole->display_name,
                        'email' => $user->email,
                    ]
                );
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Role change notification failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'new_role' => $newRole->name,
                'error' => $e->getMessage(),
            ]);

            // Log notification failure
            if ($adminId) {
                AuditLog::log(
                    actionType: 'role_notification_failed',
                    description: sprintf(
                        '角色變更通知郵件發送失敗: %s (%s)',
                        $user->name,
                        $user->email
                    ),
                    userId: $user->id,
                    adminId: $adminId,
                    resourceType: 'user',
                    resourceId: $user->id,
                    changes: [
                        'error' => $e->getMessage(),
                        'email' => $user->email,
                        'new_role' => $newRole->name,
                    ]
                );
            }

            return false;
        }
    }

    /**
     * Send the notification email to a user.
     *
     * @param User $user The recipient
     * @param Role $newRole The new role
     * @param Carbon|null $premiumExpiresAt Premium expiry date
     * @param bool $wasUnsuspended Whether user was previously suspended
     * @throws \Exception If email sending fails
     */
    protected function sendToUser(
        User $user,
        Role $newRole,
        ?Carbon $premiumExpiresAt,
        bool $wasUnsuspended
    ): void {
        Mail::to($user->email)->send(
            new RoleChangeNotification(
                $user,
                $newRole,
                $premiumExpiresAt,
                $wasUnsuspended
            )
        );
    }
}
