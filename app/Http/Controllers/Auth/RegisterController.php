<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpCodeEmail;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\OtpService;
use App\Services\RolePermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function __construct(
        protected AuthenticationService $authService,
        protected OtpService $otpService,
        protected RolePermissionService $roleService,
    ) {}

    /**
     * Show registration form (email + name).
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Handle registration form:
     * Validate email + name → check duplicate → check rate limit → send OTP → redirect to OTP page.
     */
    public function register(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'name'  => 'required|string|max:255',
            'terms' => 'accepted',
        ], [
            'email.required' => '請輸入電子郵件',
            'email.email'    => '請輸入有效的電子郵件格式',
            'name.required'  => '請輸入暱稱',
            'terms.accepted' => '請同意服務條款和隱私政策',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $email = $request->input('email');
        $name  = $request->input('name');

        // Check if email already registered
        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['email' => '此電子郵件已被註冊'])->withInput();
        }

        // Check rate limit
        $rateCheck = $this->otpService->checkRateLimit($email);
        if (!$rateCheck['allowed']) {
            return back()->withErrors(['email' => $rateCheck['message']])->withInput();
        }

        // Generate OTP and send email
        $token = $this->otpService->generate($email, 'register');
        Mail::to($email)->queue(new OtpCodeEmail($token->raw_code, 'register'));

        // Store context in session
        $request->session()->put('otp_email', $email);
        $request->session()->put('otp_purpose', 'register');
        $request->session()->put('otp_name', $name);

        return redirect()->route('register.otp');
    }

    /**
     * Show OTP input page (register flow).
     */
    public function showOtpForm(Request $request)
    {
        if (!$request->session()->has('otp_email') || $request->session()->get('otp_purpose') !== 'register') {
            return redirect()->route('register');
        }

        return view('auth.otp-verify', [
            'purpose' => 'register',
            'email'   => $request->session()->get('otp_email'),
        ]);
    }

    /**
     * Verify OTP → create account → login → redirect home.
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
        $name  = $request->session()->get('otp_name');

        if (!$email || $request->session()->get('otp_purpose') !== 'register') {
            return redirect()->route('register')->withErrors(['code' => '請重新開始註冊流程']);
        }

        // Validate OTP
        $result = $this->otpService->validate($email, $request->input('code'), 'register');

        if (!$result['valid']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        // Mark OTP as used
        $this->otpService->markUsed($result['token']);

        // Create user account
        $user = User::create([
            'name'              => $name ?? $email,
            'email'             => $email,
            'is_email_verified' => true,
            'email_verified_at' => now(),
        ]);

        // Assign default role
        $this->roleService->assignRole($user, 'regular_member');

        Log::info('SECURITY: User registered via OTP', [
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);

        // Login the new user
        Auth::login($user);

        // Clear OTP session
        $request->session()->forget(['otp_email', 'otp_purpose', 'otp_name']);

        return redirect()->route('import.index')->with('status', '帳號建立成功！歡迎加入');
    }

    /**
     * Resend OTP for registration.
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $email = $request->session()->get('otp_email');

        if (!$email || $request->session()->get('otp_purpose') !== 'register') {
            return redirect()->route('register');
        }

        $rateCheck = $this->otpService->checkRateLimit($email);
        if (!$rateCheck['allowed']) {
            return back()->withErrors(['code' => $rateCheck['message']]);
        }

        $token = $this->otpService->generate($email, 'register');
        Mail::to($email)->queue(new OtpCodeEmail($token->raw_code, 'register'));

        return back()->with('status', '新的驗證碼已發送至您的信箱');
    }
}
