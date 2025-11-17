@extends('layouts.app')

@section('title', '已匯入頻道列表')

@section('content')
<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">已匯入頻道列表</h1>
        <p class="text-gray-600">共 {{ count($channels) }} 個頻道</p>
    </div>

    @if($channels->isEmpty())
        <!-- Empty State -->
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <p class="text-gray-500 text-lg">尚未匯入任何頻道</p>
            <a href="/" class="mt-4 inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                開始匯入
            </a>
        </div>
    @else
        <!-- Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200 sticky top-0">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 w-1/4">頻道 ID</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 w-1/4">頻道名稱</th>
                            <th class="px-6 py-3 text-center text-sm font-semibold text-gray-900 w-1/4">標籤</th>
                            <th class="px-6 py-3 text-right text-sm font-semibold text-gray-900 whitespace-nowrap min-w-24">影片數</th>
                            <th class="px-6 py-3 text-right text-sm font-semibold text-gray-900 whitespace-nowrap min-w-24">留言數</th>
                            <th class="px-6 py-3 text-center text-sm font-semibold text-gray-900 whitespace-nowrap">最後匯入時間</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($channels as $index => $channel)
                            <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50 transition">
                                <!-- Channel ID -->
                                <td class="px-6 py-4">
                                    <code class="text-sm bg-gray-100 px-2 py-1 rounded">{{ $channel->channel_id }}</code>
                                </td>

                                <!-- Channel Name -->
                                <td class="px-6 py-4">
                                    <a href="https://www.youtube.com/channel/{{ $channel->channel_id }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="text-blue-600 hover:text-blue-800"
                                       title="{{ $channel->channel_name }}">
                                        {{ $channel->channel_name ?: '(未命名)' }}
                                    </a>
                                </td>

                                <!-- Tags -->
                                <td class="px-6 py-4">
                                    <div class="flex justify-center flex-wrap gap-2">
                                        @forelse($channel->tags() as $tag)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-white text-sm font-medium"
                                                  style="background-color: {{ getTagColor($tag->color) }}">
                                                {{ $tag->name }}
                                            </span>
                                        @empty
                                            <span class="text-gray-400 text-sm">無標籤</span>
                                        @endforelse
                                    </div>
                                </td>

                                <!-- Video Count -->
                                <td class="px-6 py-4 text-right">
                                    <span class="text-gray-900 font-medium">{{ number_format($channel->videos_count ?? 0) }}</span>
                                </td>

                                <!-- Comment Count -->
                                <td class="px-6 py-4 text-right">
                                    <span class="text-gray-900 font-medium">{{ number_format($channel->videos_sum_comment_count ?? 0) }}</span>
                                </td>

                                <!-- Last Import Time -->
                                <td class="px-6 py-4 text-center">
                                    <span class="text-gray-600 text-sm">
                                        @if($channel->last_import_at)
                                            <span title="{{ $channel->last_import_at->format('Y-m-d H:i:s') }}">
                                                {{ $channel->last_import_at->diffForHumans() }}
                                            </span>
                                        @else
                                            未知
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info Footer -->
        <div class="mt-6 text-center">
            <a href="/" class="text-blue-600 hover:text-blue-700">← 返回匯入</a>
        </div>
    @endif
</div>

<style>
.tag-green { background-color: #10b981; }
.tag-blue { background-color: #3b82f6; }
.tag-red { background-color: #ef4444; }
.tag-orange { background-color: #f97316; }
.tag-rose { background-color: #e11d48; }
</style>

<script>
// Helper function to get tag color
function getTagColor(colorClass) {
    const colorMap = {
        'green-500': '#10b981',
        'blue-500': '#3b82f6',
        'red-500': '#ef4444',
        'orange-500': '#f97316',
        'rose-600': '#e11d48'
    };
    return colorMap[colorClass] || '#6b7280';
}
</script>
@endsection

<?php
// Helper function for blade
function getTagColor($colorClass) {
    $colorMap = [
        'green-500' => '#10b981',
        'blue-500' => '#3b82f6',
        'red-500' => '#ef4444',
        'orange-500' => '#f97316',
        'rose-600' => '#e11d48'
    ];
    return $colorMap[$colorClass] ?? '#6b7280';
}
?>
