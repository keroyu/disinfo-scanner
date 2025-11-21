<!-- Incremental Update Modal -->
<div id="incremental-update-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-2xl w-full mx-4 max-w-2xl max-h-[90vh] flex flex-col">
        <!-- Modal Header -->
        <div class="sticky top-0 flex justify-between items-center p-6 border-b border-gray-200 bg-white rounded-t-lg">
            <h2 class="text-2xl font-bold text-gray-900" id="modal-video-title">更新影片留言</h2>
            <button
                type="button"
                id="modal-close-btn"
                class="text-gray-400 hover:text-gray-600 transition-colors text-3xl leading-none"
                aria-label="Close modal"
            >
                &times;
            </button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="flex-1 overflow-y-auto p-6" id="modal-body">
            <!-- Loading State -->
            <div id="loading-section" class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                <p class="mt-4 text-gray-600">正在檢查新留言...</p>
            </div>

            <!-- Preview Section (Hidden initially) -->
            <div id="preview-section" class="hidden">
                <!-- New Comment Count -->
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-lg font-semibold text-blue-900" id="new-comment-count">
                        <!-- Will be filled by JavaScript -->
                    </p>
                    <p class="text-sm text-blue-700 mt-1" id="last-comment-info">
                        <!-- Will be filled by JavaScript -->
                    </p>
                </div>

                <!-- Preview Comments List -->
                <div id="preview-comments-container">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">預覽留言 (前5則)</h3>
                    <div id="preview-comments-list" class="space-y-2 max-h-[300px] overflow-y-auto">
                        <!-- Will be dynamically populated by JavaScript -->
                    </div>
                </div>

                <!-- No New Comments State -->
                <div id="no-new-comments" class="hidden text-center py-8">
                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="mt-4 text-lg font-medium text-gray-900">沒有新留言需要導入</p>
                    <p class="mt-2 text-sm text-gray-500">此影片已是最新狀態</p>
                </div>
            </div>

            <!-- Error Section (Hidden initially) -->
            <div id="error-section" class="hidden">
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-red-900 font-semibold">錯誤</p>
                    <p class="text-red-700 text-sm mt-1" id="error-message"></p>
                </div>
                <button
                    type="button"
                    id="retry-btn"
                    class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    重試
                </button>
            </div>

            <!-- Success Section (Hidden initially) -->
            <div id="success-section" class="hidden">
                <div class="text-center py-8">
                    <svg class="mx-auto h-16 w-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="mt-4 text-lg font-bold text-green-900" id="success-message"></p>
                    <p class="mt-2 text-sm text-gray-600" id="success-details"></p>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="sticky bottom-0 flex justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50 rounded-b-lg">
            <button
                type="button"
                id="btn-cancel"
                class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors"
            >
                取消
            </button>
            <button
                type="button"
                id="btn-confirm"
                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed"
                disabled
            >
                確認更新
            </button>
        </div>
    </div>
</div>

