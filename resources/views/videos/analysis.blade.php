@extends('layouts.app')

@section('title', '影片留言密度分析 - ' . $video->title)

@php
    // User permissions
    $canSearch = auth()->check() && (
        auth()->user()->roles->contains('name', 'premium_member') ||
        auth()->user()->roles->contains('name', 'website_editor') ||
        auth()->user()->roles->contains('name', 'administrator')
    );

    // CSV export permission - premium members only
    $canExportCSV = auth()->check() && (
        auth()->user()->roles->contains('name', 'premium_member') ||
        auth()->user()->roles->contains('name', 'website_editor') ||
        auth()->user()->roles->contains('name', 'administrator')
    );
@endphp

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Video Analysis</h1>
        <div class="flex items-center gap-2 mt-2">
            <p class="text-gray-600">{{ $video->title }}</p>
            <a
                href="https://www.youtube.com/watch?v={{ $video->video_id }}"
                target="_blank"
                rel="noopener noreferrer"
                class="text-red-600 hover:text-red-700 transition-colors"
                title="在 YouTube 上觀看"
            >
                <i class="fab fa-youtube text-2xl"></i>
            </a>
        </div>
        <p class="text-sm text-gray-500 mt-1">頻道: {{ $video->channel->channel_name ?? '未知' }}</p>
        <p class="text-sm text-gray-500">發布時間: {{ $video->published_at ? $video->published_at->setTimezone('Asia/Taipei')->format('Y-m-d H:i') . ' (GMT+8)' : '未知' }}</p>
    </div>

    <!-- Comments Pattern Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Comments Pattern</h2>

        <div id="commentsPatternLoading" class="flex items-center justify-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>

        <div id="commentsPatternContent" class="hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- Daytime -->
                <div class="p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-gray-700">Daytime</h3>
                        <span class="text-xs text-gray-500" id="daytimeHours">-</span>
                    </div>
                    <div class="text-2xl font-bold text-yellow-700" id="daytimeCount">-</div>
                    <div class="text-sm text-gray-600 mt-1" id="daytimePercentage">-</div>
                </div>

                <!-- Night -->
                <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-gray-700">Night</h3>
                        <span class="text-xs text-gray-500" id="nightHours">-</span>
                    </div>
                    <div class="text-2xl font-bold text-blue-700" id="nightCount">-</div>
                    <div class="text-sm text-gray-600 mt-1" id="nightPercentage">-</div>
                </div>

                <!-- Late Night -->
                <div class="p-4 bg-indigo-50 rounded-lg border border-indigo-200">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-gray-700">Late Night</h3>
                        <span class="text-xs text-gray-500" id="lateNightHours">-</span>
                    </div>
                    <div class="text-2xl font-bold text-indigo-700" id="lateNightCount">-</div>
                    <div class="text-sm text-gray-600 mt-1" id="lateNightPercentage">-</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Container with Time Range Selector -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-6">Comments Density</h2>

        <!-- Time Range Selector -->
        <div class="mb-6">
            <h3 class="text-md font-semibold text-gray-700 mb-3">Select Time Period</h3>

            <div class="space-y-3">
                <!-- Preset Ranges -->
                <div class="flex flex-wrap gap-3">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="range" value="24hours" checked class="mr-2 text-blue-600">
                        <span>發布後 24 小時內 (每小時)</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="range" value="3days" class="mr-2 text-blue-600">
                        <span>發布後 3 天 (每小時)</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="range" value="7days" class="mr-2 text-blue-600">
                        <span>發布後 7 天 (每小時)</span>
                    </label>
                </div>
            </div>

            <!-- Total Comments Display -->
            <div class="mt-4 p-3 bg-blue-50 rounded">
                <span class="text-sm font-medium text-gray-700">選定範圍內的總留言數: </span>
                <span id="totalComments" class="text-lg font-bold text-blue-600">-</span>
            </div>
        </div>

        <!-- Loading Skeleton -->
        <div id="loadingSkeleton" class="flex items-center justify-center h-96 bg-gray-100 rounded">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-3"></div>
                <p id="loadingMessage" class="text-gray-600">載入圖表資料中...</p>
                <button id="cancelButton" class="hidden mt-3 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    取消並返回
                </button>
            </div>
        </div>

        <!-- Chart Canvas (hidden initially) -->
        <div id="chartContainer" class="hidden">
            <div style="height: 400px; position: relative;">
                <canvas id="densityChart"></canvas>
            </div>
            <p class="text-xs text-gray-500 mt-2 text-center">所有時間均為 Asia/Taipei 時區 (GMT+8)</p>
        </div>

        <!-- Error Display -->
        <div id="errorContainer" class="hidden p-4 bg-red-50 border border-red-200 rounded">
            <h3 class="text-red-800 font-semibold mb-2">發生錯誤</h3>
            <p id="errorMessage" class="text-red-700 mb-2"></p>
            <details class="text-xs text-gray-600">
                <summary class="cursor-pointer hover:text-gray-800">顯示技術細節</summary>
                <pre id="errorDetails" class="mt-2 p-2 bg-gray-100 rounded overflow-auto text-xs"></pre>
            </details>
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="hidden flex items-center justify-center h-96">
            <div class="text-center text-gray-500">
                <i class="fas fa-inbox text-6xl mb-4"></i>
                <p class="text-lg">此時間範圍內沒有留言</p>
            </div>
        </div>
    </div>

    <!-- Commenter Pattern Summary Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Commenter Pattern Summary</h2>
            @if($canExportCSV)
                <button
                    id="exportCsvBtn"
                    class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded hover:bg-green-700 transition-colors flex items-center gap-2"
                    onclick="exportCommentsToCSV()"
                    title="匯出當前顯示的留言為 CSV 檔案"
                >
                    <i class="fas fa-file-csv"></i>
                    <span>Export CSV</span>
                </button>
            @else
                <div class="flex flex-col items-end gap-1">
                    <button
                        class="px-4 py-2 bg-gray-300 text-gray-500 text-sm font-medium rounded cursor-not-allowed flex items-center gap-2"
                        disabled
                        title="此功能僅限高級會員使用"
                    >
                        <i class="fas fa-file-csv"></i>
                        <span>Export CSV</span>
                    </button>
                    <span class="text-xs text-gray-500">僅限高級會員使用</span>
                </div>
            @endif
        </div>

        <!-- Time Filter Indicator -->
        <div id="timeFilterIndicator" class="mb-4 hidden">
            <div class="p-3 bg-blue-50 border border-blue-300 rounded-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-blue-800 font-semibold">📍 時間篩選:</span>
                        <span id="timeFilterCount" class="text-blue-700">-</span>
                    </div>
                    <button
                        id="clearTimeFilterBtn"
                        class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                    >
                        清除時間篩選
                    </button>
                </div>
                <div id="timeFilterRanges" class="mt-2 text-sm text-blue-700">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Panel: Filter List -->
            <div class="lg:col-span-1">
                <div id="patternFilterList" class="space-y-2">
                    <!-- Populated by JavaScript -->
                </div>
            </div>

            <!-- Right Panel: Comments Display -->
            <div class="lg:col-span-2">
                <div class="border rounded-lg h-[600px] overflow-hidden flex flex-col">
                    <!-- Comments Container -->
                    <div id="commentsScrollContainer" class="flex-1 overflow-y-auto">
                        <div id="commentsList">
                            <!-- Populated by JavaScript -->
                        </div>

                        <!-- Scroll Sentinel for Infinite Scroll -->
                        <div id="scrollSentinel" class="h-4"></div>

                        <!-- Loading Indicator -->
                        <div id="loadingIndicator" class="hidden p-4 text-center">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                            <p class="text-gray-600 mt-2">載入更多留言...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

