<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\RolePermissionService;
use Illuminate\Support\Facades\Log;

/**
 * Middleware to check if the authenticated user has a specific permission.
 *
 * Usage in routes:
 *   Route::get('/admin', ...)->middleware('permission:view_admin_panel');
 *   Route::post('/import', ...)->middleware('permission:use_official_api_import');
 *
 * For visitors (unauthenticated users), permissions are checked against the 'visitor' role.
 */
class CheckPermission
{
    protected RolePermissionService $rolePermissionService;

    public function __construct(RolePermissionService $rolePermissionService)
    {
        $this->rolePermissionService = $rolePermissionService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  The permission name to check
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = auth()->user();
        $hasPermission = false;

        if ($user) {
            // Authenticated user - check their permissions through roles
            $hasPermission = $this->rolePermissionService->hasPermission($user, $permission);
        } else {
            // Visitor (unauthenticated) - check visitor role permissions
            $hasPermission = $this->checkVisitorPermission($permission);
        }

        if (!$hasPermission) {
            Log::warning('Permission denied', [
                'permission' => $permission,
                'user_id' => $user?->id,
                'email' => $user?->email,
                'ip' => $request->ip(),
                'url' => $request->url(),
            ]);

            return $this->handlePermissionDenied($request, $user, $permission);
        }

        return $next($request);
    }

    /**
     * Check if the visitor role has the given permission.
     */
    protected function checkVisitorPermission(string $permission): bool
    {
        $visitorPermissions = $this->rolePermissionService->getRolePermissions('visitor');

        return collect($visitorPermissions)->contains('name', $permission);
    }

    /**
     * Handle permission denied response based on request type and user state.
     */
    protected function handlePermissionDenied(Request $request, ?object $user, string $permission): Response
    {
        $message = $this->getPermissionDeniedMessage($user, $permission);
        $upgradeMessage = $this->getUpgradeMessage($user, $permission);

        if ($request->expectsJson()) {
            return response()->json([
                'error' => [
                    'type' => 'PermissionDenied',
                    'message' => $message,
                    'details' => [
                        'permission' => $permission,
                        'upgrade_message' => $upgradeMessage,
                    ],
                ],
            ], 403);
        }

        // For non-authenticated users trying to access protected resources
        if (!$user) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => [
                        'type' => 'LoginRequired',
                        'message' => '請登入會員',
                        'show_modal' => true,
                    ],
                ], 401);
            }
            return redirect()->route('login')->with('error', '請登入會員');
        }

        // For authenticated users without permission
        abort(403, $message);
    }

    /**
     * Get the appropriate permission denied message based on user role and permission.
     */
    protected function getPermissionDeniedMessage(?object $user, string $permission): string
    {
        if (!$user) {
            return '請登入會員';
        }

        // Check if user needs to upgrade to premium
        $premiumPermissions = [
            'use_official_api_import',
            'use_search_comments',
        ];

        if (in_array($permission, $premiumPermissions)) {
            return '需升級為高級會員';
        }

        // Admin-only permissions
        $adminPermissions = [
            'view_admin_panel',
            'manage_users',
            'manage_permissions',
        ];

        if (in_array($permission, $adminPermissions)) {
            return '無權限訪問此功能';
        }

        return '您沒有權限執行此操作';
    }

    /**
     * Get upgrade suggestion message for the user.
     */
    protected function getUpgradeMessage(?object $user, string $permission): ?string
    {
        if (!$user) {
            return '登入後即可使用此功能';
        }

        $premiumPermissions = [
            'use_official_api_import',
            'use_search_comments',
        ];

        if (in_array($permission, $premiumPermissions) &&
            $this->rolePermissionService->isRegularMember($user)) {
            return '升級為高級會員後即可使用此功能';
        }

        return null;
    }
}
