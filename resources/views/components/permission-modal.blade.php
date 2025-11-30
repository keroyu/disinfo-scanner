{{-- Permission Denied Modal Component --}}
{{-- Usage: @include('components.permission-modal', ['type' => 'login|upgrade|api_key|admin', 'feature' => '功能名稱']) --}}

@props([
    'type' => 'login',        // login, upgrade, api_key, admin
    'feature' => '',           // Feature name for the modal title
    'show' => false            // Whether to show modal immediately
])

<div x-data="{ open: @js($show), modalType: '{{ $type }}' }"
     x-show="open"
     x-cloak
     @permission-modal.window="if ($event.detail.type === modalType) { open = true }"
     @keydown.escape.window="open = false"
     x-trap.noscroll="open"
     class="fixed z-50 inset-0 overflow-y-auto"
     style="display: none;">

    <!-- Background overlay -->
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div x-show="open"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 transition-opacity"
             aria-hidden="true"
             @click="open = false">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <!-- Center modal -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal panel -->
        <div x-show="open"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"
             role="alertdialog"
             aria-modal="true"
             aria-labelledby="modal-headline"
             aria-describedby="modal-description"
             @click.away="open = false">

            @if($type === 'login')
                {{-- Login Required Modal --}}
                <div>
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100" aria-hidden="true">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-5">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-headline">
                            請登入會員
                        </h3>
                        <div class="mt-2" id="modal-description">
                            <p class="text-sm text-gray-500">
                                @if($feature)
                                    您需要登入會員才能使用「{{ $feature }}」功能。
                                @else
                                    您需要登入會員才能使用此功能。
                                @endif
                            </p>
                            <p class="mt-2 text-sm text-gray-500">
                                還沒有帳號？立即註冊成為會員，即可享有更多功能！
                            </p>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                    <a href="{{ route('login') }}"
                       class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm">
                        前往登入
                    </a>
                    <a href="{{ route('register') }}"
                       class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                        註冊帳號
                    </a>
                </div>

            @elseif($type === 'upgrade')
                {{-- Upgrade to Premium Member Modal --}}
                <div>
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100" aria-hidden="true">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-5">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-headline">
                            需升級為高級會員
                        </h3>
                        <div class="mt-2" id="modal-description">
                            <p class="text-sm text-gray-500">
                                @if($feature)
                                    「{{ $feature }}」功能僅供高級會員使用。
                                @else
                                    ⚠️ 付費功能尚未開放！如純支持可先贊助。
                                @endif
                            </p>
                            <div class="mt-4 bg-blue-50 rounded-md p-4">
                                <h4 class="text-sm font-medium text-blue-900 mb-2">高級會員專屬功能（Coming）</h4>
                                <ul class="text-sm text-blue-700 space-y-1 text-left">
                                    <li class="flex items-start">
                                        <svg class="h-5 w-5 text-blue-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        使用 YouTube 官方 API 匯入
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="h-5 w-5 text-blue-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        所有搜尋功能
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="h-5 w-5 text-blue-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        完成身份驗證後享有無限制額度
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                    <a href="https://portaly.cc/kyontw/support"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm">
                        贊助支持本站維護
                    </a>
                    <button type="button"
                            @click="open = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                        取消
                    </button>
                </div>

            @elseif($type === 'api_key')
                {{-- API Key Required Modal --}}
                <div>
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-orange-100" aria-hidden="true">
                        <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-5">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-headline">
                            需設定 YouTube API 金鑰
                        </h3>
                        <div class="mt-2" id="modal-description">
                            <p class="text-sm text-gray-500">
                                您需要先設定 YouTube API 金鑰才能使用影片更新功能。
                            </p>
                            <p class="mt-2 text-sm text-gray-500">
                                前往帳號設定頁面即可設定您的 API 金鑰。
                            </p>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                    <a href="{{ route('settings.index') }}"
                       class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm">
                        前往設定
                    </a>
                    <button type="button"
                            @click="open = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                        取消
                    </button>
                </div>

            @elseif($type === 'admin')
                {{-- T472: Admin Permission Required Modal --}}
                <div>
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100" aria-hidden="true">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-5">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-headline">
                            需要管理員權限
                        </h3>
                        <div class="mt-2" id="modal-description">
                            <p class="text-sm text-gray-500">
                                此功能僅限管理員使用，您沒有足夠的權限執行此操作。
                            </p>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6">
                    <button type="button"
                            @click="open = false"
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm">
                        關閉
                    </button>
                </div>

            @elseif($type === 'quota_exceeded')
                {{-- T476: Quota Exceeded Modal with quota info --}}
                <div x-data="{ quotaUsed: 10, quotaLimit: 10 }"
                     x-init="
                        window.addEventListener('show-quota-exceeded', (e) => {
                            quotaUsed = e.detail.used || 10;
                            quotaLimit = e.detail.limit || 10;
                            open = true;
                        });
                     ">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100" aria-hidden="true">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-5">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-headline">
                            本月配額已用完
                        </h3>
                        <div class="mt-2" id="modal-description">
                            <p class="text-sm text-gray-500">
                                @if($feature)
                                    您本月的「{{ $feature }}」配額已用完。
                                @else
                                    您本月的 API 配額已用完。
                                @endif
                            </p>
                            <div class="mt-4 p-4 bg-red-50 rounded-lg">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-medium text-red-900">本月用量</span>
                                    <span class="text-sm font-bold text-red-700" x-text="quotaUsed + '/' + quotaLimit"></span>
                                </div>
                                <div class="w-full bg-red-200 rounded-full h-2">
                                    <div class="bg-red-600 h-2 rounded-full" style="width: 100%"></div>
                                </div>
                            </div>
                            <p class="mt-4 text-sm text-gray-600">
                                完成身份驗證即可獲得無限配額。
                            </p>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                    <a href="{{ route('settings.index') }}"
                       class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm">
                        前往身份驗證
                    </a>
                    <button type="button"
                            @click="open = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                        關閉
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Alpine.js CDN (if not already included in layout) --}}
@once
@push('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush
@endonce
