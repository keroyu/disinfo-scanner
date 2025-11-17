# Data Model: YouTube API 官方導入留言

**Feature**: YouTube API 官方導入留言 | **Date**: 2025-11-17 | **Phase**: 1 Design

---

## Entity Relationship Diagram (ERD)

```
┌─────────────────┐         ┌────────────────┐         ┌──────────────────┐
│    channels     │ 1    N  │     videos     │ 1    N  │    comments      │
├─────────────────┤◄────────┤────────────────┤◄────────┤──────────────────┤
│ channel_id (PK) │         │ video_id (PK)  │         │ comment_id (PK)  │
│ channel_name    │         │ channel_id (FK)│         │ video_id (FK)    │
│ video_count     │         │ title          │         │ author_channel_id│
│ comment_count   │         │ published_at   │         │ text             │
│ first_import_at │         │ comment_count  │◄────────│ like_count       │
│ last_import_at  │         │ created_at     │◄────────│ published_at     │
│ created_at      │         │ updated_at     │         │ parent_comment_id│
│ updated_at      │         │                │         │ created_at       │
└─────────────────┘         └────────────────┘         │ updated_at       │
        │                                              └──────────────────┘
        │ N                                                      │ 1
        │                                              (self-referential)
        │                                              reply chain
        │ M
        ├────────────────────────────────────────────┐
        │                                            │
┌───────┴──────────┐                        ┌────────▼──────────┐
│  channel_tags    │                        │     authors       │
├──────────────────┤                        ├───────────────────┤
│ channel_id (PK)  │                        │ author_channel_id │
│ tag_id (PK)      │◄──────────┐            │ (PK)              │
│ created_at       │           │            │ name              │
└──────────────────┘           │            │ profile_url       │
        │                       │            │ created_at        │
        │ M                     │ N          │ updated_at        │
        └─────────────────────┬─┘            └───────────────────┘
                              │
                      ┌───────▼──────────┐
                      │       tags       │
                      ├──────────────────┤
                      │ tag_id (PK)      │
                      │ name             │
                      │ color_hex        │
                      │ created_at       │
                      │ updated_at       │
                      └──────────────────┘

Legend:
- (PK) = Primary Key
- (FK) = Foreign Key
- 1   = One (1:N or 1:1)
- N   = Many (1:N or M:N)
- M   = Multiple (M:N)
```

---

## Detailed Entity Specifications

### 1. channels 表

**用途**: 儲存 YouTube 頻道基本資訊

| 欄位名稱 | 資料型別 | 約束 | 說明 |
|---------|--------|------|------|
| `channel_id` | varchar(255) | PK, NOT NULL | YouTube 頻道 ID，11 字元固定長度 |
| `channel_name` | varchar(255) | NULLABLE | 頻道名稱，來自 YouTube API `snippet.channelTitle` |
| `video_count` | unsigned int | DEFAULT 0 | 該頻道已導入的影片數量 |
| `comment_count` | unsigned int | DEFAULT 0 | 該頻道所有影片的留言總數（冗餘欄位，實際應動態計算） |
| `first_import_at` | timestamp | NULLABLE | 首次導入此頻道的時間 |
| `last_import_at` | timestamp | NULLABLE | 最後導入此頻道的時間（導入完成後更新，FR-023） |
| `created_at` | timestamp | DEFAULT CURRENT_TIMESTAMP | Laravel 建立時間 |
| `updated_at` | timestamp | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Laravel 更新時間 |

**索引**:
- PRIMARY KEY: `channel_id`
- 無其他索引（頻道數量小，全表掃描可接受）

**驗證規則**:
- `channel_id`: 長度必須為 11 字元，僅允許字母數字、下劃線、連字符
- `channel_name`: 長度 1-255

**使用場景**:
- User Story 1: 檢查頻道是否存在
- User Story 2: 更新既有頻道資訊
- User Story 3: 建立新頻道
- FR-024: 動態計算評論數

---

### 2. videos 表

**用途**: 儲存 YouTube 影片基本資訊

| 欄位名稱 | 資料型別 | 約束 | 說明 |
|---------|--------|------|------|
| `video_id` | varchar(255) | PK, NOT NULL | YouTube 影片 ID，11 字元固定長度 |
| `channel_id` | varchar(255) | FK NOT NULL, INDEXED | 所屬頻道，參考 channels.channel_id |
| `title` | varchar(255) | NULLABLE | 影片標題，來自 YouTube API `snippet.title` |
| `published_at` | timestamp | NULLABLE | 影片發佈時間，來自 YouTube API `snippet.publishedAt` |
| `comment_count` | unsigned int | NULLABLE | 該影片的留言總數（包括回覆），導入完成後計算填入（FR-021b）[**新增欄位**] |
| `created_at` | timestamp | DEFAULT CURRENT_TIMESTAMP | Laravel 建立時間 |
| `updated_at` | timestamp | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Laravel 更新時間 |

