<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\Permission;

/**
 * Seeder to map permissions to roles based on the RBAC specification.
 *
 * Role hierarchy (from least to most privileged):
 * 1. Visitor - view Home, Videos List, video analysis only
 * 2. Regular Member - + Channels List, Comments List, U-API import, video update (with API key)
 * 3. Premium Member - + Official API import (with quota), search features
 * 4. Website Editor - all frontend features
 * 5. Administrator - all features (including admin panel)
 */
class PermissionRoleMappingSeeder extends Seeder
{
    /**
     * Permission mappings for each role.
     * Each role inherits permissions from lower roles (except visitor which is special).
     */
    private array $rolePermissions = [
        // T404: Visitor role - minimal access for unregistered users
        'visitor' => [
            'view_home',
            'view_videos_list',
            'use_video_analysis',
        ],

        // T405: Regular Member - authenticated user basic access
        'regular_member' => [
            'view_home',
            'view_videos_list',
            'view_channels_list',
            'view_comments_list',
            'use_video_analysis',
            'use_video_update',      // Requires YouTube API key to be configured
            'use_u_api_import',
            'change_password',
        ],

        // T406: Premium Member - premium features with quota limits
        'premium_member' => [
            'view_home',
            'view_videos_list',
            'view_channels_list',
            'view_comments_list',
            'use_video_analysis',
            'use_video_update',
            'use_u_api_import',
            'use_official_api_import', // Limited to 10/month unless identity verified
            'use_search_videos',
            'use_search_comments',
            'change_password',
        ],

        // T407: Website Editor - full frontend access
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

        // T408: Administrator - unrestricted access to all features
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
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting permission-role mapping...');

        // Get all roles and permissions
        $roles = Role::all()->keyBy('name');
        $permissions = Permission::all()->keyBy('name');

        if ($roles->isEmpty()) {
            $this->command->error('No roles found. Please run RoleSeeder first.');
            return;
        }

        if ($permissions->isEmpty()) {
            $this->command->error('No permissions found. Please run PermissionSeeder first.');
            return;
        }

        $mappingsCreated = 0;
        $mappingsSkipped = 0;

        foreach ($this->rolePermissions as $roleName => $permissionNames) {
            $role = $roles->get($roleName);

            if (!$role) {
                $this->command->warn("Role '{$roleName}' not found, skipping...");
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                $permission = $permissions->get($permissionName);

                if (!$permission) {
                    $this->command->warn("Permission '{$permissionName}' not found, skipping...");
                    continue;
                }

                // Check if mapping already exists
                $exists = DB::table('permission_role')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $permission->id)
                    ->exists();

                if ($exists) {
                    $mappingsSkipped++;
                    continue;
                }

                // Create the mapping
                DB::table('permission_role')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                    'created_at' => now(),
                ]);

                $mappingsCreated++;
            }

            $this->command->info("✓ Mapped " . count($permissionNames) . " permissions to role: {$roleName}");
        }

        $this->command->info("Permission-role mapping complete.");
        $this->command->info("  - Created: {$mappingsCreated} new mappings");
        $this->command->info("  - Skipped: {$mappingsSkipped} existing mappings");
    }
}
