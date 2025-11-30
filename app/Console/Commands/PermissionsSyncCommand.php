<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\Permission;

/**
 * T532: Sync permissions command
 *
 * Synchronizes permissions from seeders to ensure database is up to date.
 * Usage: php artisan permissions:sync [--force] [--dry-run]
 */
class PermissionsSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync
                            {--force : Skip confirmation prompts}
                            {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步權限資料 (Sync permissions from seeders)';

    /**
     * Permission mappings from PermissionRoleMappingSeeder
     */
    private array $rolePermissions = [
        'visitor' => [
            'view_home',
            'view_videos_list',
            'use_video_analysis',
        ],
        'regular_member' => [
            'view_home',
            'view_videos_list',
            'view_channels_list',
            'view_comments_list',
            'use_video_analysis',
            'use_video_update',
            'use_u_api_import',
            'change_password',
        ],
        'premium_member' => [
            'view_home',
            'view_videos_list',
            'view_channels_list',
            'view_comments_list',
            'use_video_analysis',
            'use_video_update',
            'use_u_api_import',
            'use_official_api_import',
            'use_search_videos',
            'use_search_comments',
            'change_password',
        ],
        'website_editor' => [
            'view_home',
            'view_videos_list',
            'view_channels_list',
            'view_comments_list',
            'use_video_analysis',
            'use_video_update',
            'use_u_api_import',
            'use_official_api_import',
            'use_search_videos',
            'use_search_comments',
            'change_password',
        ],
        'administrator' => [
            'view_home',
            'view_videos_list',
            'view_channels_list',
            'view_comments_list',
            'view_admin_panel',
            'use_video_analysis',
            'use_video_update',
            'use_u_api_import',
            'use_official_api_import',
            'use_search_videos',
            'use_search_comments',
            'change_password',
            'manage_users',
            'manage_permissions',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info('');
        $this->info('=== 權限同步 (Permissions Sync) ===');
        $this->info('');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode - no changes will be made');
            $this->info('');
        }

        // Load roles and permissions
        $roles = Role::all()->keyBy('name');
        $permissions = Permission::all()->keyBy('name');

        if ($roles->isEmpty()) {
            $this->error('No roles found. Please run: php artisan db:seed --class=RoleSeeder');
            return self::FAILURE;
        }

        if ($permissions->isEmpty()) {
            $this->error('No permissions found. Please run: php artisan db:seed --class=PermissionSeeder');
            return self::FAILURE;
        }

        $this->info("Found {$roles->count()} roles and {$permissions->count()} permissions");
        $this->info('');

        // Analyze changes
        $changes = [
            'add' => [],
            'remove' => [],
        ];

        foreach ($this->rolePermissions as $roleName => $expectedPermissions) {
            $role = $roles->get($roleName);
            if (!$role) {
                $this->warn("Role not found: {$roleName}");
                continue;
            }

            // Get current permissions for role
            $currentPermissions = DB::table('permission_role')
                ->where('role_id', $role->id)
                ->pluck('permission_id')
                ->toArray();

            $currentPermissionNames = Permission::whereIn('id', $currentPermissions)
                ->pluck('name')
                ->toArray();

            // Find permissions to add
            $toAdd = array_diff($expectedPermissions, $currentPermissionNames);
            foreach ($toAdd as $permissionName) {
                $permission = $permissions->get($permissionName);
                if ($permission) {
                    $changes['add'][] = [
                        'role' => $roleName,
                        'permission' => $permissionName,
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                    ];
                } else {
                    $this->warn("Permission not found: {$permissionName}");
                }
            }

            // Find permissions to remove (not in expected list)
            $toRemove = array_diff($currentPermissionNames, $expectedPermissions);
            foreach ($toRemove as $permissionName) {
                $permission = $permissions->get($permissionName);
                if ($permission) {
                    $changes['remove'][] = [
                        'role' => $roleName,
                        'permission' => $permissionName,
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                    ];
                }
            }
        }

        // Report changes
        $totalChanges = count($changes['add']) + count($changes['remove']);

        if ($totalChanges === 0) {
            $this->info('✅ All permissions are already in sync');
            return self::SUCCESS;
        }

        $this->info("Changes to apply: {$totalChanges}");
        $this->info('');

        // Show additions
        if (!empty($changes['add'])) {
            $this->info('Permissions to ADD:');
            foreach ($changes['add'] as $change) {
                $this->info("  + {$change['role']} → {$change['permission']}");
            }
            $this->info('');
        }

        // Show removals
        if (!empty($changes['remove'])) {
            $this->warn('Permissions to REMOVE:');
            foreach ($changes['remove'] as $change) {
                $this->warn("  - {$change['role']} → {$change['permission']}");
            }
            $this->info('');
        }

        // Dry run - stop here
        if ($dryRun) {
            $this->info('Dry run complete. Use without --dry-run to apply changes.');
            return self::SUCCESS;
        }

        // Confirm
        if (!$force) {
            if (!$this->confirm('Do you want to apply these changes?')) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        // Apply changes
        $this->info('Applying changes...');

        DB::beginTransaction();
        try {
            // Add new permissions
            foreach ($changes['add'] as $change) {
                DB::table('permission_role')->insert([
                    'role_id' => $change['role_id'],
                    'permission_id' => $change['permission_id'],
                    'created_at' => now(),
                ]);
                $this->info("  ✅ Added: {$change['role']} → {$change['permission']}");
            }

            // Remove old permissions
            foreach ($changes['remove'] as $change) {
                DB::table('permission_role')
                    ->where('role_id', $change['role_id'])
                    ->where('permission_id', $change['permission_id'])
                    ->delete();
                $this->info("  ✅ Removed: {$change['role']} → {$change['permission']}");
            }

            DB::commit();
            $this->info('');
            $this->info('✅ Permissions synced successfully');

            // Clear cache reminder
            $this->info('');
            $this->warn('Note: User permission caches may need to be cleared.');
            $this->info('Run: php artisan cache:clear');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to sync permissions: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('');

        return self::SUCCESS;
    }
}
