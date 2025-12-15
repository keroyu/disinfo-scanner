<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Users Management - DISINFO SCANNER</title>
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

            <!-- User List Content -->
            <main class="flex-1 p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Page Title -->
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Users Management</h1>
                            <p class="mt-1 text-sm text-gray-600">管理所有系統用戶與權限</p>
                        </div>
                        <div x-data="csvExport">
                            <button @click="exportCsv"
                                    :disabled="selectedUsers.length === 0"
                                    :class="selectedUsers.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-green-700'"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Export CSV
                                <span x-show="selectedUsers.length > 0" class="ml-2 bg-green-800 px-2 py-0.5 rounded text-xs" x-text="`(${selectedUsers.length})`"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Search and Filter Section (T234) -->
                    <div class="bg-white rounded-lg shadow-sm p-4 mb-6" x-data="userFilters">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Search Input -->
                            <div class="md:col-span-2">
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">搜尋用戶</label>
                                <div class="relative">
                                    <input type="text"
                                           id="search"
                                           x-model="search"
                                           @input.debounce.500ms="fetchUsers"
                                           placeholder="搜尋姓名或電子郵件..."
                                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                            </div>

                            <!-- Role Filter -->
                            <div>
                                <label for="roleFilter" class="block text-sm font-medium text-gray-700 mb-1">角色篩選</label>
                                <select id="roleFilter"
                                        x-model="roleFilter"
                                        @change="fetchUsers"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">所有角色</option>
                                    <option value="1">訪客</option>
                                    <option value="2">一般會員</option>
                                    <option value="3">高級會員</option>
                                    <option value="4">網站編輯</option>
                                    <option value="5">管理員</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- User Table -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden" x-data="userManagement">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left">
                                            <input type="checkbox"
                                                   @change="toggleSelectAll($event.target.checked)"
                                                   :checked="isAllSelected"
                                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">用戶</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">電子郵件</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">所在地</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">角色</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">註冊時間</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template x-if="loading">
                                        <tr>
                                            <td colspan="8" class="px-6 py-12 text-center">
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

                                    <template x-if="!loading && users.length === 0">
                                        <tr>
                                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                                </svg>
                                                找不到符合條件的用戶
                                            </td>
                                        </tr>
                                    </template>

                                    <template x-for="user in users" :key="user.id">
                                        <tr class="hover:bg-gray-50" :class="isSelected(user.id) ? 'bg-blue-50' : ''">
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <input type="checkbox"
                                                       :checked="isSelected(user.id)"
                                                       @change="toggleUser(user)"
                                                       class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                                                        <span x-text="user.name.charAt(0).toUpperCase()"></span>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900" x-text="user.name"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900" x-text="user.email"></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900" x-text="user.location || '-'"></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span :class="getRoleBadgeClass(user.roles[0]?.name)"
                                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                                      x-text="getRoleDisplayName(user.roles[0]?.name)">
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span x-show="user.is_email_verified"
                                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    已驗證
                                                </span>
                                                <span x-show="!user.is_email_verified"
                                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                                    未驗證
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span x-text="formatDate(user.created_at)"></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a :href="`/admin/users/${user.id}/edit`"
                                                   class="text-blue-600 hover:text-blue-900">編輯</a>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination (T233) -->
                        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200" x-show="!loading && pagination.total > 0">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    顯示第 <span class="font-medium" x-text="pagination.from"></span> 到
                                    <span class="font-medium" x-text="pagination.to"></span> 筆，共
                                    <span class="font-medium" x-text="pagination.total"></span> 筆結果
                                </div>
                                <div class="flex items-center space-x-1">
                                    <!-- Previous Button -->
                                    <button @click="previousPage"
                                            :disabled="pagination.current_page === 1"
                                            :class="pagination.current_page === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-200'"
                                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white">
                                        &laquo;
                                    </button>

                                    <!-- Page Numbers -->
                                    <template x-for="page in getPageNumbers()" :key="page">
                                        <button @click="page !== '...' && goToPage(page)"
                                                :disabled="page === '...'"
                                                :class="{
                                                    'bg-blue-600 text-white border-blue-600': page === pagination.current_page,
                                                    'bg-white text-gray-700 border-gray-300 hover:bg-gray-200': page !== pagination.current_page && page !== '...',
                                                    'cursor-default': page === '...'
                                                }"
                                                class="px-3 py-2 border rounded-lg text-sm font-medium min-w-[40px]"
                                                x-text="page">
                                        </button>
                                    </template>

                                    <!-- Next Button -->
                                    <button @click="nextPage"
                                            :disabled="pagination.current_page === pagination.last_page"
                                            :class="pagination.current_page === pagination.last_page ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-200'"
                                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white">
                                        &raquo;
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
            // Shared store for selected users (must be inside alpine:init)
            window.selectedUsersStore = Alpine.reactive({ users: [] });
            // CSV Export Component
            Alpine.data('csvExport', () => ({
                get selectedUsers() {
                    return window.selectedUsersStore.users;
                },

                exportCsv() {
                    if (this.selectedUsers.length === 0) return;

                    // Create CSV content
                    const headers = ['Email'];
                    const rows = this.selectedUsers.map(user => [user.email]);

                    let csvContent = headers.join(',') + '\n';
                    rows.forEach(row => {
                        csvContent += row.map(field => `"${(field || '').replace(/"/g, '""')}"`).join(',') + '\n';
                    });

                    // Download CSV
                    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', `users_export_${new Date().toISOString().slice(0,10)}.csv`);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            }));

            // User Filters Component
            Alpine.data('userFilters', () => ({
                search: '',
                roleFilter: '',

                fetchUsers() {
                    // Trigger user list refresh
                    window.dispatchEvent(new CustomEvent('filter-changed', {
                        detail: {
                            search: this.search,
                            role: this.roleFilter
                        }
                    }));
                }
            }));

            // User Management Component
            Alpine.data('userManagement', () => ({
                users: [],
                loading: true,
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 50,
                    total: 0,
                    from: 0,
                    to: 0
                },
                filters: {
                    search: '',
                    role: ''
                },

                get selectedUsers() {
                    return window.selectedUsersStore.users;
                },

                set selectedUsers(value) {
                    window.selectedUsersStore.users = value;
                },

                get isAllSelected() {
                    return this.users.length > 0 && this.users.every(user => this.isSelected(user.id));
                },

                isSelected(userId) {
                    return this.selectedUsers.some(u => u.id === userId);
                },

                toggleUser(user) {
                    const index = this.selectedUsers.findIndex(u => u.id === user.id);
                    if (index === -1) {
                        this.selectedUsers = [...this.selectedUsers, { id: user.id, email: user.email }];
                    } else {
                        this.selectedUsers = this.selectedUsers.filter(u => u.id !== user.id);
                    }
                },

                toggleSelectAll(checked) {
                    if (checked) {
                        // Add all current page users that aren't already selected
                        const newSelections = this.users
                            .filter(user => !this.isSelected(user.id))
                            .map(user => ({ id: user.id, email: user.email }));
                        this.selectedUsers = [...this.selectedUsers, ...newSelections];
                    } else {
                        // Remove all current page users from selection
                        const currentPageIds = this.users.map(u => u.id);
                        this.selectedUsers = this.selectedUsers.filter(u => !currentPageIds.includes(u.id));
                    }
                },

                init() {
                    this.fetchUsers();

                    // Listen for filter changes
                    window.addEventListener('filter-changed', (e) => {
                        this.filters = e.detail;
                        this.pagination.current_page = 1;
                        this.fetchUsers();
                    });
                },

                async fetchUsers() {
                    this.loading = true;
                    try {
                        const params = new URLSearchParams({
                            page: this.pagination.current_page,
                            per_page: this.pagination.per_page,
                            ...(this.filters.search && { search: this.filters.search }),
                            ...(this.filters.role && { role: this.filters.role })
                        });

                        const response = await fetch(`/api/admin/users?${params}`);
                        if (response.ok) {
                            const data = await response.json();
                            this.users = data.data;
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
                        console.error('Failed to fetch users:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                previousPage() {
                    if (this.pagination.current_page > 1) {
                        this.pagination.current_page--;
                        this.fetchUsers();
                    }
                },

                nextPage() {
                    if (this.pagination.current_page < this.pagination.last_page) {
                        this.pagination.current_page++;
                        this.fetchUsers();
                    }
                },

                goToPage(page) {
                    if (page >= 1 && page <= this.pagination.last_page) {
                        this.pagination.current_page = page;
                        this.fetchUsers();
                    }
                },

                getPageNumbers() {
                    const current = this.pagination.current_page;
                    const last = this.pagination.last_page;
                    const pages = [];

                    if (last <= 7) {
                        // Show all pages if total <= 7
                        for (let i = 1; i <= last; i++) {
                            pages.push(i);
                        }
                    } else {
                        // Always show first page
                        pages.push(1);

                        if (current > 3) {
                            pages.push('...');
                        }

                        // Show pages around current
                        const start = Math.max(2, current - 1);
                        const end = Math.min(last - 1, current + 1);

                        for (let i = start; i <= end; i++) {
                            pages.push(i);
                        }

                        if (current < last - 2) {
                            pages.push('...');
                        }

                        // Always show last page
                        pages.push(last);
                    }

                    return pages;
                },

                getRoleBadgeClass(roleName) {
                    const classes = {
                        'visitor': 'bg-gray-100 text-gray-700 border-gray-300',
                        'regular_member': 'bg-blue-100 text-blue-700 border-blue-300',
                        'premium_member': 'bg-yellow-100 text-yellow-700 border-yellow-300',
                        'website_editor': 'bg-purple-100 text-purple-700 border-purple-300',
                        'administrator': 'bg-red-100 text-red-700 border-red-300'
                    };
                    return classes[roleName] || classes['visitor'];
                },

                getRoleDisplayName(roleName) {
                    const names = {
                        'visitor': '訪客',
                        'regular_member': '一般會員',
                        'premium_member': '高級會員',
                        'website_editor': '網站編輯',
                        'administrator': '管理員'
                    };
                    return names[roleName] || '未知';
                },

                formatDate(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('zh-TW', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit'
                    });
                }
            }));
        });
    </script>
</body>
</html>
