<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetEmail;
use App\Services\PasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;

/**
 * Password Reset Controller
 *
 * Handles password reset via email with secure token
 * - Request password reset link
 * - Reset password with token
 * - Rate limiting (3 requests per hour per email)
 *
 * T052: User Story 2 - Password Reset Flow
 */
class PasswordResetController extends Controller
{
    protected PasswordService $passwordService;

    public function __construct(PasswordService $passwordService)
    {
        $this->passwordService = $passwordService;
    }

    /**
     * Show password reset request form
     *
     * @return \Illuminate\View\View
     */
    public function showRequestForm()
    {
        return view('auth.password-reset-request');
    }

    /**
     * Show password reset form with token
     *
     * @param string $token
     * @return \Illuminate\View\View
     */
    public function showResetForm(string $token)
    {
        return view('auth.password-reset', ['token' => $token]);
    }

    /**
     * Send password reset link via email
     *
     * Web endpoint for password reset request
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Rate limiting: 3 requests per hour per email
        $key = 'password-reset:' . $request->email;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'email' => sprintf('請求過於頻繁，請在 %d 分鐘後再試', ceil($seconds / 60)),
            ])->withInput();
        }

        RateLimiter::hit($key, 3600); // 1 hour

        try {
            $user = User::where('email', $request->email)->first();

            // Check if user exists
            if (!$user) {
                Log::info('Password reset requested for non-existent email', [
                    'email' => $request->email,
                    'ip_address' => $request->ip(),
                ]);

                return back()->withErrors([
                    'email' => '此電子郵件尚未註冊，請確認電子郵件地址是否正確。'
                ])->withInput();
            }

            // Create password reset token
            $token = Password::broker()->createToken($user);

            // Send email asynchronously to prevent timeout
            Mail::to($user->email)->queue(new PasswordResetEmail($user->email, $token));

            Log::info('Password reset link sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
            ]);

            return back()->with('status', '密碼重設連結已發送至您的信箱，請檢查您的收件匣（可能需要幾分鐘）。');

        } catch (\Exception $e) {
            Log::error('Password reset link send failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'email' => '發送密碼重設連結失敗，請稍後再試。'
            ])->withInput();
        }
    }

    /**
     * Reset password with token
     *
     * Web endpoint for password reset
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            // Validate password strength
            $validation = $this->passwordService->validatePasswordStrength($request->password);
            if (!$validation['valid']) {
                return back()->withErrors([
                    'password' => $this->translatePasswordErrors($validation['errors'])
                ])->withInput($request->only('email'));
            }

            // Attempt to reset password using Laravel's Password broker
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => $this->passwordService->hashPassword($password),
                        'has_default_password' => false,
                        'last_password_change_at' => now(),
                        'remember_token' => Str::random(60),
                    ])->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                $user = User::where('email', $request->email)->first();

                Log::info('Password reset successful', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip_address' => $request->ip(),
                ]);

                return redirect()->route('login')->with('status', '密碼已成功重設，請使用新密碼登入。');
            }

            // Handle various failure cases
            $errorMessage = match($status) {
                Password::INVALID_TOKEN => '密碼重設連結無效或已過期，請重新申請。',
                Password::INVALID_USER => '找不到該電子郵件對應的用戶。',
                default => '密碼重設失敗，請稍後再試。',
            };

            Log::warning('Password reset failed', [
                'email' => $request->email,
                'status' => $status,
                'ip_address' => $request->ip(),
            ]);

            return back()->withErrors([
                'email' => $errorMessage
            ])->withInput($request->only('email'));

        } catch (\Exception $e) {
            Log::error('Password reset exception', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'email' => '密碼重設失敗，請稍後再試。'
            ])->withInput($request->only('email'));
        }
    }

    /**
     * Translate password validation errors to Traditional Chinese
     *
     * @param array $errors
     * @return array
     */
    protected function translatePasswordErrors(array $errors): array
    {
        $messages = [
            'minimum_length' => '密碼長度至少需要8個字符',
            'uppercase' => '密碼必須包含至少一個大寫字母',
            'lowercase' => '密碼必須包含至少一個小寫字母',
            'number' => '密碼必須包含至少一個數字',
            'special_character' => '密碼必須包含至少一個特殊字符 (!@#$%^&*)',
        ];

        return array_map(fn($error) => $messages[$error] ?? $error, $errors);
    }
}
