<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>身份驗證管理 - DISINFO SCANNER</title>
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

            <!-- Verification List Content (T252) -->
            <main class="flex-1 p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Page Title -->
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">身份驗證管理</h1>
                            <p class="mt-1 text-sm text-gray-600">審核用戶身份驗證請求</p>
                        </div>
                    </div>

                    <!-- Filter Section -->
                    <div class="bg-white rounded-lg shadow-sm p-4 mb-6" x-data="verificationFilters">
                        <div class="flex gap-4">
                            <!-- Status Filter -->
                            <div class="flex-1">
                                <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-1">狀態篩選</label>
                                <select id="statusFilter"
                                        x-model="statusFilter"
                                        @change="fetchVerifications"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">全部狀態</option>
                                    <option value="pending">待審核</option>
                                    <option value="approved">已批准</option>
                                    <option value="rejected">已拒絕</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Verification Table -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden" x-data="verificationManagement">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">用戶</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">驗證方式</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">提交時間</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">審核時間</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template x-if="loading">
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center">
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

                                    <template x-if="!loading && verifications.length === 0">
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                找不到符合條件的驗證請求
                                            </td>
                                        </tr>
                                    </template>

                                    <template x-for="verification in verifications" :key="verification.id">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900" x-text="verification.user?.name"></div>
                                                    <div class="text-sm text-gray-500" x-text="verification.user?.email"></div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900" x-text="verification.verification_method"></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span :class="getStatusBadgeClass(verification.verification_status)"
                                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                      x-text="getStatusText(verification.verification_status)">
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span x-text="formatDate(verification.submitted_at)"></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span x-text="verification.reviewed_at ? formatDate(verification.reviewed_at) : '-'"></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a :href="`/admin/verifications/${verification.id}/review`"
                                                   class="text-blue-600 hover:text-blue-900">審核</a>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200" x-show="!loading && pagination.total > 0">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    顯示第 <span class="font-medium" x-text="pagination.from"></span> 到
                                    <span class="font-medium" x-text="pagination.to"></span> 筆，共
                                    <span class="font-medium" x-text="pagination.total"></span> 筆結果
                                </div>
                                <div class="flex space-x-2">
                                    <button @click="previousPage"
                                            :disabled="pagination.current_page === 1"
                                            :class="pagination.current_page === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-200'"
                                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white">
                                        上一頁
                                    </button>
                                    <button @click="nextPage"
                                            :disabled="pagination.current_page === pagination.last_page"
                                            :class="pagination.current_page === pagination.last_page ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-200'"
                                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white">
                                        下一頁
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            // Verification Filters Component
            Alpine.data('verificationFilters', () => ({
                statusFilter: '',

                fetchVerifications() {
                    window.dispatchEvent(new CustomEvent('filter-changed', {
                        detail: {
                            status: this.statusFilter
                        }
                    }));
                }
            }));

            // Verification Management Component
            Alpine.data('verificationManagement', () => ({
                verifications: [],
                loading: true,
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 15,
                    total: 0,
                    from: 0,
                    to: 0
                },
                filters: {
                    status: ''
                },

                init() {
                    this.fetchVerifications();

                    window.addEventListener('filter-changed', (e) => {
                        this.filters = e.detail;
                        this.pagination.current_page = 1;
                        this.fetchVerifications();
                    });
                },

                async fetchVerifications() {
                    this.loading = true;
                    try {
                        const params = new URLSearchParams({
                            page: this.pagination.current_page,
                            per_page: this.pagination.per_page,
                            ...(this.filters.status && { status: this.filters.status })
                        });

                        const response = await fetch(`/api/admin/verifications?${params}`);
                        if (response.ok) {
                            const data = await response.json();
                            this.verifications = data.data;
                            this.pagination = {
                                current_page: data.current_page,
                                last_page: data.last_page,
                                per_page: data.per_page,
                                total: data.total,
                                from: data.from,
                                to: data.to
                            };
                        }
                    } catch (error) {
                        console.error('Failed to fetch verifications:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                previousPage() {
                    if (this.pagination.current_page > 1) {
                        this.pagination.current_page--;
                        this.fetchVerifications();
                    }
                },

                nextPage() {
                    if (this.pagination.current_page < this.pagination.last_page) {
                        this.pagination.current_page++;
                        this.fetchVerifications();
                    }
                },

                getStatusBadgeClass(status) {
                    const classes = {
                        'pending': 'bg-yellow-100 text-yellow-700',
                        'approved': 'bg-green-100 text-green-700',
                        'rejected': 'bg-red-100 text-red-700'
                    };
                    return classes[status] || 'bg-gray-100 text-gray-700';
                },

                getStatusText(status) {
                    const texts = {
                        'pending': '待審核',
                        'approved': '已批准',
                        'rejected': '已拒絕'
                    };
                    return texts[status] || '未知';
                },

                formatDate(dateString) {
                    if (!dateString) return '-';
                    const date = new Date(dateString);
                    return date.toLocaleDateString('zh-TW', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            }));
        });
    </script>
</body>
</html>
