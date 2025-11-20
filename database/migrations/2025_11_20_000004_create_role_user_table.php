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
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('User ID');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade')->comment('Role ID');
            $table->timestamp('assigned_at')->useCurrent()->comment('Role assignment time (UTC)');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null')->comment('Admin who assigned role');

            // Unique constraint: user can have each role only once
            $table->unique(['user_id', 'role_id']);

            // Indexes for performance
            $table->index('user_id');
            $table->index('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
