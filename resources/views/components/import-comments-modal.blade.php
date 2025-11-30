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

        <!-- Important Notice -->
        <div class="px-6 py-3 bg-red-50 border-b border-red-200">
            <div class="text-sm text-red-700 space-y-1">
                <p class="font-semibold"><i class="fas fa-exclamation-triangle mr-1"></i>重要提示：</p>
                <p>1. 導入任何影片請優先使用 U-API！</p>
                <p>2. 留言過多可能導入失敗。</p>
                <p>3. 使用本功能需通過手機驗證，並設定 YouTube API Key。</p>
            </div>
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

            <!-- Step 2.5: No Comments -->
            <div x-show="currentStep === 'no_comments'">
                <div class="mb-4 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-circle text-orange-600 mt-1 mr-3"></i>
                        <div>
                            <h3 class="font-semibold text-orange-800 mb-1">此影片沒有留言</h3>
                            <p class="text-sm text-orange-700 mb-2" x-text="previewData?.details || '該影片目前沒有任何留言可以導入。'"></p>
                            <div class="text-sm text-gray-600 space-y-1">
                                <p><strong>影片標題：</strong><span x-text="previewData?.video_title || ''"></span></p>
                                <p><strong>頻道名稱：</strong><span x-text="previewData?.channel_title || ''"></span></p>
                            </div>
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

                    <!-- Import Limit Warning -->
                    <div x-show="previewData?.import_limit_warning" class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                            <div>
                                <h3 class="font-semibold text-yellow-800 mb-1">導入限制</h3>
                                <p class="text-sm text-yellow-700" x-text="previewData?.import_limit_warning || ''"></p>
                                <p class="text-sm text-yellow-700 mt-1">
                                    實際將導入：<strong x-text="previewData?.will_import_count || ''"></strong> 則留言
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Channel Tags -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-800 mb-2">
                            <i class="fas fa-check-circle text-blue-600 mr-2"></i>現有頻道標籤
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="tag in (previewData?.existing_channel_tags || [])" :key="tag.id">
                                <span class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-lg bg-white">
                                    <span class="inline-block w-3 h-3 rounded-full" :style="`background-color: ${getColor(tag.color)}`"></span>
                                    <span class="ml-2 font-medium text-sm text-gray-800" x-text="tag.name"></span>
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Preview Comments -->
                    <div>
                        <h3 class="font-semibold text-gray-800 mb-2">預覽留言 (實際要導入的前5則，從舊到新)</h3>
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

                    <!-- Import Limit Warning -->
                    <div x-show="previewData?.import_limit_warning" class="bg-orange-50 border border-orange-200 p-4 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-orange-600 mt-1 mr-3"></i>
                            <div>
                                <h3 class="font-semibold text-orange-800 mb-1">導入限制</h3>
                                <p class="text-sm text-orange-700" x-text="previewData?.import_limit_warning || ''"></p>
                                <p class="text-sm text-orange-700 mt-1">
                                    實際將導入：<strong x-text="previewData?.will_import_count || ''"></strong> 則留言
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- New Channel Notice -->
                    <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded">
                        <p class="text-sm text-amber-900 font-medium">⚠ 檢測到新頻道，請選擇標籤</p>
                        <p class="text-sm text-amber-700 mt-1">請至少選擇一個標籤以便分類</p>
                    </div>

                    <!-- Tag Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            選擇標籤 <span class="text-red-600">*</span>
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="tag in (previewData?.available_tags || [])" :key="tag.id">
                                <label class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 whitespace-nowrap">
                                    <input
                                        type="checkbox"
                                        :value="tag.id"
                                        x-model="selectedTags"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <span class="ml-2 flex items-center gap-2">
                                        <span class="inline-block w-3 h-3 rounded-full" :style="`background-color: ${getColor(tag.color)}`"></span>
                                        <span class="font-medium text-sm" x-text="tag.name"></span>
                                    </span>
                                </label>
                            </template>
                        </div>
                        <p x-show="selectedTags.length === 0" class="text-xs text-red-600 mt-1">請至少選擇一個標籤</p>
                    </div>

                    <!-- Preview Comments -->
                    <div>
                        <h3 class="font-semibold text-gray-800 mb-2">預覽留言 (實際要導入的前5則，從舊到新)</h3>
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
                        <p>已成功導入 <span class="font-semibold" x-text="importResult?.imported_comment_count || 0"></span> 則留言</p>
                        <p x-show="(importResult?.imported_reply_count || 0) > 0">包含 <span class="font-semibold" x-text="importResult?.imported_reply_count || 0"></span> 則回覆</p>
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

        // Color map dynamically generated from TailwindColor helper
        colorMap: {!! \App\Helpers\TailwindColor::toJson() !!},

        getColor(colorClass) {
            return this.colorMap[colorClass] || '#6b7280';
        },

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
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
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
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
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
