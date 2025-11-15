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
        Schema::create('comments', function (Blueprint $table) {
            $table->string('comment_id')->primary();
            $table->string('video_id');
            $table->string('author_channel_id');
            $table->longText('text');
            $table->unsignedInteger('like_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('video_id');
            $table->index('author_channel_id');
            $table->index(['video_id', 'comment_id']);

            $table->foreign('video_id')->references('video_id')->on('videos')->onDelete('cascade');
            $table->foreign('author_channel_id')->references('author_channel_id')->on('authors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
