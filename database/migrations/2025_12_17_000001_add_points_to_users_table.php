<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * T001: Add points and premium_expires_at columns to users table
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('points')->default(0)->after('location');
            $table->timestamp('premium_expires_at')->nullable()->after('points');

            // Index for querying active premium members
            $table->index('premium_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['premium_expires_at']);
            $table->dropColumn(['points', 'premium_expires_at']);
        });
    }
};