<script>
    let currentVideoId = null;
    let currentVideoTitle = null;

    // Open modal function (called from list page)
    function openUpdateModal(videoId, videoTitle) {
        currentVideoId = videoId;
        currentVideoTitle = videoTitle;

        // Show modal
        document.getElementById('incremental-update-modal').classList.remove('hidden');
        document.getElementById('modal-video-title').textContent = `更新影片留言: ${videoTitle}`;

        // Reset state
        resetModalState();

        // Fetch preview
        fetchPreview();
    }

    // Close modal function
    function closeUpdateModal() {
        document.getElementById('incremental-update-modal').classList.add('hidden');
        currentVideoId = null;
        currentVideoTitle = null;
    }

    // Reset modal state
    function resetModalState() {
        document.getElementById('loading-section').classList.remove('hidden');
        document.getElementById('preview-section').classList.add('hidden');
        document.getElementById('error-section').classList.add('hidden');
        document.getElementById('success-section').classList.add('hidden');
        document.getElementById('btn-confirm').disabled = true;
    }

    // Fetch preview
    function fetchPreview() {
        fetch('/api/video-update/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                video_id: currentVideoId,
                video_title: currentVideoTitle
            })
        })
        .then(response => {
            // Check if response is 403 (no API key)
            if (response.status === 403) {
                return response.json().then(data => {
                    if (data.error_type === 'no_api_key') {
                        // Close this modal and show API key permission modal
                        closeUpdateModal();
                        showPermissionModal('api_key', '影片留言更新');
                        throw new Error('NO_API_KEY'); // Stop further processing
                    }
                    throw new Error(data.error || 'Permission denied');
                });
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('loading-section').classList.add('hidden');

            if (data.success && data.data.new_comment_count > 0) {
                // Show preview section
                document.getElementById('preview-section').classList.remove('hidden');
                document.getElementById('no-new-comments').classList.add('hidden');

                // Update count info with detailed message
                const currentCount = data.data.current_comment_count;
                const newCount = data.data.new_comment_count;
                const willImport = data.data.will_import_count;
                const totalCount = currentCount + newCount;

                let countMessage = `留言總數 ${totalCount}（不含回覆），資料庫已有 ${currentCount} 則；剩下 ${newCount} 則留言`;
                if (newCount > 1000) {
                    countMessage += `，本次實際導入 ${willImport} 則`;
                } else {
                    countMessage += `，本次將導入 ${willImport} 則`;
                }

                document.getElementById('new-comment-count').textContent = countMessage;

                if (data.data.last_comment_time) {
                    document.getElementById('last-comment-info').textContent =
                        `上次留言時間: ${data.data.last_comment_time}`;
                }

                // Render preview comments
                renderPreviewComments(data.data.preview_comments);

                // Enable confirm button
                document.getElementById('btn-confirm').disabled = false;
            } else {
                // No new comments
                document.getElementById('preview-section').classList.remove('hidden');
                document.getElementById('no-new-comments').classList.remove('hidden');
                document.getElementById('preview-comments-container').classList.add('hidden');
            }
        })
        .catch(error => {
            console.error('Preview error:', error);

            // If error is NO_API_KEY, modal is already closed and permission modal shown
            if (error.message === 'NO_API_KEY') {
                return;
            }

            showError('無法載入預覽資訊: ' + error.message);
        });
    }

    // Render preview comments (using same format as API import modal)
    function renderPreviewComments(comments) {
        const container = document.getElementById('preview-comments-list');
        container.innerHTML = '';

        if (!comments || comments.length === 0) {
            container.innerHTML = '<p class="text-gray-500">沒有新的評論</p>';
            return;
        }

        comments.forEach(comment => {
            const publishedDate = new Date(comment.published_at).toLocaleDateString('zh-TW');
            const truncatedText = comment.text.substring(0, 150) + (comment.text.length > 150 ? '...' : '');

            const commentEl = document.createElement('div');
            // Distinguish between top-level comments and replies (same as API import modal)
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

    // Execute import
    function executeImport() {
        // Disable button during import
        const confirmBtn = document.getElementById('btn-confirm');
        const cancelBtn = document.getElementById('btn-cancel');

        confirmBtn.disabled = true;
        confirmBtn.textContent = '導入中...';
        cancelBtn.disabled = true;

        fetch('/api/video-update/import', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                video_id: currentVideoId
            })
        })
        .then(response => {
            // Check if response is 403 (no API key)
            if (response.status === 403) {
                return response.json().then(data => {
                    if (data.error_type === 'no_api_key') {
                        // Close this modal and show API key permission modal
                        closeUpdateModal();
                        showPermissionModal('api_key', '影片留言更新');
                        throw new Error('NO_API_KEY'); // Stop further processing
                    }
                    throw new Error(data.error || 'Permission denied');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showSuccess(data.data.message, data.data);

                // Change button to "完成" after successful import
                confirmBtn.classList.add('hidden');
                cancelBtn.disabled = false;
                cancelBtn.textContent = '完成';
                cancelBtn.classList.remove('bg-gray-300', 'text-gray-700');
                cancelBtn.classList.add('bg-blue-600', 'text-white', 'hover:bg-blue-700');

                // TODO: Update videos list row dynamically
            } else {
                showError(data.error || '導入失敗');
                confirmBtn.disabled = false;
                confirmBtn.textContent = '確認更新';
                cancelBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Import error:', error);

            // If error is NO_API_KEY, modal is already closed and permission modal shown
            if (error.message === 'NO_API_KEY') {
                return;
            }

            showError('導入失敗: ' + error.message);
            confirmBtn.disabled = false;
            confirmBtn.textContent = '確認更新';
            cancelBtn.disabled = false;
        });
    }

    // Show error
    function showError(message) {
        document.getElementById('loading-section').classList.add('hidden');
        document.getElementById('preview-section').classList.add('hidden');
        document.getElementById('error-section').classList.remove('hidden');
        document.getElementById('error-message').textContent = message;
    }

    // Show success
    function showSuccess(message, data) {
        document.getElementById('preview-section').classList.add('hidden');
        document.getElementById('success-section').classList.remove('hidden');
        document.getElementById('success-message').textContent = message;

        if (data.has_more) {
            document.getElementById('success-details').textContent =
                `還有 ${data.remaining} 則新留言可用,請再次點擊更新按鈕繼續導入。`;
        }
    }

    // HTML escape utility
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Event listeners
    document.getElementById('modal-close-btn').addEventListener('click', closeUpdateModal);
    document.getElementById('btn-cancel').addEventListener('click', closeUpdateModal);
    document.getElementById('btn-confirm').addEventListener('click', executeImport);

    document.getElementById('retry-btn').addEventListener('click', function() {
        resetModalState();
        fetchPreview();
    });

    // ESC key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('incremental-update-modal').classList.contains('hidden')) {
            closeUpdateModal();
        }
    });

    // Click backdrop to close
    document.getElementById('incremental-update-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeUpdateModal();
        }
    });
</script>
