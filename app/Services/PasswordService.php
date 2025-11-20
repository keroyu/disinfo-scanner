<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PasswordService
{
    /**
     * Default password for new users.
     */
    const DEFAULT_PASSWORD = '123456';

    /**
     * Password strength requirements.
     */
    const MIN_LENGTH = 8;
    const REQUIRE_UPPERCASE = true;
    const REQUIRE_LOWERCASE = true;
    const REQUIRE_NUMBER = true;
    const REQUIRE_SPECIAL_CHAR = true;

    /**
     * Validate password strength.
     *
     * @param string $password
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];

        // Check minimum length
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = "密碼長度至少需要 " . self::MIN_LENGTH . " 個字元";
        }

        // Check uppercase
        if (self::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "密碼必須包含至少一個大寫字母 (A-Z)";
        }

        // Check lowercase
        if (self::REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = "密碼必須包含至少一個小寫字母 (a-z)";
        }

        // Check number
        if (self::REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
            $errors[] = "密碼必須包含至少一個數字 (0-9)";
        }

        // Check special character
        if (self::REQUIRE_SPECIAL_CHAR && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = "密碼必須包含至少一個特殊字元 (!@#$%^&*...)";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Hash password using bcrypt.
     *
     * @param string $password
     * @return string
     */
    public function hashPassword(string $password): string
    {
        return Hash::make($password);
    }

    /**
     * Verify password against hash.
     *
     * @param string $password
     * @param string $hashedPassword
     * @return bool
     */
    public function verifyPassword(string $password, string $hashedPassword): bool
    {
        return Hash::check($password, $hashedPassword);
    }

    /**
     * Check if password is the default password.
     *
     * @param string $password
     * @return bool
     */
    public function isDefaultPassword(string $password): bool
    {
        return $password === self::DEFAULT_PASSWORD;
    }

    /**
     * Get default password hash.
     *
     * @return string
     */
    public function getDefaultPasswordHash(): string
    {
        return $this->hashPassword(self::DEFAULT_PASSWORD);
    }

    /**
     * Check if user needs to change password.
     *
     * @param \App\Models\User $user
     * @return bool
     */
    public function needsPasswordChange(\App\Models\User $user): bool
    {
        return $user->has_default_password === true;
    }

    /**
     * Mark password as changed (no longer default).
     *
     * @param \App\Models\User $user
     * @return void
     */
    public function markPasswordChanged(\App\Models\User $user): void
    {
        $user->has_default_password = false;
        $user->last_password_change_at = now();
        $user->save();

        Log::info('SECURITY: Password changed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'changed_at' => now()->toIso8601String(),
        ]);
    }
}
