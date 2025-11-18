# U-API Naming Refactoring Plan

**Date**: 2025-11-18
**Branch**: `006-videos-list` (to be created: `refactor-uapi-naming`)
**Priority**: High - Prevents confusion between Y-API and U-API systems

---

## Executive Summary

This refactoring plan addresses critical naming ambiguity in U-API (urtubeapi third-party) related PHP files. Currently, many U-API files use generic names like `ImportController` and `ImportService`, making it difficult to distinguish from Y-API (YouTube Official API) components.

**Goal**: Rename all U-API related files with `UrtubeApi` prefix for clarity and maintainability.

---

## System Architecture Context

DISINFO_SCANNER has **two independent API import systems**:

1. **Y-API (YouTube Official API)**
   - Purpose: Direct import from YouTube Data API v3
   - Source: `https://www.googleapis.com/youtube/v3/`
   - Features: Requires API key, official metadata, complete data
   - Controllers: `Api/YouTubeOfficialApiController.php` (to be renamed)
   - Services: `CommentImportService.php`, `YouTubeApiService.php`
   - Routes: `/api/youtube-official/*` (to be renamed from `/api/comments/*`)
   - Docs: `/specs/005-api-import-comments/`

2. **U-API (urtubeapi Third-Party)**
   - Purpose: Import from third-party service urtubeapi.analysis.tw
   - Source: `https://urtubeapi.analysis.tw/api/api_comment.php`
   - Features: No API key needed, pre-scraped data
   - Controllers: `ImportController.php` (**NEEDS RENAMING**)
   - Services: `ImportService.php`, `UrtubeapiService.php` (**NEEDS RENAMING**)
   - Routes: `/api/import/*` (**NEEDS RENAMING to `/api/uapi/*`**)
   - Docs: `/specs/001-comment-import/`

**These two systems are completely independent** with no shared code or interfaces.

---

## Problem Statement

### Current Issues

1. **Generic Controller Names**: `ImportController.php` could refer to either Y-API or U-API
2. **Generic Service Names**: `ImportService.php` provides no indication it's U-API specific
3. **Generic Routes**: `/api/import` is ambiguous
4. **Developer Confusion**: New developers cannot identify which system a file belongs to
5. **Maintenance Risk**: Future updates may accidentally modify wrong system

### Evidence from Code

All identified files have clear U-API markers in comments:

```php
// ImportService.php Line 14-24
// ===============================
// == U-API (THIRD-PARTY) ==
// == urtubeapi Service ==
// == DO NOT USE Y-API HERE ==
// ===============================
// SYSTEM ARCHITECTURE:
// 本系統共有 2 種 API 導入方式：
// 1. Y-API = YouTube 官方 API
// 2. U-API = 第三方 urtubeapi，只取得 YouTube 留言的 JSON (此文件)
// 此服務僅處理 U-API (urtubeapi) 相關功能
```

Despite clear internal documentation, **file names do not reflect this**.

---

## Files Requiring Renaming

### Phase 1: Controllers (Highest Priority)

| Current Name | New Name | Line Evidence | Routes Affected |
|--------------|----------|---------------|-----------------|
| `ImportController.php` | `UrtubeApiImportController.php` | L6: `use App\Services\ImportService` (U-API) | `POST /api/import` |
| `ImportConfirmationController.php` | `UrtubeApiConfirmationController.php` | L6: `use App\Services\ImportService` (U-API) | `POST /api/import/confirm` |
| `TagSelectionController.php` | `UrtubeApiTagSelectionController.php` | L6: `use App\Services\ImportService` (U-API) | `POST /api/tags/select` |

**Impact**: 3 files, ~150 lines total

---

### Phase 2: Core Services (High Priority)

| Current Name | New Name | Line Evidence | Used By |
|--------------|----------|---------------|---------|
| `ImportService.php` | `UrtubeApiImportService.php` | L14: `U-API (THIRD-PARTY)` | All 3 controllers |
| `DataTransformService.php` | `UrtubeApiDataTransformService.php` | L12: `Transform urtubeapi JSON` | `UrtubeApiImportService` |

**Impact**: 2 files, ~400 lines total
**Cascade**: Requires updating `use` statements in 3 controllers

---

### Phase 3: Helper Services (Medium Priority)

