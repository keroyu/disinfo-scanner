# Feature Specification: YouTube API 官方導入留言

**Feature Branch**: `005-api-import-comments`
**Created**: 2025-11-17
**Status**: Draft
**Input**: 新增：用官方API導入留言的功能。功能完全獨立於 urtubeapi，請勿share重複頁面，僅 model 採用相同樣式。user 的YouTube API金鑰寫在 .env 檔案中。頁面入口在 /comments「留言列表」頁面右上角按鈕，文字為「官方API導入」。點擊按鈕後，進入可輸入網址的 modal 視窗。

---

## Y-API 與 U-API 的差異

本系統支援兩種 API 導入方式：

1. **Y-API (YouTube Official API)** - 本文件
   - 使用 YouTube 官方 API
   - 直接從 YouTube 伺服器獲取留言數據
   - 需要 API key 和配額管理
   - 提供完整的元數據（影片標題、頻道名稱、發布時間等）
   - 功能完全獨立於 urtubeapi
   - 頁面入口：`/comments` 留言列表頁面右上角「官方API導入」按鈕

2. **U-API (urtubeapi - Third-Party)**
   - 使用第三方 urtubeapi 服務
   - 僅提供 YouTube 留言的 JSON 數據
   - 不需要 API key
   - 元數據需額外從 YouTube 網頁抓取
   - 相關文件：`specs/001-comment-import`

**重要提醒**：本功能（Y-API）全程通過 YouTube 官方 API 取得資料，和網頁爬蟲或 Metadata 抓取沒有任何關係！請勿調用相關功能！

---

## Clarifications

### Session 2025-11-17

- Q: YouTube API 回覆遞迴邏輯應採用什麼策略？→ A: 深度限制策略，遞迴到固定深度（預設 3 層），超過則停止
- Q: API 失敗時的降級方案是什麼？→ A: 使用者手動重試機制，顯示錯誤訊息並提供「重試」按鈕
- Q: 頻道標籤管理應如何實現？→ A: 多選標籤（至少 1 個，無上限），沿用 urtubeapi 既有邏輯，使用 checkbox 實現，通過 channel_tags 樞紐表存儲多對多關係
- Q: 導入成功後的頁面行為？→ A: 混合方案 - 導入完成後 modal 顯示成功訊息（「成功導入 XXX 則留言」），待用戶關閉 modal 後，執行 JavaScript 動態更新留言列表資料（無頁面重新整理）
- Q: 瀏覽器重新整理時的處理 + 數據一致性保障？→ A: (1) 分階段導入 - 先建檔 channel & video，再導入 comments；若中斷可通過「更新留言」功能（待製作）繼續；(2) 新增 videos.comment_count 欄位（默認 null），導入完成後計算實際評論數並寫入；(3) 「頻道列表」頁面動態加總每個頻道所有視頻的評論數以顯示正確的 channels.comment_count

---

## User Scenarios & Testing *(mandatory)*

**超級重要！** 避免發生歧義，建立後續文檔，都要使用是否「已建檔」或「存在於資料庫」，而非「是否存在」這種混淆的說法！！！
**超級重要！絕對不要犯這個錯！！！** 本功能全程通過 YouTube 官方 API 取得資料，和網頁爬蟲或Metadata沒有任何關係！請勿調用相關功能！

### User Story 1 - 檢查影片是否已建檔 (Priority: P1)

使用者輸入 YouTube 影片網址後，點擊「檢查是否建檔」按鈕，系統查詢資料庫 videos 確認該影片是否已存在。此步驟為整個流程的分支點，決定後續使用者的操作路徑。

**Why this priority**: 這是流程的第一個關鍵決策點。必須準確判斷影片是否已存在，以避免重複導入或錯誤流程。

**Independent Test**: 可透過輸入不同影片網址，驗證系統正確回傳「影片已建檔」或「影片未建檔」的結果。

**Acceptance Scenarios**:

