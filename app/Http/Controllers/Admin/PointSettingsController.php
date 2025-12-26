<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * T050: Admin Point Settings Controller
 * Handles configuration of point redemption settings
 */
class PointSettingsController extends Controller
{
    public function __construct(
        protected SettingService $settingService
    ) {
    }

    /**
     * Display the points settings page.
     */
    public function index(): View
    {
        $currentDays = $this->settingService->getPointRedemptionDays();
        $lastUpdated = Setting::where('key', 'point_redemption_days')
            ->value('updated_at');

        return view('admin.points.settings', [
            'currentDays' => $currentDays,
            'lastUpdated' => $lastUpdated,
        ]);
    }

    /**
     * Update the points settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'point_redemption_days' => ['required', 'integer', 'min:1', 'max:365'],
        ], [
            'point_redemption_days.required' => '兌換天數為必填欄位',
            'point_redemption_days.integer' => '兌換天數必須為整數',
            'point_redemption_days.min' => '兌換天數最少為 1 天',
            'point_redemption_days.max' => '兌換天數最多為 365 天',
        ]);

        $oldValue = $this->settingService->getPointRedemptionDays();
        $newValue = (int) $validated['point_redemption_days'];

        // T057: Audit logging for setting changes
        $this->logSettingChange($oldValue, $newValue, $request);

        // Update setting (this also clears cache)
        $this->settingService->setPointRedemptionDays($newValue);

        Log::info('Point redemption days updated', [
            'admin_id' => auth()->id(),
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);

        return redirect()
            ->route('admin.points.settings')
            ->with('success', "設定已更新：10 積分可兌換 {$newValue} 天高級會員期限");
    }

    /**
     * T057: Log setting changes to audit_logs
     */
    protected function logSettingChange(int $oldValue, int $newValue, Request $request): void
    {
        AuditLog::log(
            actionType: 'update_setting',
            description: "積分兌換天數從 {$oldValue} 天更改為 {$newValue} 天",
            adminId: auth()->id(),
            resourceType: 'setting',
            changes: [
                'key' => 'point_redemption_days',
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]
        );
    }
}
