<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\EscapesLikeQueries;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use App\Models\AuditLog;
use App\Models\IdentityVerification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserManagementController extends Controller
{
    use EscapesLikeQueries;

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

        // Apply search filter (name or email) with escaped wildcards
        if ($search) {
            $escapedSearch = $this->buildLikePattern($search);
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('name', 'like', $escapedSearch)
                  ->orWhere('email', 'like', $escapedSearch);
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
     * @param int $userId
     * @return JsonResponse
     */
    public function show(int $userId): JsonResponse
    {
        // Find user with relationships
        $user = User::with(['roles', 'apiQuota', 'identityVerification'])->find($userId);

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
     * @param int $userId
     * @return JsonResponse
     */
    public function updateRole(Request $request, int $userId): JsonResponse
    {
        // Validate request
        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        // Find user
        $targetUser = User::with('roles')->find($userId);

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

        // Get current and new roles
        $oldRole = $targetUser->roles->first();
        $newRole = Role::find($request->role_id);

        // Sync role (removes old role, adds new role)
        $targetUser->roles()->sync([$newRole->id]);

        // T279: Log role change
        AuditLog::log(
            actionType: 'user_role_changed',
            description: sprintf(
                'Admin %s (%s) 將使用者 %s (%s) 的角色從 %s 變更為 %s',
                auth()->user()->name,
                auth()->user()->email,
                $targetUser->name,
                $targetUser->email,
                $oldRole ? $oldRole->display_name : '無',
                $newRole->display_name
            ),
            userId: $targetUser->id,
            adminId: auth()->id(),
            resourceType: 'user',
            resourceId: $targetUser->id,
            changes: [
                'old_role' => $oldRole ? [
                    'id' => $oldRole->id,
                    'name' => $oldRole->name,
                    'display_name' => $oldRole->display_name,
                ] : null,
                'new_role' => [
                    'id' => $newRole->id,
                    'name' => $newRole->name,
                    'display_name' => $newRole->display_name,
                ],
            ]
        );

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

    /**
     * Manually verify user's email address (admin action)
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function verifyEmail(int $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => '找不到指定的使用者'
            ], 404);
        }

        if ($user->is_email_verified) {
            return response()->json([
                'message' => '此使用者的電子郵件已驗證'
            ], 400);
        }

        // Mark email as verified (update both fields)
        $user->is_email_verified = true;
        $user->email_verified_at = now();
        $user->save();

        // Log the manual verification
        AuditLog::log(
            actionType: 'email_manually_verified',
            description: sprintf(
                'Admin %s (%s) 手動驗證了使用者 %s (%s) 的電子郵件',
                auth()->user()->name,
                auth()->user()->email,
                $user->name,
                $user->email
            ),
            userId: $user->id,
            adminId: auth()->id(),
            resourceType: 'user',
            resourceId: $user->id,
            changes: [
                'is_email_verified' => true,
                'email_verified_at' => $user->email_verified_at->toIso8601String(),
            ]
        );

        return response()->json([
            'message' => '電子郵件已手動驗證成功',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'is_email_verified' => $user->is_email_verified,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }

    /**
     * T249: List all identity verification requests
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listVerificationRequests(Request $request): JsonResponse
    {
        $status = $request->input('status', 'pending');
        $perPage = $request->input('per_page', 15);

        $verifications = IdentityVerification::with('user')
            ->where('verification_status', $status)
            ->orderBy('submitted_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $verifications->items(),
            'meta' => [
                'current_page' => $verifications->currentPage(),
                'last_page' => $verifications->lastPage(),
                'per_page' => $verifications->perPage(),
                'total' => $verifications->total(),
            ],
        ]);
    }

    /**
     * T250: Show single verification request details
     *
     * @param int $verificationId
     * @return JsonResponse
     */
    public function showVerificationRequest(int $verificationId): JsonResponse
    {
        $verification = IdentityVerification::with('user')->find($verificationId);

        if (!$verification) {
            return response()->json([
                'message' => '找不到指定的驗證申請'
            ], 404);
        }

        return response()->json([
            'data' => $verification,
        ]);
    }

    /**
     * T251: Review identity verification request (approve/reject)
     *
     * @param Request $request
     * @param int $verificationId
     * @return JsonResponse
     */
    public function reviewVerificationRequest(Request $request, int $verificationId): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string',
        ]);

        $verification = IdentityVerification::with('user')->find($verificationId);

        if (!$verification) {
            return response()->json([
                'message' => '找不到指定的驗證申請'
            ], 404);
        }

        if ($verification->verification_status !== 'pending') {
            return response()->json([
                'message' => '此驗證申請已被審核過'
            ], 400);
        }

        $action = $request->input('action');
        $notes = $request->input('notes');

        // Update verification status
        $verification->verification_status = $action === 'approve' ? 'approved' : 'rejected';
        $verification->reviewed_at = now();
        $verification->notes = $notes;
        $verification->save();

        // T256-T257: Update API quota unlimited status
        if ($action === 'approve') {
            $apiQuota = $verification->user->apiQuota;
            if ($apiQuota) {
                $apiQuota->is_unlimited = true;
                $apiQuota->save();
            }
        } else {
            $apiQuota = $verification->user->apiQuota;
            if ($apiQuota) {
                $apiQuota->is_unlimited = false;
                $apiQuota->save();
            }
        }

        // T280: Log identity verification review
        AuditLog::log(
            actionType: 'identity_verification_reviewed',
            description: sprintf(
                'Admin %s (%s) %s了使用者 %s (%s) 的身份驗證申請。備註: %s',
                auth()->user()->name,
                auth()->user()->email,
                $action === 'approve' ? '批准' : '拒絕',
                $verification->user->name,
                $verification->user->email,
                $notes ?? '無'
            ),
            userId: $verification->user_id,
            adminId: auth()->id(),
            resourceType: 'identity_verification',
            resourceId: $verification->id,
            changes: [
                'action' => $action,
                'old_status' => 'pending',
                'new_status' => $verification->verification_status,
                'notes' => $notes,
                'api_quota_unlimited' => $action === 'approve',
            ]
        );

        return response()->json([
            'message' => $action === 'approve' ? '身份驗證已批准' : '身份驗證已拒絕',
            'verification' => [
                'id' => $verification->id,
                'user_id' => $verification->user_id,
                'verification_method' => $verification->verification_method,
                'verification_status' => $verification->verification_status,
                'reviewed_at' => $verification->reviewed_at,
                'notes' => $verification->notes,
            ],
        ]);
    }

    /**
     * T282: List audit logs with pagination and filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function auditLogs(Request $request): JsonResponse
    {
        // Validate input including date formats
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'action_type' => 'nullable|string|max:50',
            'user_id' => 'nullable|integer|exists:users,id',
            'admin_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date|date_format:Y-m-d',
            'date_to' => 'nullable|date|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $actionType = $validated['action_type'] ?? null;
        $userId = $validated['user_id'] ?? null;
        $adminId = $validated['admin_id'] ?? null;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        $query = AuditLog::with(['user', 'admin'])
            ->orderBy('created_at', 'desc');

        // T288: Filter by action type
        if ($actionType) {
            $query->where('action_type', $actionType);
        }

        // T288: Filter by user
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // T288: Filter by admin
        if ($adminId) {
            $query->where('admin_id', $adminId);
        }

        // T288: Filter by date range (using whereDate for proper date comparison)
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $auditLogs = $query->paginate($perPage);

        return response()->json([
            'data' => $auditLogs->items(),
            'meta' => [
                'current_page' => $auditLogs->currentPage(),
                'last_page' => $auditLogs->lastPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
            ],
        ]);
    }

    /**
     * T289: Export audit logs as CSV
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportAuditLogs(Request $request)
    {
        // Validate input including date formats
        $validated = $request->validate([
            'action_type' => 'nullable|string|max:50',
            'user_id' => 'nullable|integer|exists:users,id',
            'admin_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date|date_format:Y-m-d',
            'date_to' => 'nullable|date|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $actionType = $validated['action_type'] ?? null;
        $userId = $validated['user_id'] ?? null;
        $adminId = $validated['admin_id'] ?? null;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        $query = AuditLog::with(['user', 'admin'])
            ->orderBy('created_at', 'desc');

        if ($actionType) {
            $query->where('action_type', $actionType);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($adminId) {
            $query->where('admin_id', $adminId);
        }

        // Use whereDate for proper date comparison (no string concatenation)
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audit_logs_' . now()->format('Y-m-d_His') . '.csv"',
        ];

        // Use chunking to prevent memory exhaustion on large exports
        $callback = function() use ($query) {
            $file = fopen('php://output', 'w');

            // Add BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
            fputcsv($file, [
                'ID',
                'Trace ID',
                'Action Type',
                'Description',
                'User ID',
                'User Email',
                'Admin ID',
                'Admin Email',
                'IP Address',
                'Created At',
            ]);

            // Data rows - use chunk() to process in batches of 1000
            $query->chunk(1000, function ($auditLogs) use ($file) {
                foreach ($auditLogs as $log) {
                    fputcsv($file, [
                        $log->id,
                        $log->trace_id,
                        $log->action_type,
                        $log->description,
                        $log->user_id,
                        $log->user ? $log->user->email : '-',
                        $log->admin_id,
                        $log->admin ? $log->admin->email : '-',
                        $log->ip_address,
                        $log->created_at->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
