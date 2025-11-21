@extends('layouts.app')

@section('title', '帳號設定 - DISINFO_SCANNER')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">帳號設定</h1>
        <p class="mt-2 text-sm text-gray-600">管理您的帳號資訊和偏好設定</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-md bg-green-50 p-4">
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

    <!-- Account Information Card -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">帳號資訊</h2>
        </div>
        <div class="px-6 py-4">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500">姓名</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ auth()->user()->name }}</dd>
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
                                已驗證
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                未驗證
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
                           class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('current_password') border-red-500 @enderror"
                           placeholder="請輸入目前密碼">
                    @error('current_password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                        新密碼 <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password" id="new_password" required
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
                        <div class="text-xs text-gray-600 space-y-0.5">
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
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                        確認新密碼 <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                           class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                           placeholder="請再次輸入新密碼">
                    <p id="confirmation-feedback" class="mt-1 text-sm hidden"></p>
                </div>

                <div class="pt-4">
                    <button type="submit" id="password-submit-button"
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
        auth()->user()->roles->contains('name', 'paid_member') ||
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
                           class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('youtube_api_key') border-red-500 @enderror"
                           placeholder="AIza...">
                    <p class="mt-1 text-xs text-gray-500">
                        留空以保留現有金鑰。若要更新，請輸入新的 API 金鑰。
                    </p>
                    @error('youtube_api_key')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-4">
                    <button type="submit"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        儲存 API 金鑰
                    </button>
                    @if(auth()->user()->youtube_api_key)
                        <button type="button" onclick="if(confirm('確定要移除 API 金鑰嗎？移除後將無法使用影片更新功能。')) { document.getElementById('remove-api-key-form').submit(); }"
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
