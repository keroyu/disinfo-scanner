<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>影片管理 - DISINFO SCANNER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <x-admin-sidebar />

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <x-admin-header />

            <!-- Video List Content -->
            <main class="flex-1 p-6">
                <div class="max-w-7xl mx-auto" x-data="videoManagement">
                    <!-- Page Title -->
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">影片管理</h1>
                            <p class="mt-1 text-sm text-gray-600">管理所有匯入的 YouTube 影片</p>
                        </div>
                        <!-- Batch Action Toolbar (T059) -->
                        <div x-show="selectedVideos.length > 0" x-cloak
                             class="flex items-center space-x-4 bg-blue-50 border border-blue-200 rounded-lg px-4 py-2">
                            <span class="text-sm text-blue-700 font-medium">
                                <span x-text="selectedVideos.length"></span> 部影片已選取
                            </span>
                            <button @click="showBatchDeleteModal()"
                                    class="px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition">
                                批次刪除
                            </button>
                            <button @click="clearSelection()"
                                    class="text-sm text-gray-600 hover:text-gray-900">
                                取消選取
                            </button>
                        </div>
                    </div>

                    <!-- Search Section (T017) -->
                    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                        <div class="flex items-center space-x-4">
                            <div class="flex-1">
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">搜尋影片</label>
                                <div class="relative">
                                    <input type="text"
                                           id="search"
                                           x-model="search"
                                           @input.debounce.500ms="fetchVideos(1)"
                                           placeholder="搜尋影片標題或頻道名稱..."
                                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="w-48">
                                <label for="perPage" class="block text-sm font-medium text-gray-700 mb-1">每頁顯示</label>
                                <select id="perPage"
                                        x-model="perPage"
                                        @change="fetchVideos(1)"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="20">20 筆</option>
                                    <option value="50">50 筆</option>
                                    <option value="100">100 筆</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Video Table (T015) -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <!-- Checkbox Column (T057) -->
                                        <th class="px-4 py-3 w-12">
                                            <input type="checkbox"
                                                   @click="toggleSelectAll"
                                                   :checked="allSelected"
                                                   :indeterminate="someSelected"
                                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        </th>
                                        <!-- Sortable Headers (T018) -->
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            @click="toggleSort('title')">
                                            <div class="flex items-center">
                                                標題
                                                <template x-if="sortBy === 'title'">
                                                    <svg class="w-4 h-4 ml-1" :class="sortDir === 'asc' ? '' : 'rotate-180'" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                                    </svg>
                                                </template>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            @click="toggleSort('channel_name')">
                                            <div class="flex items-center">
                                                頻道
                                                <template x-if="sortBy === 'channel_name'">
                                                    <svg class="w-4 h-4 ml-1" :class="sortDir === 'asc' ? '' : 'rotate-180'" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                                    </svg>
                                                </template>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            @click="toggleSort('published_at')">
                                            <div class="flex items-center">
                                                發布日期
                                                <template x-if="sortBy === 'published_at'">
                                                    <svg class="w-4 h-4 ml-1" :class="sortDir === 'asc' ? '' : 'rotate-180'" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                                    </svg>
                                                </template>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            @click="toggleSort('actual_comment_count')">
                                            <div class="flex items-center">
                                                留言數
                                                <template x-if="sortBy === 'actual_comment_count'">
                                                    <svg class="w-4 h-4 ml-1" :class="sortDir === 'asc' ? '' : 'rotate-180'" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                                    </svg>
                                                </template>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            @click="toggleSort('created_at')">
                                            <div class="flex items-center">
                                                匯入時間
                                                <template x-if="sortBy === 'created_at'">
                                                    <svg class="w-4 h-4 ml-1" :class="sortDir === 'asc' ? '' : 'rotate-180'" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                                    </svg>
                                                </template>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <!-- Loading State (T020) -->
                                    <template x-if="loading">
                                        <tr>
                                            <td colspan="7" class="px-6 py-12 text-center">
                                                <div class="flex justify-center items-center">
                                                    <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    <span class="ml-2 text-gray-600">載入中...</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <!-- No Results (T021) -->
                                    <template x-if="!loading && videos.length === 0">
                                        <tr>
                                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                </svg>
                                                沒有找到符合的影片
                                            </td>
                                        </tr>
                                    </template>

                                    <!-- Video Rows -->
                                    <template x-for="video in videos" :key="video.video_id">
                                        <tr class="hover:bg-gray-50">
                                            <!-- Checkbox -->
                                            <td class="px-4 py-4">
                                                <input type="checkbox"
                                                       :value="video.video_id"
                                                       x-model="selectedVideos"
                                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            </td>
                                            <!-- Title -->
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <a :href="`https://www.youtube.com/watch?v=${video.video_id}`"
                                                       target="_blank"
                                                       class="text-sm font-medium text-gray-900 hover:text-blue-600 truncate max-w-xs"
                                                       :title="video.title"
                                                       x-text="video.title"></a>
                                                </div>
                                                <div class="text-xs text-gray-500" x-text="video.video_id"></div>
                                            </td>
                                            <!-- Channel -->
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900" x-text="video.channel_name"></div>
                                            </td>
                                            <!-- Published At (T022 - GMT+8) -->
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900" x-text="formatDate(video.published_at)"></div>
                                                <div class="text-xs text-gray-500">(GMT+8)</div>
                                            </td>
                                            <!-- Comment Count -->
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                      :class="video.actual_comment_count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'"
                                                      x-text="video.actual_comment_count.toLocaleString()"></span>
                                            </td>
                                            <!-- Created At -->
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span x-text="formatDate(video.created_at)"></span>
                                            </td>
                                            <!-- Actions -->
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button @click="showEditModal(video)"
                                                        class="text-blue-600 hover:text-blue-900 mr-3">編輯</button>
                                                <button @click="showDeleteModal(video)"
                                                        class="text-red-600 hover:text-red-900">刪除</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination (T019) -->
                        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200" x-show="!loading && pagination.total > 0">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    顯示第 <span class="font-medium" x-text="((pagination.current_page - 1) * perPage) + 1"></span> 到
                                    <span class="font-medium" x-text="Math.min(pagination.current_page * perPage, pagination.total)"></span> 筆，共
                                    <span class="font-medium" x-text="pagination.total"></span> 筆結果
                                </div>
                                <div class="flex space-x-2">
                                    <button @click="fetchVideos(pagination.current_page - 1)"
                                            :disabled="pagination.current_page === 1"
                                            :class="pagination.current_page === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-200'"
                                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white">
                                        上一頁
                                    </button>
                                    <span class="px-4 py-2 text-sm text-gray-700">
                                        第 <span x-text="pagination.current_page"></span> / <span x-text="pagination.last_page"></span> 頁
                                    </span>
                                    <button @click="fetchVideos(pagination.current_page + 1)"
                                            :disabled="pagination.current_page === pagination.last_page"
                                            :class="pagination.current_page === pagination.last_page ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-200'"
                                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white">
                                        下一頁
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Modal (T032-T036) -->
                    <div x-show="editModalOpen" x-cloak
                         class="fixed inset-0 z-50 overflow-y-auto"
                         @keydown.escape.window="editModalOpen = false">
                        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                            <div class="fixed inset-0 transition-opacity" @click="editModalOpen = false">
                                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                            </div>
                            <div class="relative inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full">
                                <form @submit.prevent="submitEdit">
                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">編輯影片資料</h3>

                                        <!-- Title Field -->
                                        <div class="mb-4">
                                            <label for="editTitle" class="block text-sm font-medium text-gray-700 mb-1">影片標題</label>
                                            <input type="text"
                                                   id="editTitle"
                                                   x-model="editForm.title"
                                                   required
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <template x-if="editErrors.title">
                                                <p class="mt-1 text-sm text-red-600" x-text="editErrors.title[0]"></p>
                                            </template>
                                        </div>

                                        <!-- Published At Field (T034) -->
                                        <div class="mb-4">
                                            <label for="editPublishedAt" class="block text-sm font-medium text-gray-700 mb-1">發布日期 (GMT+8)</label>
                                            <input type="datetime-local"
                                                   id="editPublishedAt"
                                                   x-model="editForm.published_at_local"
                                                   required
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <template x-if="editErrors.published_at">
                                                <p class="mt-1 text-sm text-red-600" x-text="editErrors.published_at[0]"></p>
                                            </template>
                                        </div>

                                        <!-- Video ID (read-only) -->
                                        <div class="text-sm text-gray-500">
                                            影片 ID: <span x-text="editForm.video_id"></span>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                                        <button type="submit"
                                                :disabled="editLoading"
                                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                                            <template x-if="editLoading">
                                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </template>
                                            儲存
                                        </button>
                                        <button type="button"
                                                @click="editModalOpen = false"
                                                class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:w-auto sm:text-sm">
                                            取消
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Confirmation Modal (T045-T048) -->
                    <div x-show="deleteModalOpen" x-cloak
                         class="fixed inset-0 z-50 overflow-y-auto"
                         @keydown.escape.window="deleteModalOpen = false">
                        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                            <div class="fixed inset-0 transition-opacity" @click="deleteModalOpen = false">
                                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                            </div>
                            <div class="relative inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full">
                                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                                    <div class="sm:flex sm:items-start">
                                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                            </svg>
                                        </div>
                                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                            <h3 class="text-lg leading-6 font-medium text-gray-900">確認刪除影片</h3>
                                            <div class="mt-2">
                                                <!-- T047: Confirmation message with comment count -->
                                                <p class="text-sm text-gray-500">
                                                    確定要刪除此影片嗎？此操作將同時刪除
                                                    <span class="font-semibold text-red-600" x-text="deleteCommentCount"></span>
                                                    則相關留言，且無法復原。
                                                </p>
                                                <p class="mt-2 text-sm text-gray-700">
                                                    影片標題: <span class="font-medium" x-text="deleteVideo?.title"></span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                                    <button type="button"
                                            @click="confirmDelete"
                                            :disabled="deleteLoading"
                                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                                        <template x-if="deleteLoading">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </template>
                                        確定刪除
                                    </button>
                                    <button type="button"
                                            @click="deleteModalOpen = false"
                                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:w-auto sm:text-sm">
                                        取消
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Batch Delete Confirmation Modal (T061-T064) -->
                    <div x-show="batchDeleteModalOpen" x-cloak
                         class="fixed inset-0 z-50 overflow-y-auto"
                         @keydown.escape.window="batchDeleteModalOpen = false">
                        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                            <div class="fixed inset-0 transition-opacity" @click="batchDeleteModalOpen = false">
                                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                            </div>
                            <div class="relative inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full">
                                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                                    <div class="sm:flex sm:items-start">
                                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                            </svg>
                                        </div>
                                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                            <h3 class="text-lg leading-6 font-medium text-gray-900">確認批次刪除</h3>
                                            <div class="mt-2">
                                                <!-- T063: Batch confirmation message -->
                                                <p class="text-sm text-gray-500">
                                                    確定要刪除
                                                    <span class="font-semibold text-red-600" x-text="selectedVideos.length"></span>
                                                    部影片嗎？此操作將同時刪除
                                                    <span class="font-semibold text-red-600" x-text="batchCommentCount"></span>
                                                    則相關留言，且無法復原。
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                                    <button type="button"
                                            @click="confirmBatchDelete"
                                            :disabled="batchDeleteLoading"
                                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                                        <template x-if="batchDeleteLoading">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </template>
                                        確定刪除
                                    </button>
                                    <button type="button"
                                            @click="batchDeleteModalOpen = false"
                                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:w-auto sm:text-sm">
                                        取消
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Toast Notification -->
                    <div x-show="toast.show" x-cloak
                         x-transition:enter="transform ease-out duration-300 transition"
                         x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                         x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed bottom-4 right-4 z-50">
                        <div :class="toast.type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'"
                             class="rounded-lg border p-4 shadow-lg">
                            <div class="flex items-center">
                                <template x-if="toast.type === 'success'">
                                    <svg class="h-5 w-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </template>
                                <template x-if="toast.type === 'error'">
                                    <svg class="h-5 w-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                </template>
                                <p :class="toast.type === 'success' ? 'text-green-800' : 'text-red-800'" class="text-sm font-medium" x-text="toast.message"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('videoManagement', () => ({
                // Data
                videos: [],
                loading: true,
                search: '',
                sortBy: 'created_at',
                sortDir: 'desc',
                perPage: 20,
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    total: 0
                },

                // Selection (T057-T060)
                selectedVideos: [],

                // Edit Modal (T032-T036)
                editModalOpen: false,
                editLoading: false,
                editForm: {
                    video_id: '',
                    title: '',
                    published_at_local: ''
                },
                editErrors: {},

                // Delete Modal (T045-T050)
                deleteModalOpen: false,
                deleteLoading: false,
                deleteVideo: null,
                deleteCommentCount: 0,

                // Batch Delete (T061-T065)
                batchDeleteModalOpen: false,
                batchDeleteLoading: false,
                batchCommentCount: 0,

                // Toast
                toast: {
                    show: false,
                    type: 'success',
                    message: ''
                },

                // Computed
                get allSelected() {
                    return this.videos.length > 0 && this.selectedVideos.length === this.videos.length;
                },
                get someSelected() {
                    return this.selectedVideos.length > 0 && this.selectedVideos.length < this.videos.length;
                },

                // Methods
                init() {
                    this.fetchVideos();
                },

                async fetchVideos(page = 1) {
                    this.loading = true;
                    this.pagination.current_page = page;

                    try {
                        const params = new URLSearchParams({
                            page: page,
                            per_page: this.perPage,
                            sort_by: this.sortBy,
                            sort_dir: this.sortDir,
                            ...(this.search && { search: this.search })
                        });

                        const response = await fetch(`/api/admin/videos?${params}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });

                        if (response.ok) {
                            const data = await response.json();
                            this.videos = data.data;
                            this.pagination = data.meta;
                            // Clear selection when navigating
                            this.selectedVideos = [];
                        }
                    } catch (error) {
                        console.error('Failed to fetch videos:', error);
                        this.showToast('載入影片清單失敗', 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                toggleSort(column) {
                    if (this.sortBy === column) {
                        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.sortBy = column;
                        this.sortDir = 'desc';
                    }
                    this.fetchVideos(1);
                },

                toggleSelectAll() {
                    if (this.allSelected) {
                        this.selectedVideos = [];
                    } else {
                        this.selectedVideos = this.videos.map(v => v.video_id);
                    }
                },

                clearSelection() {
                    this.selectedVideos = [];
                },

                // Edit Methods (T032-T036)
                showEditModal(video) {
                    this.editForm = {
                        video_id: video.video_id,
                        title: video.title,
                        published_at_local: this.utcToLocal(video.published_at)
                    };
                    this.editErrors = {};
                    this.editModalOpen = true;
                },

                async submitEdit() {
                    this.editLoading = true;
                    this.editErrors = {};

                    try {
                        // Convert local time back to UTC (T034)
                        const publishedAtUtc = this.localToUtc(this.editForm.published_at_local);

                        const response = await fetch(`/api/admin/videos/${this.editForm.video_id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                title: this.editForm.title,
                                published_at: publishedAtUtc
                            })
                        });

                        const data = await response.json();

                        if (response.ok) {
                            this.editModalOpen = false;
                            this.showToast('影片資料已更新', 'success'); // T035
                            this.fetchVideos(this.pagination.current_page);
                        } else if (response.status === 422) {
                            this.editErrors = data.errors || {}; // T036
                        } else if (response.status === 404) {
                            this.editModalOpen = false;
                            this.showToast('找不到此影片，可能已被刪除', 'error'); // T037
                            this.fetchVideos(this.pagination.current_page);
                        }
                    } catch (error) {
                        console.error('Failed to update video:', error);
                        this.showToast('更新影片失敗', 'error');
                    } finally {
                        this.editLoading = false;
                    }
                },

                // Delete Methods (T045-T050)
                async showDeleteModal(video) {
                    this.deleteVideo = video;
                    this.deleteCommentCount = 0;
                    this.deleteModalOpen = true;

                    // Fetch comment count (T046)
                    try {
                        const response = await fetch(`/api/admin/videos/${video.video_id}/comment-count`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        if (response.ok) {
                            const data = await response.json();
                            this.deleteCommentCount = data.comment_count;
                        }
                    } catch (error) {
                        console.error('Failed to fetch comment count:', error);
                    }
                },

                async confirmDelete() {
                    this.deleteLoading = true;

                    try {
                        const response = await fetch(`/api/admin/videos/${this.deleteVideo.video_id}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });

                        if (response.ok) {
                            this.deleteModalOpen = false;
                            this.showToast('影片及相關留言已刪除', 'success'); // T048
                            this.fetchVideos(this.pagination.current_page); // T050
                        } else if (response.status === 404) {
                            this.deleteModalOpen = false;
                            this.showToast('找不到此影片，可能已被刪除', 'error');
                            this.fetchVideos(this.pagination.current_page);
                        }
                    } catch (error) {
                        console.error('Failed to delete video:', error);
                        this.showToast('刪除影片失敗', 'error');
                    } finally {
                        this.deleteLoading = false;
                    }
                },

                // Batch Delete Methods (T061-T065)
                async showBatchDeleteModal() {
                    this.batchCommentCount = 0;
                    this.batchDeleteModalOpen = true;

                    // Fetch total comment count for all selected videos (T062)
                    try {
                        for (const videoId of this.selectedVideos) {
                            const response = await fetch(`/api/admin/videos/${videoId}/comment-count`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                            if (response.ok) {
                                const data = await response.json();
                                this.batchCommentCount += data.comment_count;
                            }
                        }
                    } catch (error) {
                        console.error('Failed to fetch comment counts:', error);
                    }
                },

                async confirmBatchDelete() {
                    this.batchDeleteLoading = true;

                    try {
                        const response = await fetch('/api/admin/videos/batch-delete', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                video_ids: this.selectedVideos
                            })
                        });

                        const data = await response.json();

                        if (response.ok) {
                            this.batchDeleteModalOpen = false;
                            this.showToast(data.message, 'success'); // T064
                            this.selectedVideos = []; // T065
                            this.fetchVideos(this.pagination.current_page);
                        } else if (response.status === 422) {
                            this.showToast(data.errors?.video_ids?.[0] || '驗證失敗', 'error');
                        }
                    } catch (error) {
                        console.error('Failed to batch delete videos:', error);
                        this.showToast('批次刪除失敗', 'error');
                    } finally {
                        this.batchDeleteLoading = false;
                    }
                },

                // Utility Methods
                formatDate(dateString) {
                    if (!dateString) return 'N/A';
                    // T022: Convert to Asia/Taipei timezone
                    const date = new Date(dateString);
                    return date.toLocaleString('zh-TW', {
                        timeZone: 'Asia/Taipei',
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },

                utcToLocal(utcString) {
                    if (!utcString) return '';
                    // Convert UTC ISO string to local datetime-local format for Asia/Taipei
                    const date = new Date(utcString);
                    // Adjust to Asia/Taipei (UTC+8)
                    const taipeiDate = new Date(date.getTime() + (8 * 60 * 60 * 1000));
                    return taipeiDate.toISOString().slice(0, 16);
                },

                localToUtc(localString) {
                    if (!localString) return '';
                    // Convert datetime-local (assumed Asia/Taipei) to UTC ISO string
                    // The input is in local time (Asia/Taipei), so we need to subtract 8 hours
                    const localDate = new Date(localString);
                    const utcDate = new Date(localDate.getTime() - (8 * 60 * 60 * 1000));
                    return utcDate.toISOString();
                },

                showToast(message, type = 'success') {
                    this.toast = { show: true, message, type };
                    setTimeout(() => {
                        this.toast.show = false;
                    }, 3000);
                }
            }));
        });
    </script>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</body>
</html>
