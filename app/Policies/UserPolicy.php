<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine if the user can view any users (admin panel access)
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can view the model
     */
    public function view(User $user, User $model): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can update the model
     */
    public function update(User $user, User $model): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can update the role of another user
     * Prevents admin from changing their own permission level
     */
    public function updateRole(User $user, User $targetUser): bool
    {
        // Must be admin
        if (!$this->isAdmin($user)) {
            return false;
        }

        // Cannot change own permission level
        if ($user->id === $targetUser->id) {
            return false;
        }

        return true;
    }

    /**
     * Check if user has administrator role
     */
    public function isAdmin(User $user): bool
    {
        return $user->roles()->where('name', 'administrator')->exists();
    }

    /**
     * Determine if the user can delete the model
     */
    public function delete(User $user, User $model): bool
    {
        return $this->isAdmin($user) && $user->id !== $model->id;
    }

    /**
     * Determine if the user can restore the model
     */
    public function restore(User $user, User $model): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can permanently delete the model
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $this->isAdmin($user) && $user->id !== $model->id;
    }

    /**
     * Determine if the user can manage identity verifications
     */
    public function manageVerifications(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can view audit logs
     */
    public function viewAuditLogs(User $user): bool
    {
        return $this->isAdmin($user);
    }
}
