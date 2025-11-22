<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Seed the roles table with 5 default roles.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'visitor',
                'display_name' => '訪客',
                'description' => 'Unregistered users with limited access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'regular_member',
                'display_name' => '一般會員',
                'description' => 'Registered users with basic access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'premium_member',
                'display_name' => '高級會員',
                'description' => 'Premium members with premium features',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'website_editor',
                'display_name' => '網站編輯',
                'description' => 'Content editors with full frontend access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'administrator',
                'display_name' => '管理員',
                'description' => 'System administrators with unrestricted access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('roles')->insert($roles);

        $this->command->info('✓ Seeded 5 roles: visitor, regular_member, premium_member, website_editor, administrator');
    }
}
