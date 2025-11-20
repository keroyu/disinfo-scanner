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
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->index()->comment('Email address to verify');
            $table->string('token', 255)->unique()->comment('SHA-256 hashed verification token');
            $table->timestamp('created_at')->useCurrent()->index()->comment('Token creation time (UTC)');
            $table->timestamp('used_at')->nullable()->comment('Token usage time (UTC, NULL if unused)');
            $table->timestamp('expires_at')->index()->comment('Token expiration time (UTC, created_at + 24 hours)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
    }
};
