@extends('layouts.app')

@section('title', 'DISINFO SCANNER')

@section('content')
<div class="max-w-4xl mx-auto text-center py-16">
    <!-- Logo or Icon -->
    <div class="mb-8">
        <i class="fas fa-search text-blue-600 text-6xl"></i>
    </div>

    <!-- Main Heading -->
    <h1 class="text-4xl font-bold text-gray-900 mb-4">DISINFO SCANNER</h1>
    <p class="text-xl text-gray-600 mb-8">YouTube 留言分析系統</p>

    <!-- Description -->
    <div class="max-w-2xl mx-auto mb-12">
        <p class="text-gray-500 mb-4">
            本系統提供 YouTube 留言資料的匯入、管理與分析功能。
        </p>
        <p class="text-gray-500">
            請前往「影片列表」頁面使用匯入功能。
        </p>
    </div>

    <!-- Quick Links -->
    <div class="flex justify-center gap-4">
        <a href="{{ route('comments.index') }}" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
            <i class="fas fa-list mr-2"></i>前往留言列表
        </a>
        <a href="{{ route('videos.index') }}" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-video mr-2"></i>前往影片列表
        </a>
    </div>
</div>
@endsection
