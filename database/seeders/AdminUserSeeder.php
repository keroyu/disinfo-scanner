<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the default admin account.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'themustbig@gmail.com');
        $password = env('ADMIN_PASSWORD', '2025Nov20');

        // Check if admin user already exists
        $existingUser = DB::table('users')->where('email', $email)->first();

        if ($existingUser) {
            $this->command->warn("⚠ Admin user {$email} already exists. Skipping.");
            return;
        }

        // Create admin user
        $userId = DB::table('users')->insertGetId([
            'name' => 'Administrator',
            'email' => $email,
            'password' => Hash::make($password),
            'is_email_verified' => true,
            'has_default_password' => false,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign administrator role
        $adminRoleId = DB::table('roles')->where('name', 'administrator')->value('id');

        if ($adminRoleId) {
            DB::table('role_user')->insert([
                'user_id' => $userId,
                'role_id' => $adminRoleId,
                'assigned_at' => now(),
                'assigned_by' => null, // System-assigned
            ]);

            $this->command->info("✓ Created admin user: {$email}");
            $this->command->info("  Password: {$password}");
            $this->command->warn("  ⚠ IMPORTANT: Change this password after first login!");
        } else {
            $this->command->error('✗ Administrator role not found. Run RoleSeeder first.');
        }
    }
}