| Current Name | New Name | Line Evidence | Used By |
|--------------|----------|---------------|---------|
| `UrlParsingService.php` | `UrtubeApiUrlParsingService.php` | L19-21: `urtubeapi.analysis.tw` | `UrtubeApiImportService` |
| `YouTubePageService.php` | `UrtubeApiYouTubePageService.php` | Used only by U-API | `UrtubeApiImportService` |
| `YouTubeMetadataService.php` | `UrtubeApiMetadataService.php` | Used only by U-API | `UrtubeApiImportService` |

**Impact**: 3 files, ~300 lines total
**Cascade**: Requires updating `use` statements in `UrtubeApiImportService`

---

### Phase 4: No Changes Required (Shared or Already Clear)

| File Name | Status | Reason |
|-----------|--------|--------|
| `UrtubeapiService.php` | ✅ Keep as-is | Already clearly named |
| `DuplicateDetectionService.php` | ✅ Keep as-is | Shared logic (Y-API + U-API) |
| `ChannelTaggingService.php` | ✅ Keep as-is | Shared logic (Y-API + U-API) |
| `ChannelTagManager.php` | ✅ Keep as-is | Y-API specific |
| `CommentImportService.php` | ✅ Keep as-is | Y-API specific (has clear markers) |
| `YouTubeApiService.php` | ✅ Keep as-is | Y-API specific |
| `YoutubeApiClient.php` | ✅ Keep as-is | Y-API specific |

---

## Route Refactoring (Recommended)

### Current Routes (Too Generic)

```php
// routes/api.php (Lines 16-22)
Route::post('/import', [ImportController::class, 'store']);
Route::post('/import/confirm', [ImportConfirmationController::class, 'confirm']);
Route::post('/import/cancel', [ImportConfirmationController::class, 'cancel']);
Route::post('/tags/select', [TagSelectionController::class, 'store']);
Route::get('/tags', [TagSelectionController::class, 'index']);
```

**Problem**: `/api/import` could mean Y-API or U-API

---

### Proposed Routes (Clear Separation)

```php
// U-API Routes (urtubeapi Third-Party)
Route::prefix('uapi')->group(function () {
    Route::post('/import', [UrtubeApiImportController::class, 'store']);
    Route::post('/confirm', [UrtubeApiConfirmationController::class, 'confirm']);
    Route::post('/cancel', [UrtubeApiConfirmationController::class, 'cancel']);
    Route::get('/tags', [UrtubeApiTagSelectionController::class, 'index']);
    Route::post('/tags/select', [UrtubeApiTagSelectionController::class, 'store']);
});

// Y-API Routes (YouTube Official API)
Route::prefix('youtube-official')->group(function () {
    Route::post('/check', [Api\YouTubeOfficialApiController::class, 'check']);
    Route::post('/import', [Api\YouTubeOfficialApiController::class, 'import']);
});
```

**Result**:
- U-API: `POST /api/uapi/import`
- Y-API: `POST /api/youtube-official/import`

**No ambiguity possible**

---

## Frontend Impact

### Files Requiring AJAX Endpoint Updates

| File | Current Endpoint | New Endpoint | Line Numbers |
|------|-----------------|--------------|--------------|
| `resources/views/import/index.blade.php` | `/api/import` | `/api/uapi/import` | ~285 |
| `resources/views/import/index.blade.php` | `/api/import/confirm` | `/api/uapi/confirm` | ~327 |
| `resources/views/import/index.blade.php` | `/api/import/cancel` | `/api/uapi/cancel` | ~358 |
| `resources/views/import/index.blade.php` | `/api/tags` | `/api/uapi/tags` | ~179 |

**New U-API Modal** (to be created):
- `resources/views/comments/uapi-import-modal.blade.php`
- Will use new `/api/uapi/*` endpoints

---

## Implementation Phases

### Phase 1: Controllers Refactoring (2-3 hours)

**Files to Rename**:
1. `app/Http/Controllers/ImportController.php` → `UrtubeApiImportController.php`
2. `app/Http/Controllers/ImportConfirmationController.php` → `UrtubeApiConfirmationController.php`
3. `app/Http/Controllers/TagSelectionController.php` → `UrtubeApiTagSelectionController.php`

**Changes Required**:
- Update class names
- Update `routes/api.php` imports
- Run tests to verify no breakage

**Commands**:
```bash
# Rename files
git mv app/Http/Controllers/ImportController.php app/Http/Controllers/UrtubeApiImportController.php
git mv app/Http/Controllers/ImportConfirmationController.php app/Http/Controllers/UrtubeApiConfirmationController.php
git mv app/Http/Controllers/TagSelectionController.php app/Http/Controllers/UrtubeApiTagSelectionController.php

# Update class names in files (manual or sed)
# Update routes/api.php
# Run tests
php artisan test
```

