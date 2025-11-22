<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_email_verified',
        'has_default_password',
        'last_password_change_at',
        'youtube_api_key',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'youtube_api_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_email_verified' => 'boolean',
            'has_default_password' => 'boolean',
            'last_password_change_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     */

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('assigned_at', 'assigned_by');
    }

    public function apiQuota()
    {
        return $this->hasOne(ApiQuota::class);
    }

    public function identityVerification()
    {
        return $this->hasOne(IdentityVerification::class);
    }

    /**
     * Accessors & Mutators
     */

    /**
     * Accessor for must_change_password (maps to has_default_password)
     * T053: User Story 2 - Mandatory Password Change
     */
    public function getMustChangePasswordAttribute(): bool
    {
        return (bool) $this->has_default_password;
    }

    /**
     * Mutator for must_change_password (maps to has_default_password)
     * T053: User Story 2 - Mandatory Password Change
     */
    public function setMustChangePasswordAttribute(bool $value): void
    {
        $this->has_default_password = $value;
    }

    /**
     * Helper methods
     */

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    public function assignRole(string $roleName, ?int $assignedBy = null): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        if (!$this->hasRole($roleName)) {
            $this->roles()->attach($role->id, [
                'assigned_at' => now(),
                'assigned_by' => $assignedBy,
            ]);
        }
    }

    public function removeRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();

        if ($role) {
            $this->roles()->detach($role->id);
        }
    }
}
