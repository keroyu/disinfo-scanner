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
                <input
                    type="text"
                    id="search_channel"
                    name="search_channel"
                    value="{{ request('search_channel', '') }}"
                    placeholder="Search by channel name..."
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                >
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
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">
                                Commenter
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">
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
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 hidden md:table-cell">
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
                                            href="https://www.youtube.com/c/{{ $comment->video->channel->channel_id }}"
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
                                <td class="px-4 py-3">
                                    @if($comment->author)
                                        @if($comment->author->profile_url)
                                            <a
                                                href="{{ $comment->author->profile_url }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-blue-600 hover:text-blue-800 text-sm"
                                                title="{{ $comment->author->name ?? $comment->author->author_channel_id }}"
                                            >
                                                {{ Str::limit($comment->author->name ?? $comment->author->author_channel_id, 20) }}
                                            </a>
                                        @else
                                            <span class="text-sm text-gray-700" title="{{ $comment->author->author_channel_id }}">
                                                {{ Str::limit($comment->author->author_channel_id, 20) }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-sm text-gray-700" title="{{ $comment->author_channel_id }}">
                                            {{ Str::limit($comment->author_channel_id, 20) }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Comment Content Cell -->
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-700 break-words">{{Str::limit($comment->text, 150)}}</div>
                                </td>

                                <!-- Likes Cell (Hidden on Tablet) -->
                                <td class="px-4 py-3 text-right text-sm text-gray-700 hidden md:table-cell">
                                    {{ $comment->like_count ?? 0 }}
                                </td>

                                <!-- Date Cell (Hidden on Tablet) -->
                                <td class="px-4 py-3 text-right text-sm text-gray-700 hidden md:table-cell">
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
    </form>
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
</script>
@endsection
