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

<!-- Confirmation Modal (hidden initially) -->
<div id="confirmation-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 p-6 animate-fade-in">
        <h2 class="text-xl font-bold text-gray-900 mb-6">確認匯入資料</h2>

        <!-- Metadata Section -->
        <div class="mb-6 space-y-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 100-12 6 6 0 000 12z" clip-rule="evenodd"></path>
                </svg>
                <div class="flex-1">
                    <p class="text-sm text-gray-600">影片名稱</p>
                    <p id="modal-video-title" class="font-medium text-gray-900 mt-0.5">(正在載入...)</p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v1h8v-1zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-1a4 4 0 00-4-4l-3.25-4.063A7 7 0 0018 8v1h-2a3 3 0 00-3 3v5h2v1h-6v-1h2v-5a2 2 0 014 0v1h2v-1a4 4 0 00-4-4H9.75L6.5 7.938A7 7 0 0016 8z"></path>
                </svg>
                <div class="flex-1">
                    <p class="text-sm text-gray-600">頻道名稱</p>
                    <p id="modal-channel-name" class="font-medium text-gray-900 mt-0.5">(正在載入...)</p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"></path>
                </svg>
                <div class="flex-1">
                    <p class="text-sm text-gray-600">留言數量</p>
                    <p id="modal-comment-count" class="font-medium text-gray-900 mt-0.5">(正在載入...)</p>
                </div>
            </div>
        </div>

        <hr class="my-6">

        <!-- Tag Selection Section (visible only for new channels) -->
        <div id="tags-section" class="hidden mb-6">
            <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded">
                <p class="text-sm text-amber-900 font-medium">⚠ 檢測到新頻道，請選擇標籤</p>
                <p class="text-sm text-amber-700 mt-1">請至少選擇一個標籤以便分類</p>
            </div>

            <p class="text-sm font-medium text-gray-700 mb-3">選擇標籤：</p>
            <div id="tags-container" class="flex flex-wrap gap-2 max-h-48 overflow-y-auto">
                <!-- Tags will be populated here -->
            </div>
            <div id="tag-error" class="hidden text-red-600 text-sm mt-2">請至少選擇一個標籤</div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-3 pt-4">
            <button
                id="modal-confirm"
                class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
                確認並寫入資料
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
    const confirmationModal = document.getElementById('confirmation-modal');
    const tagsSection = document.getElementById('tags-section');
    const tagsContainer = document.getElementById('tags-container');
    const modalConfirm = document.getElementById('modal-confirm');
    const modalCancel = document.getElementById('modal-cancel');
    const tagError = document.getElementById('tag-error');

    let currentImportId = null;
    let currentRequiresTags = false;
    let currentChannelId = null;
    let availableTags = [];
    let selectedTags = [];

    // Load available tags
    async function loadTags() {
        try {
            const response = await fetch('/api/uapi/tags');
            const data = await response.json();

            if (data.success) {
                availableTags = data.data;
                renderTagsContainer();

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

    // Render tags in the container
    function renderTagsContainer() {
        tagsContainer.innerHTML = '';
        availableTags.forEach(tag => {
            const label = document.createElement('label');
            label.className = 'inline-flex items-center px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 whitespace-nowrap';
            label.innerHTML = `
                <input type="checkbox" class="tag-checkbox" value="${tag.code}" data-id="${tag.tag_id}">
                <span class="ml-2 flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-full" style="background-color: var(--color-${tag.color})"></span>
                    <span class="font-medium text-sm">${tag.name}</span>
                </span>
            `;
            tagsContainer.appendChild(label);
        });
    }

    // Show confirmation modal with metadata
    function showConfirmationModal(importData) {
        statusSection.classList.add('hidden');
        confirmationModal.classList.remove('hidden');

        // Populate metadata
        document.getElementById('modal-video-title').textContent = importData.video_title || '(未能擷取標題)';
        document.getElementById('modal-channel-name').textContent = importData.channel_name || '(未知頻道)';
        document.getElementById('modal-comment-count').textContent = `${importData.comment_count} 則`;

        // Show/hide tag section based on requires_tags
        if (importData.requires_tags) {
            tagsSection.classList.remove('hidden');
            selectedTags = [];
            document.querySelectorAll('.tag-checkbox').forEach(cb => cb.checked = false);
            tagError.classList.add('hidden');
            // Disable confirm button if new channel and no tags selected
            updateConfirmButtonState();
        } else {
            tagsSection.classList.add('hidden');
            // Enable confirm button for existing channels
            modalConfirm.disabled = false;
        }

        currentImportId = importData.import_id;
        currentChannelId = importData.channel_id;
        currentRequiresTags = importData.requires_tags;
    }

    // Update confirm button state based on tag requirements
    function updateConfirmButtonState() {
        if (!currentRequiresTags) {
            modalConfirm.disabled = false;
            return;
        }

        const selectedCount = document.querySelectorAll('.tag-checkbox:checked').length;
        modalConfirm.disabled = selectedCount === 0;
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
        confirmationModal.classList.add('hidden');

        // Show status
        statusSection.classList.remove('hidden');
        importBtn.disabled = true;

        try {
            const response = await fetch('/api/uapi/import', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ url })
            });

            const data = await response.json();

            if (response.status === 202) {
                // Show confirmation modal with metadata
                showConfirmationModal(data.data);
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

    // Modal confirm button
    modalConfirm.addEventListener('click', async () => {
        // Collect selected tags if required
        if (currentRequiresTags) {
            selectedTags = Array.from(document.querySelectorAll('.tag-checkbox:checked')).map(cb => cb.value);

            if (selectedTags.length === 0) {
                tagError.classList.remove('hidden');
                return;
            }
        }

        modalConfirm.disabled = true;
        tagError.classList.add('hidden');

        try {
            const response = await fetch('/api/uapi/confirm', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    import_id: currentImportId,
                    tags: currentRequiresTags ? selectedTags : null
                })
            });

            const data = await response.json();

            if (response.ok) {
                confirmationModal.classList.add('hidden');
                showResults(data.data.stats);
                urlInput.value = '';
            } else {
                showError(data.message);
            }
        } catch (error) {
            showError('確認匯入失敗：' + error.message);
        } finally {
            modalConfirm.disabled = false;
        }
    });

    // Modal cancel button
    modalCancel.addEventListener('click', async () => {
        // Optionally call cancel API to clear cache
        try {
            await fetch('/api/uapi/cancel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    import_id: currentImportId
                })
            });
        } catch (error) {
            console.error('Failed to call cancel API:', error);
        }

        confirmationModal.classList.add('hidden');
        urlInput.value = '';
    });

    // Track tag selection for button state
    tagsContainer.addEventListener('change', (e) => {
        if (e.target.classList.contains('tag-checkbox')) {
            updateConfirmButtonState();
        }
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
