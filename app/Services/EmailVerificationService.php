<?php

namespace App\Services;

use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailVerificationService
{
    /**
     * Token expiration in hours.
     */
    const TOKEN_EXPIRATION_HOURS = 24;

    /**
     * Rate limit: 3 verification emails per hour.
     */
    const RATE_LIMIT_PER_HOUR = 3;

    /**
     * Generate and store email verification token.
     *
     * @param string $email
     * @return EmailVerificationToken
     */
    public function generateToken(string $email): EmailVerificationToken
    {
        // Generate random token
        $rawToken = Str::random(64);
        $hashedToken = hash('sha256', $rawToken);

        // Create token record
        $token = EmailVerificationToken::create([
            'email' => $email,
            'token' => $hashedToken,
            'created_at' => now(),
            'expires_at' => now()->addHours(self::TOKEN_EXPIRATION_HOURS),
            'used_at' => null,
        ]);

        // Store raw token for email (not saved to database)
        $token->raw_token = $rawToken;

        Log::info('Email verification token generated', [
            'email' => $email,
            'expires_at' => $token->expires_at->toIso8601String(),
        ]);

        return $token;
    }

    /**
     * Validate verification token.
     *
     * @param string $email
     * @param string $rawToken
     * @return array ['valid' => bool, 'message' => string, 'token' => ?EmailVerificationToken]
     */
    public function validateToken(string $email, string $rawToken): array
    {
        $hashedToken = hash('sha256', $rawToken);

        // Find token
        $token = EmailVerificationToken::where('email', $email)
            ->where('token', $hashedToken)
            ->first();

        if (!$token) {
            return [
                'valid' => false,
                'message' => '驗證連結無效',
                'token' => null,
            ];
        }

        // Check if already used
        if ($token->used_at !== null) {
            return [
                'valid' => false,
                'message' => '此驗證連結已被使用',
                'token' => $token,
            ];
        }

        // Check if expired
        if (now()->isAfter($token->expires_at)) {
            return [
                'valid' => false,
                'message' => '驗證連結已過期，請重新發送驗證郵件',
                'token' => $token,
            ];
        }

        return [
            'valid' => true,
            'message' => '驗證成功',
            'token' => $token,
        ];
    }

    /**
     * Mark token as used.
     *
     * @param EmailVerificationToken $token
     * @return void
     */
    public function markTokenAsUsed(EmailVerificationToken $token): void
    {
        $token->used_at = now();
        $token->save();

        Log::info('Email verification token used', [
            'email' => $token->email,
            'used_at' => $token->used_at->toIso8601String(),
        ]);
    }

    /**
     * Verify user email.
     *
     * @param User $user
     * @return void
     */
    public function verifyUserEmail(User $user): void
    {
        $user->is_email_verified = true;
        $user->email_verified_at = now();
        $user->save();

        Log::info('SECURITY: Email verified', [
            'user_id' => $user->id,
            'email' => $user->email,
            'verified_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Check if user needs email verification.
     *
     * @param User $user
     * @return bool
     */
    public function needsEmailVerification(User $user): bool
    {
        return $user->is_email_verified === false;
    }

    /**
     * Clean up expired tokens (called by scheduled task).
     *
     * @return int Number of tokens deleted
     */
    public function cleanupExpiredTokens(): int
    {
        $deletedCount = EmailVerificationToken::where('expires_at', '<', now()->subDays(7))
            ->delete();

        if ($deletedCount > 0) {
            Log::info('Cleaned up expired email verification tokens', [
                'deleted_count' => $deletedCount,
            ]);
        }

        return $deletedCount;
    }

    /**
     * Check rate limiting for email verification requests.
     *
     * @param string $email
     * @return array ['allowed' => bool, 'message' => string, 'retry_after' => ?int]
     */
    public function checkRateLimit(string $email): array
    {
        $recentTokens = EmailVerificationToken::where('email', $email)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentTokens >= self::RATE_LIMIT_PER_HOUR) {
            return [
                'allowed' => false,
                'message' => '您已達到驗證郵件發送次數上限，請稍後再試',
                'retry_after' => 3600, // 1 hour in seconds
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'retry_after' => null,
        ];
    }

    /**
     * Resend verification email (checks rate limit).
     *
     * @param string $email
     * @return array ['success' => bool, 'message' => string, 'token' => ?EmailVerificationToken]
     */
    public function resendVerification(string $email): array
    {
        // Check rate limit
        $rateLimitCheck = $this->checkRateLimit($email);
        if (!$rateLimitCheck['allowed']) {
            return [
                'success' => false,
                'message' => $rateLimitCheck['message'],
                'token' => null,
            ];
        }

        // Generate new token
        $token = $this->generateToken($email);

        return [
            'success' => true,
            'message' => '驗證郵件已重新發送',
            'token' => $token,
        ];
    }
}
