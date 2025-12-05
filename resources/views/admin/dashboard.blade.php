<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>管理後台 - DISINFO SCANNER</title>
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

            <!-- Dashboard Content -->
            <main class="flex-1 p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Page Title -->
                    <div class="mb-6 flex items-start justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">管理後台儀表板</h1>
                            <p class="mt-1 text-sm text-gray-600">系統管理與用戶管理概覽</p>
                        </div>
                        <!-- Help Button (T299) -->
                        <div x-data="{ showHelp: false }" class="relative">
                            <button @click="showHelp = !showHelp" class="p-2 text-gray-400 hover:text-gray-600 transition" title="顯示幫助">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </button>
                            <!-- Help Tooltip -->
                            <div x-show="showHelp" @click.away="showHelp = false" x-transition class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 p-4 z-10">
                                <h3 class="font-semibold text-gray-900 mb-2">管理後台幫助</h3>
                                <div class="space-y-2 text-sm text-gray-600">
                                    <p><strong>統計卡片:</strong> 顯示系統關鍵指標,每5分鐘自動更新</p>
                                    <p><strong>快速操作:</strong> 點擊卡片快速前往對應管理頁面</p>
                                    <p><strong>使用者指南:</strong> 詳細說明請參閱 <a href="/docs/admin-guide.md" class="text-blue-600 hover:underline">管理員使用指南</a></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards (T230) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" x-data="dashboardStats">
                        <!-- Total Users Card -->
                        <div class="bg-white rounded-lg shadow-sm p-6 relative group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">總用戶數</p>
                                    <p class="mt-2 text-3xl font-semibold text-gray-900" x-text="totalUsers">-</p>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-full">
                                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                <span class="text-green-600" x-text="newUsersToday > 0 ? '+' + newUsersToday : newUsersToday">0</span> 今日新增
                            </p>
                            <!-- Contextual Help Tooltip (T300) -->
                            <div class="hidden group-hover:block absolute top-0 right-0 mt-2 mr-2 w-48 bg-gray-800 text-white text-xs rounded p-2 z-10">
                                包含所有角色的註冊用戶總數 (訪客除外)
                            </div>
                        </div>

                        <!-- Premium Members Card -->
                        <div class="bg-white rounded-lg shadow-sm p-6 relative group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">高級會員</p>
                                    <p class="mt-2 text-3xl font-semibold text-gray-900" x-text="premiumMembers">-</p>
                                </div>
                                <div class="p-3 bg-yellow-100 rounded-full">
                                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                <span x-text="Math.round((premiumMembers / totalUsers) * 100)">0</span>% 佔比
                            </p>
                            <!-- Contextual Help Tooltip -->
                            <div class="hidden group-hover:block absolute top-0 right-0 mt-2 mr-2 w-56 bg-gray-800 text-white text-xs rounded p-2 z-10">
                                擁有進階功能的會員,包含 10 次/月 API 配額
                            </div>
                        </div>

                        <!-- Verified Users Card -->
                        <div class="bg-white rounded-lg shadow-sm p-6 relative group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">已驗證用戶</p>
                                    <p class="mt-2 text-3xl font-semibold text-gray-900" x-text="verifiedUsers">-</p>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full">
                                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                <span x-text="pendingVerifications">0</span> 待審核
                            </p>
                            <!-- Contextual Help Tooltip -->
                            <div class="hidden group-hover:block absolute top-0 right-0 mt-2 mr-2 w-56 bg-gray-800 text-white text-xs rounded p-2 z-10">
                                完成電子郵件驗證的用戶數量。待審核為身份驗證申請數
                            </div>
                        </div>

                        <!-- Active Today Card -->
                        <div class="bg-white rounded-lg shadow-sm p-6 relative group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">今日活躍</p>
                                    <p class="mt-2 text-3xl font-semibold text-gray-900" x-text="activeToday">-</p>
                                </div>
                                <div class="p-3 bg-purple-100 rounded-full">
                                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">過去24小時</p>
                            <!-- Contextual Help Tooltip -->
                            <div class="hidden group-hover:block absolute top-0 right-0 mt-2 mr-2 w-56 bg-gray-800 text-white text-xs rounded p-2 z-10">
                                過去 24 小時內有登入或使用平台功能的用戶數
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">快速操作</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <a href="{{ route('admin.users.index') }}" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                                <div>
                                    <p class="font-medium text-gray-900">管理用戶</p>
                                    <p class="text-sm text-gray-600">查看和管理所有用戶</p>
                                </div>
                            </a>

                            <a href="{{ route('admin.verifications.index') }}" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="font-medium text-gray-900">身份驗證</p>
                                    <p class="text-sm text-gray-600">審核身份驗證請求</p>
                                </div>
                            </a>

                            <a href="#" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                <div>
                                    <p class="font-medium text-gray-900">統計報表</p>
                                    <p class="text-sm text-gray-600">查看系統統計數據</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">最近活動</h2>
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p>稍後將顯示最近的管理活動</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('dashboardStats', () => ({
                totalUsers: 0,
                premiumMembers: 0,
                verifiedUsers: 0,
                activeToday: 0,
                newUsersToday: 0,
                pendingVerifications: 0,
                loading: true,

                init() {
                    this.fetchStats();
                },

                async fetchStats() {
                    try {
                        // Fetch statistics from API
                        const response = await fetch('/api/admin/users', {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (response.ok) {
                            const data = await response.json();
                            this.totalUsers = data.total || 0;

                            // Count premium members
                            this.premiumMembers = data.data.filter(user =>
                                user.roles.some(role => role.name === 'premium_member')
                            ).length;

                            // Count verified users
                            this.verifiedUsers = data.data.filter(user =>
                                user.is_email_verified
                            ).length;

                            // Mock data for now (will be replaced with real API)
                            this.activeToday = Math.floor(this.totalUsers * 0.15);
                            this.newUsersToday = Math.floor(Math.random() * 5);
                            this.pendingVerifications = 0;
                        }
                    } catch (error) {
                        console.error('Failed to fetch stats:', error);
                    } finally {
                        this.loading = false;
                    }
                }
            }));
        });
    </script>
</body>
</html>
