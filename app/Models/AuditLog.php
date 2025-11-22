<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $fillable = [
        'trace_id',
        'user_id',
        'admin_id',
        'action_type',
        'resource_type',
        'resource_id',
        'changes',
        'description',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($auditLog) {
            if (empty($auditLog->trace_id)) {
                $auditLog->trace_id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function log(
        string $actionType,
        string $description,
        ?int $userId = null,
        ?int $adminId = null,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $changes = null
    ): self {
        return self::create([
            'action_type' => $actionType,
            'description' => $description,
            'user_id' => $userId,
            'admin_id' => $adminId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
