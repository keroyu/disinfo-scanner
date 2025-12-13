@extends('layouts.app')

@section('title', '會員註冊 - DISINFO_SCANNER')

@section('content')
<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                註冊新帳號
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                或者
                <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500">
                    登入現有帳號
                </a>
            </p>
        </div>

        <form class="mt-8 space-y-6" action="{{ route('register.submit') }}" method="POST">
            @csrf

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
                                註冊失敗
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
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                        姓名
                    </label>
                    <input id="name" name="name" type="text" required autofocus
                           value="{{ old('name') }}"
                           aria-label="姓名"
                           aria-required="true"
                           aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                           @error('name') aria-describedby="name-error" @enderror
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm @error('name') border-red-500 @enderror"
                           placeholder="請輸入您的姓名">
                    @error('name')
                        <p id="name-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        電子郵件 <span class="text-red-500">*</span>
                    </label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="{{ old('email') }}"
                           aria-label="電子郵件"
                           aria-required="true"
                           aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                           @error('email') aria-describedby="email-error" @enderror
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm @error('email') border-red-500 @enderror"
                           placeholder="example@email.com">
                    @error('email')
                        <p id="email-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            <div class="flex items-center">
                <input id="terms" name="terms" type="checkbox" required
                       aria-label="我同意服務條款和隱私政策"
                       aria-required="true"
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="terms" class="ml-2 block text-sm text-gray-900">
                    我同意
                    <a href="#" class="text-blue-600 hover:text-blue-500">服務條款</a>
                    和
                    <a href="#" class="text-blue-600 hover:text-blue-500">隱私政策</a>
                </label>
            </div>

            <div>
                <button type="submit"
                        aria-label="提交註冊表單"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3" aria-hidden="true">
                        <svg class="h-5 w-5 text-blue-500 group-hover:text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    註冊帳號
                </button>
            </div>

            <div class="text-sm text-center text-gray-600">
                <p>註冊後，系統將發送驗證郵件至您的信箱</p>
                <p class="mt-1">請於24小時內點擊驗證連結並設定密碼</p>
            </div>
        </form>
    </div>
</div>
@endsection