1. **Given** 使用者在 modal 中輸入有效的 YouTube 影片網址，**When** 點擊「檢查是否建檔」，**Then** 系統查詢 videos 表，回傳該影片是否存在
2. **Given** 影片已在資料庫中存在，**When** 檢查完成，**Then** 顯示提示「影片已建檔，請利用更新功能導入留言」，不顯示任何預覽或別的按鈕，user 可以自己關閉 modal
3. **Given** 影片在資料庫中不存在，**When** 檢查完成，**Then** 系統繼續進行頻道檢查，進入 User Story 2 或 3
4. **Given** 使用者輸入無效網址，**When** 點擊「檢查是否建檔」，**Then** 顯示錯誤提示，不進行資料庫查詢

---

### User Story 2 - 新影片+已存在頻道的導入流程 (Priority: P1)

使用者要導入的影片不存在於資料庫，但該頻道已存在。系統從 YouTube API 取得頻道標題、影片標題、影片發布時間、預覽最新 5 則留言（按發佈時間遞減排序），讓使用者確認和修改頻道標籤，然後點擊「確認導入」一次性完整導入所有資料。

**Why this priority**: 這是最常見的使用案例。頻道已存在時，使用者只需確認標籤，系統可直接導入新影片及其留言，避免頻道資訊重複輸入。

**Independent Test**: 可透過輸入已存在頻道的新影片，驗證系統正確取得 API 資料、展示預覽、允許使用者修改標籤，並成功存入資料庫。

**Acceptance Scenarios**:

1. **Given** 影片不存在但頻道已存在，**When** 檢查完成，**Then** 提示「這是已存在頻道的新影片」
2. **Given** 提示顯示後，**When** 系統呼叫 YouTube API，**Then** 取得頻道標題、影片標題、影片發布時間、最新 5 則留言（按發佈時間遞減排序）、留言總數
3. **Given** API 資料成功取得，**When** 對話框開啟，**Then** 展示：頻道標題、影片標題、影片發布時間、預覽留言列表、確認當前頻道標籤（可編輯的 checkbox）
4. **Given** 使用者檢視預覽資料，**When** 修改頻道標籤（可選），**Then** 不持久化任何資料到資料庫
5. **Given** 使用者確認所有資訊正確，**When** 點擊「確認導入」，**Then** 系統：
   - 呼叫 YouTube API 取得該影片的所有留言（遞迴取得所有回覆）
   - 將影片資料存入 videos 表
   - 將所有留言和回覆存入 comments 表
   - 更新 channels 表（不重複插入）
6. **Given** 導入成功完成，**When** 對話框關閉，**Then** 頁面重新整理或列表更新，展示新導入的留言

---

### User Story 3 - 新影片+新頻道的導入流程 (Priority: P1)

使用者要導入的影片和頻道都不存在於資料庫。系統從 YouTube API 取得頻道標題、影片標題、影片發布時間、預覽最新 5 則留言、留言總數，讓使用者選擇新頻道的標籤，然後點擊「確認導入」一次性完整導入所有資料（頻道、影片、留言）。

**Why this priority**: 這是全新頻道和影片的導入場景。使用者需要為新頻道分類標籤，系統必須支援完整的三表插入流程。

**Independent Test**: 可透過輸入全新頻道和影片的 URL，驗證系統正確取得 API 資料、展示預覽、允許使用者選擇標籤，並成功同時存入 channels、videos、comments 表。

**Acceptance Scenarios**:

