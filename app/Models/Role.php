<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    /**
     * Relationships
     */

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot('assigned_at', 'assigned_by')
            ->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
            ->withTimestamps();
    }

    /**
     * Helper methods
     */

    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    public function grantPermission(string $permissionName): void
    {
        $permission = Permission::where('name', $permissionName)->firstOrFail();

        if (!$this->hasPermission($permissionName)) {
            $this->permissions()->attach($permission->id);
        }
    }

    public function revokePermission(string $permissionName): void
    {
        $permission = Permission::where('name', $permissionName)->first();

        if ($permission) {
            $this->permissions()->detach($permission->id);
        }
    }
}
