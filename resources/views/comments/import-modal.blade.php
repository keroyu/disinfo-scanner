<!-- YouTube API Import Modal (Tailwind CSS) -->
<div id="youtube-import-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
    <div class="bg-white rounded-lg shadow-2xl w-full mx-4 max-w-2xl max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="sticky top-0 flex justify-between items-center p-6 border-b border-gray-200 bg-white">
            <h2 class="text-2xl font-bold text-gray-900">
                <i class="fab fa-youtube text-red-600 mr-2"></i>官方API導入
            </h2>
            <button type="button" id="modal-close-btn" class="text-gray-400 hover:text-gray-600 text-3xl leading-none">
                ×
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            <!-- Step 1: URL Input -->
            <div id="step-url-input" class="import-step">
                <div class="mb-4">
                    <label for="video-url" class="block text-sm font-medium text-gray-700 mb-1">YouTube 視頻網址</label>
                    <input
                        type="text"
                        id="video-url"
                        placeholder="https://www.youtube.com/watch?v=..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                    >
                    <p class="mt-1 text-sm text-gray-500">支持 youtube.com 或 youtu.be 鏈接</p>
                </div>
                <div id="url-error" class="hidden text-red-600 bg-red-50 p-3 rounded mb-4"></div>
            </div>

            <!-- Step 2: Metadata Confirmation (for new videos) -->
            <div id="step-metadata" class="import-step hidden">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">視頻信息</h3>
                <div class="space-y-3 mb-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-700">標題</dt>
                        <dd class="text-gray-900" id="metadata-title">-</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-700">頻道</dt>
                        <dd class="text-gray-900" id="metadata-channel">-</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-700">發布日期</dt>
                        <dd class="text-gray-900" id="metadata-published">-</dd>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">標籤</label>
                    <div id="tag-selection" class="border border-gray-300 rounded-lg p-3 min-h-[100px] bg-gray-50">
                        <!-- Tags will be populated here -->
                    </div>
                </div>
            </div>

            <!-- Step 3: Preview Comments -->
            <div id="step-preview" class="import-step hidden">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">預覽評論 (前5條)</h3>
                <div id="preview-comments-container" class="space-y-2 max-h-[300px] overflow-y-auto">
                    <!-- Comments will be populated here -->
                </div>
            </div>

            <!-- Loading Indicator -->
            <div id="loading-indicator" class="hidden text-center py-8">
                <div class="inline-block">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
                </div>
                <p class="mt-4 text-gray-600">正在處理，請稍候...</p>
            </div>

            <!-- Success Message -->
            <div id="success-message" class="hidden text-green-700 bg-green-50 p-4 rounded mb-4"></div>
        </div>

        <!-- Modal Footer -->
        <div class="sticky bottom-0 flex justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50">
            <button
                type="button"
                id="btn-cancel"
                class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            >
                取消
            </button>
            <button
                type="button"
                id="btn-next"
                class="px-4 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700"
            >
                下一步
            </button>
            <button
                type="button"
                id="btn-confirm"
                class="hidden px-4 py-2 text-white bg-green-600 rounded-lg hover:bg-green-700"
            >
                確認導入
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('youtube-import-modal');
    const videoUrlInput = document.getElementById('video-url');
    const btnNext = document.getElementById('btn-next');
    const btnConfirm = document.getElementById('btn-confirm');
    const btnCancel = document.getElementById('btn-cancel');
    const closeBtn = document.getElementById('modal-close-btn');
    const loadingIndicator = document.getElementById('loading-indicator');
    const successMessage = document.getElementById('success-message');

    let currentStep = 'url-input';
    let currentVideoId = null;
    let currentVideoMetadata = null;

    // Step navigation
    btnNext.addEventListener('click', handleNextStep);
    btnConfirm.addEventListener('click', handleConfirmImport);
    btnCancel.addEventListener('click', closeModal);
    closeBtn.addEventListener('click', closeModal);

    // Video URL input - trigger metadata fetch on Enter
    videoUrlInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            handleNextStep();
        }
    });

    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    function closeModal() {
        modal.classList.add('hidden');
    }

    function showModal() {
        modal.classList.remove('hidden');
    }

    function resetModal() {
        currentStep = 'url-input';
        currentVideoId = null;
        currentVideoMetadata = null;
        videoUrlInput.value = '';
        document.getElementById('url-error').classList.add('hidden');
        showStep('url-input');
        btnNext.classList.remove('hidden');
        btnConfirm.classList.add('hidden');
    }

    function showStep(step) {
        document.querySelectorAll('.import-step').forEach(el => el.classList.add('hidden'));
        document.getElementById('step-' + step).classList.remove('hidden');
        currentStep = step;
    }

    async function handleNextStep() {
        if (currentStep === 'url-input') {
            await fetchMetadata();
        } else if (currentStep === 'metadata') {
            await fetchPreview();
        }
    }

    async function fetchMetadata() {
        const videoUrl = videoUrlInput.value.trim();

        if (!videoUrl) {
            showError('請輸入YouTube網址');
            return;
        }

        showLoading(true);
        document.getElementById('url-error').classList.add('hidden');

        try {
            // Extract video ID
            currentVideoId = extractVideoId(videoUrl);

            // Check if video exists or fetch metadata
            console.log('Fetching metadata for:', videoUrl);
            const response = await fetch('{{ route("youtube-import.metadata") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    video_url: videoUrl
                })
            });

            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));

            if (!response.ok) {
                const text = await response.text();
                console.error('Response not OK:', text.substring(0, 500));
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
            }

            const data = await response.json();

            if (!data.success) {
                showError(data.error || '無法獲取視頻信息');
                showLoading(false);
                return;
            }

            if (data.status === 'existing_video') {
                // Skip metadata, go directly to preview
                await fetchPreview();
            } else {
                // New video - show metadata
                currentVideoMetadata = data.data;
                displayMetadata(data.data);
                showStep('metadata');
                btnNext.textContent = '下一步';
                showLoading(false);
            }
        } catch (error) {
            console.error('Metadata fetch error:', error);
            showError('獲取視頻信息失敗: ' + error.message);
            showLoading(false);
        }
    }

    function displayMetadata(metadata) {
        document.getElementById('metadata-title').textContent = metadata.title || '-';
        document.getElementById('metadata-channel').textContent = metadata.channel_name || '-';
        document.getElementById('metadata-published').textContent = metadata.published_at
            ? new Date(metadata.published_at).toLocaleDateString('zh-TW')
            : '-';
    }

    async function fetchPreview() {
        if (!currentVideoId) {
            showError('視頻ID無效');
            return;
        }

        showLoading(true);

        try {
            const response = await fetch('{{ route("youtube-import.preview") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    video_url: currentVideoId
                })
            });

            const data = await response.json();

            if (!data.success) {
                showError(data.error || '無法獲取預覽評論');
                showLoading(false);
                return;
            }

            displayPreviewComments(data.data.preview_comments);
            showStep('preview');
            btnNext.classList.add('hidden');
            btnConfirm.classList.remove('hidden');
            btnConfirm.textContent = '確認導入';
            showLoading(false);
        } catch (error) {
            showError('獲取預覽評論失敗: ' + error.message);
            showLoading(false);
        }
    }

    function displayPreviewComments(comments) {
        const container = document.getElementById('preview-comments-container');
        container.innerHTML = '';

        if (!comments || comments.length === 0) {
            container.innerHTML = '<p class="text-gray-500">沒有新的評論</p>';
            return;
        }

        comments.forEach(comment => {
            const publishedDate = new Date(comment.published_at).toLocaleDateString('zh-TW');
            const truncatedText = comment.text.substring(0, 150) + (comment.text.length > 150 ? '...' : '');

            const commentEl = document.createElement('div');
            commentEl.className = comment.parent_comment_id
                ? 'pl-4 border-l-4 border-gray-300 bg-gray-50 p-3 rounded'
                : 'bg-white border border-blue-200 p-3 rounded';

            commentEl.innerHTML = `
                <div class="text-xs text-gray-500 mb-1">
                    ${comment.author_channel_id || '匿名用戶'} · ${publishedDate}
                </div>
                <p class="text-sm text-gray-700">${escapeHtml(truncatedText)}</p>
                <div class="text-xs text-gray-500 mt-1">👍 ${comment.like_count}</div>
            `;

            container.appendChild(commentEl);
        });
    }

    async function handleConfirmImport() {
        if (!currentVideoId) {
            showError('視頻ID無效');
            return;
        }

        showLoading(true);
        btnConfirm.disabled = true;
        btnCancel.disabled = true;

        try {
            const response = await fetch('{{ route("youtube-import.confirm") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    video_url: currentVideoId,
                    metadata: currentVideoMetadata
                })
            });

            const data = await response.json();

            if (!data.success) {
                showError(data.error || '導入失敗');
                showLoading(false);
                btnConfirm.disabled = false;
                btnCancel.disabled = false;
                return;
            }

            // Success
            showSuccess(`成功導入 ${data.data.total_imported} 條評論`);
            showLoading(false);
            btnConfirm.disabled = false;
            btnCancel.disabled = false;

            // Close modal after 2 seconds
            setTimeout(() => {
                closeModal();
                // Reload comments list
                if (window.location.pathname.includes('/comments')) {
                    location.reload();
                }
            }, 2000);
        } catch (error) {
            showError('導入過程出錯: ' + error.message);
            showLoading(false);
            btnConfirm.disabled = false;
            btnCancel.disabled = false;
        }
    }

    function showLoading(show) {
        if (show) {
            loadingIndicator.classList.remove('hidden');
        } else {
            loadingIndicator.classList.add('hidden');
        }
    }

    function showError(message) {
        const errorDiv = document.getElementById('url-error');
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
    }

    function showSuccess(message) {
        const successDiv = document.getElementById('success-message');
        successDiv.textContent = message;
        successDiv.classList.remove('hidden');
    }

    function extractVideoId(url) {
        const patterns = [
            /(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/,
            /(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]{11})/,
            /^([a-zA-Z0-9_-]{11})$/
        ];

        for (let pattern of patterns) {
            const match = url.match(pattern);
            if (match) {
                return match[1];
            }
        }

        throw new Error('無法從URL提取視頻ID');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Expose modal show function globally
    window.showYouTubeImportModal = function() {
        resetModal();
        showModal();
    };
});
</script>

<style>
.import-step {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
