# Research Report: YouTube API 官方導入留言功能

**Feature**: YouTube API 官方導入留言 | **Date**: 2025-11-17 | **Status**: 完成

---

## Executive Summary

本報告基於對現有 DISINFO_SCANNER 系統的全面程式碼審查，提供了實作官方 YouTube API 導入留言功能所需的所有技術決策和實作指引。所有主要技術點已確認，無需進一步研究。

---

## Research Findings

### 1. 資料庫現況與遷移策略

#### 現有表結構確認

**channels 表** ✅
- 已存在欄位：`channel_id` (PK), `channel_name`, `video_count`, `comment_count`, `first_import_at`, `last_import_at`, `created_at`, `updated_at`
- 需新增遷移：`last_import_at` 欄位已存在，無需新增
- 多對多關係：已有 `channel_tags` 樞紐表

**videos 表** ✅
- 已存在欄位：`video_id` (PK), `channel_id` (FK), `title`, `published_at`, `created_at`, `updated_at`
- 需新增遷移：**添加 `comment_count` 欄位**（預設 NULL，導入完成後計算）
- 決策：依 User Story 3.5 和 Clarification "瀏覽器重新整理時的處理"，導入完成後計算實際評論數並寫入此欄位

**comments 表** ✅
- 已存在欄位：`comment_id` (PK), `video_id` (FK), `author_channel_id` (FK), `text`, `like_count`, `published_at`, `parent_comment_id`, `created_at`, `updated_at`
- 已有 `parent_comment_id` 欄位，支持回覆關係追蹤
- 無需新增遷移

**channel_tags 樞紐表** ✅
- 已存在欄位：`channel_id` (PK), `tag_id` (PK), `created_at`
- 支持多對多關係（Channel ←→ Tag）

**tags 表** ✅
- 已存在且預設有系統標籤（不支持新建，只能選擇預定義）

#### 遷移計畫

需新增 1 個遷移：

| 遷移名稱 | 目的 | SQL |
|---------|------|-----|
| `add_comment_count_to_videos` | 新增 `comment_count` 欄位 | `ALTER TABLE videos ADD comment_count UNSIGNED INT DEFAULT NULL AFTER published_at` |

**理由**：根據 FR-021b，導入完成後必須計算該 video 的實際留言數，並寫入此欄位。此欄位用於快速查詢統計，無需遷移時預先填充（導入流程負責計算）。

---

### 2. API 設計與契約

#### 現有 API 端點確認

已實作 YouTubeApiImportController，提供以下端點：

| 端點 | 方法 | 功能 | 現況 | 修改需求 |
|-----|------|------|------|---------|
| `/api/youtube-import/show-form` | GET | 返回 modal Blade | ✅ 已實作 | 無 |
| `/api/youtube-import/metadata` | POST | 檢查影片/頻道存在 + 取得元資料 | ✅ 已實作 | 無 |
| `/api/youtube-import/preview` | POST | 預覽 5 則留言 | ✅ 已實作 | 無 |
| `/api/youtube-import/confirm-import` | POST | 執行完整導入 | ✅ 已實作 | 無 |

**結論**：所有必要 API 端點均已實作，響應格式遵循 JSON 標準，包含成功/錯誤情境處理。

---

### 3. YouTube API 集成與遞迴策略

#### YouTubeApiService 確認

已實作 `app/Services/YouTubeApiService.php`，提供：

1. **fetchVideoMetadata(videoId)** ✅
   - 功能：取得頻道名稱、影片標題、發佈時間
   - API 端點：`videos.list()` with `snippet`
   - 適用：User Story 2, 3 步驟 2

2. **fetchPreviewComments(videoId)** ✅
   - 功能：取得 5 則最新留言（按時間遞減）
   - API 端點：`commentThreads.list()` with order="time", maxResults=5
   - 適用：User Story 2, 3 步驟 3 的預覽顯示

3. **fetchAllComments(videoId, afterDate, existingCommentIds)** ✅
   - 功能：取得所有留言及回覆（遞迴至 3 層深度）
   - 特性：
     - 分頁支持（100 結果/頁）
     - 增量導入（時間戳過濾）
     - 重複檢測（via existingCommentIds）
     - 回覆遞迴取得（自動處理 >20 回覆情況）
     - 進度回呼支持
   - 適用：User Story 2, 3 步驟 5 的完整導入

4. **fetchRepliesRecursive(videoId, parentCommentId, afterDate, seenCommentIds)** ✅
   - 功能：遞迴取得評論的所有回覆
   - 深度限制：內部實作應遵循 3 層限制（已在程式碼驗證）
   - 適用：回覆鏈的完整追蹤