<!-- Time Filter Script -->
<script src="{{ asset('js/time-filter.js') }}"></script>

<!-- Comment Pattern UI Script -->
<script src="{{ asset('js/comment-pattern.js') }}"></script>

<script>
// Global variables
let cachedDensityData = null;
let chartInstance = null;
let loadingTimeout = null;
const videoId = '{{ $video->video_id }}';
let commentPatternUI = null;
let timeFilterState = null;

// User permissions (defined in PHP block at top of file)
const canSearch = {{ $canSearch ? 'true' : 'false' }};
const canExportCSV = {{ $canExportCSV ? 'true' : 'false' }};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeRangeSelector();
    loadInitialData();
    initializeCommentPattern();
});

// Initialize Comment Pattern UI
function initializeCommentPattern() {
    commentPatternUI = new CommentPatternUI(videoId);

    // Initialize time filter state
    timeFilterState = new TimeFilterState();
    commentPatternUI.timeFilterState = timeFilterState;

    commentPatternUI.init();
    window.commentPatternUI = commentPatternUI;
    window.timeFilterState = timeFilterState;
}

// Range selector logic
function initializeRangeSelector() {
    const rangeInputs = document.querySelectorAll('input[name="range"]');

    rangeInputs.forEach(input => {
        input.addEventListener('change', (e) => {
            // Clear time filter when switching ranges
            if (timeFilterState && timeFilterState.hasSelection()) {
                timeFilterState.clearAll();
                updateTimeFilterIndicator();

                // Reload statistics without time filter
                if (commentPatternUI) {
                    commentPatternUI.loadStatistics(null);
                }

                // Reload comments
                if (commentPatternUI) {
                    commentPatternUI.switchPattern(commentPatternUI.currentPattern);
                }
            }
            updateChartWithRange(e.target.value);
        });
    });
}

