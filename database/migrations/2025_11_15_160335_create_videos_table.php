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
        Schema::create('videos', function (Blueprint $table) {
            $table->string('video_id')->primary();
            $table->string('channel_id');
            $table->string('title')->nullable();
            $table->string('youtube_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('channel_id');
            $table->foreign('channel_id')->references('channel_id')->on('channels')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
