<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserManagementController extends Controller
{
    /**
     * T217: List all users with pagination, search, and role filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Get query parameters
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $roleFilter = $request->input('role');

        // Start query with eager loading
        $query = User::with('roles');

        // Apply search filter (name or email)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply role filter
        if ($roleFilter) {
            $query->whereHas('roles', function ($q) use ($roleFilter) {
                $q->where('name', $roleFilter);
            });
        }

        // Paginate results
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Transform response
        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * T218: Get user details including roles, API quota, and identity verification
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // Find user with relationships
        $user = User::with(['roles', 'apiQuota', 'identityVerification'])->find($id);

        if (!$user) {
            return response()->json([
                'message' => '找不到指定的使用者'
            ], 404);
        }

        // Transform response
        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_email_verified' => $user->is_email_verified,
                'has_default_password' => $user->has_default_password,
                'youtube_api_key' => $user->youtube_api_key ? '已設定' : null,
                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                    ];
                }),
                'api_quota' => $user->apiQuota ? [
                    'usage_count' => $user->apiQuota->usage_count,
                    'monthly_limit' => $user->apiQuota->monthly_limit,
                    'is_unlimited' => $user->apiQuota->is_unlimited,
                    'current_month' => $user->apiQuota->current_month,
                ] : null,
                'identity_verification' => $user->identityVerification ? [
                    'verification_status' => $user->identityVerification->verification_status,
                    'verification_method' => $user->identityVerification->verification_method,
                    'submitted_at' => $user->identityVerification->submitted_at,
                ] : null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * T219: Change user role (prevents self-permission change)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        // Validate request
        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        // Find user
        $targetUser = User::with('roles')->find($id);

        if (!$targetUser) {
            return response()->json([
                'message' => '找不到指定的使用者'
            ], 404);
        }

        // Check authorization (prevents self-permission change)
        if (!auth()->user()->can('updateRole', $targetUser)) {
            return response()->json([
                'message' => '您無法變更自己的權限等級'
            ], 403);
        }

        // Get new role
        $newRole = Role::find($request->role_id);

        // Sync role (removes old role, adds new role)
        $targetUser->roles()->sync([$newRole->id]);

        // Create API quota if upgrading to premium_member
        if ($newRole->name === 'premium_member' && !$targetUser->apiQuota) {
            ApiQuota::create([
                'user_id' => $targetUser->id,
                'current_month' => now()->format('Y-m'),
                'usage_count' => 0,
                'monthly_limit' => 10,
                'is_unlimited' => false,
            ]);
        }

        // Reload user with new role
        $targetUser = $targetUser->fresh(['roles']);

        // Return success response
        return response()->json([
            'message' => '使用者角色已更新',
            'data' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'roles' => $targetUser->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                    ];
                }),
            ],
        ]);
    }
}
