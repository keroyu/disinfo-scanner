<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use App\Services\Results\NotificationResult;
use Illuminate\Support\Facades\DB;

class BatchRoleService
{
    protected SessionTerminationService $sessionService;
    protected RoleChangeNotificationService $notificationService;

    public function __construct(
        ?SessionTerminationService $sessionService = null,
        ?RoleChangeNotificationService $notificationService = null
    ) {
        $this->sessionService = $sessionService ?? new SessionTerminationService();
        $this->notificationService = $notificationService ?? new RoleChangeNotificationService();
    }

    /**
     * Result object for batch operations
     * T067: Extended with notification counts for US7
     */
    public static function createResult(
        int $totalRequested,
        int $updatedCount,
        int $skippedSelf,
        int $premiumExpiresSet,
        int $premiumExpiresPreserved,
        ?array $newRole,
        int $skippedAlreadySuspended = 0,
        int $sessionsTerminated = 0,
        int $unsuspendedCount = 0,
        int $notificationsSent = 0,
        int $notificationsFailed = 0
    ): array {
        return [
            'total_requested' => $totalRequested,
            'updated_count' => $updatedCount,
            'skipped_self' => $skippedSelf,
            'premium_expires_set' => $premiumExpiresSet,
            'premium_expires_preserved' => $premiumExpiresPreserved,
            'new_role' => $newRole,
            'skipped_already_suspended' => $skippedAlreadySuspended,
            'sessions_terminated' => $sessionsTerminated,
            'unsuspended_count' => $unsuspendedCount,
            'notifications_sent' => $notificationsSent,
            'notifications_failed' => $notificationsFailed,
        ];
    }

