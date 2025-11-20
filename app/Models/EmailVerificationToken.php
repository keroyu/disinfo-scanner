<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmailVerificationToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'email',
        'token',
        'created_at',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Generate a new verification token.
     */
    public static function createToken(string $email): self
    {
        // Generate random token
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        // Create token record
        $token = self::create([
            'email' => $email,
            'token' => $hashedToken,
            'created_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        // Store plain token temporarily for email sending
        $token->plain_token = $plainToken;

        return $token;
    }

    /**
     * Check if token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if token has been used.
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Check if token is valid (not expired and not used).
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    /**
     * Mark token as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    /**
     * Find token by plain text token.
     */
    public static function findByPlainToken(string $plainToken): ?self
    {
        $hashedToken = hash('sha256', $plainToken);
        return self::where('token', $hashedToken)->first();
    }
}