**索引**:
- PRIMARY KEY: `video_id`
- FOREIGN KEY: `channel_id` → `channels.channel_id`
- INDEX: `channel_id` (用於頻道查詢)

**驗證規則**:
- `video_id`: 長度必須為 11 字元，僅允許字母數字、下劃線、連字符
- `channel_id`: 必須存在於 channels 表
- `title`: 長度 1-255
- `comment_count`: 非負整數或 NULL

**使用場景**:
- User Story 1: 檢查影片是否存在
- User Story 2, 3: 建立新影片或更新現有影片
- FR-021b: 記錄該影片的實際評論數

---

### 3. comments 表

**用途**: 儲存 YouTube 留言及其回覆

| 欄位名稱 | 資料型別 | 約束 | 說明 |
|---------|--------|------|------|
| `comment_id` | varchar(255) | PK, NOT NULL | YouTube 留言 ID |
| `video_id` | varchar(255) | FK NOT NULL, INDEXED | 所屬影片，參考 videos.video_id |
| `author_channel_id` | varchar(255) | FK NOT NULL, INDEXED | 留言作者，參考 authors.author_channel_id |
| `text` | longtext | NOT NULL | 完整留言文本，來自 YouTube API `snippet.textDisplay` |
| `like_count` | unsigned int | DEFAULT 0 | 留言點讚數，來自 YouTube API `snippet.likeCount` |
| `published_at` | timestamp | NULLABLE | 留言發佈時間，來自 YouTube API `snippet.publishedAt` |
| `parent_comment_id` | varchar(255) | FK NULLABLE, INDEXED | 若為回覆，參考父留言 comment_id；若為頂層留言則 NULL |
| `created_at` | timestamp | DEFAULT CURRENT_TIMESTAMP | Laravel 建立時間 |
| `updated_at` | timestamp | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Laravel 更新時間 |

**索引**:
- PRIMARY KEY: `comment_id`
- FOREIGN KEY: `video_id` → `videos.video_id`
- FOREIGN KEY: `author_channel_id` → `authors.author_channel_id`
- FOREIGN KEY: `parent_comment_id` → `comments.comment_id`（自參考，用於回覆鏈）
- INDEX: `[video_id, comment_id]` (複合，用於頻道-影片查詢)
- INDEX: `video_id` (用於影片查詢)
- INDEX: `author_channel_id` (用於作者查詢)

**驗證規則**:
- `comment_id`: 不為空，唯一
- `video_id`: 必須存在於 videos 表
- `author_channel_id`: 必須存在於 authors 表（get or create）
- `parent_comment_id`: 若不為空，必須存在於 comments 表；且不可指向自己
- `text`: 長度 1-65535 (longtext)
- `like_count`: 非負整數

**深度限制** (FR-008):
- 最大遞迴深度為 3 層
- 達到深度限制後的回覆將被忽略（不入庫）
- 檢查方式：追蹤 `parent_comment_id` 鏈的長度

**使用場景**:
- User Story 2, 3, 5: 導入頂層留言和回覆
- 留言列表頁面: 篩選、搜尋、排序

---

### 4. authors 表

**用途**: 儲存留言作者資訊（頻道）

| 欄位名稱 | 資料型別 | 約束 | 說明 |
|---------|--------|------|------|
| `author_channel_id` | varchar(255) | PK, NOT NULL | 作者的 YouTube 頻道 ID |
| `name` | varchar(255) | NULLABLE | 作者顯示名稱，來自 YouTube API `snippet.authorDisplayName` |
| `profile_url` | varchar(512) | NULLABLE | 作者個人頻道 URL（可選） |
| `created_at` | timestamp | DEFAULT CURRENT_TIMESTAMP | Laravel 建立時間 |
| `updated_at` | timestamp | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Laravel 更新時間 |

**索引**:
- PRIMARY KEY: `author_channel_id`

**驗證規則**:
- `author_channel_id`: 11 字元，字母數字/下劃線/連字符
- `name`: 長度 1-255
- `profile_url`: 有效 URL 格式（可選）

**使用場景**:
- 留言導入時: get or create 作者記錄
- 留言列表頁面: 顯示留言作者名稱

---

### 5. channel_tags 表 (樞紐表)

**用途**: 儲存頻道和標籤的多對多關係

