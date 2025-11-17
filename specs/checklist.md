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
