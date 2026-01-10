<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Service for terminating user sessions.
 * Used by User Suspension feature (014-users-management-enhancement).
 */
class SessionTerminationService
{
    /**
     * Terminate all active sessions for a single user.
     *
     * @param int $userId The user ID whose sessions should be terminated
     * @return int The number of sessions deleted
     */
    public function terminateUserSessions(int $userId): int
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Terminate all active sessions for multiple users.
     *
     * @param array $userIds Array of user IDs whose sessions should be terminated
     * @return int The total number of sessions deleted
     */
    public function terminateMultipleUserSessions(array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        return DB::table('sessions')
            ->whereIn('user_id', $userIds)
            ->delete();
    }
}
