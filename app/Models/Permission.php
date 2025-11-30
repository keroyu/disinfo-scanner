<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'category',
    ];

    /**
     * Relationships
     */

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role')
            ->withPivot('created_at');
    }

    /**
     * Scopes
     */

    public function scopePages($query)
    {
        return $query->where('category', 'pages');
    }

    public function scopeFeatures($query)
    {
        return $query->where('category', 'features');
    }

    public function scopeActions($query)
    {
        return $query->where('category', 'actions');
    }
}
