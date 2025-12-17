<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\RolePermissionService;
use Illuminate\Support\Facades\Log;

/**
 * Middleware to check permissions for Official API import operations.
 *
 * This middleware ensures only Premium Members and above can use Official API import.
 * Regular Members must upgrade to Premium Member to access this feature.
 *
 * Usage in routes:
 *   Route::post('/import', ...)->middleware('check.api.quota');
 */
class CheckApiQuota
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
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Unauthenticated users cannot access Official API import
        if (!$user) {
            Log::warning('Unauthenticated access attempt to Official API import', [
                'ip' => $request->ip(),
                'url' => $request->url(),
            ]);

            return response()->json([
                'error' => [
                    'type' => 'Unauthorized',
                    'message' => '請登入會員',
                ],
            ], 401);
        }

        // Administrators have unlimited access
        if ($this->rolePermissionService->isAdministrator($user)) {
            return $next($request);
        }

        // Website Editors have full frontend access
        if ($this->rolePermissionService->isWebsiteEditor($user)) {
            return $next($request);
        }

        // Premium Members can use Official API import (no quota limit)
        if ($this->rolePermissionService->isPaidMember($user)) {
            return $next($request);
        }

        // Regular Members cannot use Official API import
        if ($this->rolePermissionService->isRegularMember($user)) {
            Log::warning('Regular member attempted Official API import', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => [
                    'type' => 'PermissionDenied',
                    'message' => '需升級為高級會員',
                    'details' => [
                        'permission' => 'use_official_api_import',
                        'upgrade_message' => '升級為高級會員後即可使用官方 API 匯入功能',
                    ],
                ],
            ], 403);
        }

        // Unknown role - deny access
        Log::warning('Unknown role attempted Official API import', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
        ]);

        return response()->json([
            'error' => [
                'type' => 'PermissionDenied',
                'message' => '您沒有權限執行此操作',
            ],
        ], 403);
    }
}
