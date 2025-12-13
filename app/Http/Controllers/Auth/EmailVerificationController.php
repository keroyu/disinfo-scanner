<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationService;
use App\Services\PasswordService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    protected EmailVerificationService $emailService;
    protected PasswordService $passwordService;

    public function __construct(
        EmailVerificationService $emailService,
        PasswordService $passwordService
    ) {
        $this->emailService = $emailService;
        $this->passwordService = $passwordService;
    }

    /**
     * Show password setup form after clicking verification link (GET).
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function verify(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->route('login')->with('error', '驗證連結無效');
        }

        $email = $request->input('email');
        $token = $request->input('token');

        // Find user
        $user = User::where('email', $email)->first();

        if (!$user) {
            return redirect()->route('login')->with('error', '找不到此電子郵件的帳號');
        }

        // Check if already verified
        if ($user->is_email_verified) {
            return redirect()->route('login')->with('info', '此帳號已驗證，請直接登入');
        }

        // Validate token (but don't mark as used yet)
        $validation = $this->emailService->validateToken($email, $token);

        if (!$validation['valid']) {
            return redirect()->route('verification.notice')
                ->with('error', $validation['message'])
                ->with('registered_email', $email);
        }

        // Show password setup form
        return view('auth.verify-email', [
            'showPasswordSetup' => true,
            'email' => $email,
            'token' => $token,
        ]);
    }

    /**
     * Complete verification and set password (POST).
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function completeVerification(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.required' => '電子郵件為必填',
            'token.required' => '驗證碼為必填',
            'password.required' => '請輸入密碼',
            'password.min' => '密碼長度至少需要 8 個字元',
            'password.confirmed' => '密碼確認不符',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '驗證失敗',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $email = $request->input('email');
        $token = $request->input('token');
        $password = $request->input('password');

        // Find user
        $user = User::where('email', $email)->first();

        if (!$user) {
            return redirect()->route('login')->with('error', '找不到此電子郵件的帳號');
        }

        // Validate token
        $validation = $this->emailService->validateToken($email, $token);

        if (!$validation['valid']) {
            return redirect()->route('verification.notice')
                ->with('error', $validation['message'])
                ->with('registered_email', $email);
        }

        // Validate password strength
        $passwordValidation = $this->passwordService->validatePasswordStrength($password);
        if (!$passwordValidation['valid']) {
            return redirect()->back()
                ->withErrors(['password' => $passwordValidation['errors']])
                ->withInput();
        }

        // Mark token as used
        $this->emailService->markTokenAsUsed($validation['token']);

        // Verify user email and set password
        $this->emailService->verifyUserEmail($user);
        $user->password = $this->passwordService->hashPassword($password);
        $user->has_default_password = false;
        $user->must_change_password = false;
        $user->save();

        Log::info('SECURITY: User verified and set password', [
            'user_id' => $user->id,
            'email' => $user->email,
            'verified_at' => now()->toIso8601String(),
        ]);

        // Redirect to login page (NOT auto-login)
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => '電子郵件驗證成功！請使用新密碼登入',
            ], 200);
        }

        return redirect()->route('login')->with('success', '✓ 電子郵件驗證成功！請使用您設定的密碼登入');
    }

    /**
     * Resend verification email.
     *
     * Supports both API and web requests
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function resend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '請提供有效的電子郵件',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $email = $request->input('email');

        // Check if user already verified
        $user = User::where('email', $email)->first();
        if ($user->is_email_verified) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '此帳號已驗證',
                ], 400);
            }
            return redirect()->route('login')->with('error', '此帳號已驗證，請直接登入');
        }

        // Resend verification (includes rate limit check)
        $result = $this->emailService->resendVerification($email);

        if (!$result['success']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 429);
            }
            return redirect()->back()->with('error', $result['message'])->withInput();
        }

        // Send email
        \Mail::to($email)->queue(new \App\Mail\VerificationEmail($result['token']));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => '驗證郵件已重新發送',
            ], 200);
        }

        return redirect()->back()->with('status', 'verification-link-sent');
    }

    /**
     * Check email verification status (AJAX endpoint).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $email = $request->input('email');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'valid' => false,
                'message' => '請輸入有效的電子郵件',
            ]);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'valid' => true,
                'exists' => false,
                'message' => '找不到此電子郵件的帳號',
            ]);
        }

        if ($user->is_email_verified) {
            return response()->json([
                'valid' => true,
                'exists' => true,
                'verified' => true,
                'message' => '此帳號已完成驗證，請直接登入',
            ]);
        }

        return response()->json([
            'valid' => true,
            'exists' => true,
            'verified' => false,
            'message' => '可以重新發送驗證郵件',
        ]);
    }

    /**
     * Show email verification page (web).
     *
     * @return \Illuminate\View\View
     */
    public function showVerificationPage()
    {
        return view('auth.verify-email');
    }
}
