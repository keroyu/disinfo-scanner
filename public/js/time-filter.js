/**
 * Time Filter State Manager
 * Manages time-based filtering from Comments Density chart clicks
 */
class TimeFilterState {
    constructor() {
        this.selectedTimePoints = new Set(); // Set of ISO timestamp strings in GMT+8
        this.chartInstance = null;
        this.originalBackgroundColors = null;
    }

    /**
     * Initialize with chart instance
     */
    init(chartInstance) {
        this.chartInstance = chartInstance;
        if (chartInstance && chartInstance.data.datasets[0]) {
            // Store original colors for reset
            this.originalBackgroundColors = chartInstance.data.datasets[0].backgroundColor;
        }
    }

    /**
     * Toggle a time point selection
     * @param {string} timestamp ISO timestamp in GMT+8 (e.g., "2025-11-20T14:00:00")
     * @returns {boolean} True if point is now selected, false if deselected
     */
    toggleTimePoint(timestamp) {
        // Check if we're at the limit (20 points)
        if (!this.selectedTimePoints.has(timestamp) && this.selectedTimePoints.size >= 20) {
            this.showLimitError();
            return false;
        }

        // Show warning at 15 selections
        if (!this.selectedTimePoints.has(timestamp) && this.selectedTimePoints.size === 14) {
            this.showPerformanceWarning();
        }

        if (this.selectedTimePoints.has(timestamp)) {
            this.selectedTimePoints.delete(timestamp);
            return false;
        } else {
            this.selectedTimePoints.add(timestamp);
            return true;
        }
    }

    /**
     * Get selected time points as comma-separated ISO string for API
     */
    getTimePointsParam() {
        if (this.selectedTimePoints.size === 0) {
            return null;
        }
        return Array.from(this.selectedTimePoints).sort().join(',');
    }

    /**
     * Get count of selected time points
     */
    getCount() {
        return this.selectedTimePoints.size;
    }

    /**
     * Check if any time points are selected
     */
    hasSelection() {
        return this.selectedTimePoints.size > 0;
    }

    /**
     * Clear all selections
     */
    clearAll() {
        this.selectedTimePoints.clear();
    }

    /**
     * Get display string for selected time ranges
     */
    getDisplayString() {
        if (this.selectedTimePoints.size === 0) {
            return '';
        }

        const timestamps = Array.from(this.selectedTimePoints).sort();
        const timeRanges = timestamps.map(ts => {
            const date = new Date(ts);
            const startHour = String(date.getHours()).padStart(2, '0');
            const startMin = String(date.getMinutes()).padStart(2, '0');
            const endDate = new Date(date.getTime() + 60 * 60 * 1000);
            const endHour = String(endDate.getHours()).padStart(2, '0');
            const endMin = String(endDate.getMinutes()).padStart(2, '0');
            return `${startHour}:${startMin}-${endHour}:${endMin}`;
        });

        return timeRanges.join(', ');
    }

    /**
     * Update chart highlighting for selected points
     */
    updateChartHighlighting() {
        if (!this.chartInstance || !this.chartInstance.data.datasets[0]) {
            return;
        }

        const dataset = this.chartInstance.data.datasets[0];
        const dataPoints = dataset.data;

        // Create new backgroundColor array with more prominent selected styling
        const newBackgroundColors = dataPoints.map((point) => {
            const timestamp = this.formatTimestampForComparison(point.x);
            if (this.selectedTimePoints.has(timestamp)) {
                return 'rgba(239, 68, 68, 0.8)'; // Bright red for selected (highly visible)
            } else {
                return 'rgba(59, 130, 246, 0.1)'; // Light blue for unselected
            }
        });

        // Create border color array to add emphasis
        const newBorderColors = dataPoints.map((point) => {
            const timestamp = this.formatTimestampForComparison(point.x);
            if (this.selectedTimePoints.has(timestamp)) {
                return 'rgba(220, 38, 38, 1)'; // Solid dark red border for selected
            } else {
                return 'rgba(59, 130, 246, 0.5)'; // Semi-transparent blue border for unselected
            }
        });

        // Create point radius array to make selected points larger
        const newPointRadius = dataPoints.map((point) => {
            const timestamp = this.formatTimestampForComparison(point.x);
            if (this.selectedTimePoints.has(timestamp)) {
                return 6; // Larger radius for selected points
            } else {
                return 3; // Normal radius for unselected points
            }
        });

        dataset.backgroundColor = newBackgroundColors;
        dataset.borderColor = newBorderColors;
        dataset.pointRadius = newPointRadius;
        dataset.pointBackgroundColor = newBackgroundColors;
        dataset.pointBorderColor = newBorderColors;
        dataset.pointBorderWidth = 2;

        this.chartInstance.update('none'); // Update without animation for instant feedback
    }

    /**
     * Format timestamp for comparison (normalize to ISO string without seconds)
     */
    formatTimestampForComparison(timestamp) {
        const date = new Date(timestamp);
        // Format as ISO but only up to minutes (YYYY-MM-DDTHH:mm:00)
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}:00`;
    }

    /**
     * Show performance warning at 15 selections
     */
    showPerformanceWarning() {
        const container = document.getElementById('timeFilterIndicator');
        if (!container) return;

        const warning = document.createElement('div');
        warning.className = 'mt-2 p-2 bg-yellow-50 border border-yellow-300 rounded text-sm text-yellow-800';
        warning.id = 'performanceWarning';
        warning.innerHTML = '⚠️ 選擇多個時間段可能會影響效能。建議縮小選擇範圍。';

        // Remove existing warning if any
        const existingWarning = document.getElementById('performanceWarning');
        if (existingWarning) {
            existingWarning.remove();
        }

        container.appendChild(warning);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            warning.remove();
        }, 5000);
    }

    /**
     * Show error when trying to exceed 20 selections
     */
    showLimitError() {
        const container = document.getElementById('timeFilterIndicator');
        if (!container) return;

        const error = document.createElement('div');
        error.className = 'mt-2 p-2 bg-red-50 border border-red-300 rounded text-sm text-red-800';
        error.id = 'limitError';
        error.innerHTML = '❌ 最多只能選擇 20 個時間段';

        // Remove existing error if any
        const existingError = document.getElementById('limitError');
        if (existingError) {
            existingError.remove();
        }

        container.appendChild(error);

        // Auto-remove after 3 seconds
        setTimeout(() => {
            error.remove();
        }, 3000);
    }
}

// Make it globally accessible
window.TimeFilterState = TimeFilterState;