| 欄位名稱 | 資料型別 | 約束 | 說明 |
|---------|--------|------|------|
| `channel_id` | varchar(255) | PK, FK, NOT NULL | 頻道 ID，參考 channels.channel_id |
| `tag_id` | unsigned bigint | PK, FK, NOT NULL | 標籤 ID，參考 tags.tag_id |
| `created_at` | timestamp | NULLABLE | 關聯建立時間 |

**索引**:
- PRIMARY KEY: `[channel_id, tag_id]`
- FOREIGN KEY: `channel_id` → `channels.channel_id`
- FOREIGN KEY: `tag_id` → `tags.tag_id`

**驗證規則**:
- 一個頻道至少關聯 1 個標籤（新頻道強制）
- 標籤必須存在（FR-017b，不支持新建）
- 無重複關聯

**使用場景**:
- User Story 2: 顯示既有頻道的當前標籤，允許修改
- User Story 3: 新頻道必須選擇至少 1 個標籤
- 導入完成後: 儲存或更新頻道的標籤關聯

---

### 6. tags 表 (現有，不修改)

**用途**: 儲存系統預定義的標籤

| 欄位名稱 | 資料型別 | 約束 | 說明 |
|---------|--------|------|------|
| `tag_id` | unsigned bigint | PK, AUTO_INCREMENT | 標籤 ID |
| `name` | varchar(255) | UNIQUE, NOT NULL | 標籤名稱 |
| `color_hex` | varchar(7) | NULLABLE | 標籤顏色（HEX 格式，如 #FF0000） |
| `created_at` | timestamp | DEFAULT CURRENT_TIMESTAMP | 建立時間 |
| `updated_at` | timestamp | DEFAULT CURRENT_TIMESTAMP ON UPDATE | 更新時間 |

**注意**: 此表為現有表，本功能不新增或修改，僅讀取預定義標籤供使用者選擇。

---

## Data Migration Strategy

### Migration 1: 新增 comment_count 欄位到 videos 表

**檔名**: `{timestamp}_add_comment_count_to_videos.php`

**Up 邏輯**:
```sql
ALTER TABLE videos ADD COLUMN comment_count UNSIGNED INT DEFAULT NULL AFTER published_at;
```

**Down 邏輯**:
```sql
ALTER TABLE videos DROP COLUMN comment_count;
```

**理由**:
- FR-021b 要求：導入完成後計算實際評論數並寫入
- 預設 NULL，導入時由 CommentImportService 計算填入
- 允許查詢使用此欄位進行快速統計

---

## Data Consistency & Integrity

### 唯一性約束

| 表名 | 約束 | 說明 |
|-----|------|------|
| `channels` | PK: channel_id | 每個 YouTube 頻道 ID 唯一 |
| `videos` | PK: video_id | 每個 YouTube 影片 ID 唯一 |
| `comments` | PK: comment_id | 每個 YouTube 留言 ID 唯一 |
| `authors` | PK: author_channel_id | 每個作者頻道 ID 唯一 |
| `channel_tags` | PK: [channel_id, tag_id] | 頻道-標籤組合唯一 |

### 外鍵約束

| 表名 | 外鍵欄位 | 參考表 | 參考欄位 | ON DELETE | ON UPDATE |
|-----|---------|-------|--------|----------|-----------|
| `videos` | channel_id | channels | channel_id | RESTRICT | CASCADE |
| `comments` | video_id | videos | video_id | CASCADE | CASCADE |
| `comments` | author_channel_id | authors | author_channel_id | RESTRICT | CASCADE |
| `comments` | parent_comment_id | comments | comment_id | CASCADE | CASCADE |
| `channel_tags` | channel_id | channels | channel_id | CASCADE | CASCADE |
| `channel_tags` | tag_id | tags | tag_id | RESTRICT | CASCADE |

### 時間戳管理

**格式統一**: 所有時間戳採用 `YYYY-MM-DD HH:MM:SS` 格式（Laravel datetime casting 自動處理）

**時間戳欄位**:
- `created_at`: 記錄建立時間，自動設定，不可手動修改
- `updated_at`: 記錄最後更新時間，自動維護
- `published_at`: 來自 YouTube API，表示內容發佈時間

**時間戳轉換流程**:
1. YouTube API 返回 ISO 8601 格式（如 `2025-11-17T12:34:56Z`）
2. YouTubeApiService 透過 `Carbon::parse()` 轉換
3. 存入資料庫時自動轉換為 `YYYY-MM-DD HH:MM:SS`
4. 模型讀取時自動轉換回 Carbon 物件

### 重複檢測

**預防策略**:
- 利用 Primary Key（comment_id, video_id 等）進行唯一性檢查
- 增量導入時：檢查 existingCommentIds 清單（FR-021）
- Upsert 操作：使用 `firstOrCreate()` 或 `updateOrCreate()`