1. **Given** 影片和頻道都不存在，**When** 檢查完成，**Then** 提示「頻道和影片都未建檔！」
2. **Given** 提示顯示後，**When** 系統呼叫 YouTube API，**Then** 取得頻道標題、影片標題、影片發布日期、最新 5 則留言（按發佈時間遞減排序）
3. **Given** API 資料成功取得，**When** 對話框開啟，**Then** 展示：頻道標題、影片標題、影片發布時間、預覽留言列表、頻道標籤選擇框（新建）
4. **Given** 使用者選擇或輸入頻道標籤，**When** 檢視預覽資料，**Then** 不持久化任何資料到資料庫
5. **Given** 使用者確認所有資訊正確，**When** 點擊「確認導入」，**Then** 系統：
   - 呼叫 YouTube API 取得該影片的所有留言（遞迴取得所有回覆）
   - 將頻道資料存入 channels 表
   - 將影片資料存入 videos 表
   - 將所有留言和回覆存入 comments 表
6. **Given** 導入成功完成，**When** 對話框關閉，**Then** 頁面重新整理或列表更新，展示新導入的留言

---

### User Story 4 - UI 整合與入口 (Priority: P1)

在「留言列表」頁面的右上角新增「官方API導入」按鈕。點擊按鈕後開啟包含 URL 輸入欄位的 modal 視窗，使用者輸入影片 URL 並啟動檢查流程。

**Why this priority**: 這是使用者進入本特性的唯一入口。必須確保按鈕位置明顯、modal 交互流暢，才能提升使用體驗。

**Independent Test**: 可透過點擊「官方API導入」按鈕，驗證 modal 正確開啟，包含 URL 輸入欄和「檢查是否建檔」按鈕，且可正確關閉。

**Acceptance Scenarios**:

1. **Given** 使用者在「留言列表」頁面，**When** 檢視頁面右上角，**Then** 看到「官方API導入」按鈕（Tailwind 樣式）
2. **Given** 使用者點擊「官方API導入」按鈕，**When** 按鈕被點擊，**Then** modal 視窗開啟，展示 URL 輸入欄和「檢查是否建檔」按鈕
3. **Given** modal 已開啟，**When** 使用者輸入 YouTube 影片 URL，**Then** URL 被正確捕捉（可支援多種 URL 格式：youtu.be、youtube.com/watch?v=）
4. **Given** 使用者在任何流程階段，**When** 點擊關閉按鈕或按 ESC，**Then** modal 關閉，流程中斷，不持久化任何資料

---

### Edge Cases

- 使用者輸入無效或格式錯誤的 YouTube URL 時的錯誤提示
- YouTube API 請求超時或失敗時的降級方案（顯示錯誤訊息 + 使用者手動重試按鈕）
- 影片已被刪除或設為私密時的提示
- 頻道已被刪除時的提示
- 同時修改標籤並導入時的資料一致性保證
- 瀏覽器重新整理時未完成的導入如何處理
- 超大影片（數千則留言）的導入效能表現
- 深度超過 3 層的回覆將被忽略（遵循深度限制策略）

---

## Requirements *(mandatory)*

### Functional Requirements

#### 檢查與查詢

- **FR-001**: 系統必須支援使用者在 modal 中輸入 YouTube 影片 URL
- **FR-002**: 系統必須能夠從 URL 解析出 YouTube 影片 ID（支援 youtube.com/watch?v= 和 youtu.be 格式）
- **FR-003**: 系統必須能夠查詢 videos 表，確定指定影片是否存在
- **FR-004**: 若影片存在，系統必須提示「影片已建檔，請利用更新功能導入留言」，並關閉 modal
- **FR-005**: 若影片不存在，系統必須繼續檢查 channels 表，確定對應頻道是否存在
- **FR-006**: 系統必須使用 .env 中的 YOUTUBE_API_KEY 呼叫 YouTube API

#### API 資料取得

- **FR-007**: 系統必須呼叫 YouTube API 取得頻道標題、影片標題、影片發布時間、最新 5 則留言（時間順序最新的5則）、留言總數
- **FR-008**: 系統必須支援遞迴取得留言回覆，最大遞迴深度為 3 層（預設配置，可在 .env 中調整）
- **FR-009**: 預覽階段取得的 API 資料不應持久化到資料庫
- **FR-009a**: API 呼叫失敗時（超時、配額限制、網路錯誤），系統必須顯示清晰的錯誤訊息，並提供「重試」按鈕讓使用者手動重試

