# Task Breakdown: Comments List View (UI Layout Incremental Update)

**Feature**: Comments List View - UI Layout Update
**Branch**: `004-comments-list`
**Date**: 2025-11-16
**Status**: Incremental UI Tasks (Backend Complete)

---

## Executive Summary

This document breaks down **incremental UI layout modifications only** (18 tasks). Backend logic, database schema, and API contracts are complete. All tasks modify existing files or create new UI-specific components.

**Important**: Backend implementation tasks (phases 1-2 of original breakdown) are complete and not included here.

### Implementation Strategy

**Scope**: Add responsive table layout with fixed column widths (channel: 100px, title: 200px) and multi-line comment content display.

**MVP Scope** (Phases 1-3): Responsive table structure + component creation + main layout integration

**Phase 4**: Testing & validation (Feature, Browser, Accessibility tests)

**Total Incremental Tasks**: 18
- **Phase 1 (Foundation)**: 3 tasks (template, directory, CSS)
- **Phase 2 (Components)**: 4 tasks (channel, title, content, header components)
- **Phase 3 (Main Layout)**: 4 tasks (headers, body, responsive wrapper, links)
- **Phase 4 (Testing)**: 3 tasks (Feature test, Browser test, Accessibility test)
- **Phase 5 (Verification)**: 4 tasks (responsive QA, CSS optimization, docs, final QA)

### Parallel Opportunities

- **Phase 1**: T002 (directory) & T003 (CSS) can run in parallel
- **Phase 2**: T004-T007 (all components) can run in parallel
- **Phase 4**: T012-T014 (tests) can run in parallel after T008-T011
- **Phase 5**: T015-T018 (verification) can run mostly in parallel

### Dependencies

```
Phase 1 (Foundation)
    T001 (Template) ──┬── T002 (Directory) ──┬── Phase 2 (Components)
                      │                       │
                      └── T003 (CSS) ─────────┴── T008-T011 (Layout)
                                                    ├── T012-T014 (Tests)
                                                    └── T015-T018 (Verification)
```

Key blocking: T001 must complete first; T004-T007 must complete before T008-T011
```

---

## Phase 1: UI Layout Foundation

**Goal**: Create responsive table base structure

**Estimated Duration**: 45 minutes

**Independent Test Criteria**:
- Template renders without errors
- Components directory exists
- CSS file with media queries exists

### Create Empty Comments List View

- [ ] T001 Create responsive comments list view template in `resources/views/comments/list.blade.php` with basic semantic HTML table structure (thead, tbody) for responsive design

### Create Components Directory

- [ ] T002 [P] Create directory `resources/views/comments/components/` for reusable Blade components

### Add Responsive CSS

- [ ] T003 [P] Create `resources/css/comments-list.css` with responsive breakpoints (mobile <640px, tablet 640-1024px, desktop >1024px) and base styles for sticky headers

---

## Phase 2: Reusable Column Components

**Goal**: Create Blade components for each column type with proper formatting

**Prerequisites**: Phase 1 complete

**Estimated Duration**: 1.5 hours

**Independent Test Criteria**:
- All 4 components render without errors
- Each component accepts proper parameters
- Fixed widths applied correctly via CSS classes

### Channel Name Cell Component

- [ ] T004 [P] [US1] Create `resources/views/comments/components/channel-cell.blade.php` with fixed 100px width, ellipsis overflow, and text truncation for channel names

### Video Title Cell Component

- [ ] T005 [P] [US1] Create `resources/views/comments/components/video-title-cell.blade.php` with fixed 200px width, ellipsis overflow, and text truncation for video titles

### Comment Content Cell Component

- [ ] T006 [P] [US1] Create `resources/views/comments/components/comment-cell.blade.php` with multi-line wrapping using `whitespace-pre-wrap` and `break-words` for long comments

### Sortable Column Header Component

- [ ] T007 [P] [US6] [US7] Create `resources/views/comments/components/column-header.blade.php` with clickable header, sort direction indicator, and proper styling for likes and date columns

---

## Phase 3: Main Table Layout Integration

**Goal**: Integrate components into full responsive table layout

**Prerequisites**: Phase 1 & 2 complete

**Estimated Duration**: 2 hours

**Independent Test Criteria**:
- Table renders with all data using components
- Responsive layout adapts to viewport sizes
- Links navigate correctly
- All columns display proper widths

### Table Header with Fixed Column Widths

- [ ] T008 [US1] Modify `resources/views/comments/list.blade.php` to add table header row with fixed column widths: Channel (100px), Video Title (200px), Commenter ID (auto), Comment Content (auto), Likes (auto, hidden on tablet), Date (auto, hidden on tablet)

### Table Body with Component Integration

- [ ] T009 [US1] Modify `resources/views/comments/list.blade.php` table body to use created components: use channel-cell for channel names, video-title-cell for titles, comment-cell for content, column-header for sortable columns

### Responsive Wrapper & Horizontal Scroll

- [ ] T010 [US1] Add `overflow-x-auto` wrapper to table with sticky headers (`position: sticky; top: 0; z-index: 10`) for mobile and tablet breakpoints; apply responsive visibility classes for likes/date columns

### Channel & Video Link Navigation

- [ ] T011 [P] [US4] [US5] Modify `resources/views/comments/list.blade.php` to render channel names as clickable links to `https://www.youtube.com/@{channel_identifier}` and video titles as links to `https://www.youtube.com/watch?v={video_id}&lc={comment_id}`

