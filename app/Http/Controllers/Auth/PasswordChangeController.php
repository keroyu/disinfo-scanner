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
     * Supports both API and web requests
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function change(Request $request)
    {
        try {
            $user = Auth::user();

            // Support both field naming conventions
            $newPassword = $request->input('new_password') ?? $request->input('password');
            $newPasswordConfirmation = $request->input('new_password_confirmation') ?? $request->input('password_confirmation');

            // For mandatory password change (first login), don't require current password
            // For settings page password change, require current password
            $isMandatoryChange = $user->must_change_password;

            // Validate inputs
            if (!$newPassword || !$newPasswordConfirmation) {
                $errors = [];
                if (!$newPassword) $errors['password'] = ['請輸入新密碼'];
                if (!$newPasswordConfirmation) $errors['password_confirmation'] = ['請確認您的新密碼'];

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => '密碼驗證失敗',
                        'errors' => $errors
                    ], 422);
                }
                return redirect()->back()->withErrors($errors)->withInput();
            }

            // Check password confirmation matches
            if ($newPassword !== $newPasswordConfirmation) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => '新密碼與確認密碼不符',
                        'errors' => ['password_confirmation' => ['新密碼與確認密碼不符']]
                    ], 422);
                }
                return redirect()->back()->withErrors(['password_confirmation' => '新密碼與確認密碼不符'])->withInput();
            }

            // Verify current password (only required when NOT mandatory change)
            if (!$isMandatoryChange) {
                $currentPassword = $request->input('current_password');
                if (!$currentPassword) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => '請輸入目前密碼',
                            'errors' => ['current_password' => ['請輸入目前密碼']]
                        ], 422);
                    }
                    return redirect()->back()->withErrors(['current_password' => '請輸入目前密碼'])->withInput();
                }
                if (!$this->passwordService->verifyPassword($currentPassword, $user->password)) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => '當前密碼不正確',
                            'errors' => ['current_password' => ['當前密碼不正確']]
                        ], 422);
                    }
                    return redirect()->back()->withErrors(['current_password' => '當前密碼不正確'])->withInput();
                }
            }

            // Validate new password strength
            $validation = $this->passwordService->validatePasswordStrength($newPassword);
            if (!$validation['valid']) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => '新密碼不符合強度要求',
                        'errors' => ['password' => $validation['errors']]
                    ], 422);
                }
                return redirect()->back()->withErrors(['password' => $validation['errors']])->withInput();
            }

            // Hash and update password
            $user->password = $this->passwordService->hashPassword($newPassword);
            $user->must_change_password = false;
            $user->last_password_change_at = now();
            $user->save();

            // Log password change event
            Log::info('Password changed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => '密碼已成功更改',
                ]);
            }

            // Redirect to settings page for web requests
            return redirect()->route('settings.index')->with('success', '✓ 密碼已成功更改！');

        } catch (\Exception $e) {
            Log::error('Password change failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '密碼更改失敗，請稍後再試',
                ], 500);
            }

            return redirect()->back()->withErrors(['error' => '密碼更改失敗，請稍後再試'])->withInput();
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
