<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ApiQuotaService;
use App\Services\RolePermissionService;
use Illuminate\Support\Facades\Log;

/**
 * Middleware to check API quota for Official API import operations (T424).
 *
 * This middleware enforces the 10 imports/month limit for Premium Members
 * and allows unlimited access for verified Premium Members and Administrators.
 *
 * Usage in routes:
 *   Route::post('/import', ...)->middleware('check.api.quota');
 */
class CheckApiQuota
{
    protected ApiQuotaService $quotaService;
    protected RolePermissionService $rolePermissionService;

    public function __construct(ApiQuotaService $quotaService, RolePermissionService $rolePermissionService)
    {
        $this->quotaService = $quotaService;
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

        // Administrators have unlimited access - bypass quota check
        if ($this->rolePermissionService->isAdministrator($user)) {
            return $next($request);
        }

        // Website Editors have full frontend access - bypass quota check
        if ($this->rolePermissionService->isWebsiteEditor($user)) {
            return $next($request);
        }

        // Regular Members cannot use Official API import at all
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

        // Premium Members - check quota (T426: Only applies to non-verified Premium Members)
        if ($this->rolePermissionService->isPaidMember($user)) {
            $quotaResult = $this->quotaService->checkQuota($user);

            if (!$quotaResult['allowed']) {
                Log::info('API quota exceeded for Premium Member', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'usage' => $quotaResult['usage'],
                ]);

                return response()->json([
                    'error' => [
                        'type' => 'QuotaExceeded',
                        'message' => $quotaResult['message'],
                        'details' => [
                            'current_usage' => $quotaResult['usage']['used'],
                            'limit' => $quotaResult['usage']['limit'],
                            'remaining' => $quotaResult['usage']['remaining'],
                            'suggestion' => '請完成身份驗證以獲得無限配額',
                        ],
                    ],
                ], 429);
            }

            // Quota available - proceed
            return $next($request);
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
