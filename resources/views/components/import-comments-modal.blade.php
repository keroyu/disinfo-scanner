<div x-data="importCommentsModal()" x-show="show" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @keydown.escape.window="closeModal()" @open-import-modal.window="openModal()">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" @click.away="closeModal()">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white z-10">
            <h2 class="text-xl font-semibold text-gray-800">
                <i class="fab fa-youtube text-red-600 mr-2"></i>官方 API 導入留言
            </h2>
            <button @click="closeModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="px-6 py-4">
            <!-- Step 1: URL Input -->
            <div x-show="currentStep === 'input'">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        YouTube 影片網址
                    </label>
                    <input
                        type="text"
                        x-model="videoUrl"
                        @keydown.enter="checkVideo()"
                        placeholder="https://www.youtube.com/watch?v=... 或 https://youtu.be/..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                    >
                </div>

                <!-- Import Replies Option -->
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" x-model="importReplies" class="rounded border-gray-300 text-red-600 focus:ring-red-500 mr-2">
                        <span class="text-sm text-gray-700">導入回覆留言 (Replies)</span>
                    </label>
                </div>

                <!-- Error Message -->
                <div x-show="errorMessage" x-text="errorMessage" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"></div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-3">
                    <button @click="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        取消
                    </button>
                    <button
                        @click="checkVideo()"
                        :disabled="!videoUrl || loading"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span x-show="!loading">檢查影片</span>
                        <span x-show="loading">
                            <i class="fas fa-spinner fa-spin mr-2"></i>檢查中...
                        </span>
                    </button>
                </div>
            </div>

            <!-- Step 2: Preview (Video Already Exists) -->
            <div x-show="currentStep === 'video_exists'">
                <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-yellow-600 mt-1 mr-3"></i>
                        <div>
                            <h3 class="font-semibold text-yellow-800 mb-1">影片已建檔</h3>
                            <p class="text-sm text-yellow-700">此影片已存在於資料庫中，請使用更新功能導入新留言。</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button @click="closeModal()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        關閉
                    </button>
                </div>
            </div>

            <!-- Step 3: Preview (New Video + Existing Channel) -->
            <div x-show="currentStep === 'new_video_existing_channel'">
                <div class="space-y-4">
                    <!-- Video Info -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-800 mb-2">影片資訊</h3>
                        <p class="text-sm text-gray-600 mb-1"><strong>標題：</strong><span x-text="previewData?.video_title || ''"></span></p>
                        <p class="text-sm text-gray-600 mb-1"><strong>頻道：</strong><span x-text="previewData?.channel_title || ''"></span></p>
                        <p class="text-sm text-gray-600 mb-1"><strong>發布時間：</strong><span x-text="previewData?.video_published_at || ''"></span></p>
                        <p class="text-sm text-gray-600"><strong>留言數量：</strong><span x-text="previewData?.comment_count_total || ''"></span></p>
                    </div>

                    <!-- Existing Channel Tags -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-800 mb-2">
                            <i class="fas fa-check-circle text-blue-600 mr-2"></i>現有頻道標籤
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="tag in (previewData?.existing_channel_tags || [])" :key="tag.id">
                                <span class="px-3 py-1 rounded-full text-sm text-white" :style="`background-color: ${tag.color}`" x-text="tag.name"></span>
                            </template>
                        </div>
                    </div>

                    <!-- Preview Comments -->
                    <div>
                        <h3 class="font-semibold text-gray-800 mb-2">預覽留言 (最新5則)</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            <template x-for="comment in (previewData?.preview_comments || [])" :key="comment.comment_id">
                                <div class="bg-white border border-gray-200 p-3 rounded text-sm">
                                    <div class="flex items-start gap-2">
                                        <img :src="comment.author_profile_image_url" class="w-8 h-8 rounded-full" :alt="comment.author_display_name">
                                        <div class="flex-1">
                                            <div class="font-semibold text-gray-800" x-text="comment.author_display_name"></div>
                                            <div class="text-gray-600 text-xs mb-1" x-text="comment.published_at"></div>
                                            <div class="text-gray-700" x-text="comment.text_display"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <div x-show="errorMessage" x-text="errorMessage" class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"></div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-3">
                        <button @click="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            取消
                        </button>
                        <button
                            @click="confirmImport()"
                            :disabled="loading"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                        >
                            <span x-show="!loading">確認導入</span>
                            <span x-show="loading">
                                <i class="fas fa-spinner fa-spin mr-2"></i>導入中...
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 4: Preview (New Video + New Channel) -->
            <div x-show="currentStep === 'new_video_new_channel'">
                <div class="space-y-4">
                    <!-- Video Info -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-800 mb-2">影片資訊</h3>
                        <p class="text-sm text-gray-600 mb-1"><strong>標題：</strong><span x-text="previewData?.video_title || ''"></span></p>
                        <p class="text-sm text-gray-600 mb-1"><strong>頻道：</strong><span x-text="previewData?.channel_title || ''"></span></p>
                        <p class="text-sm text-gray-600 mb-1"><strong>發布時間：</strong><span x-text="previewData?.video_published_at || ''"></span></p>
                        <p class="text-sm text-gray-600"><strong>留言數量：</strong><span x-text="previewData?.comment_count_total || ''"></span></p>
                    </div>

                    <!-- New Channel Notice -->
                    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                            <div>
                                <h3 class="font-semibold text-yellow-800 mb-1">新頻道</h3>
                                <p class="text-sm text-yellow-700">此頻道尚未建檔，請至少選擇一個標籤。</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tag Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            選擇標籤 <span class="text-red-600">*</span>
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="tag in (previewData?.available_tags || [])" :key="tag.id">
                                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border-2 cursor-pointer transition-all"
                                       :class="selectedTags.includes(tag.id) ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-blue-400'">
                                    <input
                                        type="checkbox"
                                        :value="tag.id"
                                        x-model="selectedTags"
                                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <span class="text-sm font-medium" x-text="tag.name"></span>
                                </label>
                            </template>
                        </div>
                        <p x-show="selectedTags.length === 0" class="text-xs text-red-600 mt-1">請至少選擇一個標籤</p>
                    </div>

                    <!-- Preview Comments -->
                    <div>
                        <h3 class="font-semibold text-gray-800 mb-2">預覽留言 (最新5則)</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            <template x-for="comment in (previewData?.preview_comments || [])" :key="comment.comment_id">
                                <div class="bg-white border border-gray-200 p-3 rounded text-sm">
                                    <div class="flex items-start gap-2">
                                        <img :src="comment.author_profile_image_url" class="w-8 h-8 rounded-full" :alt="comment.author_display_name">
                                        <div class="flex-1">
                                            <div class="font-semibold text-gray-800" x-text="comment.author_display_name"></div>
                                            <div class="text-gray-600 text-xs mb-1" x-text="comment.published_at"></div>
                                            <div class="text-gray-700" x-text="comment.text_display"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <div x-show="errorMessage" x-text="errorMessage" class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"></div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-3">
                        <button @click="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            取消
                        </button>
                        <button
                            @click="confirmImport()"
                            :disabled="loading || selectedTags.length === 0"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span x-show="!loading">確認導入</span>
                            <span x-show="loading">
                                <i class="fas fa-spinner fa-spin mr-2"></i>導入中...
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 5: Success -->
            <div x-show="currentStep === 'success'">
                <div class="text-center py-8">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-green-600 text-6xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">導入成功！</h3>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p>已成功導入 <span class="font-semibold" x-text="importResult?.comments_imported || 0"></span> 則留言</p>
                        <p x-show="(importResult?.replies_imported || 0) > 0">包含 <span class="font-semibold" x-text="importResult?.replies_imported || 0"></span> 則回覆</p>
                    </div>
                </div>

                <div class="flex justify-center">
                    <button @click="closeModalAndRefresh()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        完成
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function importCommentsModal() {
    return {
        show: false,
        currentStep: 'input',
        loading: false,
        videoUrl: '',
        importReplies: true,
        selectedTags: [],
        previewData: null,
        importResult: null,
        errorMessage: '',

        openModal() {
            this.show = true;
            this.resetModal();
        },

        closeModal() {
            this.show = false;
        },

        closeModalAndRefresh() {
            this.show = false;
            window.location.reload();
        },

        resetModal() {
            this.currentStep = 'input';
            this.loading = false;
            this.videoUrl = '';
            this.importReplies = true;
            this.selectedTags = [];
            this.previewData = null;
            this.importResult = null;
            this.errorMessage = '';
        },

        async checkVideo() {
            if (!this.videoUrl) return;

            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await fetch('/api/comments/check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        video_url: this.videoUrl
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    this.errorMessage = data.message || '檢查失敗';
                    return;
                }

                this.previewData = data;
                this.currentStep = data.status;

                // Pre-select existing tags for existing channel scenario
                if (data.status === 'new_video_existing_channel' && data.existing_channel_tags) {
                    this.selectedTags = data.existing_channel_tags.map(tag => tag.id);
                }
            } catch (error) {
                this.errorMessage = '網路錯誤：' + error.message;
            } finally {
                this.loading = false;
            }
        },

        async confirmImport() {
            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await fetch('/api/comments/import', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        video_url: this.videoUrl,
                        scenario: this.currentStep,
                        channel_tags: this.selectedTags,
                        import_replies: this.importReplies
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    this.errorMessage = data.message || '導入失敗';
                    return;
                }

                this.importResult = data;
                this.currentStep = 'success';
            } catch (error) {
                this.errorMessage = '網路錯誤：' + error.message;
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>

<style>
[x-cloak] {
    display: none !important;
}
</style>
