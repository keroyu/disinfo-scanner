<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add email verification flag
            $table->boolean('is_email_verified')->default(false)->after('email_verified_at');
            $table->index('is_email_verified', 'idx_email_verified');

            // Add default password flag for mandatory password change
            $table->boolean('has_default_password')->default(true)->after('password');
            $table->index('has_default_password', 'idx_default_password');

            // Add last password change tracking
            $table->timestamp('last_password_change_at')->nullable()->after('has_default_password');

            // Add YouTube API key field
            $table->string('youtube_api_key', 255)->nullable()->after('last_password_change_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_email_verified');
            $table->dropColumn('is_email_verified');

            $table->dropIndex('idx_default_password');
            $table->dropColumn('has_default_password');

            $table->dropColumn('last_password_change_at');
            $table->dropColumn('youtube_api_key');
        });
    }
};
