<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\ApiQuotaService;
use App\Services\RolePermissionService;

/**
 * API controller for quota management endpoints (T416, T421-T423).
 *
 * Provides endpoints for checking and querying API quota status.
 */
class QuotaController extends Controller
{
    protected ApiQuotaService $quotaService;
    protected RolePermissionService $rolePermissionService;

    public function __construct(ApiQuotaService $quotaService, RolePermissionService $rolePermissionService)
    {
        $this->quotaService = $quotaService;
        $this->rolePermissionService = $rolePermissionService;
    }

    /**
     * Check current user's API quota status.
     *
     * T421: Returns quota information for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function check(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'type' => 'Unauthorized',
                    'message' => '請登入會員',
                ],
            ], 401);
        }

        // Administrators have unlimited access
        if ($this->rolePermissionService->isAdministrator($user)) {
            return response()->json([
                'allowed' => true,
                'message' => '管理員擁有無限配額',
                'usage' => [
                    'used' => 0,
                    'limit' => null,
                    'remaining' => null,
                    'unlimited' => true,
                ],
            ]);
        }

        // Website Editors have full frontend access
        if ($this->rolePermissionService->isWebsiteEditor($user)) {
            return response()->json([
                'allowed' => true,
                'message' => '網站編輯擁有無限配額',
                'usage' => [
                    'used' => 0,
                    'limit' => null,
                    'remaining' => null,
                    'unlimited' => true,
                ],
            ]);
        }

        // Regular Members cannot use Official API import
        if ($this->rolePermissionService->isRegularMember($user)) {
            return response()->json([
                'error' => [
                    'type' => 'PermissionDenied',
                    'message' => '一般會員無法使用官方 API 匯入功能',
                    'details' => [
                        'permission' => 'use_official_api_import',
                        'upgrade_message' => '升級為高級會員後即可使用',
                    ],
                ],
            ], 403);
        }

        // Premium Members - check their quota
        if ($this->rolePermissionService->isPaidMember($user)) {
            $result = $this->quotaService->checkQuota($user);
            return response()->json($result);
        }

        // Unknown role
        return response()->json([
            'error' => [
                'type' => 'Unknown',
                'message' => '無法確定您的權限等級',
            ],
        ], 400);
    }

    /**
     * Get detailed quota statistics.
     *
     * Returns comprehensive quota information including last import time.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'type' => 'Unauthorized',
                    'message' => '請登入會員',
                ],
            ], 401);
        }

        // Administrators
        if ($this->rolePermissionService->isAdministrator($user)) {
            return response()->json([
                'used' => 0,
                'limit' => null,
                'remaining' => null,
                'unlimited' => true,
                'last_import' => null,
                'current_month' => now()->format('Y-m'),
                'role' => 'administrator',
            ]);
        }

        // Website Editors
        if ($this->rolePermissionService->isWebsiteEditor($user)) {
            return response()->json([
                'used' => 0,
                'limit' => null,
                'remaining' => null,
                'unlimited' => true,
                'last_import' => null,
                'current_month' => now()->format('Y-m'),
                'role' => 'website_editor',
            ]);
        }

        // Regular Members
        if ($this->rolePermissionService->isRegularMember($user)) {
            return response()->json([
                'error' => [
                    'type' => 'NotApplicable',
                    'message' => '一般會員無法使用官方 API 匯入功能',
                ],
            ], 403);
        }

        // Premium Members
        if ($this->rolePermissionService->isPaidMember($user)) {
            $stats = $this->quotaService->getQuotaStats($user);
            $stats['role'] = 'premium_member';
            return response()->json($stats);
        }

        return response()->json([
            'error' => [
                'type' => 'Unknown',
                'message' => '無法確定您的權限等級',
            ],
        ], 400);
    }
}
