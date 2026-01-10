<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\EscapesLikeQueries;
use App\Http\Requests\Admin\BatchRoleChangeRequest;
use App\Http\Requests\Admin\BatchEmailRequest;
use App\Http\Requests\Admin\PremiumExpiryUpdateRequest;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use App\Services\IpGeolocationService;
use App\Services\BatchRoleService;
use App\Services\BatchEmailService;
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
        $perPage = $request->input('per_page', 50);
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
     * T218: Get user details including roles
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function show(int $userId): JsonResponse
    {
        // Find user with relationships
        $user = User::with(['roles'])->find($userId);

        if (!$user) {
            return response()->json([
                'message' => '找不到指定的使用者'
            ], 404);
        }

        // Get IP geolocation data
        $ipGeoService = app(IpGeolocationService::class);
        $ipLocation = $user->last_login_ip
            ? $ipGeoService->getLocation($user->last_login_ip)
            : null;

        // Transform response
        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_email_verified' => $user->is_email_verified,
                'has_default_password' => $user->has_default_password,
                'youtube_api_key' => $user->youtube_api_key ? '已設定' : null,
                'last_login_ip' => $user->last_login_ip,
                'last_login_ip_country' => $ipLocation['country'] ?? null,
                'last_login_ip_city' => $ipLocation['city'] ?? null,
                'points' => $user->points ?? 0,
                'premium_expires_at' => $user->premium_expires_at?->toIso8601String(),
                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                    ];
                }),
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

    /**
     * T013: Batch change role for multiple users (014-users-management-enhancement)
     *
     * @param BatchRoleChangeRequest $request
     * @return JsonResponse
     */
    public function batchChangeRole(BatchRoleChangeRequest $request): JsonResponse
    {
        $userIds = $request->validated()['user_ids'];
        $roleId = $request->validated()['role_id'];

        $batchRoleService = app(BatchRoleService::class);
        $result = $batchRoleService->changeRoles($userIds, $roleId, auth()->id());

        if ($result['updated_count'] === 0 && $result['skipped_self'] === 0) {
            return response()->json([
                'success' => false,
                'message' => '沒有用戶被更新',
            ], 400);
        }

        $message = sprintf('已成功變更 %d 位用戶的角色', $result['updated_count']);
        if ($result['skipped_self'] > 0) {
            $message .= sprintf('（跳過 %d 位：無法更改自己的角色）', $result['skipped_self']);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result,
        ]);
    }

    /**
     * T021: Batch send email to multiple users (014-users-management-enhancement)
     *
     * @param BatchEmailRequest $request
     * @return JsonResponse
     */
    public function batchSendEmail(BatchEmailRequest $request): JsonResponse
    {
        $userIds = $request->validated()['user_ids'];
        $subject = $request->validated()['subject'];
        $body = $request->validated()['body'];

        $batchEmailService = app(BatchEmailService::class);
        $result = $batchEmailService->send($userIds, $subject, $body, auth()->id());

        if ($result['sent_count'] === 0) {
            return response()->json([
                'success' => false,
                'message' => '郵件發送失敗',
                'data' => $result,
            ], 500);
        }

        if ($result['failed_count'] > 0) {
            $message = sprintf('已發送 %d/%d 封郵件，%d 封失敗',
                $result['sent_count'],
                $result['total_recipients'],
                $result['failed_count']
            );
        } else {
            $message = sprintf('已成功發送 %d 封郵件', $result['sent_count']);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result,
        ]);
    }

    /**
     * T026: Update premium expiry date for a user (014-users-management-enhancement)
     *
     * @param PremiumExpiryUpdateRequest $request
     * @param int $userId
     * @return JsonResponse
     */
    public function updatePremiumExpiry(PremiumExpiryUpdateRequest $request, int $userId): JsonResponse
    {
        $user = User::with('roles')->find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '找不到指定的使用者',
            ], 404);
        }

        // Check if user is a Premium Member
        $isPremiumMember = $user->roles->contains(function ($role) {
            return $role->name === 'premium_member';
        });

        if (!$isPremiumMember) {
            return response()->json([
                'success' => false,
                'message' => '此用戶不是高級會員',
            ], 400);
        }

        $oldExpiry = $user->premium_expires_at;
        $newExpiry = \Carbon\Carbon::parse($request->validated()['premium_expires_at']);

        // Update the expiry date
        $user->premium_expires_at = $newExpiry;
        $user->save();

        // Log the change
        AuditLog::log(
            actionType: 'premium_expiry_extended',
            description: sprintf(
                'Admin %s (%s) 將用戶 %s (%s) 的高級會員到期日從 %s 延長至 %s',
                auth()->user()->name,
                auth()->user()->email,
                $user->name,
                $user->email,
                $oldExpiry ? $oldExpiry->timezone('Asia/Taipei')->format('Y-m-d H:i') : 'N/A',
                $newExpiry->timezone('Asia/Taipei')->format('Y-m-d H:i')
            ),
            userId: $user->id,
            adminId: auth()->id(),
            resourceType: 'user',
            resourceId: $user->id,
            changes: [
                'old_premium_expires_at' => $oldExpiry?->toIso8601String(),
                'new_premium_expires_at' => $newExpiry->toIso8601String(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '高級會員到期日已更新',
            'data' => [
                'user_id' => $user->id,
                'email' => $user->email,
                'premium_expires_at' => $newExpiry->toIso8601String(),
                'premium_expires_at_display' => $newExpiry->timezone('Asia/Taipei')->format('Y-m-d H:i') . ' (GMT+8)',
            ],
        ]);
    }

    /**
     * T058: Batch suspend multiple users (014-users-management-enhancement)
     *
     * This is a convenience endpoint that suspends users by changing their role to 'suspended'.
     * It's equivalent to calling batchChangeRole with role_id=6.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function batchSuspend(Request $request): JsonResponse
    {
        // Validate request
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'required|integer|exists:users,id',
        ], [
            'user_ids.required' => '請先選擇用戶',
            'user_ids.array' => '用戶列表格式錯誤',
            'user_ids.min' => '請先選擇用戶',
            'user_ids.max' => '批次操作最多只能處理 100 位用戶',
            'user_ids.*.exists' => '部分用戶不存在',
        ]);

        $userIds = $validated['user_ids'];

        // Get the suspended role ID (id=6)
        $suspendedRole = Role::where('name', 'suspended')->first();
        if (!$suspendedRole) {
            return response()->json([
                'success' => false,
                'message' => '停權角色不存在，請聯繫系統管理員',
            ], 500);
        }

        $batchRoleService = app(BatchRoleService::class);
        $result = $batchRoleService->changeRoles($userIds, $suspendedRole->id, auth()->id());

        // Handle case where no users were suspended
        if ($result['updated_count'] === 0) {
            if ($result['skipped_self'] > 0 && $result['skipped_already_suspended'] === 0) {
                return response()->json([
                    'success' => false,
                    'message' => '無法停權自己的帳號',
                ], 400);
            }
            if ($result['skipped_already_suspended'] > 0 && $result['skipped_self'] === 0) {
                return response()->json([
                    'success' => true,
                    'message' => '所選用戶已是停權狀態',
                    'data' => $result,
                ]);
            }
            return response()->json([
                'success' => false,
                'message' => '沒有用戶被停權',
            ], 400);
        }

        // Build success message
        $message = sprintf('已成功停權 %d 位用戶', $result['updated_count']);
        $skipped = [];
        if ($result['skipped_self'] > 0) {
            $skipped[] = sprintf('%d 位：無法停權自己的帳號', $result['skipped_self']);
        }
        if ($result['skipped_already_suspended'] > 0) {
            $skipped[] = sprintf('%d 位：已是停權狀態', $result['skipped_already_suspended']);
        }
        if (!empty($skipped)) {
            $message .= '（跳過 ' . implode('、', $skipped) . '）';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'total_requested' => $result['total_requested'],
                'suspended_count' => $result['updated_count'],
                'skipped_self' => $result['skipped_self'],
                'skipped_already_suspended' => $result['skipped_already_suspended'],
                'sessions_terminated' => $result['sessions_terminated'],
            ],
        ]);
    }
}
