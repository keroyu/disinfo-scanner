<!-- YouTube API Import Modal -->
<div id="youtube-import-modal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">官方API導入</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <!-- Step 1: URL Input -->
                <div id="step-url-input" class="import-step">
                    <div class="form-group">
                        <label for="video-url">YouTube 視頻網址</label>
                        <input type="text" class="form-control" id="video-url" placeholder="https://www.youtube.com/watch?v=...">
                        <small class="form-text text-muted">支持 youtube.com 或 youtu.be 鏈接</small>
                    </div>
                    <div id="url-error" class="alert alert-danger" style="display: none;"></div>
                </div>

                <!-- Step 2: Metadata Confirmation (for new videos) -->
                <div id="step-metadata" class="import-step" style="display: none;">
                    <h6>視頻信息</h6>
                    <dl class="row">
                        <dt class="col-sm-3">標題</dt>
                        <dd class="col-sm-9" id="metadata-title">-</dd>

                        <dt class="col-sm-3">頻道</dt>
                        <dd class="col-sm-9" id="metadata-channel">-</dd>

                        <dt class="col-sm-3">發布日期</dt>
                        <dd class="col-sm-9" id="metadata-published">-</dd>
                    </dl>

                    <div class="form-group">
                        <label>標籤</label>
                        <div id="tag-selection" class="form-control" style="height: auto; min-height: 100px;">
                            <!-- Tags will be populated here -->
                        </div>
                    </div>
                </div>

                <!-- Step 3: Preview Comments -->
                <div id="step-preview" class="import-step" style="display: none;">
                    <h6>預覽評論 (前5條)</h6>
                    <div id="preview-comments-container" style="max-height: 300px; overflow-y: auto;">
                        <!-- Comments will be populated here -->
                    </div>
                </div>

                <!-- Loading Indicator -->
                <div id="loading-indicator" style="display: none; text-align: center;">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">加載中...</span>
                    </div>
                    <p>正在處理，請稍候...</p>
                </div>

                <!-- Success Message -->
                <div id="success-message" class="alert alert-success" style="display: none;"></div>
            </div>

            <div class="modal-footer">
                <button type="button" id="btn-cancel" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="button" id="btn-next" class="btn btn-primary">下一步</button>
                <button type="button" id="btn-confirm" class="btn btn-success" style="display: none;">確認導入</button>
            </div>
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
    const loadingIndicator = document.getElementById('loading-indicator');
    const successMessage = document.getElementById('success-message');

    let currentStep = 'url-input';
    let currentVideoId = null;
    let currentVideoMetadata = null;

    // Step navigation
    btnNext.addEventListener('click', handleNextStep);
    btnConfirm.addEventListener('click', handleConfirmImport);
    btnCancel.addEventListener('click', resetModal);

    // Video URL input - trigger metadata fetch on Enter
    videoUrlInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            handleNextStep();
        }
    });

    function resetModal() {
        currentStep = 'url-input';
        currentVideoId = null;
        currentVideoMetadata = null;
        videoUrlInput.value = '';
        document.getElementById('url-error').style.display = 'none';
        showStep('url-input');
        btnNext.style.display = 'inline-block';
        btnConfirm.style.display = 'none';
    }

    function showStep(step) {
        document.querySelectorAll('.import-step').forEach(el => el.style.display = 'none');
        document.getElementById('step-' + step).style.display = 'block';
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
        document.getElementById('url-error').style.display = 'none';

        try {
            // Extract video ID
            currentVideoId = extractVideoId(videoUrl);

            // Check if video exists or fetch metadata
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
                btnNext.innerText = '下一步';
                showLoading(false);
            }
        } catch (error) {
            showError('獲取視頻信息失敗: ' + error.message);
            showLoading(false);
        }
    }

    function displayMetadata(metadata) {
        document.getElementById('metadata-title').innerText = metadata.title || '-';
        document.getElementById('metadata-channel').innerText = metadata.channel_name || '-';
        document.getElementById('metadata-published').innerText = metadata.published_at
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
            btnNext.style.display = 'none';
            btnConfirm.style.display = 'inline-block';
            btnConfirm.innerText = '確認導入';
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
            container.innerHTML = '<p class="text-muted">沒有新的評論</p>';
            return;
        }

        comments.forEach(comment => {
            const commentEl = document.createElement('div');
            commentEl.className = 'card mb-2';
            if (comment.parent_comment_id) {
                commentEl.style.marginLeft = '20px';
            }

            const publishedDate = new Date(comment.published_at).toLocaleDateString('zh-TW');

            commentEl.innerHTML = `
                <div class="card-body p-2">
                    <small class="text-muted">${comment.author_channel_id || '匿名用戶'} - ${publishedDate}</small>
                    <p class="mb-1">${escapeHtml(comment.text.substring(0, 200))}${comment.text.length > 200 ? '...' : ''}</p>
                    <small class="text-muted">👍 ${comment.like_count}</small>
                </div>
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
                $(modal).modal('hide');
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
        loadingIndicator.style.display = show ? 'block' : 'none';
    }

    function showError(message) {
        const errorDiv = document.getElementById('url-error');
        errorDiv.innerText = message;
        errorDiv.style.display = 'block';
    }

    function showSuccess(message) {
        const successDiv = document.getElementById('success-message');
        successDiv.innerText = message;
        successDiv.style.display = 'block';
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
        $(modal).modal('show');
    };
});
</script>

<style>
#youtube-import-modal .import-step {
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

#preview-comments-container .card {
    border-left: 3px solid #007bff;
}

#preview-comments-container .card.reply {
    border-left-color: #6c757d;
    margin-left: 30px;
}
</style>