#### 遞迴深度策略決策

**Specification Clarification**: 「遞迴到固定深度（預設 3 層），超過則停止」

**實作驗證**：
- 現有 `fetchRepliesRecursive()` 已支持遞迴
- 深度限制邏輯：檢查 `parent_comment_id` 層級
- 配置：可透過 .env `YOUTUBE_IMPORT_MAX_DEPTH=3` 調整

**決策**：採用現有實作，深度限制為 3 層。超過 3 層的回覆將被忽略（在 fetchRepliesRecursive 中實裝檢查）。

---

### 4. 資料持久化與事務管理

#### CommentImportService 確認

已實作 `app/Services/CommentImportService.php`，提供分階段導入：

**執行流程** ✅
1. Channel 檢查/建立 - 若不存在則插入，存在則 Skip (FR-021a)
2. Video 建立 - 新影片時插入
3. Author 批量建立 - get or create 模式
4. Comments 批量插入 - 帶 parent_comment_id 關係
5. Video comment_count 計算 - 導入完成後計算並寫入 (FR-021b)
6. Channel 統計更新 - last_import_at 更新 (FR-023)

**事務特性** ✅
- 所有資料庫操作在單一事務內
- 任何階段失敗則全部回滾

**增量導入支持** ✅
- 檢測現有 comments 的最大 published_at
- 使用此時間戳過濾 YouTube API 查詢
- 允許「更新留言」功能後續開發

**計數邏輯** ✅
- 留言計數：TOP_LEVEL + REPLIES 分開統計
- 返回 {comments_imported, replies_imported, total_imported}

**決策**：使用現有實作，無需修改。已支持所有需求。

---

### 5. 使用者介面與 Modal 實作

#### 現有 Modal 確認

已實作 `/resources/views/comments/import-modal.blade.php`，提供：

**結構** ✅
- 多步驟設計：URL Input → Metadata/Tags → Preview → Confirm
- Tailwind CSS 樣式化
- 狀態管理：currentStep 變數追蹤

**步驟流程** ✅
1. **Step 1: URL Input**
   - 輸入欄：支援多種 URL 格式（youtu.be, youtube.com/watch?v=）
   - 按鈕：「檢查是否建檔」→ 呼叫 metadata API

2. **Step 2: Metadata/Tags**
   - 顯示：頻道名稱、影片標題
   - 標籤選擇：多選 checkbox，沿用 urtubeapi 邏輯
   - 新頻道強制選擇至少 1 個標籤

3. **Step 3: Preview Comments**
   - 展示 5 則預覽留言
   - 欄位：作者 ID、文本、點讚數、發佈時間
   - 按鈕：「確認導入」→ 呼叫 confirm-import API

4. **成功訊息**
   - 顯示：「成功導入 XXX 則留言」
   - 待用戶關閉後觸發 AJAX 重新載入列表

**JavaScript 整合** ✅
- `window.showYouTubeImportModal()` 全局函數
- AJAX 呼叫所有 API
- 動態更新列表（無頁面重新整理）

**決策**：使用現有實作，無需修改。已完全支持所有 User Story。

---

### 6. Model 關係與驗證

#### 現有 Model 確認

**Channel Model** ✅
```php
// 關係
- hasMany('videos')
- belongsToMany('tags', 'channel_tags')
// 屬性
fillable: [channel_id, channel_name, video_count, comment_count, first_import_at, last_import_at]
```

**Video Model** ✅
```php
// 關係
- belongsTo('channel')
- hasMany('comments')
// 屬性
fillable: [video_id, channel_id, title, published_at]
// 新增需求：comment_count 欄位（遷移處理）
```

**Comment Model** ✅
```php
// 關係
- belongsTo('video')
- belongsTo('author')
- belongsTo('parentComment') // parent_comment_id
- hasMany('replies') // 反向關係
// 屬性
fillable: [comment_id, video_id, author_channel_id, text, like_count, published_at, parent_comment_id]
// 查詢作用域：filterByKeyword, filterByChannel, filterByDateRange, sortByLikes, sortByDate
```

**Author Model** ✅
```php
// 關係
- hasMany('comments')
// 屬性
fillable: [author_channel_id, name, profile_url]
```

**標籤驗證** ✅
- 不支持新建標籤（FR-017b）
- 只能選擇預定義標籤（系統管理員預先建檔）
- 多對多透過 channel_tags 樞紐表
- 新頻道必須選擇至少 1 個標籤（FR-017）

**決策**：使用現有 Model，無需修改。新增 comment_count 欄位透過遷移處理。

---

### 7. 時間戳管理與資料一致性

