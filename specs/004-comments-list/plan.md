# Implementation Plan: Comments List View (UI Layout Update)

**Branch**: `004-comments-list` | **Date**: 2025-11-16 | **Spec**: `/specs/004-comments-list/spec.md`
**Input**: Incremental UI layout requirements update with fixed column widths and responsive design

**Note**: This plan document consolidates the UI layout changes only. Core logic (filtering, sorting, pagination) remains unchanged from previous implementation phases.

## Summary

Implement responsive table layout for comments list with fixed column widths (channel: 100px, title: 200px) and multi-line comment content display. Maintain horizontal scroll with sticky headers on small screens; ensure all data visibility on larger viewports (desktop). No backend logic changes required.

## Technical Context

**Language/Version**: PHP 8.1+ (Laravel 10)
**Primary Dependencies**: Laravel 10, Tailwind CSS 3, Blade templates
**Storage**: PostgreSQL (existing)
**Testing**: PHPUnit for backend; Playwright for UI layout validation
**Target Platform**: Web browser (responsive, desktop-first with mobile fallback)
**Project Type**: Web application (Laravel + Blade)
**Performance Goals**: Page load <3s; responsive layout adjustments instantaneous
**Constraints**: Fixed column widths per spec; maintain accessibility; <1s sort/filter operations
**Scale/Scope**: Comments table view UI refactor (no database schema changes)

## Constitution Check

✅ **Test-First Development**: UI layout changes will include acceptance tests verifying responsive breakpoints and column widths
✅ **API-First Design**: Backend API contracts remain stable; UI consumes existing `/api/comments` endpoints
✅ **Observable Systems**: No observability changes; existing query logging applies
✅ **Contract Testing**: No new API contracts; existing contracts validated via current tests
✅ **Semantic Versioning**: Minor version bump for UI layout improvements (backward-compatible)

## Project Structure

### Documentation (this feature)

```text
specs/004-comments-list/
├── plan.md              # This file (incremental UI update)
├── research.md          # Phase 0 (completed - backend logic)
├── data-model.md        # Phase 1 (completed - no changes needed)
├── quickstart.md        # Phase 1 (completed)
├── contracts/           # Phase 1 (completed - no changes)
└── tasks.md             # Phase 2 (will be regenerated with UI tasks)
```

### Source Code (repository root)

```text
app/
└── Http/
    └── Controllers/
        └── CommentController.php    # Existing - no changes

resources/
├── views/
│   └── comments/
│       └── list.blade.php           # MODIFY: Add responsive layout + fixed widths
│       └── components/              # NEW: Create reusable column components
│
└── css/
    └── comments-list.css             # NEW: Responsive breakpoint styles

tests/
├── Feature/
│   └── CommentsListViewTest.php      # NEW: Add responsive layout tests
└── Browser/
    └── CommentsListLayoutTest.php    # NEW: Playwright tests for column widths
```

## Incremental Changes Required

### 1. Frontend View Layer (Blade Templates)

**File**: `resources/views/comments/list.blade.php`
**Changes**:
- Apply Tailwind CSS utilities for fixed column widths
  - Channel name: `w-[100px]` (fixed width, overflow hidden with ellipsis)
  - Video title: `w-[200px]` (fixed width, text truncation)
  - Comment content: Full width, `whitespace-pre-wrap` for multi-line display
- Add responsive wrapper with `overflow-x-auto` for small screens
- Make table columns sticky with proper z-index for horizontal scrolling
- Implement responsive breakpoints:
  - Mobile (<640px): Horizontal scroll with sticky headers
  - Tablet (640px-1024px): Adjusted layout with hidden non-critical columns
  - Desktop (>1024px): Full layout, all columns visible

### 2. Styling & Layout Components

**File**: `resources/views/comments/components/` (NEW directory)
**Components to create**:
- `column-header.blade.php`: Sortable column header with fixed width
- `comment-cell.blade.php`: Multi-line comment content with proper wrapping
- `channel-cell.blade.php`: Channel name with ellipsis truncation (100px)
- `video-title-cell.blade.php`: Video title with ellipsis truncation (200px)

### 3. Responsive CSS

**File**: `resources/css/comments-list.css` (NEW)
**Breakpoints**:
```css
/* Mobile: <640px */
@media (max-width: 640px) {
  /* Horizontal scroll with sticky headers */
  .comments-table { overflow-x: auto; }
  .comments-table thead { position: sticky; top: 0; z-index: 10; }
}

/* Tablet: 640px-1024px */
@media (min-width: 640px) and (max-width: 1024px) {
  /* Hide likes/dates, show essential columns */
  .hide-tablet { display: none; }
}

/* Desktop: >1024px */
@media (min-width: 1024px) {
  /* Full layout visible */
}
```

### 4. Layout Specifications

| Column | Width | Desktop | Tablet | Mobile | Behavior |
|--------|-------|---------|--------|--------|----------|
| Channel Name | 100px | Fixed | Fixed | Fixed | Ellipsis overflow |
| Video Title | 200px | Fixed | Fixed | Fixed | Ellipsis overflow |
| Comment Content | Auto | Wrap | Wrap | Wrap | Multi-line, word-break |
| Commenter ID | Auto | Visible | Visible | Visible | Truncate as needed |
| Likes | Auto | Visible | Hidden | Hidden | Right-aligned, clickable |
| Date | Auto | Visible | Hidden | Hidden | Right-aligned, clickable |

## Backend Integration

No backend changes required. Existing endpoints and query scopes remain:
- `GET /api/comments` - Paginated comment list
- Query parameters: `search`, `from_date`, `to_date`, `sort`, `direction`
- Response format: Unchanged (JSON with comment objects)

## Testing Strategy

### Unit Tests (No changes to existing tests)
- Existing `CommentFilterService` tests cover business logic
- No new service tests required

### Feature Tests (NEW)
- Test responsive layout rendering in Blade templates
- Verify column widths applied correctly via CSS classes
- Test comment content wrapping for multi-line display

### Browser Tests (NEW - Playwright)
- Verify column widths (100px channel, 200px title) on desktop
- Test horizontal scroll activation on small screens
- Validate sticky headers remain visible while scrolling
- Test long comment text wrapping and readability

## Accessibility Considerations

- Maintain semantic HTML `<table>` structure with `<thead>/<tbody>`
- Ensure column headers remain accessible for screen readers
- Preserve text contrast ratios in responsive layouts
- Support keyboard navigation (tab, arrow keys)

## Complexity Tracking

| Aspect | Impact | Notes |
|--------|--------|-------|
| Fixed column widths | Low | CSS-only; no JS required |
| Responsive breakpoints | Low | Standard Tailwind patterns |
| Multi-line comment display | Low | CSS `whitespace-pre-wrap` handles wrapping |
| Horizontal scroll on mobile | Low | Browser-native scrolling |
| No backend changes | None | Reuses existing API contracts |
