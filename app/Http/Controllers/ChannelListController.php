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
        $channels = Channel::with('tags')
            ->withSum('videos', 'comment_count')
            ->withCount('videos')
            ->orderBy('last_import_at', 'desc')
            ->get();

        return view('channels.list', [
            'channels' => $channels,
        ]);
    }
}
