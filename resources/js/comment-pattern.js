/**
 * Comment Pattern UI Manager
 * Handles pattern filter list, infinite scroll, and comment display
 */
class CommentPatternUI {
    constructor(videoId) {
        this.videoId = videoId;
        this.currentPattern = 'all';
        this.currentOffset = 0;
        this.isLoading = false;
        this.hasMore = true;
        this.observer = null;
        this.statistics = null;
    }

    /**
     * Initialize the UI
     */
    async init() {
        await this.loadStatistics();
        this.renderFilterList();
        this.setupInfiniteScroll();
        await this.loadComments('all', 0);
    }

    /**
     * Load pattern statistics from API
     */
    async loadStatistics() {
        try {
            const response = await fetch(`/api/videos/${this.videoId}/pattern-statistics`);
            if (!response.ok) {
                throw new Error('Failed to load statistics');
            }
            const data = await response.json();
            this.statistics = data.patterns;
        } catch (error) {
            console.error('Error loading statistics:', error);
            this.showError('無法載入統計資料');
        }
    }

    /**
     * Render the filter list on the left side
     */
    renderFilterList() {
        const container = document.getElementById('patternFilterList');
        if (!container || !this.statistics) return;

        const filters = [
            { key: 'all', label: '所有留言', count: this.statistics.all.count, percentage: this.statistics.all.percentage },
            { key: 'repeat', label: '重複留言者有', count: this.statistics.repeat.count, percentage: this.statistics.repeat.percentage },
            { key: 'night_time', label: '夜間高頻留言者有', count: this.statistics.night_time.count, percentage: this.statistics.night_time.percentage },
            { key: 'aggressive', label: '高攻擊性留言者有', count: this.statistics.aggressive.count, percentage: this.statistics.aggressive.percentage },
            { key: 'simplified_chinese', label: '簡體中文留言者有', count: this.statistics.simplified_chinese.count, percentage: this.statistics.simplified_chinese.percentage }
        ];

        container.innerHTML = filters.map(filter => {
            const isActive = filter.key === this.currentPattern;
            const activeClasses = isActive ? 'bg-blue-100 border-blue-500' : 'bg-white hover:bg-gray-50';

            return `
                <button
                    class="pattern-filter-item w-full text-left p-3 border rounded-lg transition-colors ${activeClasses}"
                    data-pattern="${filter.key}"
                    onclick="window.commentPatternUI.switchPattern('${filter.key}')"
                >
                    <div class="text-sm font-medium text-gray-700">
                        ${filter.label}
                        ${filter.key !== 'all' ? `<span class="font-bold">${filter.count} 個 (${filter.percentage}%)</span>` : ''}
                    </div>
                </button>
            `;
        }).join('');
    }

    /**
     * Switch to a different pattern filter
     */
    async switchPattern(pattern) {
        if (this.isLoading) return;

        this.currentPattern = pattern;
        this.currentOffset = 0;
        this.hasMore = true;

        // Update visual highlighting
        document.querySelectorAll('.pattern-filter-item').forEach(btn => {
            const btnPattern = btn.getAttribute('data-pattern');
            if (btnPattern === pattern) {
                btn.classList.add('bg-blue-100', 'border-blue-500');
                btn.classList.remove('bg-white', 'hover:bg-gray-50');
            } else {
                btn.classList.remove('bg-blue-100', 'border-blue-500');
                btn.classList.add('bg-white', 'hover:bg-gray-50');
            }
        });

        // Clear comments and load first batch
        const commentsContainer = document.getElementById('commentsList');
        if (commentsContainer) {
            commentsContainer.innerHTML = '';
        }

        await this.loadComments(pattern, 0);
    }

