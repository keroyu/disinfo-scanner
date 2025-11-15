@extends('layouts.app')

@section('title', 'YouTube 留言資料匯入系統')

@section('content')
<div class="max-w-2xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">YouTube 留言資料匯入系統</h1>
        <p class="text-gray-600">支援兩種匯入方式：</p>
        <ul class="text-gray-600 text-sm mt-2 ml-4 list-disc">
            <li>urtubeapi 網址：<code class="bg-gray-100 px-2 py-1">https://urtubeapi.analysis.tw/api/api_comment.php?videoId=...&token=...</code></li>
            <li>YouTube 影片網址：<code class="bg-gray-100 px-2 py-1">https://www.youtube.com/watch?v=...</code></li>
        </ul>
    </div>

    <!-- Input Section -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form id="import-form">
            <div class="mb-4">
                <label for="url" class="block text-sm font-medium text-gray-700 mb-2">
                    請輸入 urtubeapi 或 YouTube 影片網址
                </label>
                <input
                    type="text"
                    id="url"
                    name="url"
                    placeholder="貼上網址"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    required
                >
            </div>
            <button
                type="submit"
                id="import-btn"
                class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition"
            >
                開始匯入
            </button>
        </form>
    </div>

    <!-- Status Section (hidden initially) -->
    <div id="status-section" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
        <div class="flex items-center justify-center">
            <div class="animate-spin h-5 w-5 text-blue-600 mr-3"></div>
            <span id="status-text" class="text-blue-600">正在解析 YouTube 影片...</span>
        </div>
    </div>

    <!-- Results Section (hidden initially) -->
    <div id="results-section" class="hidden bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-green-800 mb-3">匯入成功</h3>
        <div id="results-content" class="text-green-700 space-y-2">
            <!-- Results will be populated here -->
        </div>
    </div>

    <!-- Error Section (hidden initially) -->
    <div id="error-section" class="hidden bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-red-800 mb-2">錯誤</h3>
        <p id="error-message" class="text-red-700"></p>
    </div>
</div>

<!-- Tag Selection Modal (hidden initially) -->
<div id="tag-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6 animate-fade-in">
        <h2 class="text-xl font-bold text-gray-900 mb-4">新頻道標籤設定</h2>

        <div class="mb-4 p-4 bg-blue-50 rounded">
            <p class="text-sm text-gray-600">檢測到新頻道</p>
            <p id="modal-channel-id" class="font-mono text-sm text-gray-900 mt-1"></p>
            <p id="modal-channel-name" class="text-sm text-gray-700 mt-1"></p>
        </div>

        <div class="mb-4">
            <p class="text-sm font-medium text-gray-700 mb-3">請選擇標籤（至少選擇一個）：</p>
            <div id="tags-container" class="space-y-2">
                <!-- Tags will be populated here -->
            </div>
            <div id="tag-error" class="hidden text-red-600 text-sm mt-2">請至少選擇一個標籤</div>
        </div>

        <div class="flex gap-3">
            <button
                id="modal-confirm"
                class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition"
            >
                確認並繼續匯入
            </button>
            <button
                id="modal-cancel"
                class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition"
            >
                取消匯入
            </button>
        </div>
    </div>
</div>

