# Implementation Plan: Videos List

**Branch**: `006-videos-list` | **Date**: 2025-11-18 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/006-videos-list/spec.md`

## Summary

Create a Videos List page that displays all YouTube videos with comment activity, showing Channel Name, Video Title, Comment Count, and Last Comment Time. Users can search, filter, sort by clicking column headers, and navigate to filtered Comments List views. Page styling matches the existing Comments List design with 500 items per page pagination.

## Technical Context

**Language/Version**: PHP 8.2
**Framework**: Laravel 12.38.1
**Primary Dependencies**:
- Backend: Laravel Framework, Google API Client
- Frontend: Blade templates, Tailwind CSS, Alpine.js (inferred from existing patterns)
**Storage**: MySQL (existing database with videos, comments, channels tables)
**Testing**: PHPUnit 11.5.3, Laravel Dusk (for browser tests)
**Target Platform**: Web application (server-side rendered with Blade)
**Project Type**: Web application (Laravel MVC)
**Performance Goals**: Page load < 2 seconds for 10,000 videos, search results < 1 second
**Constraints**: Must match Comments List visual design, 500 videos per page pagination
**Scale/Scope**: Single new page (controller + view + route), reuse existing models

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Test-First Development
- **Status**: ✅ PASS
- **Plan**: Feature tests will be written before controller implementation
- **Tests Required**:
  - Feature test: Videos list page displays correctly with pagination
  - Feature test: Search filtering works (case-insensitive)
  - Feature test: Sort by column headers (published_at, comment_count, last_comment_time)
  - Feature test: Navigation links to Comments List with correct query parameters
  - Browser test: Verify visual consistency with Comments List

### II. API-First Design
- **Status**: ✅ PASS
- **Plan**: HTTP GET `/videos` route with query parameters for search/sort
- **Contract**:
  - Endpoint: `GET /videos?search={keyword}&sort={column}&direction={asc|desc}`
  - Response: Blade view with paginated HTML (following existing pattern)
  - Error handling: Empty state when no results, graceful handling of missing channels

### III. Observable Systems
- **Status**: ✅ PASS
- **Plan**: Laravel's built-in logging for errors, query logging for performance monitoring
- **Implementation**:
  - Log slow queries (>1s) to identify performance issues
  - Log empty search results for UX improvement insights
  - Error logging for missing channel data

### IV. Contract Testing
- **Status**: ✅ PASS (with adaptation)
- **Plan**: Since this is a monolithic web app (not microservices), contract testing applies to:
  - Model query scopes contract (e.g., `Video::withCommentStats()`)
  - Controller response contract (expected view data structure)
  - Route contract (URL parameters and their validation)

### V. Semantic Versioning
- **Status**: ✅ PASS
- **Plan**: This is a new feature (MINOR version bump)
- **Impact**: No breaking changes to existing APIs/routes

## Project Structure

### Documentation (this feature)

```text
specs/006-videos-list/
├── plan.md              # This file
├── spec.md              # Feature specification (already exists)
├── research.md          # Phase 0 output (to be created)
├── data-model.md        # Phase 1 output (to be created)
├── quickstart.md        # Phase 1 output (to be created)
├── contracts/           # Phase 1 output (to be created)
│   └── videos-api.md    # HTTP contract documentation
├── checklists/          # Quality checklists (already exists)
│   └── requirements.md
└── tasks.md             # Phase 2 output (created by /speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Http/
│   └── Controllers/
│       └── VideoController.php              # NEW: Videos list controller
├── Models/
│   ├── Video.php                            # MODIFY: Add query scopes for list view
│   ├── Comment.php                          # EXISTS: No changes needed
│   └── Channel.php                          # EXISTS: No changes needed
└── View/
    └── Components/
        └── (No new components needed)       # Reuse existing components

resources/
└── views/
    └── videos/
        └── list.blade.php                   # NEW: Videos list view

routes/
└── web.php                                  # MODIFY: Add /videos route

tests/
├── Feature/
│   ├── VideosListTest.php                   # NEW: Feature tests
│   └── VideosListLayoutTest.php             # NEW: Layout/styling tests
└── Browser/
    └── VideosListBrowserTest.php            # NEW: Browser tests (Dusk)

database/
└── migrations/
    └── (No migrations needed)               # Tables already exist
