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
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade')->comment('User ID');
            $table->string('verification_method', 50)->comment('Verification method (email, id_card, phone, etc.)');
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending')->index()->comment('Verification status');
            $table->timestamp('submitted_at')->useCurrent()->comment('Submission time (UTC)');
            $table->timestamp('reviewed_at')->nullable()->comment('Review completion time (UTC)');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null')->comment('Admin who reviewed');
            $table->text('notes')->nullable()->comment('Review notes or rejection reason');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');
    }
};
