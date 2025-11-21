<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EmailVerificationController extends Controller
{
    protected EmailVerificationService $emailService;

    public function __construct(EmailVerificationService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Verify user email with token.
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function verify(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '驗證參數不完整',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->route('login')->with('error', '驗證參數不完整');
        }

        $email = $request->input('email');
        $token = $request->input('token');

        // Find user
        $user = User::where('email', $email)->first();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '找不到此電子郵件的帳號',
                ], 400);
            }
            return redirect()->route('login')->with('error', '找不到此電子郵件的帳號');
        }

        // Validate token
        $validation = $this->emailService->validateToken($email, $token);

        if (!$validation['valid']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message'],
                ], 400);
            }
            return redirect()->route('login')->with('error', $validation['message']);
        }

        // Mark token as used
        $this->emailService->markTokenAsUsed($validation['token']);

        // Verify user email
        $this->emailService->verifyUserEmail($user);

        // Log the user in automatically after verification
        auth()->login($user);

        // Redirect to settings page for web requests
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => '電子郵件驗證成功！您現在可以登入了',
            ], 200);
        }

        return redirect()->route('settings.index')->with('success', '✓ 電子郵件驗證成功！歡迎使用 DISINFO_SCANNER');
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
