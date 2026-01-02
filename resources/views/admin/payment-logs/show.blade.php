<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>付款紀錄 #{{ $log->id }} - 管理後台 - DISINFO SCANNER</title>
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
                <div class="max-w-4xl mx-auto">
                    <!-- Page Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">付款紀錄 #{{ $log->id }}</h1>
                            <p class="mt-1 text-sm text-gray-600">訂單 ID: {{ $log->order_id }}</p>
                        </div>
                        <a href="{{ route('admin.payment-logs.index') }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            返回列表
                        </a>
                    </div>

                    <!-- Status Badge -->
                    <div class="mb-6">
                        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium {{ $log->status_badge_class }}">
                            {{ $log->status_label }}
                        </span>
                    </div>

                    <!-- Basic Info Card -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-medium text-gray-900">基本資訊</h2>
                        </div>
                        <div class="p-6">
                            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">訂單 ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <code class="bg-gray-100 px-2 py-1 rounded">{{ $log->order_id }}</code>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">事件類型</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if ($log->event_type === 'paid')
                                            <span class="text-green-600 font-medium">付款</span>
                                        @else
                                            <span class="text-purple-600 font-medium">退款</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">金額</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-medium">{{ $log->formatted_amount }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">淨額</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $log->currency }} {{ number_format($log->net_total ?? 0) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">付款方式</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $log->payment_method ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Trace ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $log->trace_id }}</code>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">建立時間 (GMT+8)</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $log->created_at->timezone('Asia/Taipei')->format('Y-m-d H:i:s') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">處理時間 (GMT+8)</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $log->processed_at ? $log->processed_at->timezone('Asia/Taipei')->format('Y-m-d H:i:s') : '-' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Customer & User Info -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-medium text-gray-900">客戶資訊</h2>
                        </div>
                        <div class="p-6">
                            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">客戶 Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $log->customer_email }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">客戶名稱</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $log->customer_name ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">對應用戶</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if ($log->user)
                                            <a href="#" class="text-blue-600 hover:underline">
                                                {{ $log->user->name }} (ID: {{ $log->user->id }})
                                            </a>
                                        @else
                                            <span class="text-yellow-600">未找到對應用戶</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-medium text-gray-900">商品資訊</h2>
                        </div>
                        <div class="p-6">
                            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Portaly Product ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <code class="bg-gray-100 px-2 py-1 rounded">{{ $log->portaly_product_id ?? '-' }}</code>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">對應商品</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if ($log->product)
                                            {{ $log->product->name }}
                                            @if ($log->product->deleted_at)
                                                <span class="text-red-500">(已刪除)</span>
                                            @endif
                                        @else
                                            <span class="text-yellow-600">未找到對應商品</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Error Info (if any) -->
                    @if ($log->error_message)
                        <div class="bg-red-50 rounded-lg shadow-sm overflow-hidden mb-6 border border-red-200">
                            <div class="px-6 py-4 border-b border-red-200">
                                <h2 class="text-lg font-medium text-red-900">錯誤訊息</h2>
                            </div>
                            <div class="p-6">
                                <p class="text-sm text-red-700">{{ $log->error_message }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Raw Payload -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6" x-data="{ expanded: false }">
                        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h2 class="text-lg font-medium text-gray-900">原始 Webhook 資料</h2>
                            <button @click="expanded = !expanded"
                                    class="text-sm text-blue-600 hover:text-blue-800">
                                <span x-text="expanded ? '收合' : '展開'"></span>
                            </button>
                        </div>
                        <div class="p-6" x-show="expanded" x-collapse>
                            <pre class="text-xs bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto"><code>{{ json_encode($log->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="text-sm text-blue-700">
                                <p class="font-medium">紀錄保留</p>
                                <p class="mt-1">此紀錄將保留 5 年以符合台灣稅務法規要求。Trace ID 可用於日誌系統中查詢相關處理紀錄。</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
