<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to drop the api_quotas table.
 *
 * The API quota feature has been removed - Premium Members now have
 * unlimited access to Official YouTube API import without monthly limits.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('api_quotas');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('api_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('current_month', 7);
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('monthly_limit')->default(10);
            $table->boolean('is_unlimited')->default(false);
            $table->timestamp('last_import_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('current_month');
        });
    }
};
