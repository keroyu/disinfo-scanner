@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Comments List</h1>
        <p class="text-gray-600 mt-2">Browse and analyze all collected YouTube comments</p>
    </div>

    <!-- Search and Filter Section -->
    <form method="GET" action="{{ route('comments.index') }}" class="bg-white rounded-lg shadow-md p-6 mb-6 space-y-4">
        @php
            $canSearch = auth()->check() && (
                auth()->user()->roles->contains('name', 'premium_Member') ||
                auth()->user()->roles->contains('name', 'website_editor') ||
                auth()->user()->roles->contains('name', 'administrator')
            );
        @endphp

        @if(!$canSearch)
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start">
                    <svg class="h-5 w-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <h3 class="text-sm font-medium text-blue-900">搜尋功能需要高級會員</h3>
                        <p class="text-sm text-blue-700 mt-1">升級為高級會員即可使用留言搜尋與篩選功能。</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Search Fields Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Search Comments -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700">
                    Search Comments
                    @if(!$canSearch)
                        <span class="ml-2 text-xs text-gray-500">(需高級會員)</span>
                    @endif
                </label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="{{ request('search', '') }}"
                    placeholder="Search by video title, commenter, or content..."
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 {{ !$canSearch ? 'bg-gray-100 cursor-not-allowed' : '' }}"
                    {{ !$canSearch ? 'disabled' : '' }}
                >
            </div>

            <!-- Search Channel -->
            <div>
                <label for="search_channel" class="block text-sm font-medium text-gray-700">
                    Search Channel
                    @if(!$canSearch)
                        <span class="ml-2 text-xs text-gray-500">(需高級會員)</span>
                    @endif
                </label>
                <div class="mt-1 flex gap-2">
                    <input
                        type="text"
                        id="search_channel"
                        name="search_channel"
                        value="{{ request('search_channel', '') }}"
                        placeholder="Search by channel name..."
                        class="block w-1/2 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 {{ !$canSearch ? 'bg-gray-100 cursor-not-allowed' : '' }}"
                        {{ !$canSearch ? 'disabled' : '' }}
                    >
                    <select
                        id="channel_id"
                        name="channel_id"
                        onchange="selectChannel(this)"
                        class="block w-1/2 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 {{ !$canSearch ? 'bg-gray-100 cursor-not-allowed' : '' }}"
                        {{ !$canSearch ? 'disabled' : '' }}
                    >
                        <option value="">-- Select Channel --</option>
                        @foreach($channels as $channel)
                            <option
                                value="{{ $channel->channel_id }}"
                                data-channel-name="{{ $channel->channel_name }}"
                                {{ request('channel_id') == $channel->channel_id ? 'selected' : '' }}
                            >
                                {{ $channel->channel_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Date Range and Time Period Filter -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Date Range (From/To) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600">From</span>
                    <input
                        type="date"
                        id="from_date"
                        name="from_date"
                        value="{{ request('from_date', now()->subDays(30)->format('Y-m-d')) }}"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                    <span class="text-sm text-gray-600">To</span>
                    <input
                        type="date"
                        id="to_date"
                        name="to_date"
                        value="{{ request('to_date', now()->format('Y-m-d')) }}"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
            </div>

            <!-- Time Period Filter -->
            <div>
                <label for="time_period" class="block text-sm font-medium text-gray-700 mb-1">時間段選擇</label>
                <select
                    id="time_period"
                    name="time_period"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">-- 全時段 --</option>
                    <option value="daytime" {{ request('time_period') == 'daytime' ? 'selected' : '' }}>白天（06:00-17:59）</option>
                    <option value="evening" {{ request('time_period') == 'evening' ? 'selected' : '' }}>夜間（18:00-00:59）</option>
                    <option value="late_night" {{ request('time_period') == 'late_night' ? 'selected' : '' }}>深夜（01:00-05:59）</option>
                </select>
            </div>
        </div>

        <!-- Hidden sort parameters -->
        <input type="hidden" name="sort" id="sort" value="{{ request('sort', 'date') }}">
        <input type="hidden" name="direction" id="direction" value="{{ request('direction', 'desc') }}">

        <!-- Action Buttons -->
        <div class="flex gap-3">
            @if($canSearch)
                <button
                    type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Apply Filters
                </button>
            @else
                <button
                    type="button"
                    onclick="showUpgradeForSearchModal()"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Apply Filters
                </button>
            @endif
            <a
                href="{{ route('comments.index') }}"
                class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors"
            >
                Clear Filters
            </a>
        </div>
    </form>

    <!-- Comments Table Section -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        @if($comments->isEmpty())
            <div class="p-8 text-center text-gray-500">
                <p class="text-lg">No comments found.</p>
                <p class="text-sm mt-2">Try adjusting your search or date filters.</p>
            </div>
        @else
            <!-- Results Count (Top) -->
            <div class="px-6 py-3 border-b border-gray-200 bg-gray-50">
                <div class="text-sm text-gray-700">
                    顯示第 {{ $comments->firstItem() ?? 0 }} 至 {{ $comments->lastItem() ?? 0 }} 筆，共 {{ $comments->total() ?? 0 }} 筆留言
                </div>
            </div>

            <!-- Responsive Table Wrapper -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse comments-table">
                    <!-- Table Header -->
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 w-[100px]">
                                Channel
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 w-[200px]">
                                Video Title
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 w-[215px]">
                                Commenter
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 max-w-[400px]">
                                Comment
                            </th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 hidden md:table-cell">
                                <button
                                    type="button"
                                    onclick="handleSort('likes')"
                                    class="hover:text-blue-600 cursor-pointer"
                                >
                                    Likes
                                    @if(request('sort') === 'likes')
                                        <span class="text-xs">{{ request('direction') === 'desc' ? '▼' : '▲' }}</span>
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 hidden md:table-cell w-[160px]">
                                <button
                                    type="button"
                                    onclick="handleSort('date')"
                                    class="hover:text-blue-600 cursor-pointer"
                                >
                                    Date
                                    @if(request('sort') === 'date')
                                        <span class="text-xs">{{ request('direction') === 'desc' ? '▼' : '▲' }}</span>
                                    @endif
                                </button>
                            </th>
                        </tr>
                    </thead>

                    <!-- Table Body -->
                    <tbody class="divide-y divide-gray-200">
                        @foreach($comments as $comment)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <!-- Channel Name Cell -->
                                <td class="px-4 py-3 w-[100px]">
                                    @if($comment->video && $comment->video->channel)
                                        @php
                                            $channel = $comment->video->channel;
                                            $channelId = $channel->channel_id;
                                            $channelFromDate = now()->subYears(2)->format('Y-m-d');
                                            $channelToDate = now()->format('Y-m-d');

                                            // Get channel tags and use first tag's color if available
                                            $tags = $channel->tags();

                                            $colorMap = [
                                                'green-500' => '#10b981',
                                                'blue-500' => '#3b82f6',
                                                'red-500' => '#ef4444',
                                                'orange-500' => '#f97316',
                                                'rose-600' => '#e11d48',
                                            ];

                                            $iconColor = '#9ca3af'; // Default gray

                                            if ($tags->isNotEmpty()) {
                                                $firstTag = $tags->first();
                                                $iconColor = $colorMap[$firstTag->color] ?? '#9ca3af';
                                            } else {
                                                $colors = ['#ef4444', '#3b82f6', '#10b981', '#eab308', '#a855f7', '#ec4899', '#6366f1', '#14b8a6', '#f97316', '#06b6d4'];
                                                $colorIndex = hexdec(substr(md5($channelId), 0, 8)) % count($colors);
                                                $iconColor = $colors[$colorIndex];
                                            }
                                        @endphp
                                        <div class="flex items-center gap-1.5">
                                            <span class="inline-block w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $iconColor }};"></span>
                                            @if($canSearch)
                                                <a
                                                    href="{{ route('comments.index', [
                                                        'channel_id' => $channel->channel_id,
                                                        'from_date' => $channelFromDate,
                                                        'to_date' => $channelToDate
                                                    ]) }}"
                                                    class="text-blue-600 hover:text-blue-800 truncate text-sm"
                                                    title="View all comments from {{ $channel->channel_name }} (past 2 years)"
                                                >
                                                    {{ Str::limit($channel->channel_name, 13) }}
                                                </a>
                                            @else
                                                <span class="text-gray-700 truncate text-sm" title="{{ $channel->channel_name }}">
                                                    {{ Str::limit($channel->channel_name, 13) }}
                                                </span>
                                            @endif
                                            <a
                                                href="https://www.youtube.com/channel/{{ $channel->channel_id }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-red-600 hover:text-red-700 flex-shrink-0"
                                                title="View channel on YouTube"
                                            >
                                                <i class="fab fa-youtube"></i>
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-500">Unknown</span>
                                    @endif
                                </td>

                                <!-- Video Title Cell -->
                                <td class="px-4 py-3 w-[200px]">
                                    @php
                                        $videoTitle = $comment->video?->title ?? 'Unknown Video';
                                        $fromDate = now()->subYears(2)->format('Y-m-d');
                                        $toDate = now()->format('Y-m-d');
                                    @endphp
                                    <div class="flex items-center gap-1">
                                        @if($canSearch)
                                            <a
                                                href="{{ route('comments.index', [
                                                    'video_id' => $comment->video_id,
                                                    'from_date' => $fromDate,
                                                    'to_date' => $toDate
                                                ]) }}"
                                                class="text-blue-600 hover:text-blue-800 truncate text-sm"
                                                title="View all comments for {{ $videoTitle }} (past 2 years)"
                                            >
                                                {{ Str::limit($videoTitle, 20) }}
                                            </a>
                                        @else
                                            <span class="text-gray-700 truncate text-sm" title="{{ $videoTitle }}">
                                                {{ Str::limit($videoTitle, 20) }}
                                            </span>
                                        @endif
                                        <a
                                            href="https://www.youtube.com/watch?v={{ $comment->video_id }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-red-600 hover:text-red-700 flex-shrink-0"
                                            title="View video on YouTube"
                                        >
                                            <i class="fab fa-youtube"></i>
                                        </a>
                                    </div>
                                </td>

                                <!-- Commenter ID Cell -->
                                <td class="px-4 py-3 w-[215px]">
                                    @php
                                        $commenterName = $comment->author?->name ?? $comment->author_channel_id;
                                        $fromDate = now()->subYears(2)->format('Y-m-d');
                                        $toDate = now()->format('Y-m-d');
                                        // Check if commenter has 3+ comments on this video
                                        $commentCount = $repeatCounts[$comment->video_id][$comment->author_channel_id] ?? 1;
                                        $isRepeat = $commentCount >= 3;
                                    @endphp
                                    <div class="flex items-center gap-1">
                                        @if($canSearch)
                                            <a
                                                href="{{ route('comments.index', [
                                                    'search' => $commenterName,
                                                    'from_date' => $fromDate,
                                                    'to_date' => $toDate
                                                ]) }}"
                                                class="text-blue-600 hover:text-blue-800 text-sm"
                                                title="View all comments by {{ $commenterName }} (past 2 years)"
                                            >
                                                {{ Str::limit($commenterName, 10) }}
                                            </a>
                                            @if($isRepeat)
                                                <a
                                                    href="{{ route('comments.index', [
                                                        'search' => $commenterName,
                                                        'video_id' => $comment->video_id,
                                                        'from_date' => $fromDate,
                                                        'to_date' => $toDate
                                                    ]) }}"
                                                    class="badge-orange"
                                                    title="View all comments by {{ $commenterName }} in this video (past 2 years)"
                                                >
                                                    重複
                                                </a>
                                            @endif
                                        @else
                                            <span class="text-gray-700 text-sm" title="{{ $commenterName }}">
                                                {{ Str::limit($commenterName, 10) }}
                                            </span>
                                            @if($isRepeat)
                                                <span class="px-2 py-0.5 text-xs rounded bg-gray-200 text-gray-600" title="This user has 3+ comments on this video">
                                                    重複
                                                </span>
                                            @endif
                                        @endif
                                        <a
                                            href="https://www.youtube.com/channel/{{ $comment->author_channel_id }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-red-600 hover:text-red-700 flex-shrink-0"
                                            title="View commenter channel on YouTube"
                                        >
                                            <i class="fab fa-youtube"></i>
                                        </a>
                                    </div>
                                </td>

                                <!-- Comment Content Cell -->
                                <td class="px-4 py-3 max-w-[400px]">
                                    <div
                                        class="text-sm text-gray-700 break-words cursor-pointer hover:text-blue-600 hover:underline transition-colors"
                                        onclick="openCommentModal(this)"
                                        data-comment-id="{{ $comment->comment_id }}"
                                        data-comment-text="{{ $comment->text }}"
                                        data-author-id="{{ $comment->author_channel_id }}"
                                        data-author-name="{{ $comment->author?->name ?? $comment->author_channel_id }}"
                                        data-like-count="{{ $comment->like_count ?? 0 }}"
                                        data-published-at="{{ $comment->published_at?->setTimezone('Asia/Taipei')->format('Y-m-d H:i') ?? 'N/A' }}"
                                        data-parent-id="{{ $comment->parent_comment_id }}"
                                        title="Click to view full comment"
                                    >
                                        {{Str::limit($comment->text, 150)}}
                                    </div>
                                </td>

                                <!-- Likes Cell (Hidden on Tablet) -->
                                <td class="px-4 py-3 text-right text-sm text-gray-700 hidden md:table-cell">
                                    {{ $comment->like_count ?? 0 }}
                                </td>

                                <!-- Date Cell (Hidden on Tablet) -->
                                <td class="px-4 py-3 text-right text-sm text-gray-700 hidden md:table-cell w-[160px]">
                                    {{ $comment->published_at?->setTimezone('Asia/Taipei')->format('Y-m-d H:i') ?? 'N/A' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200 bg-white flex items-center justify-between gap-6">
                <div class="text-sm text-gray-700">
                    顯示第 {{ $comments->firstItem() ?? 0 }} 至 {{ $comments->lastItem() ?? 0 }} 筆，共 {{ $comments->total() ?? 0 }} 筆留言
                </div>
                <div class="flex gap-2 pagination-container">
                    {{ $comments->appends(request()->query())->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Comment Modal -->
<div id="commentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[80vh] flex flex-col">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">完整留言</h2>
            <button
                onclick="closeCommentModal()"
                class="text-gray-400 hover:text-gray-600 transition-colors"
                title="Close"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="flex-1 overflow-y-auto p-6">
            <!-- Parent Comment Section (shown only if this is a reply) -->
            <div id="parentCommentSection" class="hidden mb-4 pb-4 border-b border-gray-200">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span id="parentAuthorName" class="font-semibold text-gray-900 text-sm"></span>
                            <span class="text-gray-500 text-xs">•</span>
                            <span id="parentPublishedAt" class="text-gray-500 text-xs"></span>
                        </div>
                        <div id="parentCommentText" class="text-gray-700 whitespace-pre-wrap break-words text-sm leading-relaxed mb-2"></div>
                        <div class="flex items-center gap-1 text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                            </svg>
                            <span id="parentLikeCount" class="text-sm"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Replies Section (shown when there are sibling replies) -->
            <div id="repliesSection" class="hidden space-y-3">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">回覆留言（依時間排序）</h3>
                <div id="repliesContainer" class="space-y-3 ml-6">
                    <!-- Replies will be inserted here -->
                </div>
            </div>

            <!-- Current Comment Section (shown when no parent comment) -->
            <div id="currentCommentSection">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span id="currentAuthorName" class="font-semibold text-gray-900 text-sm"></span>
                            <span class="text-gray-500 text-xs">•</span>
                            <span id="currentPublishedAt" class="text-gray-500 text-xs"></span>
                        </div>
                        <div id="modalCommentText" class="text-gray-700 whitespace-pre-wrap break-words text-sm leading-relaxed mb-2"></div>
                        <div class="flex items-center gap-1 text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                            </svg>
                            <span id="currentLikeCount" class="text-sm"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="border-t border-gray-200 p-4 flex justify-end">
            <button
                onclick="closeCommentModal()"
                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors"
            >
                關閉
            </button>
        </div>
    </div>
</div>

<!-- Link to Channel List -->
<div class="mt-6 text-center">
    <a
        href="{{ route('channels.index') }}"
        class="text-blue-600 hover:text-blue-800 font-medium"
    >
        ← Back to Channel List
    </a>
</div>

<script>
function selectChannel(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const channelName = selectedOption.getAttribute('data-channel-name');

    // Update the search_channel input with the selected channel name
    if (channelName) {
        document.getElementById('search_channel').value = channelName;
    } else {
        document.getElementById('search_channel').value = '';
    }
}

function handleSort(sortKey) {
    const currentSort = document.getElementById('sort').value;
    const currentDirection = document.getElementById('direction').value;

    // If clicking the same sort column, toggle direction
    if (currentSort === sortKey) {
        document.getElementById('direction').value = currentDirection === 'desc' ? 'asc' : 'desc';
    } else {
        // If clicking a different column, set it and default to desc
        document.getElementById('sort').value = sortKey;
        document.getElementById('direction').value = 'desc';
    }

    // Submit the form
    document.querySelector('form').submit();
}

async function openCommentModal(element) {
    // Extract all comment data
    const commentId = element.getAttribute('data-comment-id');
    const commentText = element.getAttribute('data-comment-text');
    const authorName = element.getAttribute('data-author-name');
    const likeCount = element.getAttribute('data-like-count');
    const publishedAt = element.getAttribute('data-published-at');
    const parentId = element.getAttribute('data-parent-id');

    // Reset sections visibility
    document.getElementById('parentCommentSection').classList.add('hidden');
    document.getElementById('repliesSection').classList.add('hidden');
    document.getElementById('currentCommentSection').classList.add('hidden');

    // Check if this is a reply (has parent_comment_id)
    if (parentId && parentId !== 'null' && parentId !== '') {
        // Show loading state
        document.getElementById('parentCommentSection').classList.remove('hidden');
        document.getElementById('repliesSection').classList.remove('hidden');
        document.getElementById('parentCommentText').textContent = '載入中...';
        document.getElementById('repliesContainer').innerHTML = '<p class="text-sm text-gray-500">載入中...</p>';

        try {
            // Fetch comment data with parent and siblings from server
            const response = await fetch(`/api/comments/${commentId}`);
            if (!response.ok) throw new Error('Failed to fetch comment data');

            const data = await response.json();

            // Fill parent comment data
            if (data.parent) {
                document.getElementById('parentAuthorName').textContent = data.parent.author_name || '匿名用戶';
                document.getElementById('parentPublishedAt').textContent = data.parent.published_at || 'N/A';
                document.getElementById('parentCommentText').textContent = data.parent.text || '';
                document.getElementById('parentLikeCount').textContent = data.parent.like_count || '0';
            }

            // Render all sibling replies (including current comment)
            const repliesContainer = document.getElementById('repliesContainer');
            repliesContainer.innerHTML = '';

            if (data.siblings && data.siblings.length > 0) {
                data.siblings.forEach(reply => {
                    const isCurrentComment = reply.comment_id === commentId;
                    const replyDiv = document.createElement('div');
                    replyDiv.className = 'flex items-start gap-3 pb-3 border-b border-gray-100 last:border-b-0';

                    replyDiv.innerHTML = `
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-50 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-semibold ${isCurrentComment ? 'text-red-600' : 'text-gray-900'} text-sm">${reply.author_name || '匿名用戶'}</span>
                                <span class="text-gray-500 text-xs">•</span>
                                <span class="text-gray-500 text-xs">${reply.published_at || 'N/A'}</span>
                            </div>
                            <div class="whitespace-pre-wrap break-words text-sm leading-relaxed mb-2 ${isCurrentComment ? 'text-red-600 font-medium' : 'text-gray-700'}">${reply.text || ''}</div>
                            <div class="flex items-center gap-1 text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                                </svg>
                                <span class="text-sm">${reply.like_count || '0'}</span>
                            </div>
                        </div>
                    `;

                    repliesContainer.appendChild(replyDiv);
                });
            } else {
                repliesContainer.innerHTML = '<p class="text-sm text-gray-500">沒有其他回覆</p>';
            }

        } catch (error) {
            console.error('Error fetching comment data:', error);
            document.getElementById('parentCommentText').textContent = '無法載入留言資料';
            document.getElementById('repliesContainer').innerHTML = '<p class="text-sm text-red-500">載入失敗</p>';
        }
    } else {
        // This is a top-level comment (no parent)
        // Show current comment section
        document.getElementById('currentCommentSection').classList.remove('hidden');
        document.getElementById('currentAuthorName').textContent = authorName || '匿名用戶';
        document.getElementById('currentPublishedAt').textContent = publishedAt || 'N/A';
        document.getElementById('modalCommentText').textContent = commentText;
        document.getElementById('currentLikeCount').textContent = likeCount || '0';
    }

    // Show modal
    document.getElementById('commentModal').classList.remove('hidden');
}

function closeCommentModal() {
    document.getElementById('commentModal').classList.add('hidden');
}

// Close modal when clicking outside the modal content
document.addEventListener('click', function(event) {
    const modal = document.getElementById('commentModal');
    if (event.target === modal) {
        closeCommentModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCommentModal();
    }
});
</script>

<style>
/* Custom Pagination Styles for White Background */
.pagination-container nav {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

/* Hide the default English summary text */
.pagination-container nav p {
    display: none !important;
}

.pagination-container nav span,
.pagination-container nav a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.5rem;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

/* Active page */
.pagination-container nav span[aria-current="page"] {
    background-color: #3b82f6;
    color: white;
    border: 1px solid #3b82f6;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
}

/* Regular page links */
.pagination-container nav a {
    background-color: white;
    color: #4b5563;
    border: 1px solid #e5e7eb;
}

.pagination-container nav a:hover {
    background-color: #f9fafb;
    border-color: #d1d5db;
    color: #1f2937;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
}

/* Disabled state */
.pagination-container nav span[aria-disabled="true"] {
    background-color: #f9fafb;
    color: #d1d5db;
    border: 1px solid #f3f4f6;
    cursor: not-allowed;
    box-shadow: none;
}

/* Previous/Next arrows */
.pagination-container nav a svg,
.pagination-container nav span svg {
    width: 1.25rem;
    height: 1.25rem;
}
</style>

{{-- Permission Modal for Regular Members trying to use search --}}
@if(auth()->check() && auth()->user()->roles->contains('name', 'regular_member'))
    <script>
    function showUpgradeForSearchModal() {
        window.dispatchEvent(new CustomEvent('permission-modal', {
            detail: { type: 'upgrade', feature: '搜尋與篩選功能' }
        }));
    }
    </script>
@endif

@endsection