```

**Structure Decision**: Following Laravel MVC pattern. This is a standard web application with server-side rendering using Blade templates. The Videos List feature fits the existing architecture:
- **Controller**: `VideoController` handles HTTP requests
- **Model**: `Video` model with added query scopes
- **View**: Blade template in `resources/views/videos/`

## Complexity Tracking

> No constitutional violations. This feature aligns with all principles:
> - Follows existing Laravel patterns (no new complexity)
> - Reuses existing database schema
> - Matches established UI patterns from Comments List
> - No new external dependencies

---

## 新增檔案列表 (New Files)

### 控制器 (Controllers)
1. **`app/Http/Controllers/VideoController.php`** - 影片列表控制器

### 視圖 (Views)
2. **`resources/views/videos/list.blade.php`** - 影片列表頁面

### 測試 (Tests)
3. **`tests/Feature/VideosListTest.php`** - 功能測試
4. **`tests/Feature/VideosListLayoutTest.php`** - 版面配置測試
5. **`tests/Browser/VideosListBrowserTest.php`** - 瀏覽器測試

### 文件 (Documentation)
6. **`specs/006-videos-list/research.md`** - 研究文件
7. **`specs/006-videos-list/data-model.md`** - 資料模型
8. **`specs/006-videos-list/quickstart.md`** - 快速入門指南
9. **`specs/006-videos-list/contracts/videos-api.md`** - API 合約文件

---

## 修改檔案列表 (Modified Files)

### 模型 (Models)
1. **`app/Models/Video.php`**
   - 新增查詢 scope: `withCommentStats()` - 計算留言數和最後留言時間
   - 新增查詢 scope: `hasComments()` - 只顯示有留言的影片
   - 新增查詢 scope: `searchByKeyword($keyword)` - 搜尋影片標題或頻道名稱 (不區分大小寫)
   - 新增查詢 scope: `sortByColumn($column, $direction)` - 依欄位排序

### 路由 (Routes)
2. **`routes/web.php`**
   - 新增路由: `GET /videos` → `VideoController@index`

### 導航 (Navigation)
3. **`resources/views/layouts/app.blade.php`**
   - 在「頻道列表」連結右側新增「影片列表」連結
   - 新增 active 狀態判斷邏輯

---

## Phase 0 Research Plan

Research tasks to be completed in `research.md`:

1. **Query Optimization**:
   - Research efficient way to calculate comment count and last comment time
   - Options: Eager loading vs. subqueries vs. database views
   - Decision criteria: Query performance for 10,000 videos

2. **Pagination Best Practices**:
   - Research Laravel pagination with query parameters preservation
   - Ensure sort/search state persists across pages
   - Standard pattern: `$query->paginate(500)->appends(request()->query())`

3. **Case-Insensitive Search**:
   - Research MySQL case-insensitive LIKE queries
   - Options: LOWER() function vs. COLLATE utf8mb4_general_ci
   - Performance comparison for 10,000 records

4. **Date Calculation for 90-Day Range**:
   - Research Carbon date manipulation for "clicked date - 90 days"
   - URL encoding strategy for passing date ranges to Comments List

---

## Phase 1 Design Plan

### Data Model (`data-model.md`)

Document existing entities and relationships:

**Video Entity** (already exists in database):
- `video_id` (PK, string)
- `channel_id` (FK to channels)
- `title` (string, nullable)
- `published_at` (timestamp, nullable)
- `comment_count` (unsigned integer, nullable) - Note: May not be up-to-date, needs recalculation

**Computed Fields** (via query scopes):
- `actual_comment_count` - COUNT of comments for this video
- `last_comment_time` - MAX(published_at) from comments for this video

**Relationships**:
- `belongsTo(Channel::class, 'channel_id')`
- `hasMany(Comment::class, 'video_id')`

### API Contract (`contracts/videos-api.md`)

**Endpoint**: `GET /videos`

**Query Parameters**:
- `search` (optional, string): Case-insensitive search in video title and channel name
- `sort` (optional, enum: `published_at|comment_count|last_comment_time`): Sort column, default: `published_at`
- `direction` (optional, enum: `asc|desc`): Sort direction, default: `desc`
- `page` (optional, integer): Page number, default: 1

**Response**: HTML page (Blade view) with:
- Paginated table of videos (500 per page)
- Each row contains: Channel Name (link), Video Title (link), Comment Count, Last Comment Time (link)
- Search form with keyword input
- Sort indicators on column headers

**Error States**:
- Empty result set: Display "No videos found. Try adjusting your search filters."
- Missing channel: Display "Unknown Channel" in channel name column

### Quickstart Guide (`quickstart.md`)

User-facing documentation:
1. Access Videos List from navigation menu
2. How to search for videos
3. How to sort by clicking column headers
4. How clicking on channel/title/time navigates to Comments List

---

## Next Steps

1. **Complete Phase 0**: Create `research.md` with query optimization decisions
2. **Complete Phase 1**: Create `data-model.md`, `contracts/videos-api.md`, `quickstart.md`
3. **Run `/speckit.tasks`**: Generate implementation tasks from this plan
4. **Implementation**: Follow TDD approach (tests first, then implementation)
