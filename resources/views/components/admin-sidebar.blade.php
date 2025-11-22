<!-- Admin Sidebar Component (T236) -->
<aside class="w-64 bg-gray-900 text-white flex-shrink-0" x-data="{ currentPath: window.location.pathname }">
    <div class="p-6">
        <!-- Logo -->
        <div class="mb-8">
            <h2 class="text-xl font-bold">DISINFO SCANNER</h2>
            <p class="text-sm text-gray-400 mt-1">管理後台</p>
        </div>

        <!-- Navigation Menu (T229) -->
        <nav class="space-y-2">
            <!-- Dashboard -->
            <a href="{{ route('admin.dashboard') }}"
               :class="currentPath === '{{ route('admin.dashboard') }}' ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white'"
               class="flex items-center px-4 py-3 rounded-lg transition">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>儀表板</span>
            </a>

            <!-- User Management -->
            <a href="{{ route('admin.users.index') }}"
               :class="currentPath.startsWith('/admin/users') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white'"
               class="flex items-center px-4 py-3 rounded-lg transition">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>用戶管理</span>
            </a>

            <!-- Identity Verification -->
            <a href="{{ route('admin.verifications.index') }}"
               :class="currentPath.startsWith('/admin/verifications') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white'"
               class="flex items-center px-4 py-3 rounded-lg transition">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>身份驗證</span>
            </a>

            <!-- Analytics (Phase 5) -->
            <a href="{{ route('admin.analytics.index') }}"
               :class="currentPath.startsWith('/admin/analytics') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white'"
               class="flex items-center px-4 py-3 rounded-lg transition">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span>統計分析</span>
            </a>

            <!-- Audit Logs (Phase 6) -->
            <a href="#"
               class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition opacity-50 cursor-not-allowed">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>審計日誌</span>
                <span class="ml-auto text-xs bg-gray-800 px-2 py-1 rounded">即將推出</span>
            </a>
        </nav>

        <!-- Divider -->
        <div class="my-6 border-t border-gray-700"></div>

        <!-- Back to Main Site -->
        <a href="{{ route('import.index') }}"
           class="flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            <span>返回主站</span>
        </a>
    </div>
</aside>
