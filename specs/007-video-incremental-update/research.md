# Research: Video Incremental Update

**Feature**: 007-video-incremental-update
**Date**: 2025-11-18
**Purpose**: Resolve technical unknowns and establish best practices for incremental comment import

## Research Tasks Completed

### 1. YouTube API publishedAfter Parameter Usage

**Decision**: Use `publishedAfter` parameter in `commentThreads.list` API call

**Rationale**:
- YouTube Data API v3 natively supports `publishedAfter` parameter for filtering comments by timestamp
- Parameter format: RFC 3339 datetime (e.g., `2025-11-05T15:00:00Z`)
- Significantly reduces API quota consumption by fetching only new comments
- Already using Google API Client library v2.18, which supports this parameter

**Implementation**:
```php
$response = $this->youtube->commentThreads->listCommentThreads('snippet,replies', [
    'videoId' => $videoId,
    'publishedAfter' => $lastCommentTimestamp, // RFC 3339 format
    'maxResults' => $maxResults,
    'order' => 'time',
    'textFormat' => 'plainText',
]);
```

**Alternatives Considered**:
- **Fetch all and filter client-side**: Rejected - wastes API quota and bandwidth
- **Use `pageToken` to resume**: Rejected - doesn't support date-based filtering

**References**:
- [YouTube Data API - CommentThreads.list](https://developers.google.com/youtube/v3/docs/commentThreads/list)
- Google API Client PHP library documentation

---

### 2. Chinese Character Truncation Strategy

**Decision**: Use `mb_substr()` with UTF-8 encoding for truncating Chinese characters

**Rationale**:
- PHP's `substr()` counts bytes, not characters - fails for multi-byte Chinese characters
- `mb_substr($string, 0, 15, 'UTF-8')` correctly counts 1 Chinese character = 1 unit
- Laravel/Blade automatically uses UTF-8 encoding
- Str::limit() helper also supports multi-byte strings but adds ellipsis automatically

**Implementation**:
```php
// In Blade template
@php
    $truncatedTitle = mb_strlen($video->title) > 15
        ? mb_substr($video->title, 0, 15) . '...'
        : $video->title;
@endphp
<span title="{{ $video->title }}">{{ $truncatedTitle }}</span>
```

**Alternatives Considered**:
- **Str::limit()**:  Rejected - uses character count but harder to control exact 15-char limit
- **CSS text-overflow**: Rejected - doesn't guarantee exactly 15 characters

**Edge Cases Handled**:
- Mixed English/Chinese text: mb_substr counts all characters uniformly
- Emoji: Counted as 1 character (may occupy 2-4 bytes but mb_substr handles correctly)

---

### 3. Idempotent Insert Pattern for Concurrent Updates

**Decision**: Use `INSERT IGNORE` or Eloquent `updateOrCreate()` with comment_id as unique key

**Rationale**:
- Prevents duplicate comment errors when multiple users update same video simultaneously
- Maintains data integrity without application-level locking
- MySQL/MariaDB natively supports `INSERT IGNORE` for silent duplicate handling
- Laravel's `firstOrCreate()` provides idempotent behavior at ORM level

**Implementation**:
```php
// Option 1: Eloquent firstOrCreate (cleaner, ORM-level)
foreach ($comments as $commentData) {
    Comment::firstOrCreate(
        ['comment_id' => $commentData['comment_id']], // Unique key
        $commentData // Additional data if creating
    );
}

// Option 2: Raw INSERT IGNORE (faster for bulk inserts)
DB::table('comments')->insertOrIgnore($comments);
```

**Chosen Approach**: Use `firstOrCreate()` for clarity and to leverage Eloquent events/observers

**Alternatives Considered**:
- **Application-level locking (mutex)**: Rejected - adds complexity, doesn't scale well
- **Database transactions with SELECT FOR UPDATE**: Rejected - overkill for simple duplicate prevention
- **Check existence before insert**: Rejected - introduces race condition window

**Performance Impact**:
- Minimal overhead - unique index on `comment_id` already exists (primary key)
- `firstOrCreate()` issues 1 SELECT + optional INSERT per comment (acceptable for 500-limit)

---

### 4. 500-Comment Batch Limit Enforcement

**Decision**: Enforce hard limit in service layer with clear user messaging

**Rationale**:
- Prevents PHP execution timeout (default 30-60 seconds)
- Aligns with existing pagination pattern (500 videos/page)
- Provides predictable performance (60 seconds max for 500 comments)
- Allows users to control update pace (click Update button multiple times if needed)

**Implementation**:
```php
public function importIncrementalComments(string $videoId, int $limit = 500): array
{
    $newComments = $this->fetchNewComments($videoId);
    $totalAvailable = count($newComments);

    // Enforce limit
    $commentsToImport = array_slice($newComments, 0, $limit);
    $imported = $this->persistComments($commentsToImport);

    return [
        'imported_count' => count($imported),
        'total_available' => $totalAvailable,
        'remaining' => max(0, $totalAvailable - $limit),
        'has_more' => $totalAvailable > $limit,
    ];
}
```

**User Messaging**:
- If total ≤ 500: "成功導入 {count} 則留言"
- If total > 500: "成功導入 500 則留言。還有 {remaining} 則新留言可用，請再次點擊更新按鈕繼續導入。"

**Alternatives Considered**:
- **Progressive streaming**: Rejected - complex UI, poor UX for large imports
- **Background job queue**: Rejected - overkill for this use case, adds async complexity
- **No limit**: Rejected - risks timeout failures

---

### 5. Modal Component Reuse Strategy

**Decision**: Create new modal component following existing patterns (import-modal.blade.php, uapi-import-modal.blade.php)

**Rationale**:
- Existing modals demonstrate clear pattern: Tailwind CSS styling, inline JavaScript, AJAX calls
- Reuse CSS classes and modal structure for visual consistency
- Self-contained component (no shared state between modals)
- Easier to maintain than shared component with conditional logic

**Structure Pattern**:
```html
<!-- Modal wrapper with Tailwind -->
<div id="incremental-update-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
    <div class="bg-white rounded-lg shadow-2xl w-full mx-4 max-w-2xl">
        <!-- Header -->
        <div class="sticky top-0 flex justify-between items-center p-6 border-b">
            <h2>更新影片留言</h2>
            <button type="button" id="modal-close-btn">×</button>
        </div>

        <!-- Body: Preview Section -->
        <div class="p-6" id="preview-section">
            <!-- Preview comments, count display -->
        </div>

        <!-- Footer: Action Buttons -->
        <div class="sticky bottom-0 flex justify-end gap-3 p-6 border-t">
            <button id="btn-confirm">確認更新</button>
        </div>
    </div>
</div>

<script>
    // Modal interaction logic (open, close, AJAX calls)
</script>
```

**Alternatives Considered**:
- **Shared modal component**: Rejected - adds conditional complexity
- **Vue.js/React component**: Rejected - project uses Blade, no SPA framework
- **Alpine.js**: Considered but rejected - adding new dependency not justified

---

### 6. Laravel Datetime Handling Best Practices

**Decision**: Use Laravel's `now()` helper and Eloquent `$casts` for automatic datetime formatting

**Rationale**:
- Laravel automatically formats Carbon instances to `Y-m-d H:i:s` when saving to database
- Models with `$casts = ['published_at' => 'datetime']` auto-convert strings to Carbon
- `now()` returns Carbon instance set to application timezone (config/app.php)
- Consistent with existing codebase conventions

**Implementation**:
```php
// When updating video after import
$video->comment_count = $newCount;
$video->updated_at = now(); // Auto-formats to YYYY-MM-DD HH:MM:SS
$video->save();

// When inserting comments (Eloquent handles published_at casting)
Comment::create([
    'comment_id' => $data['id'],
    'published_at' => $data['snippet']['publishedAt'], // RFC 3339 string
    // ... other fields
]);
// Eloquent auto-converts RFC 3339 to MySQL datetime format
```

**Format Validation**:
- Database column type: `DATETIME` (MySQL)
- Storage format: `YYYY-MM-DD HH:MM:SS` (e.g., `2025-06-13 21:00:03`)
- Application format: Carbon object for easy manipulation
- API input: RFC 3339 (YouTube API standard)

**Alternatives Considered**:
- **Manual date formatting**: Rejected - error-prone, Laravel handles this
- **Unix timestamps**: Rejected - less readable, not Laravel convention

---

## Summary of Key Decisions

| Area | Decision | Rationale |
|------|----------|-----------|
| **YouTube API Filtering** | Use `publishedAfter` parameter | Native API support, reduces quota usage |
| **Chinese Truncation** | `mb_substr()` with UTF-8 | Correctly counts multi-byte characters |
| **Concurrent Updates** | `firstOrCreate()` idempotent inserts | Prevents duplicates without locking |
| **Batch Limit** | Hard 500-comment limit in service | Prevents timeouts, predictable performance |
| **Modal Component** | New component following existing pattern | Consistency, maintainability |
| **Datetime Handling** | Laravel `now()` + Eloquent `$casts` | Framework convention, auto-formatting |

## Open Questions Resolved

All technical unknowns from Technical Context section have been resolved:
- ✅ YouTube API parameter usage
- ✅ Multi-byte character handling
- ✅ Concurrent update strategy
- ✅ Timeout prevention approach
- ✅ Component architecture
- ✅ Datetime formatting standard

**Status**: Research complete. Ready to proceed to Phase 1 (Design & Contracts).
