@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6 flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Comments List</h1>
            <p class="text-gray-600 mt-2">Browse and analyze all collected YouTube comments</p>
        </div>
        <div class="flex gap-3">
            <button type="button" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700" onclick="window.dispatchEvent(new CustomEvent('open-import-modal'))">
                <i class="fab fa-youtube mr-2"></i>官方API導入
            </button>
            <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700" onclick="window.dispatchEvent(new CustomEvent('open-uapi-modal'))">
                <i class="fas fa-upload mr-2"></i>U-API導入
            </button>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <form method="GET" action="{{ route('comments.index') }}" class="bg-white rounded-lg shadow-md p-6 mb-6 space-y-4">
        <!-- Search Fields Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Search Comments -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700">Search Comments</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="{{ request('search', '') }}"
                    placeholder="Search by video title, commenter, or content..."
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                >
            </div>

            <!-- Search Channel -->
            <div>
                <label for="search_channel" class="block text-sm font-medium text-gray-700">Search Channel</label>
                <div class="mt-1 flex gap-2">
                    <input
                        type="text"
                        id="search_channel"
                        name="search_channel"
                        value="{{ request('search_channel', '') }}"
                        placeholder="Search by channel name..."
                        class="block w-1/2 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                    <select
                        id="channel_id"
                        name="channel_id"
                        onchange="selectChannel(this)"
                        class="block w-1/2 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
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

        <!-- Date Range Filter (Default: Last 30 Days) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="from_date" class="block text-sm font-medium text-gray-700">From Date</label>
                <input
                    type="date"
                    id="from_date"
                    name="from_date"
                    value="{{ request('from_date', now()->subDays(30)->format('Y-m-d')) }}"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                >
            </div>
            <div>
                <label for="to_date" class="block text-sm font-medium text-gray-700">To Date</label>
                <input
                    type="date"
                    id="to_date"
                    name="to_date"
                    value="{{ request('to_date', now()->format('Y-m-d')) }}"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                >
            </div>
        </div>

        <!-- Hidden sort parameters -->
        <input type="hidden" name="sort" id="sort" value="{{ request('sort', 'date') }}">
        <input type="hidden" name="direction" id="direction" value="{{ request('direction', 'desc') }}">

        <!-- Action Buttons -->
        <div class="flex gap-3">
            <button
                type="submit"
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
                Apply Filters
            </button>
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
                                        <a
                                            href="https://www.youtube.com/channel/{{ $comment->video->channel->channel_id }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-blue-600 hover:text-blue-800 truncate block text-sm"
                                            title="{{ $comment->video->channel->channel_name }}"
                                        >
                                            {{ Str::limit($comment->video->channel->channel_name, 15) }}
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-500">Unknown</span>
                                    @endif
                                </td>

                                <!-- Video Title Cell -->
                                <td class="px-4 py-3 w-[200px]">
                                    <a
                                        href="https://www.youtube.com/watch?v={{ $comment->video_id }}&lc={{ $comment->comment_id }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-blue-600 hover:text-blue-800 truncate block text-sm"
                                        title="{{ $comment->video->title ?? 'Unknown Video' }}"
                                    >
                                        {{ Str::limit($comment->video?->title ?? 'Unknown Video', 20) }}
                                    </a>
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
                                            <span class="inline-block px-1.5 py-0.5 bg-orange-500 text-white text-xs rounded font-medium">
                                                重複
                                            </span>
                                        @endif
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
                                        data-published-at="{{ $comment->published_at?->format('Y-m-d H:i') ?? 'N/A' }}"
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
                                    {{ $comment->published_at?->format('Y-m-d H:i') ?? 'N/A' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200 bg-white flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    Showing {{ $comments->firstItem() ?? 0 }} to {{ $comments->lastItem() ?? 0 }} of {{ $comments->total() ?? 0 }} comments
                </div>
                <div class="flex gap-2">
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

            <!-- Current Comment Section -->
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

    // Fill current comment data
    document.getElementById('currentAuthorName').textContent = authorName || '匿名用戶';
    document.getElementById('currentPublishedAt').textContent = publishedAt || 'N/A';
    document.getElementById('modalCommentText').textContent = commentText;
    document.getElementById('currentLikeCount').textContent = likeCount || '0';

    // Check if this is a reply (has parent_comment_id)
    if (parentId && parentId !== 'null' && parentId !== '') {
        // Show loading state
        document.getElementById('parentCommentSection').classList.remove('hidden');
        document.getElementById('parentCommentText').textContent = '載入中...';

        try {
            // Fetch parent comment data from server
            const response = await fetch(`/api/comments/${parentId}`);
            if (!response.ok) throw new Error('Failed to fetch parent comment');

            const parentData = await response.json();

            // Fill parent comment data
            document.getElementById('parentAuthorName').textContent = parentData.author_name || '匿名用戶';
            document.getElementById('parentPublishedAt').textContent = parentData.published_at || 'N/A';
            document.getElementById('parentCommentText').textContent = parentData.text || '';
            document.getElementById('parentLikeCount').textContent = parentData.like_count || '0';
        } catch (error) {
            console.error('Error fetching parent comment:', error);
            document.getElementById('parentCommentText').textContent = '無法載入父留言';
        }
    } else {
        // Hide parent section if this is not a reply
        document.getElementById('parentCommentSection').classList.add('hidden');
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

<!-- Include YouTube Official API Import Modal -->
<x-import-comments-modal />

<!-- Include U-API Import Modal -->
@include('comments.uapi-import-modal')

@endsection
