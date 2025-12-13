<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthenticationService;
use App\Services\EmailVerificationService;
use App\Mail\VerificationEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class RegisterController extends Controller
{
    protected AuthenticationService $authService;
    protected EmailVerificationService $emailService;

    public function __construct(
        AuthenticationService $authService,
        EmailVerificationService $emailService
    ) {
        $this->authService = $authService;
        $this->emailService = $emailService;
    }

    /**
     * Register a new user.
     *
     * @param RegisterRequest $request
     * @return JsonResponse|RedirectResponse
     */
    public function register(RegisterRequest $request): JsonResponse|RedirectResponse
    {
        $email = $request->input('email');
        $name = $request->input('name');

        // Check rate limit for verification emails
        $rateLimitCheck = $this->emailService->checkRateLimit($email);
        if (!$rateLimitCheck['allowed']) {
            // For web requests, redirect back with error
            if (!$request->expectsJson()) {
                return back()->withErrors(['email' => $rateLimitCheck['message']])->withInput();
            }
            return response()->json([
                'success' => false,
                'message' => $rateLimitCheck['message'],
            ], 429);
        }

        // Register user
        $result = $this->authService->register($email, $name);

        if (!$result['success']) {
            // For web requests, redirect back with error
            if (!$request->expectsJson()) {
                return back()->withErrors(['email' => $result['message']])->withInput();
            }
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        // Generate verification token
        $token = $this->emailService->generateToken($email);

        // Send verification email
        Mail::to($email)->queue(new VerificationEmail($token));

        // For web requests, redirect to verify-email page with success message
        if (!$request->expectsJson()) {
            return redirect()->route('verification.notice')
                ->with('status', 'registration-success')
                ->with('registered_email', $email);
        }

        return response()->json([
            'success' => true,
            'message' => '註冊成功，請檢查您的電子郵件以驗證帳號',
            'data' => [
                'email' => $email,
                'verification_sent' => true,
                'expires_in_hours' => 24,
            ],
        ], 201);
    }

    /**
     * Show registration form (web).
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }
}
