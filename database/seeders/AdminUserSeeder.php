<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the default admin account.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'themustbig@gmail.com');

        // Check if admin user already exists
        $existingUser = DB::table('users')->where('email', $email)->first();

        if ($existingUser) {
            $this->command->warn("⚠ Admin user {$email} already exists. Skipping.");
            return;
        }

        // Create admin user (no password — uses OTP login)
        $userId = DB::table('users')->insertGetId([
            'name' => 'Administrator',
            'email' => $email,
            'is_email_verified' => true,
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
            $this->command->info("  Login via OTP sent to this email address.");
        } else {
            $this->command->error('✗ Administrator role not found. Run RoleSeeder first.');
        }
    }
}
