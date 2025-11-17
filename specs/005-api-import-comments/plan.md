# Implementation Plan: YouTube API 官方導入留言

**Branch**: `005-api-import-comments` | **Date**: 2025-11-17 | **Spec**: [Feature Specification](spec.md)
**Input**: Feature specification from `/specs/005-api-import-comments/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

新增完全獨立於 urtubeapi 的官方 YouTube API 導入留言功能。使用者可在「留言列表」頁面點擊「官方API導入」按鈕，在 modal 中輸入影片網址，系統會：
1. 檢查影片和頻道是否已存在
2. 從 YouTube API 取得預覽資料（頻道標題、影片標題、最新日期的 5 則留言，留言的總數字）**特別注意** 最新日期的 5 則留言，需要採用 datetime 的倒序 order 來取得。
3. 讓使用者確認和修改頻道標籤（新頻道必須選擇至少 1 個標籤）
4. 點擊「確認導入」後，分階段導入：channels → videos → comments（遞迴取得最多 3 層回覆）
5. 導入完成後顯示成功訊息，並動態更新留言列表（無頁面重新整理）

## Technical Context

**Language/Version**: PHP 8.1+ (Laravel 10+)
**Primary Dependencies**: Laravel, Google API Client (youtube/youtube-analytics-api), Tailwind CSS, Livewire (optional for dynamic updates)
**Storage**: SQLite (primary) / MySQL (production) - videos, channels, comments, channel_tags 表
**Testing**: PHPUnit (feature/integration tests)
**Target Platform**: Web (Laravel + Blade + Tailwind CSS)
**Project Type**: Web application (feature addition to existing system)
**Performance Goals**: URL 檢查 < 1s, API 預覽取得 < 5s, 完整導入 < 30s
**Constraints**: YouTube API 配額限制, 遞迴深度限制為 3 層, 預覽階段不持久化資料
**Scale/Scope**: 單一功能模組, 新增 1 個 Controller, 1 個 Service, 4 個主要 Model (Channel, Video, Comment, ChannelTag), 新增 modal 頁面, 新增 2-3 個遷移 (migrations)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

✅ **I. Test-First Development**: 實作前撰寫功能測試 (Feature Tests: 3 個 User Story 各 1 個, Integration Tests: API 呼叫、DB 持久化各 1 個)
✅ **II. API-First Design**: 定義明確的 Laravel Route 契約和回應格式 (JSON API, 含錯誤情境)
✅ **III. Observable Systems**: 所有 API 呼叫、資料庫操作均需結構化日誌
✅ **IV. Contract Testing**: YouTube API 回應格式應有契約測試, 評論/回覆遞迴結構應測試
✅ **V. Semantic Versioning**: 新增功能計為 MINOR 版本更新, 若資料庫遷移則文件化

## Project Structure

### Documentation (this feature)

```text
specs/005-api-import-comments/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (Laravel project structure)

```text
app/
├── Http/
│   └── Controllers/
│       └── YoutubeApiImportController.php          # 新增 - 處理 modal 互動、API 呼叫
├── Services/
│   └── YoutubeCommentImportService.php             # 新增 - 核心邏輯 (API 呼叫、資料轉換、遞迴)
├── Models/
│   ├── Channel.php                                 # 現有 - 加強 channel_tags 關係
│   ├── Video.php                                   # 現有 - 加強 comment_count 欄位
│   ├── Comment.php                                 # 現有 - 加強 parent_comment_id
│   └── ChannelTag.php                              # 現有 - 樞紐表 (多對多)

database/
├── migrations/
│   ├── [timestamp]_add_comment_count_to_videos.php          # 新增
│   ├── [timestamp]_add_parent_comment_id_to_comments.php    # 新增
│   └── [timestamp]_add_last_import_at_to_channels.php       # 新增
└── factories/
    └── YoutubeCommentFactory.php                   # 新增 - 測試資料工廠

resources/
└── views/
    └── comments/
        ├── index.blade.php                         # 修改 - 加入「官方API導入」按鈕
        └── partials/
            └── import-modal.blade.php              # 新增 - modal 版面

tests/
├── Feature/
│   └── YoutubeApiImportTest.php                    # 新增 - 3 個 User Story 功能測試
├── Unit/
│   └── YoutubeCommentImportServiceTest.php         # 新增 - Service 單元測試
└── Fixtures/
    └── youtube-api-responses.json                  # 新增 - 契約測試數據
```

**Structure Decision**: 採用 Laravel 標準結構，新增功能分為 Controller、Service、Model 三層，資料庫遷移獨立管理，測試遵循 PHPUnit Feature/Unit 分類

## Complexity Tracking

> **No Constitution Check violations detected. Design is compliant with all 5 core principles.**

---

## Phase 0 & Phase 1 Completion Summary

### Phase 0: Research ✅ COMPLETE

**Output**: `research.md` - 所有技術點已確認，無需進一步研究
- ✅ 資料庫現況驗證（所有 Model 已存在，需新增 1 個遷移）
- ✅ API 設計驗證（4 個端點已實作）
- ✅ YouTube API 集成驗證（遞迴、增量、事務都支持）
- ✅ UI Modal 實作驗證（多步驟流程已完整）
- ✅ 時間戳管理決策（YYYY-MM-DD HH:MM:SS 格式）
- ✅ 遞迴深度決策（3 層限制）

### Phase 1: Design ✅ COMPLETE

**Outputs**:
1. `data-model.md` - 完整的資料模型設計（6 個表，含 ERD）
2. `contracts/import-controller.md` - 4 個 API 端點的詳細契約
3. `contracts/youtube-api-service.md` - YouTubeApiService 的完整方法簽名
4. `quickstart.md` - 開發者快速開始指南 + 程式碼範例

**主要設計決策**:
- 新增遷移: `add_comment_count_to_videos` (計算實際留言數)
- 資料完整性: 外鍵約束 + 唯一性索引
- 事務管理: 原子性導入 (channels → videos → comments)
- 標籤驗證: 新頻道強制選擇至少 1 個標籤
- 深度限制: 回覆最多 3 層，超過忽略
- 動態計算: 頻道評論數應動態 SUM(videos.comment_count)

---

## Next Phase: Implementation

下一步執行 `/speckit.tasks` 生成 tasks.md，包含所有實裝任務的依賴關係和執行順序。

**預計任務類別**:
1. 資料庫遷移 (1 個)
2. Model 修改 (新增關係、查詢作用域)
3. Service 實作 (核心業務邏輯)
4. Controller 實作 (API 端點)
5. View 實作 (Modal + 按鈕)
6. 測試實作 (Feature + Unit 測試)
7. 文件完成 (API 文件、部署指南)
