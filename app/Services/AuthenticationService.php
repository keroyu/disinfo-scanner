<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthenticationService
{
    protected PasswordService $passwordService;
    protected EmailVerificationService $emailService;
    protected RolePermissionService $roleService;

    public function __construct(
        PasswordService $passwordService,
        EmailVerificationService $emailService,
        RolePermissionService $roleService
    ) {
        $this->passwordService = $passwordService;
        $this->emailService = $emailService;
        $this->roleService = $roleService;
    }

    /**
     * Register a new user.
     *
     * @param string $email
     * @param string|null $name
     * @return array ['success' => bool, 'message' => string, 'user' => ?User]
     */
    public function register(string $email, ?string $name = null): array
    {
        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            return [
                'success' => false,
                'message' => '此電子郵件已被註冊',
                'user' => null,
            ];
        }

        // Create user with default password
        $user = User::create([
            'name' => $name ?? $email, // Use provided name, fallback to email
            'email' => $email,
            'password' => $this->passwordService->getDefaultPasswordHash(),
            'has_default_password' => true,
            'is_email_verified' => false,
        ]);

        // Assign default role: regular_member
        $this->roleService->assignRole($user, 'regular_member');

        Log::info('SECURITY: User registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'registered_at' => now()->toIso8601String(),
        ]);

        return [
            'success' => true,
            'message' => '註冊成功，請檢查您的電子郵件以驗證帳號',
            'user' => $user,
        ];
    }

    /**
     * Authenticate user login.
     *
     * @param string $email
     * @param string $password
     * @param bool $remember
     * @return array ['success' => bool, 'message' => string, 'user' => ?User]
     */
    public function login(string $email, string $password, bool $remember = false): array
    {
        // Find user
        $user = User::where('email', $email)->first();

        if (!$user) {
            Log::warning('SECURITY: Login attempt with non-existent email', [
                'email' => $email,
                'ip' => request()->ip(),
            ]);

            return [
                'success' => false,
                'message' => '電子郵件或密碼錯誤',
                'user' => null,
            ];
        }

        // Verify password
        if (!$this->passwordService->verifyPassword($password, $user->password)) {
            Log::warning('SECURITY: Login attempt with incorrect password', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => request()->ip(),
            ]);

            return [
                'success' => false,
                'message' => '電子郵件或密碼錯誤',
                'user' => null,
            ];
        }

        // Check email verification
        if ($this->emailService->needsEmailVerification($user)) {
            Log::info('SECURITY: Login attempt with unverified email', [
                'user_id' => $user->id,
                'email' => $email,
            ]);

            return [
                'success' => false,
                'message' => '請先驗證您的電子郵件',
                'user' => $user,
            ];
        }

        // Check if user still has default password (registration not completed)
        if ($user->has_default_password) {
            Log::info('SECURITY: Login attempt with default password - registration incomplete', [
                'user_id' => $user->id,
                'email' => $email,
            ]);

            return [
                'success' => false,
                'message' => '請先完成電子郵件驗證並設定密碼',
                'user' => $user,
            ];
        }

        // Successful login
        Auth::login($user, $remember);

        // Update last login IP
        $user->last_login_ip = request()->ip();
        $user->save();

        Log::info('SECURITY: User logged in', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => request()->ip(),
            'logged_in_at' => now()->toIso8601String(),
        ]);

        return [
            'success' => true,
            'message' => '登入成功',
            'user' => $user,
        ];
    }

    /**
     * Logout user.
     *
     * @return void
     */
    public function logout(): void
    {
        $user = Auth::user();

        if ($user) {
            Log::info('SECURITY: User logged out', [
                'user_id' => $user->id,
                'email' => $user->email,
                'logged_out_at' => now()->toIso8601String(),
            ]);
        }

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    /**
     * Change user password.
     *
     * @param User $user
     * @param string $newPassword
     * @return array ['success' => bool, 'message' => string]
     */
    public function changePassword(User $user, string $newPassword): array
    {
        // Validate password strength
        $validation = $this->passwordService->validatePasswordStrength($newPassword);

        if (!$validation['valid']) {
            Log::warning('SECURITY: Password change rejected - weak password', [
                'user_id' => $user->id,
                'email' => $user->email,
                'errors' => $validation['errors'],
            ]);

            return [
                'success' => false,
                'message' => implode(', ', $validation['errors']),
            ];
        }

        // Update password
        $user->password = $this->passwordService->hashPassword($newPassword);
        $this->passwordService->markPasswordChanged($user);

        Log::info('SECURITY: Password changed successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'changed_at' => now()->toIso8601String(),
            'was_default_password' => $user->getOriginal('has_default_password') ?? false,
        ]);

        return [
            'success' => true,
            'message' => '密碼已成功變更',
        ];
    }

    /**
     * Check if user needs mandatory password change.
     *
     * @param User $user
     * @return bool
     */
    public function needsMandatoryPasswordChange(User $user): bool
    {
        return $this->passwordService->needsPasswordChange($user);
    }

    /**
     * Get current authenticated user.
     *
     * @return User|null
     */
    public function getCurrentUser(): ?User
    {
        return Auth::user();
    }

    /**
     * Check if user is authenticated.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return Auth::check();
    }
}