---

### Phase 2: Core Services Refactoring (2-3 hours)

**Files to Rename**:
1. `app/Services/ImportService.php` → `UrtubeApiImportService.php`
2. `app/Services/DataTransformService.php` → `UrtubeApiDataTransformService.php`

**Cascade Updates**:
- Update `use` statements in 3 controllers
- Update constructor injections
- Update any service provider bindings

**Commands**:
```bash
# Rename files
git mv app/Services/ImportService.php app/Services/UrtubeApiImportService.php
git mv app/Services/DataTransformService.php app/Services/UrtubeApiDataTransformService.php

# Update class names + use statements (manual or IDE refactor)
# Run tests
php artisan test
```

---

### Phase 3: Helper Services Refactoring (1-2 hours)

**Files to Rename**:
1. `app/Services/UrlParsingService.php` → `UrtubeApiUrlParsingService.php`
2. `app/Services/YouTubePageService.php` → `UrtubeApiYouTubePageService.php`
3. `app/Services/YouTubeMetadataService.php` → `UrtubeApiMetadataService.php`

**Cascade Updates**:
- Update `use` statements in `UrtubeApiImportService.php`

**Commands**:
```bash
# Rename files
git mv app/Services/UrlParsingService.php app/Services/UrtubeApiUrlParsingService.php
git mv app/Services/YouTubePageService.php app/Services/UrtubeApiYouTubePageService.php
git mv app/Services/YouTubeMetadataService.php app/Services/UrtubeApiMetadataService.php

# Update class names + use statements
# Run tests
php artisan test
```

---

### Phase 4: Routes Refactoring (1 hour)

**Files to Update**:
- `routes/api.php`

**Changes**:
```diff
// U-API Routes
- Route::post('/import', [\App\Http\Controllers\ImportController::class, 'store']);
+ Route::prefix('uapi')->group(function () {
+     Route::post('/import', [\App\Http\Controllers\UrtubeApiImportController::class, 'store']);
+     Route::post('/confirm', [\App\Http\Controllers\UrtubeApiConfirmationController::class, 'confirm']);
+     Route::post('/cancel', [\App\Http\Controllers\UrtubeApiConfirmationController::class, 'cancel']);
+     Route::get('/tags', [\App\Http\Controllers\UrtubeApiTagSelectionController::class, 'index']);
+     Route::post('/tags/select', [\App\Http\Controllers\UrtubeApiTagSelectionController::class, 'store']);
+ });
```

---

### Phase 5: Frontend Updates (1-2 hours)

**Files to Update**:
1. `resources/views/import/index.blade.php` (AJAX endpoints)
2. New file: `resources/views/comments/uapi-import-modal.blade.php`
3. `resources/views/comments/list.blade.php` (add U-API button)

**JavaScript Changes**:
```diff
// Change AJAX endpoints
- fetch('/api/import', {...})
+ fetch('/api/uapi/import', {...})

- fetch('/api/import/confirm', {...})
+ fetch('/api/uapi/confirm', {...})

- fetch('/api/tags', {...})
+ fetch('/api/uapi/tags', {...})
```

---

## Testing Strategy

### Unit Tests (Existing)
- All existing unit tests should pass after renaming
- Update test class names to match new controller/service names

### Feature Tests (Existing)
- Import flow tests should pass unchanged
- Confirm flow tests should pass unchanged

### Manual Testing Checklist

**U-API Import Flow**:
- [ ] Navigate to homepage `/`
- [ ] Input urtubeapi URL
- [ ] Click "開始匯入"
- [ ] Verify metadata displayed in modal
- [ ] Select tags (if new channel)
- [ ] Click "確認並寫入資料"
- [ ] Verify success message
- [ ] Check database records created

**U-API Modal (New)**:
- [ ] Navigate to `/comments`
- [ ] Click "U-API導入" button
- [ ] Modal opens
- [ ] Complete import flow
- [ ] Modal closes on success

---

## Rollback Plan

Each phase is atomic and can be rolled back independently:

```bash
# Rollback Phase N
git revert <commit-hash-phase-N>

# Or reset to before refactoring
git reset --hard <commit-before-refactoring>
```

**Risk Mitigation**:
- Each phase committed separately
- Tests run after each phase
- Production deployment only after all phases pass

---

## Success Criteria

### Technical
- ✅ All U-API files prefixed with `UrtubeApi`
- ✅ All tests passing
- ✅ No code duplication
- ✅ Clear separation between Y-API and U-API

