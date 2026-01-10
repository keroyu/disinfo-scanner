<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    protected AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle user login.
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'nullable',
        ], [
            'email.required' => '請輸入電子郵件',
            'email.email' => '請輸入有效的電子郵件格式',
            'password.required' => '請輸入密碼',
        ]);

        if ($validator->fails()) {
            // For web requests, redirect back with errors
            if (!$request->expectsJson()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput($request->except('password'));
            }

            return response()->json([
                'success' => false,
                'message' => '登入資訊不完整',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->input('email');
        $password = $request->input('password');
        $remember = $request->boolean('remember');

        // Attempt login
        $result = $this->authService->login($email, $password, $remember);

        if (!$result['success']) {
            // T281: Log failed admin login attempt
            // Note: We check if user exists and has admin role even on failed login
            $user = \App\Models\User::where('email', $email)->first();
            if ($user && $user->roles->contains('name', 'administrator')) {
                AuditLog::log(
                    actionType: 'admin_login_failed',
                    description: sprintf(
                        '管理員 %s (%s) 登入失敗',
                        $user->name,
                        $user->email
                    ),
                    userId: $user->id,
                    adminId: $user->id
                );
            }

            // For web requests, redirect back with error message
            if (!$request->expectsJson()) {
                return redirect()->back()
                    ->withErrors(['email' => $result['message']])
                    ->withInput($request->except('password'));
            }

            // Determine appropriate status code for API
            if (str_contains($result['message'], '驗證')) {
                $statusCode = 403; // Email not verified
            } else {
                $statusCode = 401; // Invalid credentials
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $statusCode);
        }

        // Successful login
        $user = $result['user'];

        // T052: Check if user is suspended (FR-047: 014-users-management-enhancement)
        if ($user->isSuspended()) {
            // Log out the user immediately
            $this->authService->logout();

            // For web requests, redirect with error
            if (!$request->expectsJson()) {
                return redirect()->back()
                    ->withErrors(['email' => '您的帳號已被停權，請聯繫管理員'])
                    ->withInput($request->except('password'));
            }

            return response()->json([
                'success' => false,
                'message' => '您的帳號已被停權，請聯繫管理員',
            ], 403);
        }

        // T281: Log successful admin login
        if ($user->roles->contains('name', 'administrator')) {
            AuditLog::log(
                actionType: 'admin_login_success',
                description: sprintf(
                    '管理員 %s (%s) 成功登入',
                    $user->name,
                    $user->email
                ),
                userId: $user->id,
                adminId: $user->id
            );
        }

        // For web requests, check if user needs to change password
        if (!$request->expectsJson()) {
            // Check if user has default password and needs to change it
            if ($user->has_default_password) {
                return redirect()->route('password.mandatory')
                    ->with('status', '首次登入需要更改預設密碼');
            }

            // Redirect to home page after successful login
            return redirect()->route('import.index')
                ->with('status', '登入成功！歡迎回來');
        }

        // For API requests, return JSON
        return response()->json([
            'success' => true,
            'message' => '登入成功',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'is_email_verified' => $user->is_email_verified,
                    'has_default_password' => $user->has_default_password,
                ],
            ],
        ], 200);
    }

    /**
     * Handle user logout.
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        $this->authService->logout();

        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => '登出成功',
            ], 200);
        }

        // For web requests, redirect to home page
        return redirect()->route('import.index')->with('status', '已成功登出');
    }

    /**
     * Show login form (web).
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Get current authenticated user.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '未登入',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'is_email_verified' => $user->is_email_verified,
                    'has_default_password' => $user->has_default_password,
                    'roles' => $user->roles->pluck('name'),
                ],
            ],
        ], 200);
    }
}
