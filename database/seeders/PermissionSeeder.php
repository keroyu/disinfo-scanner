<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the permissions table with default permissions.
     */
    public function run(): void
    {
        $permissions = [
            // Page Access
            ['name' => 'view_home', 'display_name' => 'View Home Page', 'category' => 'pages'],
            ['name' => 'view_channels_list', 'display_name' => 'View Channels List', 'category' => 'pages'],
            ['name' => 'view_videos_list', 'display_name' => 'View Videos List', 'category' => 'pages'],
            ['name' => 'view_comments_list', 'display_name' => 'View Comments List', 'category' => 'pages'],
            ['name' => 'view_admin_panel', 'display_name' => 'View Admin Panel', 'category' => 'pages'],

            // Feature Access
            ['name' => 'use_search_videos', 'display_name' => 'Use Videos Search', 'category' => 'features'],
            ['name' => 'use_search_comments', 'display_name' => 'Use Comments Search', 'category' => 'features'],
            ['name' => 'use_video_analysis', 'display_name' => 'Use Video Analysis', 'category' => 'features'],
            ['name' => 'use_video_update', 'display_name' => 'Use Video Update', 'category' => 'features'],
            ['name' => 'use_u_api_import', 'display_name' => 'Use U-API Import', 'category' => 'features'],
            ['name' => 'use_official_api_import', 'display_name' => 'Use Official API Import', 'category' => 'features'],

            // Actions
            ['name' => 'manage_users', 'display_name' => 'Manage Users', 'category' => 'actions'],
            ['name' => 'manage_permissions', 'display_name' => 'Manage Permissions', 'category' => 'actions'],
            ['name' => 'change_password', 'display_name' => 'Change Password', 'category' => 'actions'],
        ];

        $now = now();
        foreach ($permissions as &$permission) {
            $permission['created_at'] = $now;
            $permission['updated_at'] = $now;
        }

        DB::table('permissions')->insert($permissions);

        $this->command->info('✓ Seeded ' . count($permissions) . ' permissions across 3 categories: pages, features, actions');
    }
}
