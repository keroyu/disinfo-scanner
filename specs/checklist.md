# 修正 Database Schema: 移除 channels 統計欄位，改用 videos.comment_count

## 問題描述

目前 `channels` 表中的 `video_count` 和 `comment_count` 欄位設計不當：
- `video_count` 沒有必要（可透過查詢 videos 表統計）
- `comment_count` 應該記錄在 `videos` 表中（每個影片有多少留言）
- 前端需要頻道總留言數時，應從該頻道所有影片的 `comment_count` 加總

## 正確的設計

- ✅ `videos` 表有 `comment_count` 欄位，記錄每個影片的留言數
- ✅ 每次導入留言成功後，更新該影片的 `comment_count`
- ✅ 前端統計頻道留言數時，加總該頻道所有影片的 `comment_count`
- ❌ 不在 `channels` 表維護冗餘的統計欄位

## 修改清單

### 1. 資料庫層級
- [ ] 建立 migration: `remove_counts_from_channels_table.php`
  - 刪除 `channels.video_count`
  - 刪除 `channels.comment_count`

### 2. Model 層級
- [ ] `app/Models/Channel.php:16`
  - 從 `$fillable` 移除 `'video_count'`
  - 從 `$fillable` 移除 `'comment_count'`

### 3. Service 層級

#### CommentImportService.php
- [ ] **行 227-231**: `firstOrCreate` channel 時
  - 移除 `'video_count' => 0`
  - 移除 `'comment_count' => 0`

- [ ] **行 282-300**: `executeFullImport` 中的 channel 更新
  - 刪除「Step 4: Update channel with new counts」區塊
  - 改為更新 `videos.comment_count`

- [ ] **行 527**: `performFullImport` 中
  - 保持 `calculateCommentCount($videoId)` 更新 videos 表（已正確）
  - 移除 channel 統計更新（如果有）

#### ImportService.php
- [ ] **行 246-254**: `confirmImport` transaction 中
  - 移除 `'comment_count' => Comment::where('video_id', ...)->count()`
  - 移除 `'video_count' => $channel->video_count + 1`
  - 改為更新 `videos.comment_count`

- [ ] **行 367-379**: `resumeImport` transaction 中
  - 移除 `'comment_count' => Comment::where('video_id', ...)->count()`
  - 移除 `'video_count' => $channel->video_count + 1`
  - 改為更新 `videos.comment_count`

### 4. 前端層級

- [ ] `resources/views/channels/list.blade.php:70`
  - 將 `$channel->comment_count` 改為從關聯計算
  - 使用 `$channel->videos->sum('comment_count')` 或預載方式

- [ ] 更新相關 Controller
  - 使用 `withSum('videos', 'comment_count')` 預載統計
  - 使用 `withCount('videos')` 預載影片數量

### 5. 測試與驗證

- [ ] 執行 migration
- [ ] 測試新影片導入流程
- [ ] 測試既有影片增量導入
- [ ] 驗證頻道列表頁面統計正確
- [ ] 確認 `videos.comment_count` 正確更新

## 核心邏輯變更總結

**導入留言後的更新邏輯：**
```php
// 舊邏輯 (錯誤)
$channel->update([
    'comment_count' => Comment::where('video_id', $videoId)->count(),
    'video_count' => $channel->video_count + 1,
]);

// 新邏輯 (正確)
$video->update([
    'comment_count' => Comment::where('video_id', $videoId)->count(),
]);
```

**前端顯示頻道統計：**
```php
// 舊邏輯 (錯誤)
$channel->comment_count

// 新邏輯 (正確)
// Controller:
$channels = Channel::withSum('videos', 'comment_count')
                  ->withCount('videos')
                  ->get();

// View:
$channel->videos_sum_comment_count // 總留言數
$channel->videos_count             // 影片數量
```

## 檔案修改列表

1. ✅ 新增: `database/migrations/[timestamp]_remove_counts_from_channels_table.php`
2. ✅ 修改: `app/Models/Channel.php`
3. ✅ 修改: `app/Services/CommentImportService.php`
4. ✅ 修改: `app/Services/ImportService.php`
5. ✅ 修改: `resources/views/channels/list.blade.php`
6. ✅ 修改: `app/Http/Controllers/*Controller.php` (相關 Controller)


----


