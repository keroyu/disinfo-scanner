# Developer Quickstart: Time-Based Comment Filtering from Chart

**Feature**: 010-time-based-comment-filter
**Branch**: `010-time-based-comment-filter`
**Prerequisites**: Feature 009 (comments-pattern-summary) and Feature 008 (comment-density) completed

## Overview

This guide helps developers quickly understand and implement the time-based comment filtering feature. You'll extend existing Chart.js chart interactions to enable multi-select time filtering and integrate with the existing comments API.

**What This Feature Does**:
- Users click time points on Comments Density chart to filter comments
- Supports multi-select (up to 20 non-contiguous hourly ranges)
- Visual highlighting shows selected points
- Filtered comments display in existing Commenter Pattern Summary panel
- Works seamlessly with existing pattern filters (repeat, night_time, etc.)

---

## Quick Reference

### Key Files

| File | Purpose | Status |
|------|---------|--------|
| `app/Http/Controllers/CommentPatternController.php` | ⚠️ MODIFY | Extend getCommentsByPattern() to accept time_points parameter |
| `app/Services/CommentPatternService.php` | ⚠️ MODIFY | Add time range filtering logic |
| `app/Models/Comment.php` | ⚠️ MODIFY | Add byTimeRanges() query scope |
| `resources/views/videos/analysis.blade.php` | ⚠️ MODIFY | Add time filter UI elements |
| `public/js/time-filter.js` | ✨ NEW | Chart click handler and state management |
| `tests/Feature/Api/TimeFilteredCommentsTest.php` | ✨ NEW | API contract tests |

### Key Concepts

1. **Timezone Flow**: Frontend (GMT+8) → Backend converts to UTC → Database (UTC) → Backend converts to GMT+8 → Frontend
2. **State Management**: Client-side JavaScript object tracks selections, no server-side session
3. **Query Strategy**: Multiple OR clauses for non-contiguous time ranges
4. **Limit Enforcement**: Warning at 15 selections, hard limit at 20

---

## Local Development Setup

### 1. Check Prerequisites

Verify Feature 009 is deployed:
```bash
# Check if CommentPatternController exists
php artisan route:list | grep pattern-statistics

# Should see:
# GET|HEAD  api/videos/{videoId}/pattern-statistics .................... comment-pattern.statistics
# GET|HEAD  api/videos/{videoId}/comments ............................ comment-pattern.comments
```

Verify Chart.js is loaded:
```bash
# Check analysis.blade.php for Chart.js CDN
grep -n "chart.js" resources/views/videos/analysis.blade.php

# Should see Chart.js 4.4.0 CDN link
```

### 2. Start Development Server

```bash
# Start Laravel server
php artisan serve

# In separate terminal, watch for file changes (if using asset build)
npm run dev  # or npm run watch

# Access: http://localhost:8000/videos/{videoId}/analysis
```

### 3. Database Check

Verify published_at index exists:
```sql
-- Connect to database
mysql -u root -p disinfo_scanner

-- Check index on published_at
SHOW INDEX FROM comments WHERE Key_name LIKE '%published%';

-- Should see idx_published_at or similar
```

---

## Implementation Checklist

### Backend (PHP/Laravel)

- [ ] **Extend CommentPatternController**
  - [ ] Add `time_points` validation in getCommentsByPattern()
  - [ ] Validate max 20 time points
  - [ ] Parse comma-separated ISO timestamps
  - [ ] Log time filter requests with execution time

- [ ] **Extend CommentPatternService**
  - [ ] Add time range conversion logic (GMT+8 → UTC)
  - [ ] Integrate time filter with existing pattern filter logic
  - [ ] Maintain backward compatibility (time_points is optional)

- [ ] **Extend Comment Model**
  - [ ] Add `scopeByTimeRanges($query, array $timeRanges)` method
  - [ ] Build OR clauses for multiple time ranges
  - [ ] Test with 20 time ranges for performance

- [ ] **Write Tests**
  - [ ] Contract test: Backward compatibility (no time_points param)
  - [ ] Contract test: Single time range
  - [ ] Contract test: Multiple time ranges (3 ranges)
  - [ ] Contract test: Combined pattern + time filter
  - [ ] Contract test: Validation errors (21 points, invalid format)
  - [ ] Integration test: Timezone conversion (UTC ↔ GMT+8)
  - [ ] Performance test: 20 time ranges with 10k comments

### Frontend (JavaScript)

- [ ] **Create TimeFilterState Class**
  - [ ] Track selected time points (Set of indices)
  - [ ] Track selected timestamps (Array of ISO strings)
  - [ ] Implement toggle(), clear(), clearAll() methods
  - [ ] Enforce 20-point limit with warning at 15

- [ ] **Extend Chart Configuration**
  - [ ] Add onClick handler to Chart.js config
  - [ ] Extract clicked timestamp from chart data
  - [ ] Call TimeFilterState.toggle() on click
  - [ ] Update backgroundColor array for visual highlighting

