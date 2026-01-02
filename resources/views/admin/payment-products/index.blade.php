<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>付款商品 - 管理後台 - DISINFO SCANNER</title>
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
                <div class="max-w-7xl mx-auto">
                    <!-- Page Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">付款商品</h1>
                            <p class="mt-1 text-sm text-gray-600">管理 Portaly 付款商品設定</p>
                        </div>
                        <a href="{{ route('admin.payment-products.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            新增商品
                        </a>
                    </div>

                    <!-- Success Message -->
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

                    <!-- Status Filter -->
                    <div class="mb-4 flex items-center gap-4">
                        <span class="text-sm text-gray-600">狀態篩選:</span>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.payment-products.index', ['status' => 'all']) }}"
                               class="px-3 py-1 text-sm rounded-full {{ $currentStatus === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                                全部
                            </a>
                            <a href="{{ route('admin.payment-products.index', ['status' => 'active']) }}"
                               class="px-3 py-1 text-sm rounded-full {{ $currentStatus === 'active' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                                啟用中
                            </a>
                            <a href="{{ route('admin.payment-products.index', ['status' => 'inactive']) }}"
                               class="px-3 py-1 text-sm rounded-full {{ $currentStatus === 'inactive' ? 'bg-yellow-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                                已停用
                            </a>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-[60px] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="w-[200px] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">商品名稱</th>
                                    <th class="w-[180px] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product ID</th>
                                    <th class="w-[100px] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">連結</th>
                                    <th class="w-[100px] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">價格</th>
                                    <th class="w-[80px] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">天數</th>
                                    <th class="w-[100px] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
                                    <th class="w-[150px] px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($products as $product)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $product->id }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $product->action_type }}</div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <code class="text-xs bg-gray-100 px-2 py-1 rounded" title="{{ $product->portaly_product_id }}">
                                                {{ Str::limit($product->portaly_product_id, 20) }}
                                            </code>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <a href="{{ $product->portaly_url }}" target="_blank" rel="noopener noreferrer"
                                               class="text-blue-600 hover:text-blue-800" title="開啟 Portaly 頁面">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                </svg>
                                            </a>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $product->formatted_price }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $product->formatted_duration }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            @if ($product->status === 'active')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    啟用中
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    已停用
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end gap-2">
                                                <!-- Edit -->
                                                <a href="{{ route('admin.payment-products.edit', $product) }}"
                                                   class="text-blue-600 hover:text-blue-900" title="編輯">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                </a>

                                                <!-- Toggle Status -->
                                                <form action="{{ route('admin.payment-products.toggle-status', $product) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                            class="{{ $product->status === 'active' ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900' }}"
                                                            title="{{ $product->status === 'active' ? '停用' : '啟用' }}">
                                                        @if ($product->status === 'active')
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                                            </svg>
                                                        @else
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                        @endif
                                                    </button>
                                                </form>

                                                <!-- Delete -->
                                                <form action="{{ route('admin.payment-products.destroy', $product) }}" method="POST" class="inline"
                                                      onsubmit="return confirm('確定要刪除「{{ $product->name }}」嗎？此操作無法復原。')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900" title="刪除">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-12 text-center text-gray-500">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            </svg>
                                            <p class="mt-2">目前沒有付款商品</p>
                                            <a href="{{ route('admin.payment-products.create') }}" class="mt-2 inline-block text-blue-600 hover:underline">
                                                新增第一個商品
                                            </a>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Info Box -->
                    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="text-sm text-blue-700">
                                <p class="font-medium">使用說明</p>
                                <ul class="mt-1 list-disc list-inside space-y-1">
                                    <li>Product ID 需與 Portaly 後台設定的 ID 一致</li>
                                    <li>停用的商品不會顯示在用戶升級頁面，但 Webhook 仍會接收並記錄</li>
                                    <li>刪除商品為軟刪除，付款記錄會保留商品關聯</li>
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
