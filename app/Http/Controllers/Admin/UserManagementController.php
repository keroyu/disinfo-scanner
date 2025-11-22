<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users with pagination
     * T217: Add index method to list all users with pagination
     */
    public function index(Request $request): JsonResponse
    {
        // Authorize
        if (!Gate::forUser($request->user())->allows('viewAny', User::class)) {
            return response()->json([
                'message' => '無權限訪問此功能'
            ], 403);
        }

        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $roleFilter = $request->input('role');

        $query = User::with('roles');

        // Search by name or email
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($roleFilter) {
            $query->whereHas('roles', function ($q) use ($roleFilter) {
                $q->where('role_id', $roleFilter);
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Display the specified user with detailed information
     * T218: Add show method to get user details
     */
    public function show(Request $request, int $userId): JsonResponse
    {
        // Authorize
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => '用戶不存在'
            ], 404);
        }

        if (!Gate::forUser($request->user())->allows('view', $user)) {
            return response()->json([
                'message' => '無權限訪問此功能'
            ], 403);
        }

        // Load relationships
        $user->load(['roles', 'apiQuota', 'identityVerification']);

        // Format response
        $userData = $user->toArray();

        // Add formatted API quota
        $userData['api_quota'] = $user->apiQuota ? [
            'usage_count' => $user->apiQuota->usage_count,
            'monthly_limit' => $user->apiQuota->monthly_limit,
            'is_unlimited' => $user->apiQuota->is_unlimited,
            'current_month' => $user->apiQuota->current_month,
        ] : null;

        // Add formatted identity verification
        $userData['identity_verification'] = $user->identityVerification ? [
            'verification_status' => $user->identityVerification->verification_status,
            'verification_method' => $user->identityVerification->verification_method,
            'submitted_at' => $user->identityVerification->submitted_at,
            'reviewed_at' => $user->identityVerification->reviewed_at,
            'notes' => $user->identityVerification->notes,
        ] : [
            'verification_status' => null,
            'verification_method' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
            'notes' => null,
        ];

        return response()->json($userData);
    }

    /**
     * Update the user's role
     * T219: Add updateRole method to change user role
     */
    public function updateRole(Request $request, int $userId): JsonResponse
    {
        $targetUser = User::find($userId);

        if (!$targetUser) {
            return response()->json([
                'message' => '用戶不存在'
            ], 404);
        }

        // Check if admin is trying to change own role
        if (!Gate::forUser($request->user())->allows('updateRole', $targetUser)) {
            return response()->json([
                'message' => '不能變更自己的權限等級'
            ], 403);
        }

        // Validate request
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        // Get the role
        $role = Role::findOrFail($validated['role_id']);

        // Replace user's roles with the new role (sync replaces all)
        $targetUser->roles()->sync([$role->id]);

        // Reload relationships
        $targetUser->load('roles');

        return response()->json([
            'message' => '用戶角色已更新',
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'roles' => $targetUser->roles,
            ]
        ]);
    }

    /**
     * List verification requests (T249)
     */
    public function listVerificationRequests(Request $request): JsonResponse
    {
        // Authorize
        if (!Gate::forUser($request->user())->allows('manageVerifications', User::class)) {
            return response()->json([
                'message' => '無權限訪問此功能'
            ], 403);
        }

        $perPage = $request->input('per_page', 15);
        $statusFilter = $request->input('status');

        $query = \App\Models\IdentityVerification::with('user');

        // Filter by status if provided
        if ($statusFilter) {
            $query->where('verification_status', $statusFilter);
        }

        // Order by submitted date (newest first)
        $verifications = $query->orderBy('submitted_at', 'desc')
            ->paginate($perPage);

        return response()->json($verifications);
    }

    /**
     * Show verification request details (T250)
     */
    public function showVerificationRequest(Request $request, int $verificationId): JsonResponse
    {
        // Authorize
        if (!Gate::forUser($request->user())->allows('manageVerifications', User::class)) {
            return response()->json([
                'message' => '無權限訪問此功能'
            ], 403);
        }

        $verification = \App\Models\IdentityVerification::with('user')->find($verificationId);

        if (!$verification) {
            return response()->json([
                'message' => '找不到該驗證請求'
            ], 404);
        }

        return response()->json($verification);
    }

    /**
     * Review (approve/reject) verification request (T251)
     * T256-T259: Implementation logic
     */
    public function reviewVerificationRequest(Request $request, int $verificationId): JsonResponse
    {
        // Authorize
        if (!Gate::forUser($request->user())->allows('manageVerifications', User::class)) {
            return response()->json([
                'message' => '無權限訪問此功能'
            ], 403);
        }

        // Validate request
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:500'
        ]);

        // Find verification
        $verification = \App\Models\IdentityVerification::find($verificationId);

        if (!$verification) {
            return response()->json([
                'message' => '找不到該驗證請求'
            ], 404);
        }

        // Update verification status
        $verification->verification_status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $verification->reviewed_at = now();
        $verification->reviewed_by = $request->user()->id;
        $verification->notes = $validated['notes'] ?? null;
        $verification->save();

        // T256: When approved, set api_quotas.is_unlimited = TRUE
        // T257: When rejected, set api_quotas.is_unlimited = FALSE
        if ($validated['action'] === 'approve') {
            // Find or create API quota for the user
            $quota = \App\Models\ApiQuota::firstOrCreate(
                ['user_id' => $verification->user_id],
                [
                    'usage_count' => 0,
                    'monthly_limit' => 10,
                    'current_month' => now()->format('Y-m'),
                ]
            );

            // Set to unlimited
            $quota->is_unlimited = true;
            $quota->save();

            // T258: Send notification email to user on approval
            // (Email implementation placeholder - will be added if Mail is configured)
            try {
                \Illuminate\Support\Facades\Mail::to($verification->user->email)->send(
                    new \Illuminate\Mail\Mailable()
                );
            } catch (\Exception $e) {
                // Log error but don't fail the request
                \Illuminate\Support\Facades\Log::info('Email notification skipped: ' . $e->getMessage());
            }
        } else {
            // Rejected - ensure quota is NOT unlimited
            $quota = \App\Models\ApiQuota::where('user_id', $verification->user_id)->first();
            if ($quota) {
                $quota->is_unlimited = false;
                $quota->save();
            }

            // T258: Send notification email to user on rejection
            try {
                \Illuminate\Support\Facades\Mail::to($verification->user->email)->send(
                    new \Illuminate\Mail\Mailable()
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::info('Email notification skipped: ' . $e->getMessage());
            }
        }

        // T259: Log verification review actions
        \Illuminate\Support\Facades\Log::info('Identity verification reviewed', [
            'verification_id' => $verification->id,
            'user_id' => $verification->user_id,
            'action' => $validated['action'],
            'reviewer_id' => $request->user()->id,
            'reviewed_at' => $verification->reviewed_at,
        ]);

        // Reload with user relationship
        $verification->load('user');

        return response()->json([
            'message' => $validated['action'] === 'approve' ? '身份驗證已批准' : '身份驗證已拒絕',
            'verification' => $verification
        ]);
    }
}
