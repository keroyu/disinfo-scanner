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
            ->orderBy('last_import_at', 'desc')
            ->get();

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
