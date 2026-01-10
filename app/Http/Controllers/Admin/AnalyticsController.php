<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    /**
     * Get analytics data (T264-T267, T272-T275)
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        // Authorize
        if (!Gate::forUser($request->user())->allows('viewAny', User::class)) {
            return response()->json([
                'message' => '無權限訪問此功能'
            ], 403);
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // T272-T275: Statistics
        $stats = [
            'totalUsers' => User::count(),
            'premiumMembers' => User::whereHas('roles', function ($q) {
                $q->where('name', 'premium_member');
            })->count(),
        ];

        // T265: New registrations over time (last 30 days by default)
        $registrationsData = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $registrations = [
            'labels' => $registrationsData->pluck('date')->toArray(),
            'data' => $registrationsData->pluck('count')->toArray(),
        ];

        // T266: Active users by role
        $usersByRole = DB::table('users')
            ->join('role_user', 'users.id', '=', 'role_user.user_id')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->select('roles.name', DB::raw('COUNT(*) as count'))
            ->groupBy('roles.name')
            ->get();

        $roleLabels = [
            'visitor' => '訪客',
            'regular_member' => '一般會員',
            'premium_member' => '高級會員',
            'website_editor' => '網站編輯',
            'administrator' => '管理員'
        ];

        $usersByRoleData = [
            'labels' => $usersByRole->pluck('name')->map(fn($name) => $roleLabels[$name] ?? $name)->toArray(),
            'data' => $usersByRole->pluck('count')->toArray(),
        ];

        return response()->json([
            'stats' => $stats,
            'registrations' => $registrations,
            'usersByRole' => $usersByRoleData,
        ]);
    }

    /**
     * Export user list as CSV (T270)
     */
    public function exportUserList(Request $request): StreamedResponse
    {
        // Authorize
        if (!Gate::forUser($request->user())->allows('viewAny', User::class)) {
            abort(403, '無權限訪問此功能');
        }

        $fileName = 'users_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Headers
            fputcsv($handle, ['ID', '暱稱', '電子郵件', '角色', '註冊日期']);

            // Fetch users in chunks to avoid memory issues
            User::with(['roles'])
                ->chunk(1000, function ($users) use ($handle) {
                    foreach ($users as $user) {
                        $roles = $user->roles->pluck('name')->implode(', ');

                        fputcsv($handle, [
                            $user->id,
                            $user->name,
                            $user->email,
                            $roles,
                            $user->created_at->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Generate user activity report (T268)
     */
    public function generateActivityReport(Request $request): StreamedResponse
    {
        // Authorize
        if (!Gate::forUser($request->user())->allows('viewAny', User::class)) {
            abort(403, '無權限訪問此功能');
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $fileName = 'activity_report_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        return response()->stream(function () use ($startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Headers
            fputcsv($handle, ['用戶 ID', '暱稱', '電子郵件', '角色', '最後登入', '註冊日期']);

            // Fetch user activity
            User::with(['roles'])
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
                ->chunk(1000, function ($users) use ($handle) {
                    foreach ($users as $user) {
                        $roles = $user->roles->pluck('name')->implode(', ');

                        fputcsv($handle, [
                            $user->id,
                            $user->name,
                            $user->email,
                            $roles,
                            $user->updated_at->format('Y-m-d H:i:s'),
                            $user->created_at->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Generate API usage report (T269) - Deprecated: Returns empty report
     * API quota tracking has been removed. This endpoint is kept for backwards compatibility.
     */
    public function generateApiUsageReport(Request $request): StreamedResponse
    {
        // Authorize
        if (!Gate::forUser($request->user())->allows('viewAny', User::class)) {
            abort(403, '無權限訪問此功能');
        }

        $fileName = 'api_usage_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Headers
            fputcsv($handle, ['備註']);
            fputcsv($handle, ['API 配額追蹤功能已移除。高級會員現在享有無限制的官方 API 匯入功能。']);

            fclose($handle);
        }, 200, $headers);
    }
}