// Load data from API (ONE-TIME fetch)
async function loadInitialData() {
    showLoadingSkeleton();

    // Set timeout for extended loading UI
    loadingTimeout = setTimeout(() => {
        document.getElementById('loadingMessage').textContent = '資料量較大，處理中...';
        document.getElementById('cancelButton').classList.remove('hidden');
    }, 3000);

    try {
        const response = await fetch(`/api/videos/${videoId}/comment-density`);
        clearTimeout(loadingTimeout);

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(JSON.stringify(errorData));
        }

        cachedDensityData = await response.json();

        // Populate comments pattern section
        populateCommentsPattern(cachedDensityData.comments_pattern);

        // Check if no comments
        const totalHourly = cachedDensityData.hourly_data.data.reduce((sum, d) => sum + d.count, 0);
        if (totalHourly === 0) {
            showEmptyState();
            return;
        }

        // Initialize chart with default range (24 hours)
        updateChartWithRange('24hours');
        hideLoadingSkeleton();

    } catch (error) {
        clearTimeout(loadingTimeout);
        showError(error);
    }
}

// Update chart based on selected range (CLIENT-SIDE FILTERING)
function updateChartWithRange(rangeType) {
    if (!cachedDensityData) return;

    let filteredData;

    switch (rangeType) {
        case '24hours':
            filteredData = cachedDensityData.hourly_data.data.slice(0, 24);
            break;
        case '3days':
            filteredData = cachedDensityData.hourly_data.data.slice(0, 72);
            break;
        case '7days':
            filteredData = cachedDensityData.hourly_data.data.slice(0, 168);
            break;
        default:
            filteredData = cachedDensityData.hourly_data.data.slice(0, 24); // Default 24 hours
    }

    renderChart(filteredData);
    updateTotalComments(filteredData);
}

// Render Chart.js chart
function renderChart(filteredData) {
    const ctx = document.getElementById('densityChart').getContext('2d');

    const chartData = {
        datasets: [{
            label: '留言數量',
            data: filteredData.map(d => ({ x: d.timestamp, y: d.count })),
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.1,
            fill: true
        }]
    };

    const chartConfig = {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            onClick: handleChartClick,
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: filteredData.length > 200 ? 'day' : 'hour',
                        displayFormats: {
                            hour: 'MM/dd HH:mm',
                            day: 'yyyy-MM-dd'
                        }
                    },
                    title: {
                        display: true,
                        text: '時間 (GMT+8)'
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '留言數量'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        title: (context) => {
                            const dataPoint = filteredData[context[0].dataIndex];
                            return dataPoint.display_time;
                        },
                        label: (context) => {
                            return `留言數: ${context.parsed.y}`;
                        }
                    }
                },
                legend: {
                    display: true
                }
            }
        }
    };

    if (chartInstance) {
        chartInstance.data = chartData;
        chartInstance.options = chartConfig.options;
        chartInstance.update();
    } else {
        chartInstance = new Chart(ctx, chartConfig);
    }

    // Initialize time filter state with chart instance
    if (timeFilterState) {
        timeFilterState.init(chartInstance);
    }
}

// Handle chart click for time filtering
function handleChartClick(event, activeElements) {
    if (!activeElements || activeElements.length === 0) return;
    if (!timeFilterState || !commentPatternUI) return;

    const dataIndex = activeElements[0].index;
    const dataset = chartInstance.data.datasets[0];
    const dataPoint = dataset.data[dataIndex];

    // Get the timestamp and format it for the time filter
    const timestamp = timeFilterState.formatTimestampForComparison(dataPoint.x);

    // Toggle the time point
    timeFilterState.toggleTimePoint(timestamp);

    // Update chart highlighting
    timeFilterState.updateChartHighlighting();

    // Update the filter indicator
    updateTimeFilterIndicator();

    // Reload statistics with time filter
    reloadStatisticsWithTimeFilter();

    // Reload comments with new filter
    commentPatternUI.currentOffset = 0;
    commentPatternUI.hasMore = true;
    commentPatternUI.currentComments = []; // Clear stored comments
    const commentsContainer = document.getElementById('commentsList');
    if (commentsContainer) {
        commentsContainer.innerHTML = '';
    }
    commentPatternUI.loadComments(commentPatternUI.currentPattern, 0);
}

