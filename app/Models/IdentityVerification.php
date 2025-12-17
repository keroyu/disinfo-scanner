<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityVerification extends Model
{
    protected $fillable = [
        'user_id',
        'verification_method',
        'verification_status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Relationships
     */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Status checks
     */

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->verification_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    /**
     * Approve verification.
     */
    public function approve(int $reviewerId, ?string $notes = null): void
    {
        $this->update([
            'verification_status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewerId,
            'notes' => $notes,
        ]);
    }

    /**
     * Reject verification.
     */
    public function reject(int $reviewerId, ?string $notes = null): void
    {
        $this->update([
            'verification_status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewerId,
            'notes' => $notes,
        ]);
    }

    /**
     * Revert to pending (for re-verification).
     */
    public function revertToPending(?string $notes = null): void
    {
        $this->update([
            'verification_status' => 'pending',
            'reviewed_at' => null,
            'reviewed_by' => null,
            'notes' => $notes,
        ]);
    }
}