### 資料完整性檢查

**導入時的檢查**:
1. 影片 ID 驗證（11 字元）
2. 頻道 ID 驗證（11 字元）
3. URL 格式驗證（youtube.com/watch?v= 或 youtu.be）
4. 標籤選擇驗證（新頻道至少 1 個）
5. 回覆深度檢查（最多 3 層）

---

## Query Patterns & Performance Considerations

### 常見查詢

| 需求 | 查詢邏輯 | 索引 |
|------|--------|------|
| 檢查影片是否存在 | WHERE video_id = ? | PK: video_id |
| 檢查頻道是否存在 | WHERE channel_id = ? | PK: channel_id |
| 取得頻道所有影片 | WHERE channel_id = ? | INDEX: videos.channel_id |
| 取得影片所有留言 | WHERE video_id = ? | INDEX: comments.video_id |
| 取得頻道的留言 | JOIN videos ON comments.video_id = videos.video_id WHERE videos.channel_id = ? | INDEX: comments.video_id, videos.channel_id |
| 取得留言及回覆 | WHERE video_id = ? AND (parent_comment_id IS NULL OR parent_comment_id IN (...)) | INDEX: comments.video_id |
| 計算頻道評論數 | SUM(videos.comment_count) WHERE channel_id = ? | INDEX: videos.channel_id |

### 預期查詢性能

- **影片/頻道存在檢查**: < 100ms (Primary Key 查詢)
- **取得頻道影片列表**: < 500ms (1 個頻道通常 < 100 影片)
- **取得影片留言**: < 1s (取決於留言數，預期 < 10K)
- **頻道評論數動態計算**: < 200ms (用 withSum aggregate)

---

## Data Import Flow State Machine

```
開始
  │
  ├─► [1] 驗證影片 URL
  │    ├─ 合法 ──► [2] 查詢影片是否存在
  │    └─ 非法 ──► 終止 (錯誤)
  │
  ├─► [2] 查詢影片是否存在
  │    ├─ 存在 ──► 終止 (提示：「已建檔」)
  │    └─ 不存在 ──► [3] 查詢頻道是否存在
  │
  ├─► [3] 查詢頻道是否存在
  │    ├─ 存在 ──► [4a] 取得頻道資訊 (現有頻道流程)
  │    └─ 不存在 ──► [4b] 初始化新頻道 (新頻道流程)
  │
  ├─► [4a] 取得頻道資訊 + 顯示標籤
  │    ├─ 預覽 5 則留言 ──► [5a] 使用者確認導入
  │    └─ 使用者取消 ──► 終止
  │
  ├─► [4b] 初始化新頻道 + 強制選擇標籤
  │    ├─ 預覽 5 則留言 ──► [5b] 使用者確認導入
  │    └─ 使用者取消 ──► 終止
  │
  ├─► [5a/5b] 事務開始
  │    ├─► [6] 插入/更新 Channel
  │    ├─► [7] 插入/更新 Video
  │    ├─► [8] 取得所有留言 (YouTube API)
  │    ├─► [9] 建立 Authors (get or create)
  │    ├─► [10] 插入 Comments
  │    ├─► [11] 計算 comment_count
  │    ├─► [12] 更新 Channel tags
  │    ├─► [13] 更新 last_import_at
  │    └─► [14] 事務提交 ──► 成功終止
  │
  └─► 例外處理 ──► 事務回滾 ──► 終止 (錯誤)
```

---

## Model Relationships Code Reference

```php
// Channel Model
public function videos() {
    return $this->hasMany(Video::class, 'channel_id', 'channel_id');
}

public function tags() {
    return $this->belongsToMany(Tag::class, 'channel_tags', 'channel_id', 'tag_id');
}

// Video Model
public function channel() {
    return $this->belongsTo(Channel::class, 'channel_id', 'channel_id');
}

public function comments() {
    return $this->hasMany(Comment::class, 'video_id', 'video_id');
}

// Comment Model
public function video() {
    return $this->belongsTo(Video::class, 'video_id', 'video_id');
}

public function author() {
    return $this->belongsTo(Author::class, 'author_channel_id', 'author_channel_id');
}

public function parentComment() {
    return $this->belongsTo(Comment::class, 'parent_comment_id', 'comment_id');
}

public function replies() {
    return $this->hasMany(Comment::class, 'parent_comment_id', 'comment_id');
}

// Author Model
public function comments() {
    return $this->hasMany(Comment::class, 'author_channel_id', 'author_channel_id');
}
```

---

**資料模型版本**: 1.0 | **最後更新**: 2025-11-17
