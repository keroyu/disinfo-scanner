<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class BatchRoleService
{
    /**
     * Result object for batch operations
     */
    public static function createResult(
        int $totalRequested,
        int $updatedCount,
        int $skippedSelf,
        int $premiumExpiresSet,
        int $premiumExpiresPreserved,
        ?array $newRole
    ): array {
        return [
            'total_requested' => $totalRequested,
            'updated_count' => $updatedCount,
            'skipped_self' => $skippedSelf,
            'premium_expires_set' => $premiumExpiresSet,
            'premium_expires_preserved' => $premiumExpiresPreserved,
            'new_role' => $newRole,
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

        // Get role information
        $newRole = Role::find($roleId);
        if (!$newRole) {
            return self::createResult($totalRequested, 0, 0, 0, 0, null);
        }

        $isPremiumMemberRole = $newRole->name === 'premium_member';
        $affectedUserIds = [];

        // Use transaction for atomicity
        DB::transaction(function () use (
            $userIds,
            $roleId,
            $adminId,
            $newRole,
            $isPremiumMemberRole,
            &$updatedCount,
            &$skippedSelf,
            &$premiumExpiresSet,
            &$premiumExpiresPreserved,
            &$affectedUserIds
        ) {
            foreach ($userIds as $userId) {
                // Skip self-role change
                if ($userId == $adminId) {
                    $skippedSelf++;
                    continue;
                }

                $user = User::with('roles')->find($userId);
                if (!$user) {
                    continue;
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

                $updatedCount++;
                $affectedUserIds[] = $userId;
            }

            // Log the batch operation
            if ($updatedCount > 0) {
                $admin = User::find($adminId);
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
                    ]
                );
            }
        });

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
            ] : null
        );
    }
}