- [ ] **Integrate with Existing CommentPatternUI**
  - [ ] Pass time_points parameter to API calls
  - [ ] Display time filter indicator ("📍 Selected: X time periods")
  - [ ] Add "Clear Time Selection" button
  - [ ] Maintain infinite scroll with time filter

- [ ] **UI Updates**
  - [ ] Add time filter indicator above comment list
  - [ ] Show selected time ranges in readable format
  - [ ] Display warning message at 15 selections
  - [ ] Prevent selection beyond 20 points

---

## Code Snippets

### Backend: Add Time Filter to Query

```php
// In app/Models/Comment.php

/**
 * Scope to filter comments by multiple time ranges
 */
public function scopeByTimeRanges($query, array $timeRanges)
{
    if (empty($timeRanges)) {
        return $query;
    }

    return $query->where(function($q) use ($timeRanges) {
        foreach ($timeRanges as $range) {
            $q->orWhere(function($subQuery) use ($range) {
                $subQuery->where('published_at', '>=', $range['start'])
                         ->where('published_at', '<', $range['end']);
            });
        }
    });
}
```

### Backend: Controller Validation

```php
// In app/Http/Controllers/CommentPatternController.php

$validated = $request->validate([
    'pattern' => ['required', Rule::in(['all', 'top_liked', 'repeat', 'night_time', 'aggressive', 'simplified_chinese'])],
    'time_points' => 'nullable|string|max:1000',
    'offset' => 'integer|min:0',
    'limit' => 'integer|min:1|max:100'
]);

// Parse time points
$timePointsIso = null;
if (!empty($validated['time_points'])) {
    $timePointsIso = explode(',', $validated['time_points']);

    if (count($timePointsIso) > 20) {
        return response()->json([
            'error' => [
                'type' => 'ValidationError',
                'message' => 'Maximum 20 time points allowed',
                'details' => ['count' => count($timePointsIso), 'limit' => 20]
            ]
        ], 422);
    }
}
```

### Frontend: Chart onClick Handler

```javascript
// In public/js/time-filter.js

const chartConfig = {
    type: 'bar',
    data: densityData,
    options: {
        onClick: (event, activeElements) => {
            if (activeElements.length > 0) {
                const index = activeElements[0].index;
                const timestamp = densityData.labels[index];
                handleTimePointClick(index, timestamp);
            }
        },
        // ... other options
    }
};

function handleTimePointClick(index, timestamp) {
    const success = timeFilterState.toggleTimePoint(index, timestamp);

    if (success) {
        // Update chart highlighting
        updateChartHighlighting(index, timeFilterState.selectedTimePoints.has(index));

        // Reload comments with time filter
        reloadComments();
    }
}

function updateChartHighlighting(index, isSelected) {
    const color = isSelected
        ? 'rgba(59, 130, 246, 0.6)'  // Darker blue
        : 'rgba(59, 130, 246, 0.1)'; // Light blue

    chartInstance.data.datasets[0].backgroundColor[index] = color;
    chartInstance.update('none');  // Update without animation
}
```

### Frontend: State Management

```javascript
class TimeFilterState {
    constructor() {
        this.selectedTimePoints = new Set();
        this.selectedTimestamps = [];
    }

    toggleTimePoint(index, isoTimestamp) {
        if (this.selectedTimePoints.has(index)) {
            // Deselect
            this.selectedTimePoints.delete(index);
            this.selectedTimestamps = this.selectedTimestamps.filter(
                t => t !== isoTimestamp
            );
            return true;
        } else {
            // Check limit
            if (this.selectedTimePoints.size >= 20) {
                showError('Maximum 20 time periods can be selected');
                return false;
            }

            // Check warning threshold
            if (this.selectedTimePoints.size >= 15) {
                showWarning('Selecting many time periods may slow performance.');
            }

            // Select
            this.selectedTimePoints.add(index);
            this.selectedTimestamps.push(isoTimestamp);
            return true;
        }
    }

    toQueryString() {
        return this.selectedTimestamps.length > 0
            ? `time_points=${this.selectedTimestamps.join(',')}`
            : '';
    }
}
```

---

## Testing Guide

### Manual Testing Steps

#### Test 1: Single Time Point Selection
1. Navigate to `/videos/{videoId}/analysis`
2. Wait for Comments Density chart to load
3. Click any data point on the chart
4. **Expected**:
   - Chart point becomes darker blue
   - Comments panel updates with filtered results
   - Time indicator shows "📍 Selected: 1 time period"
   - Comments match the hourly time range

#### Test 2: Multiple Time Points
1. Continue from Test 1
2. Click 2 more non-contiguous time points
3. **Expected**:
   - All 3 points highlighted in darker blue
   - Comments panel shows combined results
   - Time indicator shows "📍 Selected: 3 time periods"
   - All comments fall within one of the three hourly ranges

#### Test 3: Deselection
1. Continue from Test 2
2. Click one of the previously selected points again
3. **Expected**:
   - Point returns to light blue
   - Comments panel updates (removes that time range)
   - Time indicator shows "📍 Selected: 2 time periods"

