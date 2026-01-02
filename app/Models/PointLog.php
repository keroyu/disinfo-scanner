<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Point Log Model
 * T008: Tracks all point transactions for users
 */
class PointLog extends Model
{
    /**
     * Indicates if the model should be timestamped.
     * Only created_at is used, no updated_at.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'amount',
        'action',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the point log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this is a point earning action.
     */
    public function isEarning(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Check if this is a point spending action.
     */
    public function isSpending(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Get action display name in Traditional Chinese.
     * T106: Added 'uapi_import' action type for U-API video imports
     */
    public function getActionDisplayAttribute(): string
    {
        return match ($this->action) {
            'report' => '回報貼文',
            'redeem' => '兌換期限',
            'uapi_import' => 'U-API 導入',
            default => $this->action,
        };
    }
}
