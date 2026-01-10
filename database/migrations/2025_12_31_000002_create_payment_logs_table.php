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
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 100)->unique();
            $table->enum('event_type', ['paid', 'refund']);
            $table->foreignId('product_id')->nullable()->constrained('payment_products')->nullOnDelete();
            $table->string('portaly_product_id', 100)->nullable()->index();
            $table->string('customer_email', 255)->index();
            $table->string('customer_name', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('TWD');
            $table->unsignedInteger('net_total')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('status', 50)->index();
            $table->json('raw_payload');
            $table->char('trace_id', 36)->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
