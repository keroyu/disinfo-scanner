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
            // Add last login IP address field (supports both IPv4 and IPv6)
            $table->string('last_login_ip', 45)->nullable()->after('youtube_api_key');
            $table->index('last_login_ip', 'idx_last_login_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_last_login_ip');
            $table->dropColumn('last_login_ip');
        });
    }
};