#### 資料入庫對應

- **FR-010**: 系統必須將以下 YouTube API 欄位對應到 channels 表：
  - API `snippet.channelTitle` → `channels.name`
  - API `snippet.channelId` → `channels.channel_id`
- **FR-011**: 系統必須將以下 YouTube API 欄位對應到 videos 表：
  - API `snippet.videoId` → `videos.video_id`
  - API `snippet.channelId` → `videos.channel_id`
  - API `snippet.title` → `videos.title`
  - API `snippet.publishedAt` → `videos.published_at`
- **FR-012**: 系統必須將以下 YouTube API 欄位對應到 comments 表：
  - API `snippet.textDisplay` → `comments.content` 或 `text`
  - API `snippet.authorChannelId.value` → `comments.author_channel_id`
  - API `snippet.likeCount` → `comments.like_count`
  - API `snippet.publishedAt` → `comments.published_at`
  - API `id` → `comments.comment_id`
  - Parent comment ID （若存在）→ `comments.parent_comment_id`
- **FR-013**: 系統必須為新導入的留言設定 `created_at` 和 `updated_at` 時間戳

#### 標籤管理

- **FR-014**: 若頻道已存在（User Story 2），modal 必須展示當前頻道的現有標籤，使用者可修改（多選 checkbox，沿用 urtubeapi 界面邏輯）
- **FR-015**: 若頻道不存在（User Story 3），modal 必須動態載入所有可用標籤，展示為多選 checkbox 列表（每個標籤顯示彩色圓點 + 標籤名稱），讓使用者選擇（必須至少 1 個）
- **FR-016**: 系統必須允許使用者修改標籤選擇，修改內容不應在「確認導入」前存入資料庫
- **FR-017**: 新頻道情況下，若標籤欄位為空或未選擇，「確認導入」按鈕應禁用或提示「請至少選擇一個標籤」
- **FR-017a**: 標籤與頻道的多對多關係通過 `channel_tags` 樞紐表存儲（參考 urtubeapi ChannelTagging 邏輯）
- **FR-017b**: 系統不支援新建標籤，只能選擇預定義標籤（tag 資料已由系統管理員預先建檔）

#### 預覽與確認

- **FR-018**: modal 必須展示 API 取得的頻道標題、影片標題、影片發佈時間、最新 5 則留言的預覽（按發佈時間遞減排序）、留言總數
- **FR-019**: 預覽留言應展示作者 ID、留言文本、點讚數、發佈時間
- **FR-020**: 系統必須提供「確認導入」按鈕，點擊後開始完整導入流程

#### 資料持久化

- **FR-021**: 分階段導入策略 - 優先序為：(1) channels (2) videos (3) comments；若任何階段失敗可透過「更新留言」功能（待製作）繼續導入剩餘留言
- **FR-021a**: 系統必須檢查 channels 表，若頻道不存在則插入，若存在則不重複插入
- **FR-021b**: 系統必須在所有 comments 插入完成後，計算該 video 的實際留言數，並寫入 `videos.comment_count` 欄位
- **FR-022**: 系統必須為每條留言設定正確的 `parent_comment_id`，以保留回覆關係
- **FR-023**: 導入成功後，系統必須更新 channels 表的 `last_import_at` 欄位
- **FR-024**: 「頻道列表」頁面顯示 comment_count 時，系統應動態計算 SUM(videos.comment_count) WHERE channel_id = ? 以確保數據正確性（而非直接顯示 channels.comment_count）

#### UI 與用戶體驗

