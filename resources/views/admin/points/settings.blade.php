<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>積分設定 - 管理後台 - DISINFO SCANNER</title>
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

            <!-- Settings Content -->
            <main class="flex-1 p-6">
                <div class="max-w-4xl mx-auto">
                    <!-- Page Title -->
                    <div class="mb-6">
                        <h1 class="text-3xl font-bold text-gray-900">積分設定</h1>
                        <p class="mt-1 text-sm text-gray-600">管理積分系統的兌換設定</p>
                    </div>

                    <!-- T055: Success Message -->
                    @if (session('success'))
                        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg" role="alert">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span>{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif

                    <!-- T056: Validation Error Display -->
                    @if ($errors->any())
                        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg" role="alert">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                <ul class="list-disc list-inside">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <!-- Settings Card -->
                    <div class="bg-white rounded-lg shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">積分兌換設定</h2>
                            <p class="mt-1 text-sm text-gray-500">設定用戶使用多少積分可兌換 1 天高級會員期限</p>
                        </div>

                        <form action="{{ route('admin.points.settings.update') }}" method="POST" class="p-6">
                            @csrf

                            <!-- T053: Current Value Display (Updated 2025-12-27: X points = 1 day) -->
                            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-600">目前設定</p>
                                        <p class="mt-1 text-2xl font-bold text-gray-900">
                                            {{ $currentPoints }} 積分 = 1 天
                                        </p>
                                    </div>
                                    <div class="p-3 bg-blue-100 rounded-full">
                                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                @if ($lastUpdated)
                                    <p class="mt-2 text-xs text-gray-500">
                                        最後更新: {{ \Carbon\Carbon::parse($lastUpdated)->timezone('Asia/Taipei')->format('Y-m-d H:i') }} (GMT+8)
                                    </p>
                                @endif
                            </div>

                            <!-- T054: Form for Updating Points Per Day (Updated 2025-12-27) -->
                            <div class="mb-6">
                                <label for="points_per_day" class="block text-sm font-medium text-gray-700 mb-2">
                                    每日所需積分
                                </label>
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center">
                                        <input
                                            type="number"
                                            id="points_per_day"
                                            name="points_per_day"
                                            value="{{ old('points_per_day', $currentPoints) }}"
                                            min="1"
                                            max="1000"
                                            class="w-24 px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('points_per_day') border-red-500 @enderror"
                                            required
                                        >
                                        <span class="text-gray-600 ml-2">積分 = 1 天</span>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-gray-500">
                                    有效範圍: 1 - 1000 積分
                                </p>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex items-center justify-end gap-4">
                                <button
                                    type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                                >
                                    儲存設定
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Info Box -->
                    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="text-sm text-blue-700">
                                <p class="font-medium">設定說明</p>
                                <ul class="mt-1 list-disc list-inside space-y-1">
                                    <li>此設定會影響所有用戶的積分兌換結果</li>
                                    <li>變更會立即生效，無需重新啟動服務</li>
                                    <li>所有設定變更都會記錄在審計日誌中</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