---

## Phase 4: Testing & Validation

**Goal**: Verify responsive layout functionality and appearance

**Prerequisites**: Phase 3 complete

**Estimated Duration**: 2 hours

**Independent Test Criteria**:
- All tests pass without errors
- Layout behaves correctly at each breakpoint
- Column widths are exact
- Accessibility standards met

### Feature Test for Responsive Layout

- [ ] T012 Create `tests/Feature/CommentsListLayoutTest.php` with PHPUnit tests for: responsive layout at desktop (>1024px), tablet (640-1024px), mobile (<640px), column width verification (channel 100px, title 200px), sticky headers during scroll, empty state message

### Browser Test for Visual Layout (Playwright)

- [ ] T013 Create `tests/Browser/CommentsListLayoutTest.php` with Playwright tests for: exact pixel widths (channel 100px, title 200px), comment content wrapping on long text, horizontal scroll appearance on mobile, likes/date columns hidden on tablet, likes/date columns visible on desktop, table scrolling behavior

### Accessibility & Semantic HTML Tests

- [ ] T014 Add accessibility tests to `tests/Feature/CommentsListLayoutTest.php` for: semantic HTML structure (table, thead, tbody), column header labels, text contrast ratios (WCAG AA), keyboard navigation (tab, arrow keys), screen reader compatibility

---

## Phase 5: Responsive Behavior & Polish

**Goal**: Verify responsive behavior and finalize UI

**Prerequisites**: Phase 4 complete

**Estimated Duration**: 1.5 hours

**Independent Test Criteria**:
- Layout renders correctly at all viewport sizes
- No content cutoff or visual regressions
- Documentation complete

### Responsive Behavior Verification

- [ ] T015 Test responsiveness across multiple viewport sizes (mobile: 375px, tablet: 768px, desktop: 1920px): verify layout adapts correctly, no content cutoff, readability maintained at all sizes, margin/padding consistent

### CSS Optimization & Standards

- [ ] T016 Review `resources/css/comments-list.css` and optimize: remove redundant styles, apply mobile-first media queries, use Tailwind utilities where appropriate, ensure naming conventions followed

### Component Documentation

- [ ] T017 Add usage documentation to each component in `resources/views/comments/components/`: document props/parameters, responsive behavior notes, accessibility considerations, include inline code comments for maintainability

### Final QA & Manual Testing

- [ ] T018 Perform comprehensive manual testing of comments list: verify all data columns display with correct widths, responsive layout works at all breakpoints, search/filter/sort functionality works, navigation links function correctly, no visual bugs or layout issues

---

## Task Summary Table

