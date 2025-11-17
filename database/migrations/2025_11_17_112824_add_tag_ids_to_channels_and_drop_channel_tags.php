<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add tag_ids column to channels table
        Schema::table('channels', function (Blueprint $table) {
            $table->string('tag_ids')->nullable()->after('channel_name');
        });

        // Step 2: Migrate data from channel_tags to channels.tag_ids
        $channels = DB::table('channels')->get();
        foreach ($channels as $channel) {
            $tagIds = DB::table('channel_tags')
                ->where('channel_id', $channel->channel_id)
                ->pluck('tag_id')
                ->toArray();

            if (!empty($tagIds)) {
                DB::table('channels')
                    ->where('channel_id', $channel->channel_id)
                    ->update(['tag_ids' => implode(',', $tagIds)]);
            }
        }

        // Step 3: Drop channel_tags table
        Schema::dropIfExists('channel_tags');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Recreate channel_tags table
        Schema::create('channel_tags', function (Blueprint $table) {
            $table->string('channel_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['channel_id', 'tag_id']);
            $table->foreign('channel_id')->references('channel_id')->on('channels')->onDelete('cascade');
            $table->foreign('tag_id')->references('tag_id')->on('tags')->onDelete('cascade');
        });

        // Step 2: Migrate data back from channels.tag_ids to channel_tags
        $channels = DB::table('channels')->whereNotNull('tag_ids')->get();
        foreach ($channels as $channel) {
            $tagIds = explode(',', $channel->tag_ids);
            foreach ($tagIds as $tagId) {
                DB::table('channel_tags')->insert([
                    'channel_id' => $channel->channel_id,
                    'tag_id' => (int) trim($tagId),
                    'created_at' => now(),
                ]);
            }
        }

        // Step 3: Remove tag_ids column from channels
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('tag_ids');
        });
    }
};
