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
        Schema::create('channel_tags', function (Blueprint $table) {
            $table->string('channel_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['channel_id', 'tag_id']);

            $table->foreign('channel_id')->references('channel_id')->on('channels')->onDelete('cascade');
            $table->foreign('tag_id')->references('tag_id')->on('tags')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_tags');
    }
};
