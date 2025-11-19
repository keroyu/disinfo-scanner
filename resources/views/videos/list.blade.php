@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6 flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Videos List</h1>
            <p class="text-gray-600 mt-2">Browse all YouTube videos with comment activity</p>
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
    <form method="GET" action="{{ route('videos.index') }}" class="bg-white rounded-lg shadow-md p-6 mb-6 space-y-4">
        <!-- Search Field -->
        <div>
            <label for="search" class="block text-sm font-medium text-gray-700">Search Videos</label>
            <input
                type="text"
                id="search"
                name="search"
                value="{{ request('search', '') }}"
                placeholder="Search by video title or channel name..."
                class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
            >
        </div>

        <!-- Hidden sort parameters -->
        <input type="hidden" name="sort" id="sort" value="{{ request('sort', 'published_at') }}">
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
                href="{{ route('videos.index') }}"
                class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors"
            >
                Clear Filters
            </a>
        </div>
    </form>

    <!-- Videos Table Section -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        @if($videos->isEmpty())
            <div class="p-8 text-center text-gray-500">
                <p class="text-lg">No videos found.</p>
                <p class="text-sm mt-2">Try adjusting your search filters.</p>
            </div>
        @else
            <!-- Results Count (Top) -->
            <div class="px-6 py-3 border-b border-gray-200 bg-gray-50">
                <div class="text-sm text-gray-700">
                    顯示第 {{ $videos->firstItem() ?? 0 }} 至 {{ $videos->lastItem() ?? 0 }} 筆，共 {{ $videos->total() ?? 0 }} 部影片
                </div>
            </div>

            <!-- Responsive Table Wrapper -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse videos-table">
                    <!-- Table Header -->
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 w-[150px]">
                                Channel Name
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">
                                Video Title
                            </th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700 w-[180px]">
                                Actions
                            </th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 w-[120px]">
                                <button
                                    type="button"
                                    onclick="handleSort('actual_comment_count')"
                                    class="hover:text-blue-600 cursor-pointer"
                                >
                                    Comment Count
                                    @if($sort === 'actual_comment_count')
                                        <span class="text-xs">{{ $direction === 'desc' ? '▼' : '▲' }}</span>
                                    @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 w-[150px]">
                                <button
                                    type="button"
                                    onclick="handleSort('last_comment_time')"
                                    class="hover:text-blue-600 cursor-pointer"
                                >
                                    Last Comment Time
                                    @if($sort === 'last_comment_time')
                                        <span class="text-xs">{{ $direction === 'desc' ? '▼' : '▲' }}</span>
                                    @endif
                                </button>
                            </th>
                        </tr>
                    </thead>

                    <!-- Table Body -->
                    <tbody class="divide-y divide-gray-200">
                        @foreach($videos as $video)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <!-- Channel Name Cell -->
                                <td class="px-4 py-3">
                                    @if($video->channel)
                                        <a
                                            href="{{ route('comments.index', ['search_channel' => $video->channel->channel_name]) }}"
                                            class="text-blue-600 hover:text-blue-800 truncate block text-sm"
                                            title="{{ $video->channel->channel_name }}"
                                        >
                                            {{ Str::limit($video->channel->channel_name, 25) }}
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-500">Unknown Channel</span>
                                    @endif
                                </td>

                                <!-- Video Title Cell -->
                                <td class="px-4 py-3">
                                    @php
                                        $fromDate = $video->published_at ? \Carbon\Carbon::parse($video->published_at)->format('Y-m-d') : null;
                                        $toDate = now()->format('Y-m-d');
                                        $fullTitle = $video->title ?? 'Unknown Video';
                                        // Truncate to 15 Chinese characters using mb_substr
                                        $truncatedTitle = mb_strlen($fullTitle) > 15
                                            ? mb_substr($fullTitle, 0, 15) . '...'
                                            : $fullTitle;
                                    @endphp
                                    <a
                                        href="{{ route('comments.index', array_filter([
                                            'search' => $fullTitle,
                                            'from_date' => $fromDate,
                                            'to_date' => $toDate
                                        ])) }}"
                                        class="text-blue-600 hover:text-blue-800 block text-sm"
                                        title="{{ $fullTitle }} ({{ $fromDate }} to {{ $toDate }})"
                                    >
                                        {{ $truncatedTitle }}
                                    </a>
                                </td>

                                <!-- Actions Cell (Update and Analysis Buttons) -->
                                <td class="px-4 py-3 text-center">
                                    <div class="flex gap-2 justify-center">
                                        <button
                                            type="button"
                                            onclick="openUpdateModal('{{ $video->video_id }}', '{{ addslashes($fullTitle) }}')"
                                            class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors"
                                            title="更新此影片的新留言"
                                        >
                                            更新
                                        </button>
                                        <a
                                            href="{{ $video->analysisUrl() }}"
                                            class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors inline-block"
                                            title="查看留言密度分析"
                                        >
                                            分析
                                        </a>
                                    </div>
                                </td>

                                <!-- Comment Count Cell -->
                                <td class="px-4 py-3 text-right text-sm text-gray-700">
                                    {{ $video->actual_comment_count ?? 0 }}
                                </td>

                                <!-- Last Comment Time Cell -->
                                <td class="px-4 py-3 text-right text-sm">
                                    @if($video->last_comment_time)
                                        @php
                                            $lastCommentDate = \Carbon\Carbon::parse($video->last_comment_time)->setTimezone('Asia/Taipei');
                                            $fromDate = $lastCommentDate->copy()->subDays(90)->format('Y-m-d');
                                            $toDate = $lastCommentDate->format('Y-m-d');
                                        @endphp
                                        <a
                                            href="{{ route('comments.index', [
                                                'search' => $video->title,
                                                'from_date' => $fromDate,
                                                'to_date' => $toDate
                                            ]) }}"
                                            class="text-blue-600 hover:text-blue-800"
                                            title="View comments from {{ $fromDate }} to {{ $toDate }}"
                                        >
                                            {{ $lastCommentDate->format('Y-m-d H:i') }}
                                        </a>
                                    @else
                                        <span class="text-gray-500">N/A</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200 bg-white flex items-center justify-between gap-6">
                <div class="text-sm text-gray-700">
                    顯示第 {{ $videos->firstItem() ?? 0 }} 至 {{ $videos->lastItem() ?? 0 }} 筆，共 {{ $videos->total() ?? 0 }} 部影片
                </div>
                <div class="flex gap-2 pagination-container">
                    {{ $videos->appends(request()->query())->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

<script>
    function handleSort(column) {
        const sortInput = document.getElementById('sort');
        const directionInput = document.getElementById('direction');
        const currentSort = sortInput.value;
        const currentDirection = directionInput.value;

        // If clicking the same column, toggle direction
        if (currentSort === column) {
            directionInput.value = currentDirection === 'desc' ? 'asc' : 'desc';
        } else {
            // New column, default to descending
            sortInput.value = column;
            directionInput.value = 'desc';
        }

        // Submit the form
        document.querySelector('form').submit();
    }
</script>

<!-- Include Incremental Update Modal -->
@include('videos.incremental-update-modal')

<style>
/* Custom Pagination Styles for White Background */
.pagination-container nav {
    display: flex;
    gap: 0.25rem;
}

.pagination-container nav span,
.pagination-container nav a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.5rem;
    padding: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.375rem;
    transition: all 0.15s ease-in-out;
}

/* Active page */
.pagination-container nav span[aria-current="page"] {
    background-color: #2563eb;
    color: white;
    border: 1px solid #2563eb;
}

/* Regular page links */
.pagination-container nav a {
    background-color: white;
    color: #374151;
    border: 1px solid #d1d5db;
}

.pagination-container nav a:hover {
    background-color: #f3f4f6;
    border-color: #9ca3af;
    color: #111827;
}

/* Disabled state */
.pagination-container nav span[aria-disabled="true"] {
    background-color: #f9fafb;
    color: #9ca3af;
    border: 1px solid #e5e7eb;
    cursor: not-allowed;
}

/* Previous/Next arrows */
.pagination-container nav a svg,
.pagination-container nav span svg {
    width: 1.25rem;
    height: 1.25rem;
}
</style>

<!-- Include Import Modals -->
@include('components.import-comments-modal')
@include('comments.uapi-import-modal')

@endsection
