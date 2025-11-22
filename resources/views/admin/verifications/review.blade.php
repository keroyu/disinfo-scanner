<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>審核身份驗證 - DISINFO SCANNER</title>
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

            <!-- Verification Review Content (T253) -->
            <main class="flex-1 p-6">
                <div class="max-w-4xl mx-auto" x-data="verificationReview" x-init="init('{{ $verificationId }}')">
                    <!-- Back Button -->
                    <div class="mb-6">
                        <a href="{{ route('admin.verifications.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            返回驗證列表
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

                    <!-- Verification Details -->
                    <div x-show="!loading">
                        <!-- Page Title -->
                        <div class="mb-6">
                            <h1 class="text-3xl font-bold text-gray-900">審核身份驗證</h1>
                            <p class="mt-1 text-sm text-gray-600">審核用戶的身份驗證請求</p>
                        </div>

                        <!-- Already Reviewed Alert -->
                        <div x-show="verification.verification_status !== 'pending'" class="mb-6 p-4 rounded-lg" :class="verification.verification_status === 'approved' ? 'bg-green-50 border-l-4 border-green-400' : 'bg-red-50 border-l-4 border-red-400'">
                            <div class="flex">
                                <svg x-show="verification.verification_status === 'approved'" class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <svg x-show="verification.verification_status === 'rejected'" class="w-5 h-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium" :class="verification.verification_status === 'approved' ? 'text-green-800' : 'text-red-800'">
                                        <span x-show="verification.verification_status === 'approved'">此驗證請求已批准</span>
                                        <span x-show="verification.verification_status === 'rejected'">此驗證請求已拒絕</span>
                                    </p>
                                    <p class="text-sm mt-1" :class="verification.verification_status === 'approved' ? 'text-green-700' : 'text-red-700'">
                                        審核時間：<span x-text="formatDate(verification.reviewed_at)"></span>
                                    </p>
                                    <p x-show="verification.notes" class="text-sm mt-1" :class="verification.verification_status === 'approved' ? 'text-green-700' : 'text-red-700'">
                                        備註：<span x-text="verification.notes"></span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- User Info Card -->
                        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">用戶資訊</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">姓名</label>
                                    <p class="text-gray-900" x-text="verification.user?.name"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">電子郵件</label>
                                    <p class="text-gray-900" x-text="verification.user?.email"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">驗證方式</label>
                                    <p class="text-gray-900" x-text="verification.verification_method"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">提交時間</label>
                                    <p class="text-gray-900" x-text="formatDate(verification.submitted_at)"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Review Form (T254, T255) -->
                        <div x-show="verification.verification_status === 'pending'" class="bg-white rounded-lg shadow-sm p-6 mb-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">審核決定</h2>

                            <form @submit.prevent="submitReview">
                                <!-- Notes Field (T255) -->
                                <div class="mb-6">
                                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                        審核備註 <span class="text-gray-500">(選填)</span>
                                    </label>
                                    <textarea id="notes"
                                              x-model="reviewNotes"
                                              rows="4"
                                              placeholder="輸入審核意見或拒絕原因..."
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                              maxlength="500"></textarea>
                                    <p class="mt-1 text-sm text-gray-500">
                                        <span x-text="reviewNotes.length"></span>/500 字元
                                    </p>
                                </div>

                                <!-- Action Buttons (T254) -->
                                <div class="flex justify-end space-x-3">
                                    <a href="{{ route('admin.verifications.index') }}"
                                       class="px-6 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        取消
                                    </a>
                                    <button type="button"
                                            @click="submitReview('reject')"
                                            :disabled="submitting"
                                            :class="submitting ? 'opacity-50 cursor-not-allowed' : 'hover:bg-red-700'"
                                            class="px-6 py-2 bg-red-600 text-white rounded-lg text-sm font-medium">
                                        <span x-show="!submitting || reviewAction !== 'reject'">拒絕</span>
                                        <span x-show="submitting && reviewAction === 'reject'">處理中...</span>
                                    </button>
                                    <button type="button"
                                            @click="submitReview('approve')"
                                            :disabled="submitting"
                                            :class="submitting ? 'opacity-50 cursor-not-allowed' : 'hover:bg-green-700'"
                                            class="px-6 py-2 bg-green-600 text-white rounded-lg text-sm font-medium">
                                        <span x-show="!submitting || reviewAction !== 'approve'">批准</span>
                                        <span x-show="submitting && reviewAction === 'approve'">處理中...</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Review History (if already reviewed) -->
                        <div x-show="verification.verification_status !== 'pending'" class="bg-white rounded-lg shadow-sm p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">審核記錄</h2>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-700">審核結果：</span>
                                    <span class="text-sm" :class="verification.verification_status === 'approved' ? 'text-green-600 font-medium' : 'text-red-600 font-medium'">
                                        <span x-show="verification.verification_status === 'approved'">已批准</span>
                                        <span x-show="verification.verification_status === 'rejected'">已拒絕</span>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-700">審核時間：</span>
                                    <span class="text-sm text-gray-900" x-text="formatDate(verification.reviewed_at)"></span>
                                </div>
                                <div x-show="verification.notes">
                                    <span class="block text-sm font-medium text-gray-700 mb-1">審核備註：</span>
                                    <p class="text-sm text-gray-900 bg-gray-50 p-3 rounded" x-text="verification.notes"></p>
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
            Alpine.data('verificationReview', () => ({
                verification: {},
                loading: true,
                submitting: false,
                reviewAction: '',
                reviewNotes: '',

                async init(verificationId) {
                    await this.fetchVerification(verificationId);
                },

                async fetchVerification(verificationId) {
                    this.loading = true;
                    try {
                        const response = await fetch(`/api/admin/verifications/${verificationId}`);
                        if (response.ok) {
                            this.verification = await response.json();
                        } else {
                            alert('無法載入驗證請求');
                            window.location.href = '{{ route("admin.verifications.index") }}';
                        }
                    } catch (error) {
                        console.error('Failed to fetch verification:', error);
                        alert('載入驗證請求時發生錯誤');
                    } finally {
                        this.loading = false;
                    }
                },

                async submitReview(action) {
                    if (this.submitting) return;

                    // Confirm action
                    const confirmMessage = action === 'approve'
                        ? '確定要批准此身份驗證嗎？批准後用戶將獲得無限 API 配額。'
                        : '確定要拒絕此身份驗證嗎？';

                    if (!confirm(confirmMessage)) {
                        return;
                    }

                    this.submitting = true;
                    this.reviewAction = action;

                    try {
                        const response = await fetch(`/api/admin/verifications/${this.verification.id}/review`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            },
                            body: JSON.stringify({
                                action: action,
                                notes: this.reviewNotes || null
                            })
                        });

                        if (response.ok) {
                            const result = await response.json();
                            alert(result.message);
                            // Reload verification to show updated status
                            await this.fetchVerification(this.verification.id);
                            // Redirect to list after 2 seconds
                            setTimeout(() => {
                                window.location.href = '{{ route("admin.verifications.index") }}';
                            }, 2000);
                        } else {
                            const error = await response.json();
                            alert(error.message || '審核失敗');
                        }
                    } catch (error) {
                        console.error('Failed to submit review:', error);
                        alert('提交審核時發生錯誤');
                    } finally {
                        this.submitting = false;
                        this.reviewAction = '';
                    }
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
                }
            }));
        });
    </script>
</body>
</html>
