@extends('layouts.app')

@section('title', ($purpose === 'register' ? '輸入註冊驗證碼' : '輸入登入驗證碼') . ' - DISINFO_SCANNER')

@section('content')
<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                {{ $purpose === 'register' ? '驗證您的電子郵件' : '輸入登入驗證碼' }}
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                驗證碼已發送至<br>
                <span class="font-medium text-gray-900">{{ $email }}</span>
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4" role="status">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">{{ session('status') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4" role="alert">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <div class="text-sm text-red-700">
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

        {{-- OTP verification form --}}
        @if($purpose === 'register')
            <form class="mt-8 space-y-6" action="{{ route('register.otp.verify') }}" method="POST">
        @else
            <form class="mt-8 space-y-6" action="{{ route('login.otp.verify') }}" method="POST">
        @endif
            @csrf

            <div>
                <label for="code" class="block text-sm font-medium text-gray-700 mb-1">
                    6 位數驗證碼 <span class="text-red-500">*</span>
                </label>
                <input id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}"
                       maxlength="6" required autofocus autocomplete="one-time-code"
                       aria-label="6 位數驗證碼"
                       aria-required="true"
                       aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}"
                       class="appearance-none block w-full px-3 py-3 border border-gray-300 placeholder-gray-400 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-center text-2xl font-mono tracking-widest sm:text-sm @error('code') border-red-500 @enderror"
                       placeholder="000000">
                @error('code')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <button type="submit"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    {{ $purpose === 'register' ? '驗證並建立帳號' : '驗證並登入' }}
                </button>
            </div>
        </form>

        {{-- Resend form --}}
        <div class="text-center">
            <p class="text-sm text-gray-600">沒有收到驗證碼？</p>
            @if($purpose === 'register')
                <form action="{{ route('register.otp.resend') }}" method="POST" class="inline">
            @else
                <form action="{{ route('login.otp.resend') }}" method="POST" class="inline">
            @endif
                @csrf
                <button type="submit" class="mt-1 font-medium text-blue-600 hover:text-blue-500 text-sm">
                    重新發送驗證碼
                </button>
            </form>
        </div>

        <div class="text-center">
            <p class="text-xs text-gray-500">驗證碼有效期限為 10 分鐘，每小時最多可發送 3 次</p>
        </div>
    </div>
</div>
@endsection
