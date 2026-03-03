<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthenticationService
{
    public function __construct(
        protected RolePermissionService $roleService,
        protected IpGeolocationService $ipGeoService,
    ) {}

    /**
     * Register a new user (without password).
     * Used by RegisterController OTP flow.
     *
     * @return array ['success' => bool, 'message' => string, 'user' => ?User]
     */
    public function register(string $email, ?string $name = null): array
    {
        if (User::where('email', $email)->exists()) {
            return [
                'success' => false,
                'message' => '此電子郵件已被註冊',
                'user'    => null,
            ];
        }

        $user = User::create([
            'name'              => $name ?? $email,
            'email'             => $email,
            'is_email_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->roleService->assignRole($user, 'regular_member');

        Log::info('SECURITY: User registered', [
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);

        return [
            'success' => true,
            'message' => '註冊成功',
            'user'    => $user,
        ];
    }

    /**
     * Logout user.
     */
    public function logout(): void
    {
        $user = Auth::user();

        if ($user) {
            Log::info('SECURITY: User logged out', [
                'user_id'       => $user->id,
                'email'         => $user->email,
                'logged_out_at' => now()->toIso8601String(),
            ]);
        }

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    /**
     * Get current authenticated user.
     */
    public function getCurrentUser(): ?User
    {
        return Auth::user();
    }

    /**
     * Check if user is authenticated.
     */
    public function isAuthenticated(): bool
    {
        return Auth::check();
    }
}
