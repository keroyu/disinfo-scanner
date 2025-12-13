<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\PasswordChangeRequest;
use App\Models\IdentityVerification;

class UserSettingsController extends Controller
{
    /**
     * Display the user settings page.
     */
    public function index()
    {
        return view('settings.index');
    }

    /**
     * Update the user's display name.
     */
    public function updateName(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => '姓名為必填欄位',
            'name.max' => '姓名長度不得超過 255 個字元',
        ]);

        $request->user()->update(['name' => $validated['name']]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'name' => $validated['name']]);
        }

        return redirect()->route('settings.index')
            ->with('success', '✓ 姓名已成功更新');
    }

    /**
     * Update the user's password from settings page.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]+$/'],
        ], [
            'current_password.current_password' => __('auth.password.current_incorrect'),
            'password.regex' => __('auth.password.weak_password'),
            'password.min' => __('auth.password.validation.min_length'),
        ]);

        $user = auth()->user();
        $user->password = Hash::make($request->password);
        $user->last_password_change_at = now();
        $user->save();

        return redirect()->route('settings.index')->with('success', __('auth.password.change_success'));
    }

    /**
     * Update the user's YouTube API key.
     * T493: Add YouTube API key format validation
     * T494: Save YouTube API key to user record
     */
    public function updateApiKey(Request $request)
    {
        $request->validate([
            'youtube_api_key' => [
                'nullable',
                'string',
                'max:255',
                // T493: YouTube API key format validation - must start with 'AIza' and be 39 characters
                function ($attribute, $value, $fail) {
                    if ($value && $value !== '••••••••••••••••') {
                        if (!preg_match('/^AIza[A-Za-z0-9_-]{35}$/', $value)) {
                            $fail('YouTube API 金鑰格式不正確。金鑰應以「AIza」開頭，總共 39 個字元。');
                        }
                    }
                },
            ],
        ]);

        $user = auth()->user();

        // Only update if a new key is provided (not the masked version)
        if ($request->youtube_api_key && $request->youtube_api_key !== '••••••••••••••••') {
            $user->youtube_api_key = $request->youtube_api_key;
            $user->save();

            return redirect()->route('settings.index')->with('success', 'YouTube API 金鑰已成功儲存。');
        }

        return redirect()->route('settings.index')->with('success', 'API 金鑰未更改。');
    }

    /**
     * Remove the user's YouTube API key.
     */
    public function removeApiKey()
    {
        $user = auth()->user();
        $user->youtube_api_key = null;
        $user->save();

        return redirect()->route('settings.index')->with('success', 'YouTube API 金鑰已移除。');
    }

    /**
     * Submit identity verification request.
     * T497: Add identity verification submission form to settings
     * T498: Validate verification method field
     * T499: Create identity verification record on submission
     */
    public function submitVerification(Request $request)
    {
        $user = auth()->user();

        // Check if user is Premium Member
        if (!$user->hasRole('premium_member')) {
            return redirect()->route('settings.index')
                ->with('error', '只有高級會員可以申請身分驗證。');
        }

        // T498: Validate verification method field
        $request->validate([
            'verification_method' => ['required', 'string', 'in:government_id,social_media,organization'],
            'verification_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'verification_method.required' => '請選擇驗證方式。',
            'verification_method.in' => '請選擇有效的驗證方式。',
            'verification_notes.max' => '備註不得超過 500 個字元。',
        ]);

        // Check if user already has pending or approved verification
        $existingVerification = $user->identityVerification;
        if ($existingVerification) {
            if ($existingVerification->isPending()) {
                return redirect()->route('settings.index')
                    ->with('error', '您已有一筆待審核的驗證申請。');
            }
            if ($existingVerification->isApproved()) {
                return redirect()->route('settings.index')
                    ->with('error', '您的身分已經驗證通過。');
            }
            // If rejected, allow re-submission by updating the existing record
            $existingVerification->update([
                'verification_method' => $request->verification_method,
                'verification_status' => 'pending',
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
                'notes' => $request->verification_notes,
            ]);
        } else {
            // T499: Create identity verification record on submission
            IdentityVerification::create([
                'user_id' => $user->id,
                'verification_method' => $request->verification_method,
                'verification_status' => 'pending',
                'submitted_at' => now(),
                'notes' => $request->verification_notes,
            ]);
        }

        return redirect()->route('settings.index')
            ->with('success', '身分驗證申請已送出，請等待管理員審核。');
    }
}
