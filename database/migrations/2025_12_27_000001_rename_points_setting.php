<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T070: Rename setting key from point_redemption_days to points_per_day
 *
 * This migration changes the redemption model from:
 *   "10 points = N days" to "X points = 1 day"
 *
 * The setting key changes from:
 *   point_redemption_days (days granted per redemption)
 * to:
 *   points_per_day (points required per day of extension)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing setting key
        DB::table('settings')
            ->where('key', 'point_redemption_days')
            ->update([
                'key' => 'points_per_day',
                'value' => '10', // Reset to default since meaning changed
                'updated_at' => now(),
            ]);

        // If no setting exists (fresh install), create one
        $exists = DB::table('settings')
            ->where('key', 'points_per_day')
            ->exists();

        if (!$exists) {
            DB::table('settings')->insert([
                'key' => 'points_per_day',
                'value' => '10',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'points_per_day')
            ->update([
                'key' => 'point_redemption_days',
                'value' => '3', // Restore old default
                'updated_at' => now(),
            ]);
    }
};
