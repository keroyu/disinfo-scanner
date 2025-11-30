<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RolePermissionService
{
    /**
     * Cache duration for role permissions (in seconds).
     */
    const CACHE_TTL = 3600; // 1 hour

    /**
     * Check if user has a specific role.
     *
     * @param User $user
     * @param string $roleName
     * @return bool
     */
    public function hasRole(User $user, string $roleName): bool
    {
        return $user->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if user has any of the specified roles.
     *
     * @param User $user
     * @param array $roleNames
     * @return bool
     */
    public function hasAnyRole(User $user, array $roleNames): bool
    {
        return $user->roles()->whereIn('name', $roleNames)->exists();
    }

    /**
     * Check if user has all of the specified roles.
     *
     * @param User $user
     * @param array $roleNames
     * @return bool
     */
    public function hasAllRoles(User $user, array $roleNames): bool
    {
        $userRoleNames = $user->roles->pluck('name')->toArray();
        return empty(array_diff($roleNames, $userRoleNames));
    }

    /**
     * Check if user has a specific permission.
     *
     * @param User $user
     * @param string $permissionName
     * @return bool
     */
    public function hasPermission(User $user, string $permissionName): bool
    {
        $cacheKey = "user_permissions:{$user->id}";

        $permissions = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $user->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('name')
                ->unique()
                ->toArray();
        });

        return in_array($permissionName, $permissions);
    }

    /**
     * Check if user has any of the specified permissions.
     *
     * @param User $user
     * @param array $permissionNames
     * @return bool
     */
    public function hasAnyPermission(User $user, array $permissionNames): bool
    {
        foreach ($permissionNames as $permission) {
            if ($this->hasPermission($user, $permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all permissions for a user.
     *
     * @param User $user
     * @return array
     */
    public function getUserPermissions(User $user): array
    {
        $cacheKey = "user_permissions:{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $user->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->unique('id')
                ->map(function ($permission) {
                    return [
                        'name' => $permission->name,
                        'display_name' => $permission->display_name,
                        'category' => $permission->category,
                    ];
                })
                ->values()
                ->toArray();
        });
    }

    /**
     * Assign role to user.
     *
     * @param User $user
     * @param string $roleName
     * @param User|null $assignedBy
     * @return bool
     */
    public function assignRole(User $user, string $roleName, ?User $assignedBy = null): bool
    {
        $role = Role::where('name', $roleName)->first();

        if (!$role) {
            Log::warning('Attempted to assign non-existent role', [
                'role_name' => $roleName,
                'user_id' => $user->id,
            ]);
            return false;
        }

        // Check if user already has this role
        if ($this->hasRole($user, $roleName)) {
            return true;
        }

        // Assign role
        $user->roles()->attach($role->id, [
            'assigned_at' => now(),
            'assigned_by' => $assignedBy ? $assignedBy->id : null,
        ]);

        // Clear permission cache
        $this->clearUserPermissionCache($user);

        Log::info('SECURITY: Role assigned', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $roleName,
            'assigned_by' => $assignedBy ? $assignedBy->id : null,
            'assigned_at' => now()->toIso8601String(),
        ]);

        return true;
    }

    /**
     * Remove role from user.
     *
     * @param User $user
     * @param string $roleName
     * @return bool
     */
    public function removeRole(User $user, string $roleName): bool
    {
        $role = Role::where('name', $roleName)->first();

        if (!$role) {
            return false;
        }

        $user->roles()->detach($role->id);

        // Clear permission cache
        $this->clearUserPermissionCache($user);

        Log::info('SECURITY: Role removed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $roleName,
            'removed_at' => now()->toIso8601String(),
        ]);

        return true;
    }

    /**
     * Sync user roles (replace all roles with new set).
     *
     * @param User $user
     * @param array $roleNames
     * @param User|null $assignedBy
     * @return void
     */
    public function syncRoles(User $user, array $roleNames, ?User $assignedBy = null): void
    {
        $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->toArray();

        // Prepare sync data with timestamps
        $syncData = [];
        foreach ($roleIds as $roleId) {
            $syncData[$roleId] = [
                'assigned_at' => now(),
                'assigned_by' => $assignedBy ? $assignedBy->id : null,
            ];
        }

        $user->roles()->sync($syncData);

        // Clear permission cache
        $this->clearUserPermissionCache($user);

        Log::info('SECURITY: Roles synced', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $roleNames,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get all available roles.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllRoles()
    {
        return Role::all();
    }

    /**
     * Get permissions for a specific role.
     *
     * @param string $roleName
     * @return array
     */
    public function getRolePermissions(string $roleName): array
    {
        $role = Role::where('name', $roleName)->with('permissions')->first();

        if (!$role) {
            return [];
        }

        return $role->permissions->map(function ($permission) {
            return [
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'category' => $permission->category,
            ];
        })->toArray();
    }

    /**
     * Clear user permission cache.
     *
     * @param User $user
     * @return void
     */
    public function clearUserPermissionCache(User $user): void
    {
        Cache::forget("user_permissions:{$user->id}");
    }

    /**
     * Check if user is administrator.
     *
     * @param User $user
     * @return bool
     */
    public function isAdministrator(User $user): bool
    {
        return $this->hasRole($user, 'administrator');
    }

    /**
     * Check if user is website editor.
     *
     * @param User $user
     * @return bool
     */
    public function isWebsiteEditor(User $user): bool
    {
        return $this->hasRole($user, 'website_editor');
    }

    /**
     * Check if user is Premium Member.
     *
     * @param User $user
     * @return bool
     */
    public function isPaidMember(User $user): bool
    {
        return $this->hasRole($user, 'premium_member');
    }

    /**
     * Check if user is regular member.
     *
     * @param User $user
     * @return bool
     */
    public function isRegularMember(User $user): bool
    {
        return $this->hasRole($user, 'regular_member');
    }
}
