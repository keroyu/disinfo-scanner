<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'YouTube 留言資料匯入系統')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
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
</body>
</html>
