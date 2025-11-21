<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordChangeRequest;
use App\Services\PasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Password Change Controller
 *
 * Handles mandatory password change for newly registered users
 * and password changes from Settings page
 *
 * T051: User Story 2 - Mandatory Password Change
 */
class PasswordChangeController extends Controller
{
    protected PasswordService $passwordService;

    public function __construct(PasswordService $passwordService)
    {
        $this->passwordService = $passwordService;
        // Note: Middleware 'auth' is applied in routes/web.php for this controller
    }

    /**
     * Show mandatory password change page
     *
     * @return \Illuminate\View\View
     */
    public function showMandatoryChangeForm()
    {
        $user = Auth::user();

        // If user doesn't need to change password, redirect to home
        if (!$user->must_change_password) {
            return redirect('/');
        }

        return view('auth.mandatory-password-change');
    }

    /**
     * Change user password
     *
     * API endpoint for password change
     *
     * @param PasswordChangeRequest $request
     * @return JsonResponse
     */
    public function change(PasswordChangeRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verify current password
            if (!$this->passwordService->verifyPassword($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => '當前密碼不正確',
                    'errors' => [
                        'current_password' => ['當前密碼不正確']
                    ]
                ], 422);
            }

            // Validate new password strength
            $validation = $this->passwordService->validatePasswordStrength($request->new_password);
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => '新密碼不符合強度要求',
                    'errors' => [
                        'new_password' => $validation['errors']
                    ]
                ], 422);
            }

            // Hash and update password
            $user->password = $this->passwordService->hashPassword($request->new_password);
            $user->must_change_password = false;
            $user->save();

            // Log password change event
            Log::info('Password changed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '密碼已成功更改',
            ]);

        } catch (\Exception $e) {
            Log::error('Password change failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '密碼更改失敗，請稍後再試',
            ], 500);
        }
    }


    /**
     * Skip mandatory password change (debug only - remove in production)
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function skip()
    {
        if (config('app.env') !== 'production') {
            $user = Auth::user();
            $user->must_change_password = false;
            $user->save();

            return redirect('/')->with('warning', 'Password change skipped (debug mode)');
        }

        abort(404);
    }
}
