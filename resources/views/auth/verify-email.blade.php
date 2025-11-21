@extends('layouts.app')

@section('title', '驗證您的電子郵件 - DISINFO_SCANNER')

@section('content')
<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        @if (session('status') === 'verification-link-sent')
            <div class="rounded-md bg-green-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">
                            驗證郵件已重新發送！
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if (session('verified'))
            {{-- Email verification successful --}}
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100">
                    <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    電子郵件驗證成功！
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    您的帳號已成功驗證，現在可以開始使用系統功能
                </p>

                <div class="mt-8">
                    <a href="{{ route('login') }}"
                       class="w-full inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        前往登入
                    </a>
                </div>
            </div>
        @else
            {{-- Email verification pending --}}
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-yellow-100">
                    <svg class="h-10 w-10 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    請驗證您的電子郵件
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    我們已向您的電子郵件發送驗證連結
                </p>
                <p class="mt-1 text-center text-sm text-gray-600">
                    請檢查您的收件匣並點擊驗證連結
                </p>

                <div class="mt-6 border-t border-gray-200 pt-6">
                    <p class="text-sm text-gray-700 mb-4">
                        沒有收到驗證郵件？
                    </p>

                    @if ($errors->any())
                        <div class="mb-4 rounded-md bg-red-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-red-800">
                                        @foreach ($errors->all() as $error)
                                            {{ $error }}
                                        @endforeach
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('verification.resend') }}" class="space-y-4" id="resendForm">
                        @csrf

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 text-left">
                                電子郵件
                            </label>
                            <input id="email"
                                   name="email"
                                   type="email"
                                   required
                                   value="{{ old('email') }}"
                                   class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('email') border-red-500 @enderror"
                                   placeholder="example@email.com">

                            <!-- Real-time feedback message -->
                            <div id="emailFeedback" class="mt-2 text-sm hidden"></div>
                        </div>

                        <button type="submit"
                                id="resendButton"
                                class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            重新發送驗證郵件
                        </button>
                    </form>

                    <p class="mt-4 text-xs text-gray-500">
                        驗證連結將在發送後24小時內有效
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        每小時最多可重新發送3次
                    </p>
                </div>

                <div class="mt-6 border-t border-gray-200 pt-6">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-blue-600 hover:text-blue-500">
                            登出
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function() {
    const emailInput = document.getElementById('email');
    const emailFeedback = document.getElementById('emailFeedback');
    const resendButton = document.getElementById('resendButton');
    const resendForm = document.getElementById('resendForm');
    let checkTimeout;

    if (!emailInput) return;

    // Debounced email check function
    function checkEmailStatus() {
        const email = emailInput.value.trim();

        // Clear previous feedback
        emailFeedback.classList.add('hidden');
        emailFeedback.className = 'mt-2 text-sm hidden';

        // Don't check if email is empty or invalid format
        if (!email || !email.includes('@')) {
            resendButton.disabled = false;
            return;
        }

        // Show loading state
        emailFeedback.textContent = '檢查中...';
        emailFeedback.classList.remove('hidden');
        emailFeedback.classList.add('text-gray-600');

        // Make AJAX request to check status
        fetch('{{ route('verification.check-status') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ email: email })
        })
        .then(response => response.json())
        .then(data => {
            emailFeedback.classList.remove('hidden', 'text-gray-600', 'text-red-600', 'text-green-600', 'text-blue-600');

            if (!data.valid) {
                // Invalid email format
                emailFeedback.classList.add('text-red-600');
                emailFeedback.textContent = '⚠ ' + data.message;
                resendButton.disabled = true;
            } else if (!data.exists) {
                // Email not found
                emailFeedback.classList.add('text-red-600');
                emailFeedback.textContent = '⚠ ' + data.message;
                resendButton.disabled = true;
            } else if (data.verified) {
                // Already verified - show notice with login link
                emailFeedback.classList.add('text-blue-600', 'font-medium');
                emailFeedback.innerHTML = '✓ ' + data.message + ' <a href="{{ route('login') }}" class="underline hover:text-blue-700">前往登入</a>';
                resendButton.disabled = true;
            } else {
                // Not verified - can resend
                emailFeedback.classList.add('text-green-600');
                emailFeedback.textContent = '✓ ' + data.message;
                resendButton.disabled = false;
            }
        })
        .catch(error => {
            console.error('Email check failed:', error);
            emailFeedback.classList.add('hidden');
            resendButton.disabled = false;
        });
    }

    // Add event listener with debouncing
    emailInput.addEventListener('input', function() {
        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(checkEmailStatus, 500); // 500ms delay
    });

    // Check on page load if there's a value
    if (emailInput.value.trim()) {
        checkEmailStatus();
    }
})();
</script>
@endpush

@endsection
