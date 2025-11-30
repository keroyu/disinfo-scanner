<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\RolePermissionService;

/**
 * T531: Check user permissions command
 *
 * Checks and displays all permissions for a specific user.
 * Usage: php artisan permissions:check {user_id} [--email=<email>]
 */
class PermissionsCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:check
                            {user_id? : The user ID to check}
                            {--email= : Find user by email instead of ID}
                            {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '檢查使用者權限 (Check user permissions)';

    /**
     * Execute the console command.
     */
    public function handle(RolePermissionService $rolePermissionService): int
    {
        $userId = $this->argument('user_id');
        $email = $this->option('email');
        $format = $this->option('format');

        // Find user
        if ($email) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->error("User not found with email: {$email}");
                return self::FAILURE;
            }
        } elseif ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User not found with ID: {$userId}");
                return self::FAILURE;
            }
        } else {
            $this->error('Please provide a user ID or use --email option');
            $this->info('Usage: php artisan permissions:check {user_id}');
            $this->info('       php artisan permissions:check --email=user@example.com');
            return self::FAILURE;
        }

        $this->info('');
        $this->info('=== 使用者權限檢查 (User Permission Check) ===');
        $this->info('');

        // User info
        $this->info('User Information:');
        $this->info("  ID: {$user->id}");
        $this->info("  Email: {$user->email}");
        $this->info("  Name: " . ($user->name ?? 'N/A'));
        $this->info("  Email Verified: " . ($user->email_verified_at ? 'Yes' : 'No'));
        $this->info("  YouTube API Key: " . ($user->youtube_api_key ? 'Configured' : 'Not Set'));
        $this->info('');

        // Roles
        $roles = $user->roles;
        $roleNames = $roles->pluck('name')->toArray();

        $this->info('Roles:');
        if ($roles->isEmpty()) {
            $this->warn('  No roles assigned');
        } else {
            foreach ($roles as $role) {
                $this->info("  - {$role->name} ({$role->display_name})");
            }
        }
        $this->info('');

        // Special status checks
        $this->info('Status:');
        $this->info("  Is Administrator: " . ($rolePermissionService->isAdministrator($user) ? 'Yes' : 'No'));
        $this->info("  Is Website Editor: " . ($rolePermissionService->isWebsiteEditor($user) ? 'Yes' : 'No'));
        $this->info("  Is Premium Member: " . ($rolePermissionService->isPaidMember($user) ? 'Yes' : 'No'));
        $this->info("  Is Regular Member: " . ($rolePermissionService->isRegularMember($user) ? 'Yes' : 'No'));
        $this->info('');

        // Get all permissions
        $permissions = $rolePermissionService->getUserPermissions($user);

        if ($format === 'json') {
            $data = [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'email_verified' => (bool) $user->email_verified_at,
                    'youtube_api_key_configured' => !empty($user->youtube_api_key),
                ],
                'roles' => $roleNames,
                'permissions' => $permissions,
                'status' => [
                    'is_administrator' => $rolePermissionService->isAdministrator($user),
                    'is_website_editor' => $rolePermissionService->isWebsiteEditor($user),
                    'is_premium_member' => $rolePermissionService->isPaidMember($user),
                    'is_regular_member' => $rolePermissionService->isRegularMember($user),
                ],
            ];
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        // Permissions table
        $this->info('Permissions:');
        if (empty($permissions)) {
            $this->warn('  No permissions');
        } else {
            $headers = ['Name', 'Display Name', 'Category'];
            $rows = array_map(function ($p) {
                return [$p['name'], $p['display_name'], $p['category']];
            }, $permissions);

            $this->table($headers, $rows);

            // Summary by category
            $byCategory = collect($permissions)->groupBy('category');
            $this->info('');
            $this->info('Summary:');
            $this->info('  Total: ' . count($permissions) . ' permissions');
            foreach ($byCategory as $category => $perms) {
                $this->info("  - {$category}: " . count($perms));
            }
        }

        $this->info('');

        // Quick permission tests
        // Clear cache first to ensure fresh check
        $rolePermissionService->clearUserPermissionCache($user);

        $this->info('Quick Permission Tests:');
        $testPermissions = [
            'view_channels_list' => '瀏覽頻道列表',
            'view_comments_list' => '瀏覽留言列表',
            'view_admin_panel' => '瀏覽管理後台',
            'use_search_videos' => '影片搜尋',
            'use_official_api_import' => '官方 API 匯入',
            'manage_users' => '管理使用者',
        ];

        foreach ($testPermissions as $permission => $label) {
            $hasPermission = $rolePermissionService->hasPermission($user, $permission);
            $status = $hasPermission ? '✅' : '❌';
            $this->info("  {$status} {$label} ({$permission})");
        }

        $this->info('');

        return self::SUCCESS;
    }
}
