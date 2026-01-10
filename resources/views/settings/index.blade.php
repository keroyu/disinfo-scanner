@extends('layouts.app')

@section('title', '帳號設定 - DISINFO_SCANNER')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">帳號設定</h1>
        <p class="mt-2 text-sm text-gray-600">管理您的帳號資訊和偏好設定</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-md bg-green-50 p-4" role="status">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">
                        {{ session('success') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 rounded-md bg-red-50 p-4" role="alert">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">
                        {{ session('error') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Account Information Card -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">帳號資訊</h2>
        </div>
        <div class="px-6 py-4">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                <div x-data="{ editing: false, name: '{{ auth()->user()->name }}', saving: false }">
                    <dt class="text-sm font-medium text-gray-500">暱稱</dt>
                    <dd class="mt-1">
                        <template x-if="!editing">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-900" x-text="name"></span>
                                <button @click="editing = true" type="button" class="text-blue-600 hover:text-blue-800 text-xs">編輯</button>
                            </div>
                        </template>
                        <template x-if="editing">
                            <form @submit.prevent="
                                saving = true;
                                fetch('{{ route('settings.name') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({ name: name })
                                })
                                .then(r => r.json())
                                .then(data => {
                                    saving = false;
                                    if (data.success) {
                                        editing = false;
                                    } else {
                                        alert(data.message || '更新失敗');
                                    }
                                })
                                .catch(() => { saving = false; alert('更新失敗'); });
                            " class="flex items-center gap-2">
                                <input type="text" x-model="name" required maxlength="255"
                                       class="text-sm px-2 py-1 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500 w-40">
                                <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 text-xs" x-text="saving ? '...' : '儲存'"></button>
                                <button type="button" @click="editing = false; name = '{{ auth()->user()->name }}'" class="text-gray-500 hover:text-gray-700 text-xs">取消</button>
                            </form>
                        </template>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">電子郵件</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ auth()->user()->email }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">會員等級</dt>
                    <dd class="mt-1">
                        @if(auth()->user()->roles->isNotEmpty())
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ auth()->user()->roles->first()->display_name }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                訪客
                            </span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">帳號狀態</dt>
                    <dd class="mt-1">
                        @if(auth()->user()->is_email_verified)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Email驗證
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                Email未驗證
                            </span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">註冊時間</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ auth()->user()->created_at->timezone('Asia/Taipei')->format('Y-m-d H:i') }} (GMT+8)</dd>
                </div>
                @if(auth()->user()->last_password_change_at)
                <div>
                    <dt class="text-sm font-medium text-gray-500">上次密碼變更</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ auth()->user()->last_password_change_at->timezone('Asia/Taipei')->format('Y-m-d H:i') }} (GMT+8)</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>

    <!-- Password Change Section (T123) -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">密碼設定</h2>
            <p class="mt-1 text-sm text-gray-500">定期更改密碼以保護您的帳號安全</p>
        </div>
        <div class="px-6 py-4">
            <form action="{{ route('settings.password') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">
                        目前密碼 <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="current_password" id="current_password" required
                           aria-label="目前密碼"
                           aria-required="true"
                           aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}"
                           @error('current_password') aria-describedby="current-password-error" @enderror
                           class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('current_password') border-red-500 @enderror"
                           placeholder="請輸入目前密碼">
                    @error('current_password')
                        <p id="current-password-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                        新密碼 <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password" id="new_password" required
                           aria-label="新密碼"
                           aria-required="true"
                           aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                           aria-describedby="password-requirements @error('password') password-error @enderror"
                           class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('password') border-red-500 @enderror"
                           placeholder="至少8個字元，包含大小寫字母、數字和特殊符號">

                    <!-- Password Strength Indicator -->
                    <div class="mt-2 space-y-1">
                        <div class="flex items-center text-xs text-gray-500">
                            <span id="strength-indicator" class="flex items-center">
                                <span class="inline-block w-2 h-2 rounded-full mr-1 bg-gray-300"></span>
                                <span id="strength-text">輸入密碼以檢查強度</span>
                            </span>
                        </div>
                        <div id="password-requirements" class="text-xs text-gray-600 space-y-0.5">
                            <p id="req-length" class="flex items-center">
                                <span class="inline-block w-4 text-gray-400">○</span> 至少8個字元
                            </p>
                            <p id="req-uppercase" class="flex items-center">
                                <span class="inline-block w-4 text-gray-400">○</span> 至少1個大寫字母 (A-Z)
                            </p>
                            <p id="req-lowercase" class="flex items-center">
                                <span class="inline-block w-4 text-gray-400">○</span> 至少1個小寫字母 (a-z)
                            </p>
                            <p id="req-number" class="flex items-center">
                                <span class="inline-block w-4 text-gray-400">○</span> 至少1個數字 (0-9)
                            </p>
                            <p id="req-special" class="flex items-center">
                                <span class="inline-block w-4 text-gray-400">○</span> 至少1個特殊符號 (!@#$%等)
                            </p>
                        </div>
                    </div>

                    @error('password')
                        <p id="password-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                        確認新密碼 <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                           aria-label="確認新密碼"
                           aria-required="true"
                           class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                           placeholder="請再次輸入新密碼">
                    <p id="confirmation-feedback" class="mt-1 text-sm hidden"></p>
                </div>

                <div class="pt-4">
                    <button type="submit" id="password-submit-button"
                            aria-label="提交密碼更新表單"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        更新密碼
                    </button>
                    <p id="submit-feedback" class="mt-2 text-sm text-gray-600 hidden"></p>
                </div>
            </form>
        </div>
    </div>

    <!-- YouTube API Key Section (T124) -->
    @if(auth()->user()->roles->contains('name', 'regular_member') ||
        auth()->user()->roles->contains('name', 'premium_member') ||
        auth()->user()->roles->contains('name', 'website_editor') ||
        auth()->user()->roles->contains('name', 'administrator'))
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">YouTube API 金鑰</h2>
            <p class="mt-1 text-sm text-gray-500">設定您的 YouTube API 金鑰以使用影片更新功能</p>
        </div>
        <div class="px-6 py-4">
            <form action="{{ route('settings.api-key') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Two-column layout: Instructions on left, Privacy notice on right -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- Left: How to get API key -->
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">如何取得 YouTube API 金鑰？</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <ol class="list-decimal list-inside space-y-1">
                                        <li>前往 <a href="https://console.cloud.google.com/" target="_blank" class="underline">Google Cloud Console</a></li>
                                        <li>建立新專案或選擇現有專案</li>
                                        <li>啟用 YouTube Data API v3</li>
                                        <li>建立 API 金鑰憑證</li>
                                        <li>複製金鑰並貼到下方欄位</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Privacy notice -->
                    <div class="bg-gray-50 border border-gray-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">隱私保護</h3>
                                <div class="mt-2 text-sm text-gray-700 leading-relaxed space-y-2">
                                    <p>您的 YouTube API Key 只用於您自行使用，抓取影片／留言資料。我們不會保存、分享或傳送您的 API Key 至任何第三方。</p>
                                    <p>如有疑慮，請在使用完成後前往 Google 網站刪除 / 停用該 API Key。</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="youtube_api_key" class="block text-sm font-medium text-gray-700 mb-1">
                        API 金鑰
                        @if(auth()->user()->youtube_api_key)
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                已設定
                            </span>
                        @else
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                未設定
                            </span>
                        @endif
                    </label>
                    <input type="text" name="youtube_api_key" id="youtube_api_key"
                           value="{{ old('youtube_api_key', auth()->user()->youtube_api_key ? '••••••••••••••••' : '') }}"
                           aria-label="YouTube API 金鑰"
                           aria-invalid="{{ $errors->has('youtube_api_key') ? 'true' : 'false' }}"
                           aria-describedby="youtube-api-key-help @error('youtube_api_key') youtube-api-key-error @enderror"
                           class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('youtube_api_key') border-red-500 @enderror"
                           placeholder="AIza...">
                    <p id="youtube-api-key-help" class="mt-1 text-xs text-gray-500">
                        留空以保留現有金鑰。若要更新，請輸入新的 API 金鑰。
                    </p>
                    @error('youtube_api_key')
                        <p id="youtube-api-key-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-4">
                    <button type="submit"
                            aria-label="提交 API 金鑰儲存表單"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        儲存 API 金鑰
                    </button>
                    @if(auth()->user()->youtube_api_key)
                        <button type="button" onclick="if(confirm('確定要移除 API 金鑰嗎？移除後將無法使用影片更新功能。')) { document.getElementById('remove-api-key-form').submit(); }"
                                aria-label="移除 API 金鑰"
                                class="ml-3 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            移除金鑰
                        </button>
                    @endif
                </div>
            </form>

            @if(auth()->user()->youtube_api_key)
            <form id="remove-api-key-form" action="{{ route('settings.api-key.remove') }}" method="POST" class="hidden">
                @csrf
            </form>
            @endif
        </div>
    </div>
    @endif

    <!-- Points System Section (T012-T015, T109-T113: All logged-in users) -->
    @auth
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">積分系統</h2>
            <p class="mt-1 text-sm text-gray-500">透過回報貼文或 U-API 導入影片累積積分，可兌換高級會員期限延長</p>
        </div>
        <div class="px-6 py-4">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                <!-- T013: Display current points balance -->
                <div>
                    <dt class="text-sm font-medium text-gray-500">目前積分</dt>
                    <dd class="mt-1 text-3xl font-bold text-blue-600">{{ auth()->user()->points }}</dd>
                </div>
                <!-- T014: Display premium expiration date (premium only) -->
                @if(auth()->user()->isPremium())
                <div>
                    <dt class="text-sm font-medium text-gray-500">高級會員到期</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ auth()->user()->premium_expires_at->timezone('Asia/Taipei')->format('Y-m-d H:i') }} (GMT+8)
                    </dd>
                </div>
                @endif
            </dl>

            <!-- Redemption / Point Earning Section (T021-T024, T109-T113) -->
            <div class="mt-6 pt-6 border-t border-gray-200" x-data="{
                showConfirm: false,
                showLogs: false,
                logs: [],
                loading: false,
                error: null,
                async fetchLogs() {
                    this.loading = true;
                    this.error = null;
                    try {
                        const response = await fetch('{{ route('settings.points.logs') }}', {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (!response.ok) {
                            const data = await response.json();
                            throw new Error(data.error || '載入失敗');
                        }
                        const data = await response.json();
                        this.logs = data.data;
                    } catch (e) {
                        this.error = e.message || '載入積分記錄時發生錯誤';
                    } finally {
                        this.loading = false;
                    }
                }
            }">
                @if(auth()->user()->isPremium())
                    <!-- Premium Member: Show Redemption Section -->
                    <h3 class="text-sm font-medium text-gray-900 mb-4">兌換積分</h3>

                    <!-- Batch redemption info (Updated 2025-12-27) -->
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3 text-sm text-blue-700">
                                <p>兌換規則：<strong>{{ $pointsPerDay }} 積分 = 1 天</strong> 高級會員期限延長</p>
                                <p class="mt-1">點擊兌換後，將一次性扣除所有可兌換的積分。</p>
                            </div>
                        </div>
                    </div>

                @if($redeemableDays > 0)
                    <!-- Batch redemption preview -->
                    <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-4">
                        <div class="text-sm text-green-700">
                            <p>您目前有 <strong>{{ auth()->user()->points }} 積分</strong></p>
                            <p class="mt-1">可兌換：<strong class="text-green-800 text-lg">{{ $redeemableDays }} 天</strong>（扣除 {{ $pointsToDeduct }} 積分，剩餘 {{ $remainingPoints }} 積分）</p>
                        </div>
                    </div>

                    <!-- Batch redemption button -->
                    <button @click="showConfirm = true"
                            type="button"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        兌換全部（{{ $pointsToDeduct }} 積分 → {{ $redeemableDays }} 天）
                    </button>

                    <!-- Confirmation dialog (Alpine.js) -->
                    <div x-show="showConfirm" x-cloak
                         class="fixed inset-0 z-50 overflow-y-auto"
                         aria-labelledby="modal-title" role="dialog" aria-modal="true">
                        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                            <!-- Background overlay -->
                            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                                 @click="showConfirm = false"
                                 aria-hidden="true"></div>

                            <!-- Modal panel -->
                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                            <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                                <div class="sm:flex sm:items-start">
                                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                            確認兌換積分
                                        </h3>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-500">
                                                您確定要兌換嗎？
                                            </p>
                                            <div class="mt-3 bg-gray-50 rounded-md p-3">
                                                <p class="text-sm text-gray-700">
                                                    扣除積分：<strong class="text-gray-900">{{ $pointsToDeduct }} 積分</strong>
                                                </p>
                                                <p class="text-sm text-gray-700 mt-1">
                                                    延長天數：<strong class="text-green-600">{{ $redeemableDays }} 天</strong>
                                                </p>
                                                <p class="text-sm text-gray-700 mt-1">
                                                    剩餘積分：<strong class="text-gray-900">{{ $remainingPoints }} 積分</strong>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                    <form action="{{ route('settings.points.redeem') }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                            確認兌換
                                        </button>
                                    </form>
                                    <button type="button"
                                            @click="showConfirm = false"
                                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                                            取消
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Disabled button when points < pointsPerDay -->
                    <button type="button"
                            disabled
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gray-400 cursor-not-allowed">
                        積分不足
                    </button>
                    <!-- Insufficient points message -->
                    <p class="mt-2 text-sm text-red-600">
                        積分不足，需要至少 {{ $pointsPerDay }} 積分才能兌換 1 天。目前還差 {{ $pointsPerDay - auth()->user()->points }} 積分。
                    </p>
                @endif

                @else
                    <!-- T110-T113: Regular Member Section -->
                    <h3 class="text-sm font-medium text-gray-900 mb-4">獲取積分</h3>

                    <!-- T111: Upgrade prompt -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3 text-sm text-yellow-700">
                                <p class="font-medium">需升級為高級會員才能兌換積分</p>
                                <p class="mt-1">升級後即可使用積分延長會員期限。</p>
                            </div>
                        </div>
                    </div>

                    <!-- T112: Points earning info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3 text-sm text-blue-700">
                                <p class="font-medium">如何獲得積分？</p>
                                <ul class="mt-2 list-disc list-inside space-y-1">
                                    <li>透過 U-API 導入影片可獲得積分（+1 積分/影片）</li>
                                    <li>回報貼文可獲得積分（+1 積分/貼文）</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- T031: View point logs button -->
                <button @click="showLogs = true"
                        type="button"
                        class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    查看記錄
                </button>
            </div>

            <!-- T032-T034: Point logs modal (Alpine.js) -->
            <div x-show="showLogs" x-cloak
                 x-init="$watch('showLogs', value => { if (value && logs.length === 0) { fetchLogs(); } })"
                 class="fixed inset-0 z-50 overflow-y-auto"
                 aria-labelledby="logs-modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <!-- Background overlay -->
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                         @click="showLogs = false"
                         aria-hidden="true"></div>

                    <!-- Modal panel -->
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="logs-modal-title">
                                    積分記錄
                                </h3>
                                <div class="mt-4">
                                    <!-- Loading state -->
                                    <div x-show="loading" class="text-center py-8">
                                        <svg class="animate-spin h-8 w-8 mx-auto text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500">載入中...</p>
                                    </div>

                                    <!-- Error state -->
                                    <div x-show="error && !loading" class="text-center py-8">
                                        <svg class="h-12 w-12 mx-auto text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <p class="mt-2 text-sm text-red-600" x-text="error"></p>
                                    </div>

                                    <!-- T034: Empty state -->
                                    <div x-show="!loading && !error && logs.length === 0" class="text-center py-8">
                                        <svg class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500">尚無積分記錄</p>
                                    </div>

                                    <!-- T033: Logs list -->
                                    <div x-show="!loading && !error && logs.length > 0" class="max-h-96 overflow-y-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">時間</th>
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">類型</th>
                                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">積分</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <template x-for="log in logs" :key="log.id">
                                                    <tr>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500" x-text="log.created_at_display"></td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900" x-text="log.action_display"></td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right"
                                                            :class="log.amount > 0 ? 'text-green-600' : 'text-red-600'"
                                                            x-text="(log.amount > 0 ? '+' : '') + log.amount"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                            <button type="button"
                                    @click="showLogs = false"
                                    class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm">
                                關閉
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Account Actions -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">帳號操作</h2>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-900">登出</h3>
                    <p class="text-sm text-gray-500">登出目前帳號</p>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        登出
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('new_password');
    const confirmationInput = document.getElementById('password_confirmation');
    const strengthIndicator = document.querySelector('#strength-indicator .inline-block');
    const strengthText = document.getElementById('strength-text');
    const confirmationFeedback = document.getElementById('confirmation-feedback');
    const submitButton = document.getElementById('password-submit-button');
    const submitFeedback = document.getElementById('submit-feedback');

    // Only run validation if password fields exist (they're in password change section)
    if (!passwordInput || !confirmationInput) return;

    const requirements = {
        length: {el: document.getElementById('req-length'), regex: /.{8,}/},
        uppercase: {el: document.getElementById('req-uppercase'), regex: /[A-Z]/},
        lowercase: {el: document.getElementById('req-lowercase'), regex: /[a-z]/},
        number: {el: document.getElementById('req-number'), regex: /[0-9]/},
        special: {el: document.getElementById('req-special'), regex: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/}
    };

    let allRequirementsMet = false;
    let passwordsMatch = false;

    function validatePassword() {
        const password = passwordInput.value;
        let metCount = 0;

        // Check each requirement
        for (const [key, req] of Object.entries(requirements)) {
            const met = req.regex.test(password);
            if (met) {
                metCount++;
                req.el.querySelector('.inline-block').textContent = '✓';
                req.el.querySelector('.inline-block').classList.remove('text-gray-400');
                req.el.querySelector('.inline-block').classList.add('text-green-600');
            } else {
                req.el.querySelector('.inline-block').textContent = '○';
                req.el.querySelector('.inline-block').classList.remove('text-green-600');
                req.el.querySelector('.inline-block').classList.add('text-gray-400');
            }
        }

        // Update strength indicator
        if (metCount === 0) {
            strengthIndicator.className = 'inline-block w-2 h-2 rounded-full mr-1 bg-gray-300';
            strengthText.textContent = '輸入密碼以檢查強度';
            allRequirementsMet = false;
        } else if (metCount <= 2) {
            strengthIndicator.className = 'inline-block w-2 h-2 rounded-full mr-1 bg-red-500';
            strengthText.textContent = '密碼強度：弱';
            allRequirementsMet = false;
        } else if (metCount <= 4) {
            strengthIndicator.className = 'inline-block w-2 h-2 rounded-full mr-1 bg-yellow-500';
            strengthText.textContent = '密碼強度：中等';
            allRequirementsMet = false;
        } else {
            strengthIndicator.className = 'inline-block w-2 h-2 rounded-full mr-1 bg-green-500';
            strengthText.textContent = '密碼強度：強';
            allRequirementsMet = true;
        }

        validateConfirmation();
        updateSubmitButton();
    }

    function validateConfirmation() {
        const password = passwordInput.value;
        const confirmation = confirmationInput.value;

        if (confirmation.length === 0) {
            // No input yet, hide feedback
            confirmationFeedback.classList.add('hidden');
            confirmationInput.classList.remove('border-red-500', 'border-green-500');
            confirmationInput.classList.add('border-gray-300');
            passwordsMatch = false;
        } else if (password === confirmation) {
            // Passwords match
            confirmationFeedback.textContent = '✓ 密碼相符';
            confirmationFeedback.classList.remove('hidden', 'text-red-600');
            confirmationFeedback.classList.add('text-green-600');
            confirmationInput.classList.remove('border-red-500', 'border-gray-300');
            confirmationInput.classList.add('border-green-500');
            passwordsMatch = true;
        } else {
            // Passwords don't match
            confirmationFeedback.textContent = '✗ 密碼不相符';
            confirmationFeedback.classList.remove('hidden', 'text-green-600');
            confirmationFeedback.classList.add('text-red-600');
            confirmationInput.classList.remove('border-green-500', 'border-gray-300');
            confirmationInput.classList.add('border-red-500');
            passwordsMatch = false;
        }

        updateSubmitButton();
    }

    function updateSubmitButton() {
        const currentPassword = document.getElementById('current_password').value;

        if (!currentPassword) {
            submitButton.disabled = true;
            submitFeedback.textContent = '請輸入目前密碼';
            submitFeedback.classList.remove('hidden', 'text-green-600');
            submitFeedback.classList.add('text-gray-600');
        } else if (!allRequirementsMet) {
            submitButton.disabled = true;
            submitFeedback.textContent = '請確保新密碼符合所有要求';
            submitFeedback.classList.remove('hidden', 'text-green-600');
            submitFeedback.classList.add('text-gray-600');
        } else if (!passwordsMatch) {
            submitButton.disabled = true;
            submitFeedback.textContent = '請確保兩次輸入的密碼相同';
            submitFeedback.classList.remove('hidden', 'text-green-600');
            submitFeedback.classList.add('text-gray-600');
        } else {
            submitButton.disabled = false;
            submitFeedback.textContent = '✓ 可以提交';
            submitFeedback.classList.remove('hidden', 'text-gray-600');
            submitFeedback.classList.add('text-green-600');
        }
    }

    // Add event listeners
    passwordInput.addEventListener('input', validatePassword);
    confirmationInput.addEventListener('input', validateConfirmation);
    document.getElementById('current_password').addEventListener('input', updateSubmitButton);

    // Initial validation
    updateSubmitButton();
});
</script>
@endsection
