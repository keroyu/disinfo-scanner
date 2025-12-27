<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\PasswordChangeRequest;
use App\Services\PointRedemptionService;
use App\Services\SettingService;

class UserSettingsController extends Controller
{
    /**
     * Display the user settings page.
     * T078: Pass configurable points per day to view (Updated 2025-12-27)
     * Updated: Pass batch redemption calculation data
     */
    public function index(SettingService $settingService, PointRedemptionService $redemptionService)
    {
        $pointsPerDay = $settingService->getPointsPerDay();
        $user = auth()->user();

        // Calculate batch redemption preview for premium users
        $redeemableDays = 0;
        $pointsToDeduct = 0;
        $remainingPoints = 0;

        if ($user && $user->isPremium()) {
            $redeemableDays = $redemptionService->calculateRedeemableDays($user->points);
            $pointsToDeduct = $redemptionService->calculatePointsToDeduct($user->points);
            $remainingPoints = $user->points - $pointsToDeduct;
        }

        return view('settings.index', [
            'pointsPerDay' => $pointsPerDay,
            'redeemableDays' => $redeemableDays,
            'pointsToDeduct' => $pointsToDeduct,
            'remainingPoints' => $remainingPoints,
        ]);
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
     * Redeem points to extend premium membership.
     * T019: Add redeemPoints() method to UserSettingsController
     * T025: Implement atomic transaction in PointRedemptionService
     * T026: Log redemption to point_logs in PointRedemptionService
     */
    public function redeemPoints(PointRedemptionService $redemptionService)
    {
        $user = auth()->user();
        $result = $redemptionService->redeem($user);

        if ($result['success']) {
            return redirect()->route('settings.index')
                ->with('success', $result['message']);
        }

        return redirect()->route('settings.index')
            ->with('error', $result['message']);
    }

    /**
     * Get point logs for the authenticated user.
     * T029: Add pointLogs() method to UserSettingsController
     */
    public function pointLogs()
    {
        $user = auth()->user();

        // Only premium users can view point logs
        if (!$user->isPremium()) {
            return response()->json(['error' => '只有有效的高級會員才能查看積分記錄。'], 403);
        }

        $logs = $user->pointLogs()->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'amount' => $log->amount,
                'action' => $log->action,
                'action_display' => $log->action_display,
                'created_at' => $log->created_at->toDateTimeString(),
                'created_at_display' => $log->created_at->timezone('Asia/Taipei')->format('Y-m-d H:i') . ' (GMT+8)',
            ];
        });

        return response()->json([
            'data' => $logs,
            'meta' => [
                'total' => $logs->count(),
                'current_points' => $user->points,
            ],
        ]);
    }
}