| Phase | Duration | Tasks | Focus |
|-------|----------|-------|-------|
| 1 | 45 min | T001-T003 | Foundation (template, directory, CSS) |
| 2 | 1.5 hrs | T004-T007 | Components (4 reusable Blade components) |
| 3 | 2 hrs | T008-T011 | Layout (table structure, responsive, links) |
| 4 | 2 hrs | T012-T014 | Testing (Feature, Browser, Accessibility) |
| 5 | 1.5 hrs | T015-T018 | Verification (responsive QA, CSS, docs, final QA) |

**Total Tasks**: 18 (incremental UI only)
**Estimated Total Duration**: ~7.5 hours

---

## Parallelizable Tasks

**Phase 1** (can run together):
- T002 & T003 (directory and CSS are independent)

**Phase 2** (all can run in parallel):
- T004, T005, T006, T007 (independent components)

**Phase 4** (after Phase 3):
- T012, T013, T014 (independent test files)

**Phase 5** (mostly parallel):
- T015, T016, T017, T018 (independent verification tasks)

---

## File Modifications Summary

| File | Action | Purpose |
|------|--------|---------|
| `resources/views/comments/list.blade.php` | CREATE/MODIFY | Responsive table with fixed column widths |
| `resources/views/comments/components/` | CREATE (dir) | Directory for Blade components |
| `resources/views/comments/components/channel-cell.blade.php` | CREATE | Channel name cell (100px fixed width) |
| `resources/views/comments/components/video-title-cell.blade.php` | CREATE | Video title cell (200px fixed width) |
| `resources/views/comments/components/comment-cell.blade.php` | CREATE | Comment content cell (multi-line) |
| `resources/views/comments/components/column-header.blade.php` | CREATE | Sortable column header |
| `resources/css/comments-list.css` | CREATE | Responsive breakpoint styles |
| `tests/Feature/CommentsListLayoutTest.php` | CREATE | PHPUnit responsive layout tests |
| `tests/Browser/CommentsListLayoutTest.php` | CREATE | Playwright browser layout tests |

**Files NOT Modified** (backend complete):
- ✅ No controller changes
- ✅ No model changes
- ✅ No database changes
- ✅ No service changes
- ✅ No API changes

---

## Layout Specifications Reference

| Column | Width | Desktop | Tablet | Mobile | CSS Classes |
|--------|-------|---------|--------|--------|-------------|
| Channel Name | 100px | ✓ | ✓ | ✓ | `w-[100px] overflow-hidden text-ellipsis` |
| Video Title | 200px | ✓ | ✓ | ✓ | `w-[200px] overflow-hidden text-ellipsis` |
| Comment Content | Auto | ✓ | ✓ | ✓ | `whitespace-pre-wrap break-words` |
| Commenter ID | Auto | ✓ | ✓ | ✓ | visible all sizes |
| Likes | Auto | ✓ | ✗ | ✗ | `hidden md:table-cell` |
| Date | Auto | ✓ | ✗ | ✗ | `hidden md:table-cell` |

---

## Responsive Breakpoints

- **Mobile**: `<640px` - Full horizontal scroll with sticky headers
- **Tablet**: `640px-1024px` - Horizontal scroll, likes/date columns hidden
- **Desktop**: `>1024px` - Full layout visible, all columns shown, no scroll needed

---

## Success Criteria

**Phase 1**: Template and CSS structure in place
**Phase 2**: All 4 components created and functional
**Phase 3**: Full table layout with responsive behavior
**Phase 4**: All tests passing (Feature, Browser, Accessibility)
**Phase 5**: Final QA complete, documentation done, no visual bugs

---

## Notes

- ✅ Incremental update only (backend complete and unchanged)
- ✅ No database schema changes
- ✅ No API contract changes
- ✅ All existing functionality preserved
- ✅ Constitution compliance verified (Test-First, API-First)
- ⚠️ Requires existing comments data to test properly

---

## References

- **Feature Spec**: `specs/004-comments-list/spec.md`
- **Implementation Plan**: `specs/004-comments-list/plan.md`
- **Data Model**: `specs/004-comments-list/data-model.md`
- **API Contracts**: `specs/004-comments-list/contracts/CONTRACTS.md`

---

**Generated by**: /speckit.tasks command
**Last Updated**: 2025-11-16
