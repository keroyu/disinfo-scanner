<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Edit User - DISINFO SCANNER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
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
                <div class="max-w-4xl mx-auto" x-data="userEdit" x-init="init('{{ $userId }}')" x-cloak>
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
                            <h1 class="text-3xl font-bold text-gray-900">Edit User</h1>
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
                                    <div class="flex items-center space-x-2">
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
                                        <button x-show="!user.is_email_verified"
                                                @click="manuallyVerifyEmail"
                                                :disabled="verifyingEmail"
                                                class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                            <span x-show="!verifyingEmail">手動通過驗證</span>
                                            <span x-show="verifyingEmail">處理中...</span>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">註冊時間</label>
                                    <p class="text-gray-900" x-text="formatDate(user.created_at)"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">最後登入 IP</label>
                                    <div class="flex items-center space-x-2">
                                        <p class="text-gray-900" x-text="user.last_login_ip || '尚未登入'"></p>
                                        <template x-if="user.last_login_ip_country">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                                <span x-text="user.last_login_ip_country"></span>
                                                <template x-if="user.last_login_ip_city">
                                                    <span x-text="', ' + user.last_login_ip_city"></span>
                                                </template>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <!-- T035: Points Display (US5) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">積分</label>
                                    <p class="text-gray-900 text-lg font-semibold" x-text="user.points ?? 0"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Role Management Card (T030-T032: Inline Role Buttons) -->
                        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">角色管理</h2>

                            <!-- Warning for self-edit (T032) -->
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
                                    <label class="block text-sm font-medium text-gray-700 mb-3">用戶角色</label>

                                    <!-- T030: Inline Role Buttons (FR-035 order: 一般會員, 高級會員, 網站編輯, 管理員) -->
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="role in orderedRoles" :key="role.id">
                                            <button type="button"
                                                    @click="selectRole(role.id)"
                                                    :disabled="isSelfEdit"
                                                    :class="{
                                                        'bg-blue-600 text-white border-blue-600': selectedRoleId == role.id,
                                                        'bg-white text-gray-700 border-gray-300 hover:bg-gray-50': selectedRoleId != role.id,
                                                        'opacity-50 cursor-not-allowed': isSelfEdit
                                                    }"
                                                    class="px-4 py-2 border rounded-lg text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                                <span x-text="role.display_name"></span>
                                            </button>
                                        </template>
                                    </div>

                                    <p class="mt-3 text-sm text-gray-500">
                                        當前角色：<span class="font-medium" x-text="getCurrentRoleName()"></span>
                                        <template x-if="selectedRoleId != originalRoleId">
                                            <span class="text-blue-600"> → <span x-text="getSelectedRoleName()"></span></span>
                                        </template>
                                    </p>

                                    <!-- T031: Premium expiry warning when selecting Premium Member for non-Premium user -->
                                    <div x-show="showPremiumExpiryWarning" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                        <p class="text-sm text-blue-700">
                                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                            此用戶將被設為高級會員，到期日將自動設為 30 天後。
                                        </p>
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-3">
                                    <a href="{{ route('admin.users.index') }}"
                                       class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        取消
                                    </a>
                                    <button type="submit"
                                            :disabled="isSelfEdit || saving || selectedRoleId == originalRoleId"
                                            :class="(isSelfEdit || saving || selectedRoleId == originalRoleId) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium">
                                        <span x-show="!saving">保存變更</span>
                                        <span x-show="saving">保存中...</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- T027: Premium Expiry Management Card (US3) - Only show for Premium Members -->
                        <div x-show="isPremiumMember" class="bg-white rounded-lg shadow-sm p-6 mb-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">高級會員到期日管理</h2>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">當前到期日</label>
                                <div class="flex items-center space-x-3">
                                    <p class="text-gray-900 text-lg" x-text="formatPremiumExpiry(user.premium_expires_at)"></p>
                                    <!-- FR-027: Expired indicator -->
                                    <span x-show="isPremiumExpired"
                                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                        已過期
                                    </span>
                                    <span x-show="!isPremiumExpired && user.premium_expires_at"
                                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        有效
                                    </span>
                                </div>
                            </div>

                            <form @submit.prevent="updatePremiumExpiry">
                                <div class="mb-4">
                                    <label for="new_expiry" class="block text-sm font-medium text-gray-700 mb-2">延長到期日至</label>
                                    <input type="date"
                                           id="new_expiry"
                                           x-model="newPremiumExpiry"
                                           :min="tomorrowDate"
                                           class="w-full max-w-xs px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-sm text-gray-500">只能選擇明天或之後的日期</p>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit"
                                            :disabled="!newPremiumExpiry || savingExpiry"
                                            :class="(!newPremiumExpiry || savingExpiry) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-green-700'"
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium">
                                        <span x-show="!savingExpiry">延長到期日</span>
                                        <span x-show="savingExpiry">更新中...</span>
                                    </button>
                                </div>
                            </form>
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
                savingExpiry: false,
                verifyingEmail: false,
                isSelfEdit: false,
                selectedRoleId: null,
                originalRoleId: null,
                availableRoles: [],
                currentUserId: null,
                newPremiumExpiry: '',

                // Ordered roles for inline buttons (FR-035)
                get orderedRoles() {
                    const order = ['regular_member', 'premium_member', 'website_editor', 'administrator'];
                    return this.availableRoles
                        .filter(r => order.includes(r.name))
                        .sort((a, b) => order.indexOf(a.name) - order.indexOf(b.name));
                },

                // Check if user is Premium Member
                get isPremiumMember() {
                    if (!this.user.roles || this.user.roles.length === 0) return false;
                    return this.user.roles.some(r => r.name === 'premium_member');
                },

                // Check if premium has expired
                get isPremiumExpired() {
                    if (!this.user.premium_expires_at) return false;
                    return new Date(this.user.premium_expires_at) < new Date();
                },

                // Show warning when selecting Premium Member for non-Premium user
                get showPremiumExpiryWarning() {
                    if (!this.selectedRoleId || !this.originalRoleId) return false;
                    const selectedRole = this.availableRoles.find(r => r.id == this.selectedRoleId);
                    const originalRole = this.availableRoles.find(r => r.id == this.originalRoleId);
                    return selectedRole?.name === 'premium_member' && originalRole?.name !== 'premium_member';
                },

                // Get tomorrow's date for min date picker
                get tomorrowDate() {
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    return tomorrow.toISOString().split('T')[0];
                },

                async init(userId) {
                    await this.fetchCurrentUser();
                    await this.fetchRoles();
                    await this.fetchUser(userId);

                    this.isSelfEdit = this.currentUserId == userId;

                    if (this.user.roles && this.user.roles.length > 0) {
                        this.selectedRoleId = this.user.roles[0].id;
                        this.originalRoleId = this.user.roles[0].id;
                    }

                    // Set default date picker value to current expiry date
                    if (this.user.premium_expires_at) {
                        const expiryDate = new Date(this.user.premium_expires_at);
                        this.newPremiumExpiry = expiryDate.toISOString().split('T')[0];
                    }
                },

                async fetchCurrentUser() {
                    try {
                        const response = await fetch('/api/auth/me', {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (response.ok) {
                            const result = await response.json();
                            if (result.success && result.data && result.data.user) {
                                this.currentUserId = result.data.user.id;
                            }
                        }
                    } catch (error) {
                        console.error('Failed to fetch current user:', error);
                    }
                },

                async fetchUser(userId) {
                    this.loading = true;
                    try {
                        const response = await fetch(`/api/admin/users/${userId}`);
                        if (!response.ok) {
                            const error = await response.json().catch(() => ({ message: 'Unknown error' }));
                            console.error('API Error:', response.status, error);
                            return;
                        }

                        const data = await response.json();

                        if (!data.data) {
                            console.error('Invalid response format:', data);
                            return;
                        }

                        this.user = data.data;
                    } catch (error) {
                        console.error('Failed to fetch user:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                async fetchRoles() {
                    try {
                        const response = await fetch('/api/admin/users', {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (response.ok) {
                            // Hardcoded roles (matching database)
                            this.availableRoles = [
                                { id: 1, name: 'visitor', display_name: '訪客' },
                                { id: 2, name: 'regular_member', display_name: '一般會員' },
                                { id: 3, name: 'premium_member', display_name: '高級會員' },
                                { id: 4, name: 'website_editor', display_name: '網站編輯' },
                                { id: 5, name: 'administrator', display_name: '管理員' }
                            ];
                        }
                    } catch (error) {
                        console.error('Failed to fetch roles:', error);
                    }
                },

                selectRole(roleId) {
                    if (!this.isSelfEdit) {
                        this.selectedRoleId = roleId;
                    }
                },

                async updateRole() {
                    if (this.isSelfEdit) {
                        alert('您不能變更自己的權限等級！');
                        return;
                    }

                    if (this.selectedRoleId == this.originalRoleId) {
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
                            this.originalRoleId = this.selectedRoleId;
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

                async updatePremiumExpiry() {
                    if (!this.newPremiumExpiry) {
                        alert('請選擇新的到期日');
                        return;
                    }

                    this.savingExpiry = true;
                    try {
                        const response = await fetch(`/api/admin/users/${this.user.id}/premium-expiry`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            },
                            body: JSON.stringify({
                                premium_expires_at: this.newPremiumExpiry
                            })
                        });

                        if (response.ok) {
                            const result = await response.json();
                            alert(result.message || '到期日已更新！');
                            await this.fetchUser(this.user.id);
                            this.newPremiumExpiry = '';
                        } else {
                            const error = await response.json();
                            alert(error.message || '更新到期日失敗');
                        }
                    } catch (error) {
                        console.error('Failed to update premium expiry:', error);
                        alert('更新到期日時發生錯誤');
                    } finally {
                        this.savingExpiry = false;
                    }
                },

                async manuallyVerifyEmail() {
                    if (!confirm('確定要手動通過此使用者的電子郵件驗證嗎？')) {
                        return;
                    }

                    this.verifyingEmail = true;
                    try {
                        const response = await fetch(`/api/admin/users/${this.user.id}/verify-email`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            }
                        });

                        if (response.ok) {
                            alert('電子郵件已手動驗證成功！');
                            await this.fetchUser(this.user.id);
                        } else {
                            const error = await response.json();
                            alert(error.message || '驗證失敗');
                        }
                    } catch (error) {
                        console.error('Failed to verify email:', error);
                        alert('驗證電子郵件時發生錯誤');
                    } finally {
                        this.verifyingEmail = false;
                    }
                },

                getCurrentRoleName() {
                    if (this.user.roles && this.user.roles.length > 0) {
                        return this.user.roles[0].display_name;
                    }
                    return 'N/A';
                },

                getSelectedRoleName() {
                    const role = this.availableRoles.find(r => r.id == this.selectedRoleId);
                    return role ? role.display_name : 'N/A';
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

                formatPremiumExpiry(dateString) {
                    if (!dateString) return '未設定';
                    const date = new Date(dateString);
                    // Format in GMT+8
                    return date.toLocaleDateString('zh-TW', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        timeZone: 'Asia/Taipei'
                    }) + ' (GMT+8)';
                }
            }));
        });
    </script>
</body>
</html>
