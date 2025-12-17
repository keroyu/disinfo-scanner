<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>數據分析 - DISINFO SCANNER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <x-admin-sidebar />

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <x-admin-header />

            <!-- Analytics Content (T264-T267) -->
            <main class="flex-1 p-6">
                <div class="max-w-7xl mx-auto" x-data="analyticsData">
                    <!-- Page Title -->
                    <div class="mb-6">
                        <h1 class="text-3xl font-bold text-gray-900">數據分析</h1>
                        <p class="mt-1 text-sm text-gray-600">查看平台使用統計和趨勢</p>
                    </div>

                    <!-- Statistics Cards (T272-T275) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <!-- Total Users -->
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">總註冊用戶</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2" x-text="stats.totalUsers || '0'"></p>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-full">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Verified Users -->
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">已驗證用戶</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2" x-text="stats.verifiedUsers || '0'"></p>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Premium Members -->
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">高級會員</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2" x-text="stats.premiumMembers || '0'"></p>
                                </div>
                                <div class="p-3 bg-purple-100 rounded-full">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- API Usage -->
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">API 總使用量</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2" x-text="stats.totalApiCalls || '0'"></p>
                                </div>
                                <div class="p-3 bg-orange-100 rounded-full">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Registration Chart (T265) -->
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">新用戶註冊趨勢</h2>
                            <div class="h-64">
                                <canvas id="registrationChart"></canvas>
                            </div>
                        </div>

                        <!-- Users by Role (T266) -->
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">用戶角色分佈</h2>
                            <div class="h-64">
                                <canvas id="userRoleChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Date Range Filter (T271) & Export Buttons (T268-T270) -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">報表匯出</h2>

                        <!-- Date Range Filter -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="startDate" class="block text-sm font-medium text-gray-700 mb-1">開始日期</label>
                                <input type="date"
                                       id="startDate"
                                       x-model="dateRange.start"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="endDate" class="block text-sm font-medium text-gray-700 mb-1">結束日期</label>
                                <input type="date"
                                       id="endDate"
                                       x-model="dateRange.end"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="flex items-end">
                                <button @click="applyDateFilter"
                                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    應用篩選
                                </button>
                            </div>
                        </div>

                        <!-- Export Buttons -->
                        <div class="flex flex-wrap gap-3">
                            <button @click="exportUserList"
                                    :disabled="exporting"
                                    :class="exporting ? 'opacity-50 cursor-not-allowed' : 'hover:bg-green-700'"
                                    class="px-6 py-2 bg-green-600 text-white rounded-lg text-sm font-medium">
                                <span x-show="!exporting">匯出用戶列表 (CSV)</span>
                                <span x-show="exporting">匯出中...</span>
                            </button>
                            <button @click="exportActivityReport"
                                    :disabled="exporting"
                                    :class="exporting ? 'opacity-50 cursor-not-allowed' : 'hover:bg-purple-700'"
                                    class="px-6 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium">
                                <span x-show="!exporting">匯出用戶活動報表</span>
                                <span x-show="exporting">匯出中...</span>
                            </button>
                            <button @click="exportApiUsageReport"
                                    :disabled="exporting"
                                    :class="exporting ? 'opacity-50 cursor-not-allowed' : 'hover:bg-orange-700'"
                                    class="px-6 py-2 bg-orange-600 text-white rounded-lg text-sm font-medium">
                                <span x-show="!exporting">匯出 API 使用報表</span>
                                <span x-show="exporting">匯出中...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('analyticsData', () => ({
                stats: {},
                dateRange: {
                    start: '',
                    end: ''
                },
                exporting: false,
                charts: {
                    registration: null,
                    userRole: null
                },

                async init() {
                    // Set default date range (last 30 days)
                    const today = new Date();
                    const thirtyDaysAgo = new Date(today);
                    thirtyDaysAgo.setDate(today.getDate() - 30);

                    this.dateRange.end = today.toISOString().split('T')[0];
                    this.dateRange.start = thirtyDaysAgo.toISOString().split('T')[0];

                    await this.fetchAnalytics();
                    this.initCharts();
                },

                async fetchAnalytics() {
                    try {
                        const params = new URLSearchParams({
                            start_date: this.dateRange.start,
                            end_date: this.dateRange.end
                        });

                        const response = await fetch(`/api/admin/analytics?${params}`);
                        if (response.ok) {
                            const data = await response.json();
                            this.stats = data.stats;
                            this.updateCharts(data);
                        }
                    } catch (error) {
                        console.error('Failed to fetch analytics:', error);
                    }
                },

                initCharts() {
                    // Registration Chart
                    const regCtx = document.getElementById('registrationChart').getContext('2d');
                    this.charts.registration = new Chart(regCtx, {
                        type: 'line',
                        data: {
                            labels: [],
                            datasets: [{
                                label: '新註冊用戶',
                                data: [],
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            }
                        }
                    });

                    // User Role Chart
                    const roleCtx = document.getElementById('userRoleChart').getContext('2d');
                    this.charts.userRole = new Chart(roleCtx, {
                        type: 'doughnut',
                        data: {
                            labels: [],
                            datasets: [{
                                data: [],
                                backgroundColor: [
                                    'rgb(59, 130, 246)',
                                    'rgb(16, 185, 129)',
                                    'rgb(139, 92, 246)',
                                    'rgb(245, 158, 11)',
                                    'rgb(239, 68, 68)'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });

                },

                updateCharts(data) {
                    // Update registration chart
                    if (data.registrations) {
                        this.charts.registration.data.labels = data.registrations.labels || [];
                        this.charts.registration.data.datasets[0].data = data.registrations.data || [];
                        this.charts.registration.update();
                    }

                    // Update user role chart
                    if (data.usersByRole) {
                        this.charts.userRole.data.labels = data.usersByRole.labels || [];
                        this.charts.userRole.data.datasets[0].data = data.usersByRole.data || [];
                        this.charts.userRole.update();
                    }
                },

                async applyDateFilter() {
                    await this.fetchAnalytics();
                },

                async exportUserList() {
                    this.exporting = true;
                    try {
                        const response = await fetch('/api/admin/reports/users/export', {
                            method: 'GET',
                            headers: { 'Accept': 'application/json' }
                        });
                        if (response.ok) {
                            const blob = await response.blob();
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = `users_${new Date().toISOString().split('T')[0]}.csv`;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            window.URL.revokeObjectURL(url);
                        }
                    } catch (error) {
                        console.error('Export failed:', error);
                        alert('匯出失敗');
                    } finally {
                        this.exporting = false;
                    }
                },

                async exportActivityReport() {
                    this.exporting = true;
                    try {
                        const params = new URLSearchParams({
                            start_date: this.dateRange.start,
                            end_date: this.dateRange.end
                        });

                        const response = await fetch(`/api/admin/reports/activity?${params}`);
                        if (response.ok) {
                            const blob = await response.blob();
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = `activity_report_${this.dateRange.start}_to_${this.dateRange.end}.csv`;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            window.URL.revokeObjectURL(url);
                        }
                    } catch (error) {
                        console.error('Export failed:', error);
                        alert('匯出失敗');
                    } finally {
                        this.exporting = false;
                    }
                },

                async exportApiUsageReport() {
                    this.exporting = true;
                    try {
                        const params = new URLSearchParams({
                            start_date: this.dateRange.start,
                            end_date: this.dateRange.end
                        });

                        const response = await fetch(`/api/admin/reports/api-usage?${params}`);
                        if (response.ok) {
                            const blob = await response.blob();
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = `api_usage_${this.dateRange.start}_to_${this.dateRange.end}.csv`;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            window.URL.revokeObjectURL(url);
                        }
                    } catch (error) {
                        console.error('Export failed:', error);
                        alert('匯出失敗');
                    } finally {
                        this.exporting = false;
                    }
                }
            }));
        });
    </script>
</body>
</html>