<style>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.animate-fade-in {
    animation: fadeIn 0.2s ease-out;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('import-form');
    const urlInput = document.getElementById('url');
    const importBtn = document.getElementById('import-btn');
    const statusSection = document.getElementById('status-section');
    const resultsSection = document.getElementById('results-section');
    const errorSection = document.getElementById('error-section');
    const tagModal = document.getElementById('tag-modal');
    const tagsContainer = document.getElementById('tags-container');
    const modalConfirm = document.getElementById('modal-confirm');
    const modalCancel = document.getElementById('modal-cancel');
    const tagError = document.getElementById('tag-error');

    let currentImportId = null;
    let currentChannelId = null;
    let selectedTags = [];

    // Load available tags
    async function loadTags() {
        try {
            const response = await fetch('/api/tags');
            const data = await response.json();

            if (data.success) {
                tagsContainer.innerHTML = '';
                data.data.forEach(tag => {
                    const label = document.createElement('label');
                    label.className = 'flex items-center p-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50';
                    label.innerHTML = `
                        <input type="checkbox" class="tag-checkbox" value="${tag.code}" data-id="${tag.tag_id}">
                        <span class="ml-3 flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded-full" style="background-color: var(--color-${tag.color})"></span>
                            <span class="font-medium">${tag.name}</span>
                        </span>
                    `;
                    tagsContainer.appendChild(label);
                });

                // Add CSS variables for tag colors
                const colorMap = {
                    'green-500': '#10b981',
                    'blue-500': '#3b82f6',
                    'red-500': '#ef4444',
                    'orange-500': '#f97316',
                    'rose-600': '#e11d48'
                };

                const style = document.createElement('style');
                let colorStyles = '';
                Object.entries(colorMap).forEach(([name, hex]) => {
                    colorStyles += `--color-${name}: ${hex};`;
                });
                style.innerHTML = `:root { ${colorStyles} }`;
                document.head.appendChild(style);
            }
        } catch (error) {
            console.error('Failed to load tags:', error);
        }
    }

    // Form submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const url = urlInput.value.trim();

        if (!url) {
            showError('請輸入網址');
            return;
        }

        // Hide previous sections
        resultsSection.classList.add('hidden');
        errorSection.classList.add('hidden');
        tagModal.classList.add('hidden');

        // Show status
        statusSection.classList.remove('hidden');
        importBtn.disabled = true;

        try {
            const response = await fetch('/api/import', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ url })
            });

            const data = await response.json();

            if (response.status === 202) {
                // New channel - show modal
                statusSection.classList.add('hidden');
                currentImportId = data.data.import_id;
                currentChannelId = data.data.channel_id;
                document.getElementById('modal-channel-id').textContent = data.data.channel_id;
                document.getElementById('modal-channel-name').textContent = data.data.channel_name || '(未命名)';
                tagModal.classList.remove('hidden');
                selectedTags = [];
                document.querySelectorAll('.tag-checkbox').forEach(cb => cb.checked = false);
                tagError.classList.add('hidden');
            } else if (response.ok) {
                // Success
                statusSection.classList.add('hidden');
                showResults(data.data.stats);
                urlInput.value = '';
            } else {
                showError(data.message);
                statusSection.classList.add('hidden');
            }
        } catch (error) {
            showError('匯入失敗：' + error.message);
            statusSection.classList.add('hidden');
        } finally {
            importBtn.disabled = false;
        }
    });

    // Modal confirm
    modalConfirm.addEventListener('click', async () => {
        selectedTags = Array.from(document.querySelectorAll('.tag-checkbox:checked')).map(cb => cb.value);

        if (selectedTags.length === 0) {
            tagError.classList.remove('hidden');
            return;
        }

        modalConfirm.disabled = true;
        tagError.classList.add('hidden');

        try {
            const response = await fetch('/api/tags/select', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    import_id: currentImportId,
                    channel_id: currentChannelId,
                    tags: selectedTags
                })
            });

            const data = await response.json();

            if (response.ok) {
                tagModal.classList.add('hidden');
                showResults(data.data.stats);
                urlInput.value = '';
            } else {
                showError(data.message);
            }
        } catch (error) {
            showError('標籤選擇失敗：' + error.message);
        } finally {
            modalConfirm.disabled = false;
        }
    });

    // Modal cancel
    modalCancel.addEventListener('click', () => {
        tagModal.classList.add('hidden');
        urlInput.value = '';
    });

    // Helper functions
    function showResults(stats) {
        resultsSection.classList.remove('hidden');
        resultsSection.className = 'bg-green-50 border border-green-200 rounded-lg p-6 mb-6';
        resultsSection.querySelector('h3').textContent = '✓ 成功匯入';
        resultsSection.querySelector('h3').className = 'text-lg font-semibold text-green-800 mb-3';

        const resultsContent = document.getElementById('results-content');
        resultsContent.innerHTML = `
            <p class="font-semibold">成功匯入 ${stats.total_processed || stats.newly_added} 則留言</p>
            <ul class="text-green-700 text-sm mt-2 ml-4 list-disc">
                <li>新增: ${stats.newly_added} 筆</li>
                <li>跳過: ${stats.skipped || 0} 筆</li>
            </ul>
        `;
    }

    function showError(message) {
        errorSection.classList.remove('hidden');
        document.getElementById('error-message').textContent = message;
    }

    // Load tags on page load
    loadTags();
});
</script>
@endsection
