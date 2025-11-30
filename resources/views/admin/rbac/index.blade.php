<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>權限管理 - DISINFO SCANNER</title>
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

            <!-- RBAC Content -->
            <main class="flex-1 p-6">
                <div class="max-w-7xl mx-auto" x-data="rbacManager">
                    <!-- Page Title -->
                    <div class="mb-6">
                        <h1 class="text-3xl font-bold text-gray-900">權限管理 (RBAC)</h1>
                        <p class="mt-1 text-sm text-gray-600">管理角色與權限對照表</p>
                    </div>

                    <!-- Role Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
                        <template x-for="role in roles" :key="role.name">
                            <div class="bg-white rounded-lg shadow p-4">
                                <div class="text-sm text-gray-500" x-text="role.name"></div>
                                <div class="text-xl font-bold text-gray-900" x-text="role.display_name"></div>
                                <div class="text-sm text-blue-600" x-text="role.permission_count + ' 項權限'"></div>
                            </div>
                        </template>
                    </div>

                    <!-- Permission Matrix -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">權限對照表</h2>
                            <p class="text-sm text-gray-500">顯示各角色擁有的權限</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            權限名稱
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            分類
                                        </th>
                                        <template x-for="role in roles" :key="role.name">
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider" x-text="role.display_name"></th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template x-for="permission in permissions" :key="permission.name">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900" x-text="permission.display_name"></div>
                                                <div class="text-xs text-gray-500" x-text="permission.name"></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs rounded-full"
                                                      :class="{
                                                          'bg-blue-100 text-blue-800': permission.category === 'pages',
                                                          'bg-green-100 text-green-800': permission.category === 'features',
                                                          'bg-purple-100 text-purple-800': permission.category === 'actions'
                                                      }"
                                                      x-text="getCategoryLabel(permission.category)"></span>
                                            </td>
                                            <template x-for="role in roles" :key="role.name">
                                                <td class="px-4 py-4 text-center">
                                                    <template x-if="hasPermission(role.name, permission.name)">
                                                        <svg class="w-5 h-5 text-green-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    </template>
                                                    <template x-if="!hasPermission(role.name, permission.name)">
                                                        <svg class="w-5 h-5 text-gray-300 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    </template>
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="mt-6 bg-white rounded-lg shadow p-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">圖例說明</h3>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <div class="flex items-center">
                                <span class="px-2 py-1 rounded-full bg-blue-100 text-blue-800 text-xs mr-2">頁面</span>
                                <span class="text-gray-600">頁面存取權限</span>
                            </div>
                            <div class="flex items-center">
                                <span class="px-2 py-1 rounded-full bg-green-100 text-green-800 text-xs mr-2">功能</span>
                                <span class="text-gray-600">功能使用權限</span>
                            </div>
                            <div class="flex items-center">
                                <span class="px-2 py-1 rounded-full bg-purple-100 text-purple-800 text-xs mr-2">操作</span>
                                <span class="text-gray-600">管理操作權限</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-600">有此權限</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-300 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-600">無此權限</span>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('rbacManager', () => ({
            roles: [],
            permissions: [],
            rolePermissions: {},
            loading: true,

            async init() {
                await this.loadData();
            },

            async loadData() {
                try {
                    const response = await fetch('/api/admin/rbac/matrix', {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        this.roles = data.roles;
                        this.permissions = data.permissions;
                        this.rolePermissions = data.role_permissions;
                    } else {
                        // Fallback to hardcoded data if API not ready
                        this.loadFallbackData();
                    }
                } catch (error) {
                    console.error('Error loading RBAC data:', error);
                    this.loadFallbackData();
                }
                this.loading = false;
            },

            loadFallbackData() {
                // Hardcoded data based on PermissionRoleMappingSeeder
                this.roles = [
                    { name: 'visitor', display_name: '訪客', permission_count: 3 },
                    { name: 'regular_member', display_name: '一般會員', permission_count: 8 },
                    { name: 'premium_member', display_name: '高級會員', permission_count: 11 },
                    { name: 'website_editor', display_name: '網站編輯', permission_count: 11 },
                    { name: 'administrator', display_name: '管理員', permission_count: 14 }
                ];

                this.permissions = [
                    { name: 'view_home', display_name: '查看首頁', category: 'pages' },
                    { name: 'view_videos_list', display_name: '查看影片列表', category: 'pages' },
                    { name: 'view_channels_list', display_name: '查看頻道列表', category: 'pages' },
                    { name: 'view_comments_list', display_name: '查看留言列表', category: 'pages' },
                    { name: 'view_admin_panel', display_name: '查看管理後台', category: 'pages' },
                    { name: 'use_video_analysis', display_name: '使用影片分析', category: 'features' },
                    { name: 'use_video_update', display_name: '使用影片更新', category: 'features' },
                    { name: 'use_u_api_import', display_name: '使用 U-API 匯入', category: 'features' },
                    { name: 'use_official_api_import', display_name: '使用官方 API 匯入', category: 'features' },
                    { name: 'use_search_videos', display_name: '搜尋影片', category: 'features' },
                    { name: 'use_search_comments', display_name: '搜尋留言', category: 'features' },
                    { name: 'change_password', display_name: '變更密碼', category: 'actions' },
                    { name: 'manage_users', display_name: '管理使用者', category: 'actions' },
                    { name: 'manage_permissions', display_name: '管理權限', category: 'actions' }
                ];

                this.rolePermissions = {
                    'visitor': ['view_home', 'view_videos_list', 'use_video_analysis'],
                    'regular_member': ['view_home', 'view_videos_list', 'view_channels_list', 'view_comments_list', 'use_video_analysis', 'use_video_update', 'use_u_api_import', 'change_password'],
                    'premium_member': ['view_home', 'view_videos_list', 'view_channels_list', 'view_comments_list', 'use_video_analysis', 'use_video_update', 'use_u_api_import', 'use_official_api_import', 'use_search_videos', 'use_search_comments', 'change_password'],
                    'website_editor': ['view_home', 'view_videos_list', 'view_channels_list', 'view_comments_list', 'use_video_analysis', 'use_video_update', 'use_u_api_import', 'use_official_api_import', 'use_search_videos', 'use_search_comments', 'change_password'],
                    'administrator': ['view_home', 'view_videos_list', 'view_channels_list', 'view_comments_list', 'view_admin_panel', 'use_video_analysis', 'use_video_update', 'use_u_api_import', 'use_official_api_import', 'use_search_videos', 'use_search_comments', 'change_password', 'manage_users', 'manage_permissions']
                };
            },

            hasPermission(roleName, permissionName) {
                return this.rolePermissions[roleName]?.includes(permissionName) || false;
            },

            getCategoryLabel(category) {
                const labels = {
                    'pages': '頁面',
                    'features': '功能',
                    'actions': '操作'
                };
                return labels[category] || category;
            }
        }));
    });
    </script>
</body>
</html>
