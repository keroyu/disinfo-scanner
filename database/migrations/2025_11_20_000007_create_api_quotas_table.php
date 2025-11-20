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
        Schema::create('api_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade')->comment('User ID');
            $table->string('current_month', 7)->index()->comment('Month in format YYYY-MM');
            $table->unsignedInteger('usage_count')->default(0)->comment('Number of imports used this month');
            $table->unsignedInteger('monthly_limit')->default(10)->comment('Monthly import limit (10 or NULL for unlimited)');
            $table->boolean('is_unlimited')->default(false)->comment('True if identity verified (unlimited access)');
            $table->timestamp('last_import_at')->nullable()->comment('Last import timestamp (UTC)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_quotas');
    }
};
