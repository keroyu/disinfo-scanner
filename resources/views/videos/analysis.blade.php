@extends('layouts.app')

@section('title', '影片留言密度分析 - ' . $video->title)

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">留言密度分析</h1>
        <p class="text-gray-600 mt-2">{{ $video->title }}</p>
        <p class="text-sm text-gray-500 mt-1">頻道: {{ $video->channel->channel_name ?? '未知' }}</p>
        <p class="text-sm text-gray-500">發布時間: {{ $video->published_at->setTimezone('Asia/Taipei')->format('Y-m-d H:i') }} (GMT+8)</p>
    </div>

    <!-- Time Range Selector -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">選擇時間範圍</h2>

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

    <!-- Chart Container -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">留言密度趨勢圖</h2>

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
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

<script>
// Global variables
let cachedDensityData = null;
let chartInstance = null;
let loadingTimeout = null;
const videoId = '{{ $video->video_id }}';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeRangeSelector();
    loadInitialData();
});

// Range selector logic
function initializeRangeSelector() {
    const rangeInputs = document.querySelectorAll('input[name="range"]');

    rangeInputs.forEach(input => {
        input.addEventListener('change', (e) => {
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
}

// Update total comments display
function updateTotalComments(filteredData) {
    const total = filteredData.reduce((sum, d) => sum + d.count, 0);
    document.getElementById('totalComments').textContent = total.toLocaleString();
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

// Cancel button handler
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('cancelButton').addEventListener('click', () => {
        window.location.href = '{{ route("videos.index") }}';
    });
});
</script>
@endsection