#### 時間格式決策

**規格要求**（Caveat）：寫入DB的時間格式請保持一致：`2025-06-13 21:00:03`

**現有實作確認**：
- Laravel 自動使用 `YYYY-MM-DD HH:MM:SS` 格式
- `created_at`, `updated_at` 自動由 Laravel 管理
- `published_at` 由 YouTube API 提供，需統一格式

**決策**：
1. 使用 Laravel datetime casting（自動轉換）
2. YouTube API 回應 published_at 為 ISO 8601，需轉換為 `YYYY-MM-DD HH:MM:SS` 格式
3. 實作細節：在 CommentImportService 中使用 `Carbon::parse()` 轉換

---

### 8. 日誌與可觀測性

#### 結構化日誌需求（憲法 III. Observable Systems）

**現有實作**：
- YouTubeApiService 已有完整日誌
- CommentImportService 已有追蹤 ID（trace_id）
- 錯誤情況：詳細日誌記錄

**加強需求**：
- 所有 API 呼叫記錄：來源、時間戳、記錄數
- 導入流程追蹤：開始 → 各階段完成 → 結束
- 錯誤捕捉：詳細堆疊追蹤

**決策**：使用現有日誌系統，CommentImportService 在導入各階段添加關鍵日誌點。

---

### 9. 錯誤處理與降級方案

#### 現有異常類別確認

- `YouTubeApiException` - API 呼叫失敗
- `InvalidVideoIdException` - 無效影片 ID
- `AuthenticationException` - API 金鑰配置錯誤

#### 降級方案確認（FR-009a）

**規格要求**：API 呼叫失敗時，系統必須顯示清晰的錯誤訊息，並提供「重試」按鈕

**現有實作**：
- YouTubeApiImportController 已捕捉所有異常
- 回傳標準 JSON 錯誤格式
- 前端 modal 顯示錯誤訊息和「重試」按鈕

**決策**：使用現有實作。無需新增程式碼，已完全滿足需求。

---

### 10. 頻道列表動態計算（FR-024）

#### 需求澄清

**規格**：「頻道列表」頁面顯示 comment_count 時，系統應動態計算 SUM(videos.comment_count) WHERE channel_id = ?

**現有實作確認**：
- Channel Model 有 `comment_count` 欄位（存儲值）
- 需添加查詢作用域：`withDynamicCommentCount()` 或在 Controller 中動態計算

**決策**：
1. 在 Channel Model 新增查詢作用域：
   ```php
   public function scopeWithDynamicCommentCount($query) {
       return $query->withSum('videos', 'comment_count');
   }
   ```
2. CommentController index() 應用此作用域
3. Blade 模板中顯示 `$channel->videos_sum_comment_count` 而非 `$channel->comment_count`

---

## Decision Summary

| 決策項目 | 決策內容 | 理由 |
|---------|--------|------|
| 遞迴深度 | 3 層限制，超過忽略 | 符合 Spec 要求，現有程式碼支持 |
| 遷移新增 | 1 個：add_comment_count_to_videos | FR-021b 需求，計算實際留言數 |
| 時間格式 | YYYY-MM-DD HH:MM:SS，透過 Laravel casting | 保持資料庫一致性 |
| 增量導入 | 時間戳 + 重複檢測雙停止條件 | 現有實作支持，無需修改 |
| 標籤驗證 | 不支持新建，只能選預定義 | 系統設計決策，已實作 |
| 事務管理 | Channels → Videos → Comments，任一失敗則回滾 | 保證資料一致性 |
| Modal 狀態 | 多步驟設計，JavaScript 管理 | 使用者體驗佳，已完整實作 |
| 頻道計數 | 動態計算 SUM(videos.comment_count) | 應對導入中斷情況 |
| 日誌記錄 | 結構化日誌，含追蹤 ID | 憲法要求，現有實作已支持 |
| 錯誤降級 | 顯示錯誤 + 「重試」按鈕 | 使用者友善，現有實作完整 |

---

## Phase 1 Readiness Confirmation

✅ **所有技術點確認無誤，可進行 Phase 1 設計工作**

- 資料庫遷移策略清晰（新增 1 個遷移）
- API 端點已完全實作（無需新增）
- YouTube API 整合已驗證（支持遞迴、增量、事務）
- UI Modal 已完整實作（支持多步驟、標籤選擇）
- Model 關係已確認（無需大幅修改）
- 時間戳、日誌、錯誤處理均已支持

---

**報告時間**: 2025-11-17 | **下一步**: 執行 `/speckit.plan` Phase 1（資料模型、API 契約、快速開始指南）
