<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>編輯用戶 - DISINFO SCANNER</title>
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

            <!-- User Edit Content -->
            <main class="flex-1 p-6">
                <div class="max-w-4xl mx-auto" x-data="userEdit" x-init="init('{{ $userId }}')">
                    <!-- Back Button -->
                    <div class="mb-6">
                        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            返回用戶列表
                        </a>
                    </div>

                    <!-- Loading State -->
                    <div x-show="loading" class="bg-white rounded-lg shadow-sm p-12 text-center">
                        <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-3 text-gray-600">載入中...</p>
                    </div>

                    <!-- User Details -->
                    <div x-show="!loading">
                        <!-- Page Title -->
                        <div class="mb-6">
                            <h1 class="text-3xl font-bold text-gray-900">編輯用戶</h1>
                            <p class="mt-1 text-sm text-gray-600" x-text="user.email"></p>
                        </div>

                        <!-- User Info Card -->
                        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">基本資訊</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">用戶名稱</label>
                                    <p class="text-gray-900" x-text="user.name"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">電子郵件</label>
                                    <p class="text-gray-900" x-text="user.email"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">電子郵件驗證</label>
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
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">註冊時間</label>
                                    <p class="text-gray-900" x-text="formatDate(user.created_at)"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Role Management Card (T235) -->
                        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">角色管理</h2>

                            <!-- Warning for self-edit (T239-T241) -->
                            <div x-show="isSelfEdit" class="mb-4 p-4 bg-yellow-50 border-l-4 border-yellow-400">
                                <div class="flex">
                                    <svg class="w-5 h-5 text-yellow-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-yellow-800">警告：您不能變更自己的權限等級</p>
                                        <p class="text-sm text-yellow-700 mt-1">為了系統安全，管理員無法更改自己的角色。如需更改，請聯繫其他管理員。</p>
                                    </div>
                                </div>
                            </div>

                            <form @submit.prevent="updateRole">
                                <div class="mb-4">
                                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">用戶角色</label>
                                    <select id="role"
                                            x-model="selectedRoleId"
                                            :disabled="isSelfEdit"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed">
                                        <template x-for="role in availableRoles" :key="role.id">
                                            <option :value="role.id" x-text="role.display_name"></option>
                                        </template>
                                    </select>
                                    <p class="mt-2 text-sm text-gray-500">當前角色：<span class="font-medium" x-text="getCurrentRoleName()"></span></p>
                                </div>

                                <div class="flex justify-end space-x-3">
                                    <a href="{{ route('admin.users.index') }}"
                                       class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        取消
                                    </a>
                                    <button type="submit"
                                            :disabled="isSelfEdit || saving"
                                            :class="isSelfEdit || saving ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium">
                                        <span x-show="!saving">保存變更</span>
                                        <span x-show="saving">保存中...</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- API Quota Card -->
                        <div class="bg-white rounded-lg shadow-sm p-6 mb-6" x-show="user.api_quota">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">API 配額</h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">本月使用量</label>
                                    <p class="text-2xl font-bold text-gray-900" x-text="user.api_quota?.usage_count || 0"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">月限額</label>
                                    <p class="text-2xl font-bold text-gray-900" x-text="user.api_quota?.monthly_limit || 0"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">配額狀態</label>
                                    <span x-show="user.api_quota?.is_unlimited"
                                          class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">
                                        無限制
                                    </span>
                                    <span x-show="!user.api_quota?.is_unlimited"
                                          class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-700">
                                        限制配額
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Identity Verification Card -->
                        <div class="bg-white rounded-lg shadow-sm p-6" x-show="user.identity_verification?.status">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">身份驗證</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">驗證方式</label>
                                    <p class="text-gray-900" x-text="user.identity_verification?.method || 'N/A'"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">驗證狀態</label>
                                    <span :class="getVerificationBadgeClass(user.identity_verification?.status)"
                                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                          x-text="getVerificationStatus(user.identity_verification?.status)">
                                    </span>
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
            Alpine.data('userEdit', () => ({
                user: {},
                loading: true,
                saving: false,
                isSelfEdit: false,
                selectedRoleId: null,
                availableRoles: [],
                currentUserId: null,

                async init(userId) {
                    await this.fetchCurrentUser();
                    await this.fetchRoles();
                    await this.fetchUser(userId);

                    this.isSelfEdit = this.currentUserId == userId;

                    if (this.user.roles && this.user.roles.length > 0) {
                        this.selectedRoleId = this.user.roles[0].id;
                    }
                },

                async fetchCurrentUser() {
                    try {
                        const response = await fetch('/api/auth/me');
                        if (response.ok) {
                            const data = await response.json();
                            this.currentUserId = data.id;
                        }
                    } catch (error) {
                        console.error('Failed to fetch current user:', error);
                    }
                },

                async fetchUser(userId) {
                    this.loading = true;
                    try {
                        const response = await fetch(`/api/admin/users/${userId}`);
                        if (response.ok) {
                            this.user = await response.json();
                        } else {
                            alert('無法載入用戶資訊');
                            window.location.href = '{{ route("admin.users.index") }}';
                        }
                    } catch (error) {
                        console.error('Failed to fetch user:', error);
                        alert('載入用戶資訊時發生錯誤');
                    } finally {
                        this.loading = false;
                    }
                },

                async fetchRoles() {
                    try {
                        const response = await fetch('/api/admin/users');
                        if (response.ok) {
                            const data = await response.json();
                            // Extract unique roles from users
                            const roles = [
                                { id: 1, name: 'visitor', display_name: '訪客' },
                                { id: 2, name: 'regular_member', display_name: '一般會員' },
                                { id: 3, name: 'premium_member', display_name: '高級會員' },
                                { id: 4, name: 'website_editor', display_name: '網站編輯' },
                                { id: 5, name: 'administrator', display_name: '管理員' }
                            ];
                            this.availableRoles = roles;
                        }
                    } catch (error) {
                        console.error('Failed to fetch roles:', error);
                    }
                },

                async updateRole() {
                    if (this.isSelfEdit) {
                        alert('您不能變更自己的權限等級！');
                        return;
                    }

                    this.saving = true;
                    try {
                        const response = await fetch(`/api/admin/users/${this.user.id}/role`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            },
                            body: JSON.stringify({
                                role_id: this.selectedRoleId
                            })
                        });

                        if (response.ok) {
                            alert('角色已成功更新！');
                            await this.fetchUser(this.user.id);
                        } else {
                            const error = await response.json();
                            alert(error.message || '更新角色失敗');
                        }
                    } catch (error) {
                        console.error('Failed to update role:', error);
                        alert('更新角色時發生錯誤');
                    } finally {
                        this.saving = false;
                    }
                },

                getCurrentRoleName() {
                    if (this.user.roles && this.user.roles.length > 0) {
                        return this.user.roles[0].display_name;
                    }
                    return 'N/A';
                },

                formatDate(dateString) {
                    if (!dateString) return 'N/A';
                    const date = new Date(dateString);
                    return date.toLocaleDateString('zh-TW', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },

                getVerificationBadgeClass(status) {
                    const classes = {
                        'pending': 'bg-yellow-100 text-yellow-700',
                        'approved': 'bg-green-100 text-green-700',
                        'rejected': 'bg-red-100 text-red-700'
                    };
                    return classes[status] || 'bg-gray-100 text-gray-700';
                },

                getVerificationStatus(status) {
                    const statuses = {
                        'pending': '待審核',
                        'approved': '已通過',
                        'rejected': '已拒絕'
                    };
                    return statuses[status] || 'N/A';
                }
            }));
        });
    </script>
</body>
</html>
