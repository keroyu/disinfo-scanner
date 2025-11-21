@extends('layouts.app')

@section('title', '設定新密碼 - DISINFO_SCANNER')

@section('content')
<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                設定新密碼
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                請設定一個安全的新密碼
            </p>
        </div>

        <form class="mt-8 space-y-6" action="{{ route('password.update') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email ?? old('email') }}">

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                密碼重設失敗
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="rounded-md shadow-sm space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        電子郵件
                    </label>
                    <input id="email" name="email" type="email" autocomplete="email" required readonly
                           value="{{ $email ?? old('email') }}"
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 bg-gray-50 text-gray-900 rounded-md focus:outline-none sm:text-sm"
                           placeholder="example@email.com">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        新密碼 <span class="text-red-500">*</span>
                    </label>
                    <input id="password" name="password" type="password" required autofocus
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm @error('password') border-red-500 @enderror"
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
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                           placeholder="請再次輸入新密碼">
                </div>
            </div>

            <div>
                <button type="submit"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-blue-500 group-hover:text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    重設密碼
                </button>
            </div>

            <div class="text-center">
                <a href="{{ route('login') }}" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                    返回登入
                </a>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mt-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">
                            安全提醒
                        </h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>重設連結僅能使用一次</li>
                                <li>完成重設後，您將無法使用舊密碼登入</li>
                                <li>建議使用密碼管理器儲存您的密碼</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const strengthIndicator = document.querySelector('#strength-indicator .inline-block');
    const strengthText = document.getElementById('strength-text');

    const requirements = {
        length: {el: document.getElementById('req-length'), regex: /.{8,}/},
        uppercase: {el: document.getElementById('req-uppercase'), regex: /[A-Z]/},
        lowercase: {el: document.getElementById('req-lowercase'), regex: /[a-z]/},
        number: {el: document.getElementById('req-number'), regex: /[0-9]/},
        special: {el: document.getElementById('req-special'), regex: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/}
    };

    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let metCount = 0;

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
        } else if (metCount <= 2) {
            strengthIndicator.className = 'inline-block w-2 h-2 rounded-full mr-1 bg-red-500';
            strengthText.textContent = '密碼強度：弱';
        } else if (metCount <= 4) {
            strengthIndicator.className = 'inline-block w-2 h-2 rounded-full mr-1 bg-yellow-500';
            strengthText.textContent = '密碼強度：中等';
        } else {
            strengthIndicator.className = 'inline-block w-2 h-2 rounded-full mr-1 bg-green-500';
            strengthText.textContent = '密碼強度：強';
        }
    });
});
</script>
@endsection
