<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'sometimes|boolean',
        ], [
            'email.required' => '請輸入電子郵件',
            'email.email' => '請輸入有效的電子郵件格式',
            'password.required' => '請輸入密碼',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '登入資訊不完整',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->input('email');
        $password = $request->input('password');
        $remember = $request->input('remember', false);

        // Attempt login
        $result = $this->authService->login($email, $password, $remember);

        if (!$result['success']) {
            // Determine appropriate status code
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