### Documentation
- ✅ README updated with new file structure
- ✅ Spec documents reference correct file names
- ✅ API documentation updated with new routes

### Developer Experience
- ✅ New developers can identify U-API vs Y-API files at a glance
- ✅ IDE autocomplete shows `UrtubeApi*` for U-API classes
- ✅ No confusion during code reviews

---

## Timeline Estimate

| Phase | Duration | Blocker |
|-------|----------|---------|
| Phase 1: Controllers | 2-3 hours | None |
| Phase 2: Core Services | 2-3 hours | Phase 1 complete |
| Phase 3: Helper Services | 1-2 hours | Phase 2 complete |
| Phase 4: Routes | 1 hour | Phase 1-3 complete |
| Phase 5: Frontend | 1-2 hours | Phase 4 complete |
| **Total** | **7-11 hours** | |

**Buffer**: +3 hours for unexpected issues
**Total with Buffer**: **10-14 hours** (1.5-2 working days)

---

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Breaking existing imports | High | Medium | Run tests after each phase |
| Frontend AJAX calls fail | High | Low | Update endpoints first, keep old routes temporarily |
| Service provider binding issues | Medium | Low | Check `app/Providers/` for manual bindings |
| Merge conflicts with other branches | Medium | Medium | Create from latest main, communicate with team |
| Forgotten `use` statement updates | Medium | Medium | Use IDE refactor tools, grep for old class names |

---

## Post-Refactoring Tasks

1. **Update Documentation**:
   - [ ] Update `/specs/001-comment-import/plan.md` with new file names
   - [ ] Update README.md project structure
   - [ ] Update API documentation (if exists)

2. **Team Communication**:
   - [ ] Notify team of new naming convention
   - [ ] Update coding standards document
   - [ ] Create migration guide for in-progress branches

3. **Monitoring**:
   - [ ] Monitor error logs for 24 hours after deployment
   - [ ] Check API request success rates
   - [ ] Verify no 404s on old routes (if removed)

---

## Approval Required

- [ ] **Technical Lead**: Approve refactoring approach
- [ ] **Backend Team**: Review cascade impacts
- [ ] **Frontend Team**: Review endpoint changes
- [ ] **QA Team**: Review testing strategy

---

## Related Documents

- Feature Spec: `/specs/001-comment-import/spec.md` (U-API)
- Feature Spec: `/specs/005-api-import-comments/spec.md` (Y-API)
- Architecture Doc: `/specs/001-comment-import/plan.md` (Line 15-35: U-API vs Y-API)

---

## Appendix: Complete File Mapping

### Controllers
```
app/Http/Controllers/
├── ImportController.php                    → UrtubeApiImportController.php
├── ImportConfirmationController.php        → UrtubeApiConfirmationController.php
├── TagSelectionController.php              → UrtubeApiTagSelectionController.php
├── Api/ImportCommentsController.php        → Api/YouTubeOfficialApiController.php (Y-API)
└── YouTubeApiImportController.php          → [DELETE - obsolete Y-API]
```

### Services
```
app/Services/
├── ImportService.php                       → UrtubeApiImportService.php
├── DataTransformService.php                → UrtubeApiDataTransformService.php
├── UrlParsingService.php                   → UrtubeApiUrlParsingService.php
├── YouTubePageService.php                  → UrtubeApiYouTubePageService.php
├── YouTubeMetadataService.php              → UrtubeApiMetadataService.php
├── UrtubeapiService.php                    → [KEEP - already clear]
├── DuplicateDetectionService.php           → [KEEP - shared]
├── ChannelTaggingService.php               → [KEEP - shared]
├── CommentImportService.php                → [KEEP - Y-API, clear]
├── YouTubeApiService.php                   → [KEEP - Y-API, clear]
└── YoutubeApiClient.php                    → [KEEP - Y-API, clear]
```

### Routes
```
routes/api.php:
├── POST /api/import                        → POST /api/uapi/import
├── POST /api/import/confirm                → POST /api/uapi/confirm
├── POST /api/import/cancel                 → POST /api/uapi/cancel
├── POST /api/tags/select                   → POST /api/uapi/tags/select
├── GET  /api/tags                          → GET  /api/uapi/tags
├── POST /api/comments/check                → POST /api/youtube-official/check
└── POST /api/comments/import               → POST /api/youtube-official/import
```

---

**Status**: Draft - Awaiting Approval
**Next Step**: Review and approve plan, then begin Phase 1 implementation
**Estimated Start Date**: TBD
**Estimated Completion Date**: TBD + 2 working days