#### Test 4: Combined Filter
1. Continue from Test 3
2. Click "重複留言者" pattern filter
3. **Expected**:
   - Comments panel shows only repeat commenters from selected time ranges
   - Time selection remains highlighted
   - Pattern filter AND time filter both active

#### Test 5: Limit Warning
1. Click "Clear All"
2. Rapidly click 15 different time points
3. **Expected**:
   - Warning message appears: "Selecting many time periods may slow performance"
   - Can still select up to 20 total

#### Test 6: Hard Limit
1. Continue from Test 5
2. Try to select a 21st point
3. **Expected**:
   - Selection prevented
   - Error message: "Maximum 20 time periods can be selected"
   - Cannot select additional points

### Automated Testing

Run backend tests:
```bash
# Run all feature tests
php artisan test --filter=TimeFilteredComments

# Run specific contract tests
php artisan test tests/Feature/Api/TimeFilteredCommentsTest.php

# Run with coverage
php artisan test --coverage --filter=TimeFilteredComments
```

Expected test coverage:
- Contract tests: 100% (all API scenarios)
- Unit tests: 90%+ (timezone conversion, query building)
- Integration tests: 85%+ (combined filtering)

---

## Performance Monitoring

### Key Metrics to Track

1. **API Response Time**
   - Target: <2s for single time range
   - Target: <3s for 15-20 time ranges
   - Check Laravel logs for execution time

2. **Chart Update Time**
   - Target: <200ms for highlighting update
   - Use Chrome DevTools Performance tab

3. **Database Query Performance**
   ```sql
   -- Check slow query log
   SELECT * FROM mysql.slow_log
   WHERE sql_text LIKE '%published_at%'
   ORDER BY query_time DESC
   LIMIT 10;
   ```

### Performance Optimization Tips

- Ensure `idx_published_at` index exists
- Use `chart.update('none')` to skip animations
- Cache pattern filter author IDs (avoid recomputation)
- Set query timeout to 5 seconds in controller

---

## Common Issues & Solutions

### Issue 1: Chart Points Not Highlighting
**Symptom**: Clicks detected but colors don't change
**Solution**: Check backgroundColor array length matches data points:
```javascript
console.log(chartInstance.data.datasets[0].data.length);
console.log(chartInstance.data.datasets[0].backgroundColor.length);
// Should be equal
```

### Issue 2: Wrong Comments Returned
**Symptom**: Comments don't match selected time range
**Solution**: Verify timezone conversion:
```php
// Debug in CommentPatternService
Log::info('Time range conversion', [
    'input_gmt8' => $isoTimestamp,
    'converted_utc_start' => $startUtc->toIso8601String(),
    'converted_utc_end' => $endUtc->toIso8601String()
]);
```

### Issue 3: Slow Query Performance
**Symptom**: Response time >5s with many time ranges
**Solution**:
1. Check index usage: `EXPLAIN SELECT ... WHERE published_at ...`
2. Reduce time ranges (enforce lower limit)
3. Add composite index: `(video_id, published_at)`

### Issue 4: State Desync
**Symptom**: Chart highlighting doesn't match loaded comments
**Solution**: Clear state when switching time range views (24h/3d/7d):
```javascript
rangeSelector.addEventListener('change', () => {
    timeFilterState.clearAll();
    chartInstance.data.datasets[0].backgroundColor = initialColors;
    chartInstance.update();
    reloadComments();
});
```

---

## Debugging Checklist

- [ ] Check browser console for JavaScript errors
- [ ] Check Laravel logs: `tail -f storage/logs/laravel.log`
- [ ] Verify API request in Network tab (check time_points parameter)
- [ ] Verify API response structure (check time_filter object)
- [ ] Check database query in Laravel Telescope (if installed)
- [ ] Verify timezone conversion in backend (log input/output)
- [ ] Check chart data structure (labels match timestamps)
- [ ] Verify state object consistency (selected indices match timestamps)

---

## Next Steps

After completing this feature:

1. **Run full test suite**: `php artisan test`
2. **Check code coverage**: Aim for 90%+ on new code
3. **Performance test**: Test with 20 time ranges on production-size dataset
4. **Documentation**: Update API documentation with new parameter
5. **User testing**: Validate with analysts on real data

---

## References

- **Feature Spec**: `specs/010-time-based-comment-filter/spec.md`
- **Data Model**: `specs/010-time-based-comment-filter/data-model.md`
- **API Contract**: `specs/010-time-based-comment-filter/contracts/time-filtered-comments-api.md`
- **Research**: `specs/010-time-based-comment-filter/research.md`
- **Chart.js Docs**: https://www.chartjs.org/docs/latest/
- **Laravel Query Scopes**: https://laravel.com/docs/12.x/eloquent#query-scopes
- **Constitution**: `.specify/memory/constitution.md` (Principle VI: Timezone Consistency)

---

## Questions?

If you encounter issues not covered here:

1. Check Laravel logs for backend errors
2. Check browser console for frontend errors
3. Review existing Feature 009 implementation for patterns
4. Consult the API contract for expected behavior
5. Test timezone conversion separately (unit tests)

**Remember**: This feature extends existing infrastructure. When in doubt, reference Feature 009's implementation patterns.