    /**
     * Change roles for multiple users
     *
     * @param array $userIds Array of user IDs to update
     * @param int $roleId The new role ID
     * @param int $adminId The admin performing the action
     * @return array BatchResult with operation details
     */
    public function changeRoles(array $userIds, int $roleId, int $adminId): array
    {
        $totalRequested = count($userIds);
        $updatedCount = 0;
        $skippedSelf = 0;
        $premiumExpiresSet = 0;
        $premiumExpiresPreserved = 0;
        $skippedAlreadySuspended = 0;
        $sessionsTerminated = 0;
        $unsuspendedCount = 0;

        // Get role information
        $newRole = Role::find($roleId);
        if (!$newRole) {
            return self::createResult($totalRequested, 0, 0, 0, 0, null);
        }

        $isPremiumMemberRole = $newRole->name === 'premium_member';
        $isSuspendedRole = $newRole->name === 'suspended';
        $affectedUserIds = [];
        $suspendedUserIds = [];
        $previousRoles = [];

        // Use transaction for atomicity
        DB::transaction(function () use (
            $userIds,
            $roleId,
            $adminId,
            $newRole,
            $isPremiumMemberRole,
            $isSuspendedRole,
            &$updatedCount,
            &$skippedSelf,
            &$premiumExpiresSet,
            &$premiumExpiresPreserved,
            &$skippedAlreadySuspended,
            &$unsuspendedCount,
            &$affectedUserIds,
            &$suspendedUserIds,
            &$previousRoles
        ) {
            foreach ($userIds as $userId) {
                // Skip self-role change (FR-012: Cannot change own role)
                if ($userId == $adminId) {
                    $skippedSelf++;
                    continue;
                }

                $user = User::with('roles')->find($userId);
                if (!$user) {
                    continue;
                }

                $currentRole = $user->roles->first();
                $currentRoleName = $currentRole?->name ?? '';

                // FR-048: Prevent self-suspension
                if ($isSuspendedRole && $userId == $adminId) {
                    $skippedSelf++;
                    continue;
                }

                // FR-053: Skip already-suspended users when suspending
                if ($isSuspendedRole && $currentRoleName === 'suspended') {
                    $skippedAlreadySuspended++;
                    continue;
                }

                // Store previous role for audit log (suspension/unsuspension tracking)
                $previousRoles[$userId] = $currentRoleName;

                // Track unsuspension (T057: changing FROM suspended TO another role)
                if ($currentRoleName === 'suspended' && !$isSuspendedRole) {
                    $unsuspendedCount++;
                }

                // Sync role (removes old role, adds new role)
                $user->roles()->sync([$roleId]);

                // Handle premium_expires_at for Premium Member role
                if ($isPremiumMemberRole) {
                    if (is_null($user->premium_expires_at)) {
                        // Set to 30 days from now for new Premium Members
                        $user->premium_expires_at = now()->addDays(30);
                        $user->save();
                        $premiumExpiresSet++;
                    } else {
                        // Preserve existing expiry
                        $premiumExpiresPreserved++;
                    }
                }

                // Track suspended users for session termination (FR-046)
                if ($isSuspendedRole) {
                    $suspendedUserIds[] = $userId;
                }

                $updatedCount++;
                $affectedUserIds[] = $userId;
            }

            // Log the batch operation
            if ($updatedCount > 0) {
                $admin = User::find($adminId);
                $actionType = $isSuspendedRole ? 'user_suspended' : ($unsuspendedCount > 0 && !$isSuspendedRole ? 'batch_role_change' : 'batch_role_change');

                // FR-051: Audit logging for suspension/unsuspension
                if ($isSuspendedRole) {
                    AuditLog::log(
                        actionType: 'user_suspended',
                        description: sprintf(
                            'Admin %s (%s) 停權 %d 位用戶',
                            $admin->name,
                            $admin->email,
                            $updatedCount
                        ),
                        userId: null,
                        adminId: $adminId,
                        resourceType: 'batch',
                        resourceId: null,
                        changes: [
                            'suspended_user_ids' => $affectedUserIds,
                            'skipped_already_suspended' => $skippedAlreadySuspended,
                            'skipped_self' => $skippedSelf,
                            'previous_roles' => $previousRoles,
                        ]
                    );
                } else {
                    AuditLog::log(
                        actionType: 'batch_role_change',
                        description: sprintf(
                            'Admin %s (%s) 批次變更 %d 位用戶的角色為 %s',
                            $admin->name,
                            $admin->email,
                            $updatedCount,
                            $newRole->display_name
                        ),
                        userId: null,
                        adminId: $adminId,
                        resourceType: 'batch',
                        resourceId: null,
                        changes: [
                            'affected_user_ids' => $affectedUserIds,
                            'new_role' => [
                                'id' => $newRole->id,
                                'name' => $newRole->name,
                                'display_name' => $newRole->display_name,
                            ],
                            'premium_expires_at_set' => $premiumExpiresSet,
                            'premium_expires_at_preserved' => $premiumExpiresPreserved,
                            'unsuspended_count' => $unsuspendedCount,
                            'previous_roles' => $previousRoles,
                        ]
                    );
                }

                // Log unsuspension separately if users were unsuspended
                if ($unsuspendedCount > 0 && !$isSuspendedRole) {
                    AuditLog::log(
                        actionType: 'user_unsuspended',
                        description: sprintf(
                            'Admin %s (%s) 解除 %d 位用戶的停權狀態',
                            $admin->name,
                            $admin->email,
                            $unsuspendedCount
                        ),
                        userId: null,
                        adminId: $adminId,
                        resourceType: 'batch',
                        resourceId: null,
                        changes: [
                            'unsuspended_user_ids' => array_keys(array_filter($previousRoles, fn($r) => $r === 'suspended')),
                            'new_role' => $newRole->name,
                        ]
                    );
                }
            }
        });

        // FR-046: Terminate sessions for suspended users (after transaction commit)
        if (!empty($suspendedUserIds)) {
            $sessionsTerminated = $this->sessionService->terminateMultipleUserSessions($suspendedUserIds);

            // Log session termination
            if ($sessionsTerminated > 0) {
                $admin = User::find($adminId);
                AuditLog::log(
                    actionType: 'sessions_terminated',
                    description: sprintf(
                        'Admin %s (%s) 終止 %d 個登入會話（用戶停權）',
                        $admin->name,
                        $admin->email,
                        $sessionsTerminated
                    ),
                    userId: null,
                    adminId: $adminId,
                    resourceType: 'session',
                    resourceId: null,
                    changes: [
                        'user_ids' => $suspendedUserIds,
                        'sessions_terminated' => $sessionsTerminated,
                    ]
                );
            }
        }

        // T065: Send role change notification emails (FR-055)
        $notificationsSent = 0;
        $notificationsFailed = 0;

        if ($updatedCount > 0 && !empty($affectedUserIds)) {
            try {
                // Fetch affected users for notification
                $affectedUsers = User::whereIn('id', $affectedUserIds)->get();

                // Determine premium expiry date for Premium Member notifications
                $premiumExpiresAt = $isPremiumMemberRole ? now()->addDays(30) : null;

                // Send notifications
                $notificationResult = $this->notificationService->notify(
                    $affectedUsers,
                    $newRole,
                    $premiumExpiresAt,
                    $previousRoles,
                    $adminId
                );

                $notificationsSent = $notificationResult->sentCount;
                $notificationsFailed = $notificationResult->failedCount;
            } catch (\Exception $e) {
                // FR-062: Email failure does not block role change
                // Log the error but continue
                \Illuminate\Support\Facades\Log::error('Batch role change notification failed', [
                    'affected_user_ids' => $affectedUserIds,
                    'role_id' => $roleId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::createResult(
            $totalRequested,
            $updatedCount,
            $skippedSelf,
            $premiumExpiresSet,
            $premiumExpiresPreserved,
            $newRole ? [
                'id' => $newRole->id,
                'name' => $newRole->name,
                'display_name' => $newRole->display_name,
            ] : null,
            $skippedAlreadySuspended,
            $sessionsTerminated,
            $unsuspendedCount,
            $notificationsSent,
            $notificationsFailed
        );
    }
}
