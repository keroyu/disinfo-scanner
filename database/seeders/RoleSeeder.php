<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Seed the roles table with 6 default roles.
     * Uses updateOrCreate pattern to safely add new roles without duplicates.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'name' => 'visitor',
                'display_name' => '訪客',
                'description' => 'Unregistered users with limited access',
            ],
            [
                'id' => 2,
                'name' => 'regular_member',
                'display_name' => '一般會員',
                'description' => 'Registered users with basic access',
            ],
            [
                'id' => 3,
                'name' => 'premium_member',
                'display_name' => '高級會員',
                'description' => 'Premium members with premium features',
            ],
            [
                'id' => 4,
                'name' => 'website_editor',
                'display_name' => '網站編輯',
                'description' => 'Content editors with full frontend access',
            ],
            [
                'id' => 5,
                'name' => 'administrator',
                'display_name' => '管理員',
                'description' => 'System administrators with unrestricted access',
            ],
            [
                'id' => 6,
                'name' => 'suspended',
                'display_name' => '停權中',
                'description' => 'Suspended users with no access (014-users-management-enhancement)',
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                [
                    'name' => $role['name'],
                    'display_name' => $role['display_name'],
                    'description' => $role['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✓ Seeded 6 roles: visitor, regular_member, premium_member, website_editor, administrator, suspended');
    }
}
