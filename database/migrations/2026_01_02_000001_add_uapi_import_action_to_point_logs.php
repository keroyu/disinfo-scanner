<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * T106: Add 'uapi_import' action type to point_logs table for U-API imports
     */
    public function up(): void
    {
        // For MySQL: Modify enum to add new value
        // For SQLite: Need to recreate the column since SQLite doesn't support MODIFY
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE point_logs MODIFY COLUMN action ENUM('report', 'redeem', 'uapi_import') NOT NULL");
        } else {
            // For SQLite (used in testing), we need to:
            // 1. Create a new temporary table
            // 2. Copy data
            // 3. Drop old table
            // 4. Rename new table
            Schema::table('point_logs', function (Blueprint $table) {
                // Change enum to string to allow any action type
                // This is more flexible for future action types
            });

            // SQLite approach: change to VARCHAR
            DB::statement('CREATE TABLE point_logs_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                amount INTEGER NOT NULL,
                action VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )');

            DB::statement('INSERT INTO point_logs_new (id, user_id, amount, action, created_at)
                SELECT id, user_id, amount, action, created_at FROM point_logs');

            DB::statement('DROP TABLE point_logs');

            DB::statement('ALTER TABLE point_logs_new RENAME TO point_logs');

            // Recreate indexes
            DB::statement('CREATE INDEX point_logs_user_id_index ON point_logs (user_id)');
            DB::statement('CREATE INDEX point_logs_created_at_index ON point_logs (created_at)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Remove 'uapi_import' from enum (will fail if data exists with this value)
            DB::statement("ALTER TABLE point_logs MODIFY COLUMN action ENUM('report', 'redeem') NOT NULL");
        } else {
            // For SQLite, recreate with original enum constraint
            DB::statement('CREATE TABLE point_logs_old (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                amount INTEGER NOT NULL,
                action TEXT CHECK (action IN (\'report\', \'redeem\')) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )');

            DB::statement('INSERT INTO point_logs_old (id, user_id, amount, action, created_at)
                SELECT id, user_id, amount, action, created_at FROM point_logs WHERE action IN (\'report\', \'redeem\')');

            DB::statement('DROP TABLE point_logs');

            DB::statement('ALTER TABLE point_logs_old RENAME TO point_logs');

            // Recreate indexes
            DB::statement('CREATE INDEX point_logs_user_id_index ON point_logs (user_id)');
            DB::statement('CREATE INDEX point_logs_created_at_index ON point_logs (created_at)');
        }
    }
};
