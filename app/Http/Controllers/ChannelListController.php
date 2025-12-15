<?php

namespace App\Http\Controllers;

use App\Models\Channel;

class ChannelListController extends Controller
{
    /**
     * GET /channels - Get all imported channels with tags
     */
    public function index()
    {
        $channels = Channel::withSum('videos', 'comment_count')
            ->withCount('videos')
            ->selectRaw('channels.*, (SELECT MAX(published_at) FROM videos WHERE videos.channel_id = channels.channel_id) as latest_video_published_at')
            ->orderBy('last_import_at', 'desc')
            ->paginate(100);

        // Load tags for each channel (tags() is now a method, not a relationship)
        // No need for eager loading since we fetch tags on-demand in the view

        $breadcrumbs = [
            ['label' => '首頁', 'url' => route('import.index')],
            ['label' => '頻道列表'],
        ];

        return view('channels.list', [
            'channels' => $channels,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
