<?php

namespace App\Services;

use App\Models\OtpToken;
use Illuminate\Support\Facades\Log;

class OtpService
{
    const EXPIRATION_MINUTES = 10;
    const RATE_LIMIT_PER_HOUR = 3;
    const MAX_ATTEMPTS = 5;

    /**
     * Generate and store a new OTP for given email and purpose.
     * Returns OtpToken with raw_code attached (not persisted).
     */
    public function generate(string $email, string $purpose): OtpToken
    {
        $rawCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = hash('sha256', $rawCode);

        $token = OtpToken::create([
            'email' => $email,
            'code_hash' => $codeHash,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
            'used_at' => null,
            'attempts' => 0,
            'created_at' => now(),
        ]);

        // Attach raw code for immediate use (not stored in DB)
        $token->raw_code = $rawCode;

        Log::info('OTP generated', [
            'email' => $email,
            'purpose' => $purpose,
            'expires_at' => $token->expires_at->toIso8601String(),
        ]);

        return $token;
    }

    /**
     * Validate OTP code for given email and purpose.
     * Increments attempt counter; invalidates token after MAX_ATTEMPTS.
     *
     * @return array ['valid' => bool, 'message' => string, 'token' => OtpToken|null]
     */
    public function validate(string $email, string $code, string $purpose): array
    {
        $codeHash = hash('sha256', $code);

        // Find the most recent valid (unused, non-expired) token
        $token = OtpToken::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->orderByDesc('created_at')
            ->first();

        if (!$token) {
            return [
                'valid' => false,
                'message' => 'OTP 無效或已過期，請重新發送',
                'token' => null,
            ];
        }

        // Increment attempts
        $token->increment('attempts');
        $token->refresh();

        // Check code
        if (!hash_equals($token->code_hash, $codeHash)) {
            $remaining = self::MAX_ATTEMPTS - $token->attempts;

            if ($token->attempts >= self::MAX_ATTEMPTS) {
                Log::warning('OTP invalidated after max attempts', [
                    'email' => $email,
                    'purpose' => $purpose,
                ]);
                return [
                    'valid' => false,
                    'message' => '已超過最大嘗試次數，請重新發送 OTP',
                    'token' => null,
                ];
            }

            return [
                'valid' => false,
                'message' => "OTP 錯誤，還剩 {$remaining} 次嘗試機會",
                'token' => null,
            ];
        }

        return [
            'valid' => true,
            'message' => 'OTP 驗證成功',
            'token' => $token,
        ];
    }

    /**
     * Mark OTP token as used.
     */
    public function markUsed(OtpToken $token): void
    {
        $token->used_at = now();
        $token->save();
    }

    /**
     * Check rate limit for given email (max 3 OTPs per hour).
     *
     * @return array ['allowed' => bool, 'message' => string, 'retry_after' => int|null]
     */
    public function checkRateLimit(string $email): array
    {
        $count = OtpToken::where('email', $email)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($count >= self::RATE_LIMIT_PER_HOUR) {
            return [
                'allowed' => false,
                'message' => '您已達到 OTP 發送次數上限（每小時最多 3 次），請稍後再試',
                'retry_after' => 3600,
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'retry_after' => null,
        ];
    }

    /**
     * Clean up expired OTP tokens.
     */
    public function cleanupExpired(): void
    {
        $deleted = OtpToken::where('expires_at', '<', now()->subDays(1))->delete();

        if ($deleted > 0) {
            Log::info('Cleaned up expired OTP tokens', ['count' => $deleted]);
        }
    }
}