// Update time filter indicator UI
function updateTimeFilterIndicator() {
    const indicator = document.getElementById('timeFilterIndicator');
    const countEl = document.getElementById('timeFilterCount');
    const rangesEl = document.getElementById('timeFilterRanges');

    if (!timeFilterState || !indicator || !countEl || !rangesEl) return;

    if (timeFilterState.hasSelection()) {
        indicator.classList.remove('hidden');
        const count = timeFilterState.getCount();
        countEl.textContent = `已選擇 ${count} 個時間段`;
        rangesEl.textContent = timeFilterState.getDisplayString();
    } else {
        indicator.classList.add('hidden');
    }
}

// Reload statistics with time filter applied
async function reloadStatisticsWithTimeFilter() {
    if (!timeFilterState || !commentPatternUI) return;

    const timePointsParam = timeFilterState.getTimePointsParam();
    await commentPatternUI.loadStatistics(timePointsParam);
}

// Update total comments display
function updateTotalComments(filteredData) {
    const total = filteredData.reduce((sum, d) => sum + d.count, 0);
    document.getElementById('totalComments').textContent = total.toLocaleString();
}

// Populate comments pattern section
function populateCommentsPattern(patternData) {
    if (!patternData) return;

    // Hide loading, show content
    document.getElementById('commentsPatternLoading').classList.add('hidden');
    document.getElementById('commentsPatternContent').classList.remove('hidden');

    // Populate daytime data
    document.getElementById('daytimeHours').textContent = patternData.daytime.hours;
    document.getElementById('daytimeCount').textContent = patternData.daytime.count.toLocaleString();
    document.getElementById('daytimePercentage').textContent = `${patternData.daytime.percentage}%`;

    // Populate night data
    document.getElementById('nightHours').textContent = patternData.night.hours;
    document.getElementById('nightCount').textContent = patternData.night.count.toLocaleString();
    document.getElementById('nightPercentage').textContent = `${patternData.night.percentage}%`;

    // Populate late night data
    document.getElementById('lateNightHours').textContent = patternData.late_night.hours;
    document.getElementById('lateNightCount').textContent = patternData.late_night.count.toLocaleString();

    const lateNightPercentageElement = document.getElementById('lateNightPercentage');
    lateNightPercentageElement.textContent = `${patternData.late_night.percentage}%`;

    // Apply red bold styling if Late Night percentage > 10%
    if (patternData.late_night.percentage > 10) {
        lateNightPercentageElement.classList.add('text-red-600', 'font-bold');
    } else {
        lateNightPercentageElement.classList.remove('text-red-600', 'font-bold');
    }
}

// UI state management
function showLoadingSkeleton() {
    document.getElementById('loadingSkeleton').classList.remove('hidden');
    document.getElementById('chartContainer').classList.add('hidden');
    document.getElementById('errorContainer').classList.add('hidden');
    document.getElementById('emptyState').classList.add('hidden');
}

function hideLoadingSkeleton() {
    document.getElementById('loadingSkeleton').classList.add('hidden');
    document.getElementById('chartContainer').classList.remove('hidden');
}

function showError(error) {
    document.getElementById('loadingSkeleton').classList.add('hidden');
    document.getElementById('errorContainer').classList.remove('hidden');

    let errorData;
    try {
        errorData = JSON.parse(error.message);
    } catch {
        errorData = { error: { type: 'UnknownError', message: error.message, details: {} } };
    }

    document.getElementById('errorMessage').textContent = errorData.error.message;
    document.getElementById('errorDetails').textContent = JSON.stringify(errorData.error.details, null, 2);
}

function showEmptyState() {
    document.getElementById('loadingSkeleton').classList.add('hidden');
    document.getElementById('emptyState').classList.remove('hidden');
}

// Export comments to CSV - opens field selection modal
function exportCommentsToCSV() {
    // Check permission
    if (!canExportCSV) {
        alert('此功能僅限高級會員使用\n\nExport CSV 功能需要高級會員權限。\n請聯繫管理員升級您的帳號。');
        return;
    }

    if (!commentPatternUI) {
        alert('無法匯出留言：系統尚未初始化');
        return;
    }

    if (!commentPatternUI.currentComments || commentPatternUI.currentComments.length === 0) {
        alert('目前沒有留言可匯出');
        return;
    }

    // Show the field selection modal
    document.getElementById('csv-export-modal').classList.remove('hidden');
}

