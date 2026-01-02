@extends('layouts.app')

@section('title', '升級會員 - DISINFO_SCANNER')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    {{-- Page Header --}}
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">升級為高級會員</h1>
        <p class="mt-2 text-lg text-gray-600">選擇適合您的方案，享受更多功能</p>
    </div>

    {{-- Email Warning Banner --}}
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8 rounded-r-lg">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-yellow-800">
                    重要提醒：將導向 Portaly 進行付款，務必輸入與本站相同的 Email 帳號。
                </p>
                <p class="mt-1 text-sm text-yellow-700">
                    您的帳號 Email：<span class="font-semibold">{{ auth()->user()->email }}</span>
                </p>
            </div>
        </div>
    </div>

    {{-- Premium Features --}}
    <div class="bg-indigo-50 rounded-lg p-6 mb-8">
        <h2 class="text-lg font-semibold text-indigo-900 mb-4">高級會員專屬功能</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="flex items-start">
                <svg class="h-5 w-5 text-indigo-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-indigo-800">使用 YouTube 官方 API 匯入（無限制）</span>
            </div>
            <div class="flex items-start">
                <svg class="h-5 w-5 text-indigo-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-indigo-800">所有搜尋功能</span>
            </div>
            <div class="flex items-start">
                <svg class="h-5 w-5 text-indigo-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-indigo-800">取得 Threads 回報權限</span>
            </div>
            <div class="flex items-start">
                <svg class="h-5 w-5 text-indigo-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-indigo-800">查看被回報的 Threads 貼文資料庫</span>
            </div>
        </div>
    </div>

    {{-- Product Grid --}}
    @if($products->count() > 0)
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($products as $product)
                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $product->name }}</h3>
                        <p class="text-3xl font-bold text-indigo-600 mb-2">
                            NT$ {{ number_format($product->price) }}
                        </p>
                        @if($product->duration_days)
                            <p class="text-gray-600 mb-4">
                                <span class="inline-flex items-center">
                                    <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ $product->duration_days }} 天
                                </span>
                            </p>
                        @endif
                        <a href="{{ $product->portaly_url }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="block w-full bg-indigo-600 text-white text-center py-3 rounded-lg hover:bg-indigo-700 transition-colors duration-200 font-medium">
                            立即購買
                            <svg class="inline-block h-4 w-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-12 bg-white rounded-lg shadow">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900">目前沒有可購買的商品</h3>
            <p class="mt-1 text-sm text-gray-500">請稍後再回來查看</p>
        </div>
    @endif

    {{-- Help Section --}}
    <div class="mt-8 text-center text-sm text-gray-500">
        <p>付款完成後，系統將自動升級您的帳號。</p>
        <p class="mt-1">如有問題，請聯繫客服。</p>
    </div>
</div>
@endsection
