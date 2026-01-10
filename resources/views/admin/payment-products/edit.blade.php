<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>編輯付款商品 - 管理後台 - DISINFO SCANNER</title>
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

            <!-- Content -->
            <main class="flex-1 p-6">
                <div class="max-w-3xl mx-auto">
                    <!-- Page Header -->
                    <div class="mb-6">
                        <nav class="flex mb-4" aria-label="Breadcrumb">
                            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                                <li class="inline-flex items-center">
                                    <a href="{{ route('admin.payment-products.index') }}" class="text-gray-600 hover:text-blue-600">
                                        付款商品
                                    </a>
                                </li>
                                <li>
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="ml-1 text-gray-500">編輯商品</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <h1 class="text-3xl font-bold text-gray-900">編輯付款商品</h1>
                        <p class="mt-1 text-sm text-gray-600">修改「{{ $product->name }}」的設定</p>
                    </div>

                    <!-- Validation Errors -->
                    @if ($errors->any())
                        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg" role="alert">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
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

                    <!-- Form -->
                    <div class="bg-white rounded-lg shadow-sm">
                        <form action="{{ route('admin.payment-products.update', $product) }}" method="POST" class="p-6 space-y-6">
                            @csrf
                            @method('PUT')

                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                    商品名稱 <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                                       placeholder="例: 30天高級會員"
                                       required>
                                <p class="mt-1 text-xs text-gray-500">顯示在用戶升級頁面的商品名稱</p>
                            </div>

                            <!-- Portaly Product ID -->
                            <div>
                                <label for="portaly_product_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Portaly Product ID <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="portaly_product_id" name="portaly_product_id" value="{{ old('portaly_product_id', $product->portaly_product_id) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('portaly_product_id') border-red-500 @enderror"
                                       placeholder="例: 07eMToUCpzTcsg8zKSDM"
                                       required>
                                <p class="mt-1 text-xs text-gray-500">從 Portaly 後台取得的商品 ID，用於 Webhook 匹配</p>
                            </div>

                            <!-- Portaly URL -->
                            <div>
                                <label for="portaly_url" class="block text-sm font-medium text-gray-700 mb-2">
                                    Portaly 連結 <span class="text-red-500">*</span>
                                </label>
                                <input type="url" id="portaly_url" name="portaly_url" value="{{ old('portaly_url', $product->portaly_url) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('portaly_url') border-red-500 @enderror"
                                       placeholder="https://portaly.cc/kyontw/product/07eMToUCpzTcsg8zKSDM"
                                       required>
                                <p class="mt-1 text-xs text-gray-500">用戶點擊購買後跳轉的 Portaly 商品頁面 URL</p>
                            </div>

                            <!-- Price and Currency -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                                        價格 <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">NT$</span>
                                        <input type="number" id="price" name="price" value="{{ old('price', $product->price) }}"
                                               class="w-full pl-12 pr-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('price') border-red-500 @enderror"
                                               placeholder="190"
                                               min="1"
                                               required>
                                    </div>
                                </div>
                                <div>
                                    <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">
                                        幣別
                                    </label>
                                    <select id="currency" name="currency"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="TWD" {{ old('currency', $product->currency) === 'TWD' ? 'selected' : '' }}>TWD (新台幣)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Duration Days -->
                            <div>
                                <label for="duration_days" class="block text-sm font-medium text-gray-700 mb-2">
                                    會員天數 <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="number" id="duration_days" name="duration_days" value="{{ old('duration_days', $product->duration_days) }}"
                                           class="w-32 px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('duration_days') border-red-500 @enderror"
                                           min="1"
                                           required>
                                    <span class="text-gray-600">天</span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">購買此商品後延長的會員期限天數</p>
                            </div>

                            <!-- Action Type -->
                            <div>
                                <label for="action_type" class="block text-sm font-medium text-gray-700 mb-2">
                                    動作類型 <span class="text-red-500">*</span>
                                </label>
                                <select id="action_type" name="action_type"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="extend_premium" {{ old('action_type', $product->action_type) === 'extend_premium' ? 'selected' : '' }}>
                                        延長高級會員 (extend_premium)
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Webhook 收到付款通知後執行的動作</p>
                            </div>

                            <!-- Status -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                    狀態 <span class="text-red-500">*</span>
                                </label>
                                <select id="status" name="status"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="active" {{ old('status', $product->status) === 'active' ? 'selected' : '' }}>啟用中</option>
                                    <option value="inactive" {{ old('status', $product->status) === 'inactive' ? 'selected' : '' }}>停用</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">停用的商品不會顯示在用戶升級頁面</p>
                            </div>

                            <!-- Meta Info -->
                            <div class="pt-4 border-t border-gray-200">
                                <div class="grid grid-cols-2 gap-4 text-sm text-gray-500">
                                    <div>
                                        <span class="font-medium">建立時間:</span>
                                        {{ $product->created_at->timezone('Asia/Taipei')->format('Y-m-d H:i') }} (GMT+8)
                                    </div>
                                    <div>
                                        <span class="font-medium">更新時間:</span>
                                        {{ $product->updated_at->timezone('Asia/Taipei')->format('Y-m-d H:i') }} (GMT+8)
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
                                <a href="{{ route('admin.payment-products.index') }}"
                                   class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                                    取消
                                </a>
                                <button type="submit"
                                        class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">
                                    儲存變更
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
