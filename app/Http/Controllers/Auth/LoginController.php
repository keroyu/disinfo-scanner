<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpCodeEmail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\IpGeolocationService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    public function __construct(
        protected AuthenticationService $authService,
        protected OtpService $otpService,
        protected IpGeolocationService $ipGeoService,
    ) {}

    /**
     * Show login form (email only).
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle login form:
     * Validate email → find user → check rate limit → send OTP → redirect to OTP page.
     */
    public function login(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ], [
            'email.required' => '請輸入電子郵件',
            'email.email'    => '請輸入有效的電子郵件格式',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $email = $request->input('email');

        // Find user – return same error message to avoid email enumeration
        $user = User::where('email', $email)->first();
        if (!$user) {
            Log::warning('SECURITY: Login attempt with non-existent email', [
                'email' => $email,
                'ip'    => $request->ip(),
            ]);
            return back()->withErrors(['email' => '若此信箱已註冊，驗證碼將發送至您的信箱'])->withInput();
        }

        // Check suspended
        if ($user->isSuspended()) {
            return back()->withErrors(['email' => '您的帳號已被停權，請聯繫管理員'])->withInput();
        }

        // Check rate limit
        $rateCheck = $this->otpService->checkRateLimit($email);
        if (!$rateCheck['allowed']) {
            return back()->withErrors(['email' => $rateCheck['message']])->withInput();
        }

        // Generate OTP and send
        $token = $this->otpService->generate($email, 'login');
        Mail::to($email)->queue(new OtpCodeEmail($token->raw_code, 'login'));

        // Store context in session
        $request->session()->put('otp_email', $email);
        $request->session()->put('otp_purpose', 'login');

        return redirect()->route('login.otp');
    }

    /**
     * Show OTP input page (login flow).
     */
    public function showOtpForm(Request $request)
    {
        if (!$request->session()->has('otp_email') || $request->session()->get('otp_purpose') !== 'login') {
            return redirect()->route('login');
        }

        return view('auth.otp-verify', [
            'purpose' => 'login',
            'email'   => $request->session()->get('otp_email'),
        ]);
    }

    /**
     * Verify OTP → Auth::login() → redirect home.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6|regex:/^\d{6}$/',
        ], [
            'code.required' => '請輸入驗證碼',
            'code.size'     => '驗證碼必須為 6 位數字',
            'code.regex'    => '驗證碼必須為 6 位數字',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $email = $request->session()->get('otp_email');

        if (!$email || $request->session()->get('otp_purpose') !== 'login') {
            return redirect()->route('login')->withErrors(['code' => '請重新開始登入流程']);
        }

        // Validate OTP
        $result = $this->otpService->validate($email, $request->input('code'), 'login');

        if (!$result['valid']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        // Mark OTP as used
        $this->otpService->markUsed($result['token']);

        $user = User::where('email', $email)->first();

        if (!$user) {
            return redirect()->route('login')->withErrors(['code' => '帳號不存在，請重新登入']);
        }

        // Double-check suspended status
        if ($user->isSuspended()) {
            return redirect()->route('login')->withErrors(['email' => '您的帳號已被停權，請聯繫管理員']);
        }

        // Log in the user
        Auth::login($user);

        // Update last login IP and location
        $ip = $request->ip();
        $user->last_login_ip = $ip;
        $ipLocation = $this->ipGeoService->getLocation($ip);
        if (!empty($ipLocation['city'])) {
            $parts = [];
            if (!empty($ipLocation['country'])) {
                $parts[] = $ipLocation['country'];
            }
            $parts[] = $ipLocation['city'];
            $user->location = implode(', ', $parts);
        }
        $user->save();

        // Audit log for admin
        if ($user->roles->contains('name', 'administrator')) {
            AuditLog::log(
                actionType: 'admin_login_success',
                description: sprintf('管理員 %s (%s) 成功登入', $user->name, $user->email),
                userId: $user->id,
                adminId: $user->id
            );
        }

        Log::info('SECURITY: User logged in via OTP', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'ip'      => $ip,
        ]);

        // Clear OTP session
        $request->session()->forget(['otp_email', 'otp_purpose']);

        return redirect()->route('import.index')->with('status', '登入成功！歡迎回來');
    }

    /**
     * Resend OTP for login.
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $email = $request->session()->get('otp_email');

        if (!$email || $request->session()->get('otp_purpose') !== 'login') {
            return redirect()->route('login');
        }

        $rateCheck = $this->otpService->checkRateLimit($email);
        if (!$rateCheck['allowed']) {
            return back()->withErrors(['code' => $rateCheck['message']]);
        }

        $token = $this->otpService->generate($email, 'login');
        Mail::to($email)->queue(new OtpCodeEmail($token->raw_code, 'login'));

        return back()->with('status', '新的驗證碼已發送至您的信箱');
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request)
    {
        $this->authService->logout();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => true, 'message' => '登出成功'], 200);
        }

        return redirect()->route('import.index')->with('status', '已成功登出');
    }

    /**
     * Get current authenticated user (API).
     */
    public function me(): JsonResponse
    {
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            return response()->json(['success' => false, 'message' => '未登入'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id'                => $user->id,
                    'email'             => $user->email,
                    'name'              => $user->name,
                    'is_email_verified' => $user->is_email_verified,
                    'roles'             => $user->roles->pluck('name'),
                ],
            ],
        ], 200);
    }
}
