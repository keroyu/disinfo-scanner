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
        Schema::create('payment_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('portaly_product_id', 100)->unique();
            $table->string('portaly_url', 500);
            $table->unsignedInteger('price');
            $table->string('currency', 3)->default('TWD');
            $table->unsignedInteger('duration_days')->nullable();
            $table->string('action_type', 50)->default('extend_premium');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_products');
    }
};
