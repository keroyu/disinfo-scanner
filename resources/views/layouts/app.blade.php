<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'YouTube 留言資料匯入系統')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="text-xl font-bold text-blue-600">DISINFO_SCANNER</a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/" class="text-gray-600 hover:text-gray-900">首頁</a>
                    <a href="{{ route('channels.index') }}" class="{{ request()->is('channels*') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">頻道列表</a>
                    <a href="{{ route('videos.index') }}" class="{{ request()->is('videos*') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">影片列表</a>
                    <a href="{{ route('comments.index') }}" class="{{ request()->is('comments*') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">留言列表</a>

                    @auth
                        {{-- User Dropdown Menu --}}
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open"
                                    @click.away="open = false"
                                    class="flex items-center space-x-2 text-gray-700 hover:text-gray-900 focus:outline-none">
                                <div class="flex items-center space-x-2">
                                    <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-semibold">
                                        {{ substr(auth()->user()->name, 0, 1) }}
                                    </div>
                                    <span class="hidden sm:block font-medium">{{ auth()->user()->name }}</span>
                                    @if(auth()->user()->roles->isNotEmpty())
                                        <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ auth()->user()->roles->first()->display_name }}
                                        </span>
                                    @endif
                                </div>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            {{-- Dropdown Menu --}}
                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                                 style="display: none;">
                                <div class="py-1">
                                    <div class="px-4 py-2 border-b border-gray-100">
                                        <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                                        <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
                                    </div>

                                    <a href="{{ route('settings.index') }}"
                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-cog mr-2"></i> 帳號設定
                                    </a>

                                    @if(auth()->user()->roles->contains('name', 'regular_member'))
                                        <button onclick="showUpgradeModal()"
                                                class="w-full text-left block px-4 py-2 text-sm text-orange-600 hover:bg-orange-50">
                                            <i class="fas fa-star mr-2"></i> 升級為付費會員
                                        </button>
                                    @endif

                                    @if(auth()->user()->roles->contains('name', 'paid_member'))
                                        <div class="px-4 py-2 text-xs text-gray-500 border-t border-gray-100">
                                            <i class="fas fa-chart-line mr-1"></i>
                                            本月 API 用量:
                                            @php
                                                $quota = auth()->user()->apiQuota;
                                            @endphp
                                            @if($quota && $quota->is_unlimited)
                                                <span class="text-green-600 font-semibold">無限制</span>
                                            @elseif($quota)
                                                <span class="font-semibold">{{ $quota->usage_count }}/{{ $quota->monthly_limit }}</span>
                                            @else
                                                <span>0/10</span>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="border-t border-gray-100"></div>

                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit"
                                                class="w-full text-left block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            <i class="fas fa-sign-out-alt mr-2"></i> 登出
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- Include upgrade modal for regular members --}}
                        @if(auth()->user()->roles->contains('name', 'regular_member'))
                            @once
                                @include('components.permission-modal', ['type' => 'upgrade'])
                                <script>
                                function showUpgradeModal() {
                                    window.dispatchEvent(new CustomEvent('permission-modal'));
                                }
                                </script>
                            @endonce
                        @endif
                    @else
                        {{-- Guest Links --}}
                        <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900">登入</a>
                        <a href="{{ route('register') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">註冊</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    @if(isset($breadcrumbs) && count($breadcrumbs) > 0)
    <div class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <nav class="flex text-sm text-gray-600">
                @foreach($breadcrumbs as $index => $crumb)
                    @if($index > 0)<span class="mx-2 text-gray-400">></span>@endif
                    @if(isset($crumb['url']))
                        <a href="{{ $crumb['url'] }}" class="hover:text-blue-600 transition-colors">{{ $crumb['label'] }}</a>
                    @else
                        <span class="text-gray-900 font-medium">{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            </nav>
        </div>
    </div>
    @endif

    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        @yield('content')
    </main>

    <footer class="bg-white border-t mt-12">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">YouTube 留言資料管理系統 MVP v1.0.0</p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