    /**
     * Load comments from API
     */
    async loadComments(pattern, offset) {
        if (this.isLoading || !this.hasMore) return;

        this.isLoading = true;
        this.showLoadingIndicator();

        try {
            const url = `/api/videos/${this.videoId}/comments?pattern=${pattern}&offset=${offset}&limit=100`;
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error('Failed to load comments');
            }

            const data = await response.json();

            // Handle placeholder patterns
            if (pattern === 'aggressive' || pattern === 'simplified_chinese') {
                this.showPlaceholderMessage(pattern);
                this.hasMore = false;
                return;
            }

            this.appendComments(data.comments);
            this.hasMore = data.has_more;
            this.currentOffset = offset + data.comments.length;

            if (data.comments.length === 0 && offset === 0) {
                this.showEmptyState();
            }

        } catch (error) {
            console.error('Error loading comments:', error);
            this.showError('無法載入留言');
        } finally {
            this.isLoading = false;
            this.hideLoadingIndicator();
        }
    }

    /**
     * Append comments to the list
     */
    appendComments(comments) {
        const container = document.getElementById('commentsList');
        if (!container) return;

        // Calculate date range (past 2 years)
        const toDate = new Date();
        const fromDate = new Date();
        fromDate.setFullYear(fromDate.getFullYear() - 2);

        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        const fromDateStr = formatDate(fromDate);
        const toDateStr = formatDate(toDate);

        comments.forEach(comment => {
            // Build URL for Comments List search
            const searchUrl = `/comments?search=${encodeURIComponent(comment.author_channel_id)}&from_date=${fromDateStr}&to_date=${toDateStr}`;

            const commentHtml = `
                <div class="comment-item p-4 border-b border-gray-200 hover:bg-gray-50">
                    <div class="flex items-start gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <a
                                    href="${searchUrl}"
                                    class="font-semibold text-blue-600 hover:text-blue-800 text-sm"
                                    title="View all comments by ${this.escapeHtml(comment.author_channel_id)} (past 2 years)"
                                >
                                    ${this.escapeHtml(comment.author_name)}
                                </a>
                                <a
                                    href="https://www.youtube.com/channel/${this.escapeHtml(comment.author_channel_id)}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-red-600 hover:text-red-700 flex-shrink-0"
                                    title="View commenter channel on YouTube"
                                >
                                    <i class="fab fa-youtube"></i>
                                </a>
                                <span class="text-xs text-gray-500">${comment.published_at || 'N/A'}</span>
                            </div>
                            <p class="text-gray-700 text-sm whitespace-pre-wrap">${this.escapeHtml(comment.text)}</p>
                            <div class="mt-2 flex items-center gap-4 text-xs text-gray-500">
                                <span><i class="fas fa-thumbs-up mr-1"></i>${comment.like_count || 0}</span>
                                <span class="font-mono">${this.escapeHtml(comment.author_channel_id)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', commentHtml);
        });
    }

    /**
     * Setup infinite scroll using Intersection Observer
     */
    setupInfiniteScroll() {
        const sentinel = document.getElementById('scrollSentinel');
        if (!sentinel) return;

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.isLoading && this.hasMore) {
                    this.loadComments(this.currentPattern, this.currentOffset);
                }
            });
        }, {
            root: null,
            rootMargin: '100px',
            threshold: 0.1
        });

        this.observer.observe(sentinel);
    }

    /**
     * Show placeholder message for unimplemented patterns
     */
    showPlaceholderMessage(pattern) {
        const container = document.getElementById('commentsList');
        if (!container) return;

        const messages = {
            'aggressive': '此功能待人工審查實作',
            'simplified_chinese': '此功能待語言偵測實作'
        };

        container.innerHTML = `
            <div class="flex items-center justify-center h-64">
                <div class="text-center text-gray-500">
                    <i class="fas fa-info-circle text-4xl mb-3"></i>
                    <p class="text-lg">${messages[pattern]}</p>
                </div>
            </div>
        `;
    }

    /**
     * Show empty state when no comments found
     */
    showEmptyState() {
        const container = document.getElementById('commentsList');
        if (!container) return;

        container.innerHTML = `
            <div class="flex items-center justify-center h-64">
                <div class="text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-3"></i>
                    <p class="text-lg">此篩選條件下沒有留言</p>
                </div>
            </div>
        `;
    }

    /**
     * Show loading indicator
     */
    showLoadingIndicator() {
        const indicator = document.getElementById('loadingIndicator');
        if (indicator) {
            indicator.classList.remove('hidden');
        }
    }

    /**
     * Hide loading indicator
     */
    hideLoadingIndicator() {
        const indicator = document.getElementById('loadingIndicator');
        if (indicator) {
            indicator.classList.add('hidden');
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        const container = document.getElementById('commentsList');
        if (!container) return;

        container.innerHTML = `
            <div class="p-4 bg-red-50 border border-red-200 rounded">
                <h3 class="text-red-800 font-semibold mb-2">發生錯誤</h3>
                <p class="text-red-700">${this.escapeHtml(message)}</p>
            </div>
        `;
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Make it globally accessible
window.CommentPatternUI = CommentPatternUI;
