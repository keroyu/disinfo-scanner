<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>審計日誌 - DISINFO SCANNER Admin</title>
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

            <!-- Audit Log Content -->
            <main class="flex-1 p-6">
                <div class="max-w-7xl mx-auto" x-data="auditLogs" x-init="init()">
                    <!-- Page Header -->
                    <div class="mb-6">
                        <h1 class="text-3xl font-bold text-gray-900">審計日誌</h1>
                        <p class="mt-1 text-sm text-gray-600">查看所有管理員操作和系統事件記錄</p>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                        <h2 class="text-lg font-semibold mb-4">篩選條件</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Action Type Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">操作類型</label>
                                <select x-model="filters.actionType" @change="applyFilters" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <option value="">全部</option>
                                    <option value="user_role_changed">角色變更</option>
                                    <option value="identity_verification_reviewed">身份驗證審核</option>
                                    <option value="admin_login_success">管理員登入成功</option>
                                    <option value="admin_login_failed">管理員登入失敗</option>
                                </select>
                            </div>

                            <!-- Date From -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">起始日期</label>
                                <input type="date" x-model="filters.dateFrom" @change="applyFilters" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>

                            <!-- Date To -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">結束日期</label>
                                <input type="date" x-model="filters.dateTo" @change="applyFilters" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                        </div>

                        <!-- Filter Actions -->
                        <div class="mt-4 flex gap-2">
                            <button @click="applyFilters" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                套用篩選
                            </button>
                            <button @click="clearFilters" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                                清除篩選
                            </button>
                            <button @click="exportCSV" class="ml-auto px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                匯出 CSV
                            </button>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div x-show="loading" class="bg-white rounded-lg shadow-sm p-12 text-center">
                        <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-3 text-gray-600">載入中...</p>
                    </div>

                    <!-- Audit Logs Table -->
                    <div x-show="!loading" class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">時間</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作類型</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">描述</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">管理員</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP 位址</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="log in logs" :key="log.id">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="formatDate(log.created_at)"></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="getActionTypeClass(log.action_type)" x-text="getActionTypeLabel(log.action_type)"></span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900" x-text="log.description"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="log.admin?.email || '-'"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="log.ip_address || '-'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        <!-- Empty State -->
                        <div x-show="logs.length === 0" class="text-center py-12">
                            <p class="text-gray-500">沒有找到審計日誌</p>
                        </div>

                        <!-- Pagination -->
                        <div x-show="meta.last_page > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <button @click="previousPage" :disabled="meta.current_page === 1" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                                    上一頁
                                </button>
                                <button @click="nextPage" :disabled="meta.current_page === meta.last_page" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                                    下一頁
                                </button>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        顯示第 <span class="font-medium" x-text="(meta.current_page - 1) * meta.per_page + 1"></span> 到
                                        <span class="font-medium" x-text="Math.min(meta.current_page * meta.per_page, meta.total)"></span> 筆，
                                        共 <span class="font-medium" x-text="meta.total"></span> 筆
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                        <button @click="previousPage" :disabled="meta.current_page === 1" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                            上一頁
                                        </button>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700" x-text="`第 ${meta.current_page} / ${meta.last_page} 頁`"></span>
                                        <button @click="nextPage" :disabled="meta.current_page === meta.last_page" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                            下一頁
                                        </button>
                                    </nav>
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
            Alpine.data('auditLogs', () => ({
                loading: false,
                logs: [],
                meta: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 15,
                    total: 0,
                },
                filters: {
                    actionType: '',
                    dateFrom: '',
                    dateTo: '',
                },

                async init() {
                    await this.fetchLogs();
                },

                async fetchLogs(page = 1) {
                    this.loading = true;
                    try {
                        const params = new URLSearchParams({
                            page: page,
                            per_page: this.meta.per_page,
                        });

                        if (this.filters.actionType) params.append('action_type', this.filters.actionType);
                        if (this.filters.dateFrom) params.append('date_from', this.filters.dateFrom);
                        if (this.filters.dateTo) params.append('date_to', this.filters.dateTo);

                        const response = await fetch(`/api/admin/audit-logs?${params}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            }
                        });

                        if (!response.ok) throw new Error('Failed to fetch audit logs');

                        const data = await response.json();
                        this.logs = data.data;
                        this.meta = data.meta;
                    } catch (error) {
                        console.error('Error fetching audit logs:', error);
                        alert('載入審計日誌時發生錯誤');
                    } finally {
                        this.loading = false;
                    }
                },

                async applyFilters() {
                    await this.fetchLogs(1);
                },

                clearFilters() {
                    this.filters = {
                        actionType: '',
                        dateFrom: '',
                        dateTo: '',
                    };
                    this.fetchLogs(1);
                },

                async previousPage() {
                    if (this.meta.current_page > 1) {
                        await this.fetchLogs(this.meta.current_page - 1);
                    }
                },

                async nextPage() {
                    if (this.meta.current_page < this.meta.last_page) {
                        await this.fetchLogs(this.meta.current_page + 1);
                    }
                },

                formatDate(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleString('zh-TW', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                },

                getActionTypeLabel(actionType) {
                    const labels = {
                        'user_role_changed': '角色變更',
                        'identity_verification_reviewed': '身份驗證審核',
                        'admin_login_success': '登入成功',
                        'admin_login_failed': '登入失敗',
                    };
                    return labels[actionType] || actionType;
                },

                getActionTypeClass(actionType) {
                    const classes = {
                        'user_role_changed': 'bg-blue-100 text-blue-800',
                        'identity_verification_reviewed': 'bg-purple-100 text-purple-800',
                        'admin_login_success': 'bg-green-100 text-green-800',
                        'admin_login_failed': 'bg-red-100 text-red-800',
                    };
                    return classes[actionType] || 'bg-gray-100 text-gray-800';
                },

                exportCSV() {
                    const params = new URLSearchParams();
                    if (this.filters.actionType) params.append('action_type', this.filters.actionType);
                    if (this.filters.dateFrom) params.append('date_from', this.filters.dateFrom);
                    if (this.filters.dateTo) params.append('date_to', this.filters.dateTo);

                    window.location.href = `/api/admin/audit-logs/export?${params}`;
                }
            }));
        });
    </script>
</body>
</html>