- **FR-026**: 在「留言列表」頁面右上角添加「官方API導入」按鈕（Tailwind CSS 樣式）
- **FR-027**: modal 必須包含 URL 輸入欄、「檢查是否建檔」按鈕、關閉按鈕
- **FR-028**: modal 必須支援 ESC 鍵關閉
- **FR-029**: 系統必須在 modal 中展示清晰的提示和錯誤訊息
- **FR-030**: 導入進行中應顯示進度指示（如加載動畫）
- **FR-031**: 導入成功後，modal 應顯示成功訊息「成功導入 XXX 則留言」，等待使用者關閉 modal
- **FR-031a**: 使用者關閉成功訊息 modal 後，系統應自動執行 JavaScript 動態更新留言列表資料（AJAX 重新載入，無頁面重新整理）

#### 獨立性與樣式

- **FR-032**: 本功能必須完全獨立於 urtubeapi，不共用 UI 頁面
- **FR-033**: 本功能必須採用相同的 model 樣式（如 Comment、Video、Channel 等），保持資料模型一致
- **FR-034**: 所有 UI 元件必須使用 Tailwind CSS，風格與既有留言列表頁面一致

---

## Key Entities

- **YouTube API Response**: YouTube API 返回的影片、頻道、留言資料結構
- **Video**: 影片記錄（video_id, channel_id, title, published_at）
- **Channel**: 頻道記錄（channel_id, name, tags, comment_count, last_import_at）
- **Comment**: 留言記錄（comment_id, video_id, author_channel_id, content, like_count, parent_comment_id, published_at）

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 使用者能在 3 秒內點擊「官方API導入」按鈕並看到 modal 開啟
- **SC-002**: URL 檢查（影片 + 頻道存在查詢）在 1 秒內完成
- **SC-003**: YouTube API 取得預覽資料在 5 秒內完成（含網路延遲）
- **SC-004**: 完整導入（API 取得所有留言 + 資料庫插入）在 30 秒內完成（不含 API 延遲）
- **SC-005**: 導入成功後，所有留言（包括回覆）都能在留言列表中找到
- **SC-006**: 留言的所有欄位（內容、作者 ID、點讚數、發佈時間、parent_comment_id）都正確對應 API 資料
- **SC-007**: 頻道和影片資料完整準確（頻道標題、影片標題都與 API 返回資料一致）
- **SC-008**: 新導入的留言與既有留言相容，無資料衝突或重複
- **SC-009**: 導入成功後，modal 顯示成功訊息「成功導入 XXX 則留言」，訊息清晰可見
- **SC-010**: 使用者關閉成功訊息 modal 後 2 秒內，留言列表應自動更新，新導入的留言應在列表頂部或對應位置顯示（無頁面刷新）

---

## Assumptions

- YouTube API Key 已正確配置在 .env 中
- videos 表已存在，包含 video_id, channel_id, title, published_at；且需新增 comment_count 欄位（默認 null）
- channels 表已存在，包含 channel_id, channel_name, last_import_at
- comments 表支援 parent_comment_id 外鍵關聯（待新增遷移）
- 「更新留言」功能為未來功能（本特性不實現，但設計時應為其預留空間）
- YouTube API 響應遵循標準格式（Google API Client Library 返回結構）
- 使用者有有效的網際網路連線
- 使用者輸入的 URL 能被正確解析為 YouTube 影片 URL
- 系統採用 Laravel + Tailwind CSS 技術棧
- 留言列表頁面已存在，可正常加載評論資料

---

## Constraints

- 功能完全獨立於 urtubeapi，不共用頁面或路由
- YouTube API 配額限制（需考慮 API 消耗）
- 預覽階段不應持久化任何資料
- 標籤欄位不應為空（必須驗證）
- 同時支援多種 YouTube URL 格式（youtu.be、youtube.com/watch?v=）
- 所有 UI 元件必須使用 Tailwind CSS
- 導入分階段進行（channels → videos → comments），單一階段失敗可允許部分導入
- 導入完成後必須計算 videos.comment_count，確保與 comments 表實際記錄數一致
- 「頻道列表」頁面顯示的 comment_count 應為動態計算（SUM），而非存儲值，以應對導入中斷情況