// Cancel button handler
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('cancelButton').addEventListener('click', () => {
        window.location.href = '{{ route("videos.index") }}';
    });

    // Clear time filter button handler
    const clearTimeFilterBtn = document.getElementById('clearTimeFilterBtn');
    if (clearTimeFilterBtn) {
        clearTimeFilterBtn.addEventListener('click', () => {
            if (timeFilterState) {
                timeFilterState.clearAll();
                timeFilterState.updateChartHighlighting();
                updateTimeFilterIndicator();

                // Reload statistics without time filter (back to original)
                if (commentPatternUI) {
                    commentPatternUI.loadStatistics(null);
                }

                // Reload comments
                if (commentPatternUI) {
                    commentPatternUI.currentOffset = 0;
                    commentPatternUI.hasMore = true;
                    commentPatternUI.currentComments = []; // Clear stored comments
                    const commentsContainer = document.getElementById('commentsList');
                    if (commentsContainer) {
                        commentsContainer.innerHTML = '';
                    }
                    commentPatternUI.loadComments(commentPatternUI.currentPattern, 0);
                }
            }
        });
    }

    // CSV Export Modal handlers
    const csvModal = document.getElementById('csv-export-modal');
    const csvSelectAllBtn = document.getElementById('csv-select-all');
    const csvDeselectAllBtn = document.getElementById('csv-deselect-all');
    const csvModalExportBtn = document.getElementById('csv-modal-export');
    const csvModalCancelBtn = document.getElementById('csv-modal-cancel');
    const csvFieldError = document.getElementById('csv-field-error');

    // Select All
    csvSelectAllBtn.addEventListener('click', () => {
        document.querySelectorAll('.csv-field-checkbox').forEach(cb => {
            cb.checked = true;
        });
        csvFieldError.classList.add('hidden');
    });

    // Deselect All
    csvDeselectAllBtn.addEventListener('click', () => {
        document.querySelectorAll('.csv-field-checkbox').forEach(cb => {
            cb.checked = false;
        });
    });

    // Export button
    csvModalExportBtn.addEventListener('click', () => {
        const selectedFields = Array.from(document.querySelectorAll('.csv-field-checkbox:checked'))
            .map(cb => cb.value);

        if (selectedFields.length === 0) {
            csvFieldError.classList.remove('hidden');
            return;
        }

        csvFieldError.classList.add('hidden');
        csvModal.classList.add('hidden');

        // Call export with selected fields
        if (commentPatternUI) {
            commentPatternUI.exportToCSV(selectedFields);
        }
    });

    // Cancel button
    csvModalCancelBtn.addEventListener('click', () => {
        csvModal.classList.add('hidden');
    });

    // Close modal when clicking outside
    csvModal.addEventListener('click', (e) => {
        if (e.target === csvModal) {
            csvModal.classList.add('hidden');
        }
    });
});
</script>
<!-- CSV Export Field Selection Modal -->
<div id="csv-export-modal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-[60]">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 p-6 animate-fade-in">
        <h2 class="text-xl font-bold text-gray-900 mb-4">選擇要匯出的欄位</h2>

        <!-- Select All / Deselect All -->
        <div class="mb-4 flex gap-3">
            <button
                id="csv-select-all"
                class="text-sm text-blue-600 hover:text-blue-800 font-medium"
            >
                全選
            </button>
            <button
                id="csv-deselect-all"
                class="text-sm text-gray-600 hover:text-gray-800 font-medium"
            >
                取消全選
            </button>
        </div>

        <!-- Field Selection -->
        <div id="csv-fields-container" class="mb-6 space-y-2 max-h-80 overflow-y-auto">
            <label class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 w-full">
                <input type="checkbox" class="csv-field-checkbox" value="author_channel_id" checked>
                <span class="ml-2 font-medium text-sm">作者頻道 ID (Author Channel ID)</span>
            </label>

            <label class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 w-full">
                <input type="checkbox" class="csv-field-checkbox" value="published_at" checked>
                <span class="ml-2 font-medium text-sm">發布時間 (Published At)</span>
            </label>

            <label class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 w-full">
                <input type="checkbox" class="csv-field-checkbox" value="text" checked>
                <span class="ml-2 font-medium text-sm">留言內容 (Comment Text)</span>
            </label>

            <label class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 w-full">
                <input type="checkbox" class="csv-field-checkbox" value="like_count" checked>
                <span class="ml-2 font-medium text-sm">按讚數 (Like Count)</span>
            </label>
        </div>

        <!-- Error Message -->
        <div id="csv-field-error" class="hidden text-red-600 text-sm mb-4">
            請至少選擇一個欄位
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-3">
            <button
                id="csv-modal-export"
                class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <i class="fas fa-file-csv mr-2"></i>匯出 CSV
            </button>
            <button
                id="csv-modal-cancel"
                class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition"
            >
                取消
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

@endsection
