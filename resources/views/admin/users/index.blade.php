<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Users Management - DISINFO SCANNER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
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
                <div class="max-w-7xl mx-auto" x-data="batchActions">
                    <!-- Page Title -->
                    <div class="mb-6">
                        <h1 class="text-3xl font-bold text-gray-900">Users Management</h1>
                        <p class="mt-1 text-sm text-gray-600">管理所有系統用戶與權限</p>
                    </div>

                    <!-- Batch Action Toolbar (014-users-management-enhancement T015) -->
                    <div x-show="selectedUsers.length > 0" x-cloak
                         class="sticky top-0 z-10 bg-white shadow-sm rounded-lg p-4 mb-4 border border-blue-200">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div class="flex items-center text-sm font-medium text-blue-700">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                已選擇 <span class="mx-1 font-bold" x-text="selectedUsers.length"></span> 位用戶
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- Batch Change Role Button -->
                                <button @click="showRoleModal = true"
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    批次更改角色
                                </button>

                                <!-- Batch Send Email Button -->
                                <button @click="showEmailModal = true"
                                        class="inline-flex items-center px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    批次發送郵件
                                </button>

                                <!-- Export CSV Button -->
                                <button @click="exportCsv"
                                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Export CSV
                                </button>

                                <!-- Clear Selection -->
                                <button @click="clearSelection"
                                        class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    取消選擇
                                </button>
                            </div>
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
                                    <option value="visitor">訪客</option>
                                    <option value="regular_member">一般會員</option>
                                    <option value="premium_member">高級會員</option>
                                    <option value="website_editor">網站編輯</option>
                                    <option value="administrator">管理員</option>
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">積分</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">所在地</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">角色</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">註冊時間</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider sticky right-0 bg-gray-50">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template x-if="loading">
                                        <tr>
                                            <td colspan="9" class="px-6 py-12 text-center">
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
                                            <td colspan="9" class="px-6 py-12 text-center text-gray-500">
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
                                                <div class="text-sm text-gray-900" x-text="user.points ?? 0"></div>
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
                                                    Email驗證
                                                </span>
                                                <span x-show="!user.is_email_verified"
                                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                                    Email未驗證
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span x-text="formatDate(user.created_at)"></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium sticky right-0 bg-white">
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

                    <!-- Role Change Modal (T016) -->
                    <div x-show="showRoleModal" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center"
                         @keydown.escape.window="showRoleModal = false">
                        <div class="fixed inset-0 bg-black bg-opacity-50" @click="showRoleModal = false"></div>
                        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">批次更改角色</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                將 <span class="font-bold text-blue-600" x-text="selectedUsers.length"></span> 位用戶的角色更改為：
                            </p>
                            <select x-model="selectedRoleId" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 mb-4">
                                <option value="">選擇角色...</option>
                                <option value="2">一般會員</option>
                                <option value="3">高級會員</option>
                                <option value="4">網站編輯</option>
                                <option value="5">管理員</option>
                            </select>
                            <div x-show="selectedRoleId == 3" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
                                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                新高級會員將自動設置 30 天的會員到期日
                            </div>
                            <div x-show="roleMessage" :class="roleSuccess ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'" class="mb-4 p-3 border rounded-lg text-sm" x-text="roleMessage"></div>
                            <div class="flex justify-end gap-3">
                                <button @click="showRoleModal = false; roleMessage = ''" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">取消</button>
                                <button @click="batchChangeRole" :disabled="!selectedRoleId || roleChanging"
                                        :class="!selectedRoleId || roleChanging ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg">
                                    <span x-show="!roleChanging">確認更改</span>
                                    <span x-show="roleChanging">處理中...</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Email Composition Modal (T023) -->
                    <div x-show="showEmailModal" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center"
                         @keydown.escape.window="showEmailModal = false">
                        <div class="fixed inset-0 bg-black bg-opacity-50" @click="showEmailModal = false"></div>
                        <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">批次發送郵件</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                收件人：<span class="font-bold text-purple-600" x-text="selectedUsers.length"></span> 位用戶
                            </p>
                            <div x-show="selectedUsers.length > 50 && selectedUsers.length <= 100" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
                                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                您將發送給超過 50 位用戶，請確認操作
                            </div>
                            <div x-show="selectedUsers.length > 100" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
                                批次郵件最多只能發送給 100 位用戶
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">郵件主題</label>
                                <input type="text" x-model="emailSubject" placeholder="請輸入郵件主題..."
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">郵件內容</label>
                                <textarea x-model="emailBody" rows="6" placeholder="請輸入郵件內容..."
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 resize-none"></textarea>
                            </div>
                            <div x-show="emailMessage" :class="emailSuccess ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'" class="mb-4 p-3 border rounded-lg text-sm" x-text="emailMessage"></div>
                            <div class="flex justify-end gap-3">
                                <button @click="showEmailModal = false; emailMessage = ''" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">取消</button>
                                <button @click="batchSendEmail" :disabled="!emailSubject || !emailBody || emailSending || selectedUsers.length > 100"
                                        :class="!emailSubject || !emailBody || emailSending || selectedUsers.length > 100 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-purple-700'"
                                        class="px-4 py-2 bg-purple-600 text-white rounded-lg">
                                    <span x-show="!emailSending">發送郵件</span>
                                    <span x-show="emailSending">發送中...</span>
                                </button>
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

            // Batch Actions Component (014-users-management-enhancement)
            Alpine.data('batchActions', () => ({
                showRoleModal: false,
                showEmailModal: false,
                selectedRoleId: '',
                roleChanging: false,
                roleMessage: '',
                roleSuccess: false,
                emailSubject: '',
                emailBody: '',
                emailSending: false,
                emailMessage: '',
                emailSuccess: false,

                get selectedUsers() {
                    return window.selectedUsersStore.users;
                },

                set selectedUsers(value) {
                    window.selectedUsersStore.users = value;
                },

                clearSelection() {
                    window.selectedUsersStore.users = [];
                },

                exportCsv() {
                    if (this.selectedUsers.length === 0) return;

                    const headers = ['Email'];
                    const rows = this.selectedUsers.map(user => [user.email]);

                    let csvContent = headers.join(',') + '\n';
                    rows.forEach(row => {
                        csvContent += row.map(field => `"${(field || '').replace(/"/g, '""')}"`).join(',') + '\n';
                    });

                    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', `users_export_${new Date().toISOString().slice(0,10)}.csv`);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                },

                async batchChangeRole() {
                    if (!this.selectedRoleId || this.roleChanging) return;

                    this.roleChanging = true;
                    this.roleMessage = '';

                    try {
                        const response = await fetch('/api/admin/users/batch-role', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                user_ids: this.selectedUsers.map(u => u.id),
                                role_id: parseInt(this.selectedRoleId)
                            })
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            this.roleSuccess = true;
                            this.roleMessage = data.message;
                            // Refresh user list
                            window.dispatchEvent(new CustomEvent('refresh-users'));
                            // Clear selection after success
                            setTimeout(() => {
                                this.showRoleModal = false;
                                this.roleMessage = '';
                                this.selectedRoleId = '';
                                this.clearSelection();
                            }, 1500);
                        } else {
                            this.roleSuccess = false;
                            this.roleMessage = data.message || '操作失敗';
                        }
                    } catch (error) {
                        this.roleSuccess = false;
                        this.roleMessage = '網路錯誤，請稍後再試';
                    } finally {
                        this.roleChanging = false;
                    }
                },

                async batchSendEmail() {
                    if (!this.emailSubject || !this.emailBody || this.emailSending) return;
                    if (this.selectedUsers.length > 100) return;

                    this.emailSending = true;
                    this.emailMessage = '';

                    try {
                        const response = await fetch('/api/admin/users/batch-email', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                user_ids: this.selectedUsers.map(u => u.id),
                                subject: this.emailSubject,
                                body: this.emailBody
                            })
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            this.emailSuccess = true;
                            this.emailMessage = data.message;
                            setTimeout(() => {
                                this.showEmailModal = false;
                                this.emailMessage = '';
                                this.emailSubject = '';
                                this.emailBody = '';
                                this.clearSelection();
                            }, 2000);
                        } else {
                            this.emailSuccess = false;
                            this.emailMessage = data.message || '發送失敗';
                        }
                    } catch (error) {
                        this.emailSuccess = false;
                        this.emailMessage = '網路錯誤，請稍後再試';
                    } finally {
                        this.emailSending = false;
                    }
                }
            }));

            // User Filters Component
            Alpine.data('userFilters', () => ({
                search: '',
                roleFilter: '',

                fetchUsers() {
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
                        const newSelections = this.users
                            .filter(user => !this.isSelected(user.id))
                            .map(user => ({ id: user.id, email: user.email }));
                        this.selectedUsers = [...this.selectedUsers, ...newSelections];
                    } else {
                        const currentPageIds = this.users.map(u => u.id);
                        this.selectedUsers = this.selectedUsers.filter(u => !currentPageIds.includes(u.id));
                    }
                },

                init() {
                    this.fetchUsers();

                    window.addEventListener('filter-changed', (e) => {
                        this.filters = e.detail;
                        this.pagination.current_page = 1;
                        this.fetchUsers();
                    });

                    window.addEventListener('refresh-users', () => {
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
                            const meta = data.meta;
                            this.pagination = {
                                current_page: meta.current_page,
                                last_page: meta.last_page,
                                per_page: meta.per_page,
                                total: meta.total,
                                from: ((meta.current_page - 1) * meta.per_page) + 1,
                                to: Math.min(meta.current_page * meta.per_page, meta.total)
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
                        for (let i = 1; i <= last; i++) {
                            pages.push(i);
                        }
                    } else {
                        pages.push(1);

                        if (current > 3) {
                            pages.push('...');
                        }

                        const start = Math.max(2, current - 1);
                        const end = Math.min(last - 1, current + 1);

                        for (let i = start; i <= end; i++) {
                            pages.push(i);
                        }

                        if (current < last - 2) {
                            pages.push('...');
                        }

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