tag_id 的欄位，可以記錄複數的 tag，以半形
逗號“,”作為分隔，方便複數tag讀取和寫入，記得一併告訴我你打算改哪些程式
。 

⏺ 明白了！我將在 channels 表新增 tag_ids
  欄位（字串類型），以逗號分隔存儲多個 tag ID（例如："6,9"），然後刪除
  channel_tags 表。

  📋 我打算進行以下修改：

  1. 資料庫層級

  Migration: 新增 tag_ids 欄位到 channels 表
  - 在 channels 表新增 tag_ids 欄位（varchar/text 類型）
  - 將現有 channel_tags 的資料遷移到 channels.tag_ids（格式："6,9"）
  - 刪除 channel_tags 表

  ---
  2. Model 層級

  app/Models/Channel.php
  - 移除 belongsToMany(Tag::class, 'channel_tags', ...) 關係
  - 新增 tag_ids 到 $fillable
  - 新增 accessor/helper 方法：
    - getTagIdsArray(): 將 "6,9" 轉為 [6, 9]
    - setTagIdsAttribute(): 自動處理陣列轉字串
    - tags(): 新的關係方法，用 whereIn() 查詢 tags

  app/Models/Tag.php
  - 移除 belongsToMany(Channel::class, 'channel_tags', ...) 關係

  刪除 app/Models/ChannelTag.php
  - 整個檔案刪除（不再需要 pivot model）

  ---
  3. Service 層級

  app/Services/ChannelTagManager.php
  - 修改 getChannelTags(): 使用新的查詢方式
  // 舊: $channel->tags()->get()
  // 新: Tag::whereIn('tag_id', $channel->getTagIdsArray())->get()
  - 修改 syncChannelTags(): 改為更新字串欄位
  // 舊: $channel->tags()->sync($tagIds)
  // 新: $channel->update(['tag_ids' => implode(',', $tagIds)])

  app/Services/CommentImportService.php (行 499)
  - 保持不變（使用 ChannelTagManager，內部邏輯已修改）

  ---
  4. Controller 層級

  app/Http/Controllers/ChannelListController.php
  - 修改預載方式：
  // 舊: Channel::with('tags')
  // 新: 需要手動處理或使用自定義 scope

  ---
  5. View 層級

  resources/views/channels/list.blade.php (行 57)
  - 修改 tags 顯示邏輯：
  // 舊: @forelse($channel->tags as $tag)
  // 新: @forelse($channel->getTagsCollection() as $tag)

  ---
  6. Migration 檔案

  刪除 database/migrations/*_create_channel_tags_table.php
  - 刪除建立 pivot table 的 migration

  新增 database/migrations/*_add_tag_ids_to_channels_and_drop_channel_t
  ags.php
  - 資料遷移步驟：
    a. 新增 channels.tag_ids 欄位
    b. 將 channel_tags 資料轉換並寫入 channels.tag_ids
    c. 刪除 channel_tags 表

  ---
  7. 其他可能受影響的檔案

  檢查並修改：
  - app/Services/ChannelTaggingService.php（如果存在）
  - tests/Feature/ImportConfirmationFlowTest.php
  - resources/views/components/import-comments-modal.blade.php

  ---
  📝 資料格式示例

  目前（channel_tags 表）：
  channel_id: UC_xVdv15MR17rQf-nUl92MA
  tag_id: 6

  channel_id: UCiWXd0nmBjlKROwzMyPV-Nw
  tag_id: 6

  channel_id: UCiWXd0nmBjlKROwzMyPV-Nw
  tag_id: 9

  修改後（channels 表）：
  channel_id: UC_xVdv15MR17rQf-nUl92MA
  tag_ids: "6"

  channel_id: UCiWXd0nmBjlKROwzMyPV-Nw
  tag_ids: "6,9"

  ---
  ✅ 修改檔案總覽

  1. ✅ 新增 Migration（資料遷移 + 刪除 channel_tags）
  2. ✅ 修改 app/Models/Channel.php
  3. ✅ 修改 app/Models/Tag.php
  4. ✅ 刪除 app/Models/ChannelTag.php
  5. ✅ 修改 app/Services/ChannelTagManager.php
  6. ✅ 修改 app/Http/Controllers/ChannelListController.php
  7. ✅ 修改 resources/views/channels/list.blade.php
  8. ✅ 刪除 database/migrations/*_create_channel_tags_table.php
