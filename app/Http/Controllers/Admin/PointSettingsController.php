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
 * T079: Admin Point Settings Controller
 *
 * Updated 2025-12-27: Changed from point_redemption_days to points_per_day
 * - Old: 10 points = N days (configurable days 1-365)
 * - New: X points = 1 day (configurable points 1-1000)
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
        $currentPoints = $this->settingService->getPointsPerDay();
        $lastUpdated = Setting::where('key', 'points_per_day')
            ->value('updated_at');

        return view('admin.points.settings', [
            'currentPoints' => $currentPoints,
            'lastUpdated' => $lastUpdated,
        ]);
    }

    /**
     * Update the points settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'points_per_day' => ['required', 'integer', 'min:1', 'max:1000'],
        ], [
            'points_per_day.required' => '每日所需積分為必填欄位',
            'points_per_day.integer' => '每日所需積分必須為整數',
            'points_per_day.min' => '每日所需積分最少為 1',
            'points_per_day.max' => '每日所需積分最多為 1000',
        ]);

        $oldValue = $this->settingService->getPointsPerDay();
        $newValue = (int) $validated['points_per_day'];

        // Audit logging for setting changes
        $this->logSettingChange($oldValue, $newValue, $request);

        // Update setting (this also clears cache)
        $this->settingService->setPointsPerDay($newValue);

        Log::info('Points per day updated', [
            'admin_id' => auth()->id(),
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);

        return redirect()
            ->route('admin.points.settings')
            ->with('success', "設定已更新：{$newValue} 積分可兌換 1 天高級會員期限");
    }

    /**
     * Log setting changes to audit_logs
     */
    protected function logSettingChange(int $oldValue, int $newValue, Request $request): void
    {
        AuditLog::log(
            actionType: 'update_setting',
            description: "每日所需積分從 {$oldValue} 積分更改為 {$newValue} 積分",
            adminId: auth()->id(),
            resourceType: 'setting',
            changes: [
                'key' => 'points_per_day',
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]
        );
    }
}
