# Quickstart Guide: Videos List

**Feature**: Videos List
**Audience**: End Users, Content Analysts
**Last Updated**: 2025-11-18

---

## Overview

The **Videos List** page provides a comprehensive view of all YouTube videos that have collected comments in the system. Use this page to:
- Identify which videos are generating the most discussion
- Find videos by channel or title
- Navigate quickly to related comments for detailed analysis

---

## Accessing the Videos List

### Method 1: Navigation Menu

1. Open the application in your web browser
2. Look for the navigation bar at the top of the page
3. Click on **"Videos List"** (positioned to the right of "Channels List")

### Method 2: Direct URL

Navigate directly to:
```
https://your-domain.com/videos
```

---

## Understanding the Videos List

### Page Layout

The Videos List displays a table with 4 columns:

| Column | Description | Clickable |
|--------|-------------|-----------|
| **Channel Name** | The YouTube channel that published the video | ✅ Yes |
| **Video Title** | The title of the video | ✅ Yes |
| **Comment Count** | Total number of comments collected for this video | ✅ Yes (for sorting) |
| **Last Comment Time** | When the most recent comment was posted (format: YYYY-MM-DD HH:MM) | ✅ Yes |

### Default Display

When you first load the page:
- Videos are sorted by **video publication date** (newest videos first)
- Shows **500 videos per page**
- Only videos with at least **one comment** are displayed

---

## Searching for Videos

### Two Separate Search Fields

The search panel provides two independent search fields:

#### 1. Search Videos Field
- **Purpose**: Search by video title only
- **Location**: Left side of search panel
- **Example**: Enter "climate change" to find videos with those words in the title

#### 2. Search Channel Field
- **Purpose**: Filter videos by channel
- **Location**: Right side of search panel
- **Two ways to use**:
  - **Text Input**: Type channel name (e.g., "CNN")
  - **Dropdown Selector**: Select from list of all channels
  - **Auto-sync**: Selecting from dropdown auto-fills the text input

### How to Search

1. **By Video Title**: Enter keywords in the "Search Videos" field
2. **By Channel**: Either type in "Search Channel" field OR select from the dropdown
3. **Combine Both**: Search for specific video titles within a specific channel
4. Click **"Apply Filters"** to see results

### Clearing Search Filters

Click the **"Clear Filters"** button to reset all filters and view all videos again.

---

## Sorting Videos

### How to Sort

Click on any **column header** with a sort icon (Comment Count or Last Comment Time) to sort by that column.

### Available Sort Options

| Column | Sort Behavior | Default |
|--------|---------------|---------|
| **Video Publication Date** | Newest to oldest | ✅ Default |
| **Comment Count** | Click header to sort | Highest to lowest |
| **Last Comment Time** | Click header to sort | Most recent first |

### Toggling Sort Direction

- **First click**: Sort descending (high to low, or newest to oldest)
- **Second click**: Sort ascending (low to high, or oldest to newest)
- **Visual indicator**: An arrow (▼ or ▲) shows the current sort direction

---

## Navigating to Comments

Each video row provides **3 clickable links** to view related comments:

### 1. Click on **Channel Name**

**What it does**: Stays on Videos List and filters to show only videos from that channel

**Use case**: "Show me all videos from this channel"

**Example**:
- You click on "CNN" in the Channel Name column
- The Videos List reloads with channel filter pre-filled: `?search_channel=CNN`
- You see only videos published by CNN

---

### 2. Click on **Video Title**

**What it does**: Opens the Comments List filtered to show comments for that specific video

**Use case**: "Show me all comments for this video"

**Example**:
- You click on "Climate Change Documentary" in the Video Title column
- You're taken to Comments List with title search pre-filled: `?search=Climate Change Documentary`

---

### 3. Click on **Last Comment Time**

**What it does**: Opens the Comments List with:
- Video title filter
- Date range: 90 days before the clicked date to the clicked date

**Use case**: "Show me recent comments (last 90 days) for this video"

**Example**:
- Last Comment Time shows: `2025-07-23 09:58`
- You click on that timestamp
- You're taken to Comments List with:
  - Video title: `Climate Change Documentary`
  - Date range: `2025-04-24` to `2025-07-23` (90 days before to clicked date)

---

## Pagination

### Navigating Between Pages

- **500 videos per page** (matching Comments List pagination)
- Use the page numbers at the bottom to navigate
- Use **Previous** / **Next** buttons for quick navigation
- Page indicator shows: "Showing 1 to 500 of 1,523 videos"

### State Preservation

When you navigate to a different page:
- ✅ Search keywords are preserved
- ✅ Sort column and direction are preserved
- ✅ All filters remain applied

**Example**: If you search for "news", sort by comment count, and go to page 2, all those settings remain active.

---

## Common Use Cases

### Use Case 1: Find Most Discussed Videos

**Goal**: Identify videos with the most comments

**Steps**:
1. Go to Videos List
2. Click on **"Comment Count"** column header
3. Videos with highest comment counts appear first

---

### Use Case 2: Find Recent Activity

**Goal**: See which videos received comments recently

**Steps**:
1. Go to Videos List
2. Click on **"Last Comment Time"** column header
3. Videos with most recent comments appear first

---

### Use Case 3: Analyze a Specific Channel

**Goal**: View all videos from a particular channel and their comment activity

**Steps**:
1. Go to Videos List
2. **Option A**: Type channel name in **"Search Channel"** field (e.g., "BBC")
3. **Option B**: Select channel from the dropdown
4. Click **"Apply Filters"**
5. View all videos from that channel
6. **Alternative**: Click on any **Channel Name** link in the table to instantly filter to that channel

---

### Use Case 4: Investigate a Trending Video

**Goal**: Find a video and analyze its recent comments

**Steps**:
1. Go to Videos List
2. Search for video keywords (e.g., "election debate")
3. Find the video in results
4. Click on **Video Title** to see all comments
5. OR click on **Last Comment Time** to see recent comments (last 90 days)

---

## Edge Cases & Special Behaviors

### Unknown Channel

**What you see**: "Unknown Channel" in the Channel Name column

**Why**: The channel data is missing or was deleted from the database

**What to do**: You can still click on the video title to view comments

---

### No Videos Found

**What you see**: Message: "No videos found. Try adjusting your search filters."

**Why**: Your search/filter criteria matched zero videos

**What to do**:
- Try a broader search term
- Click "Clear Filters" to see all videos

---

### Videos Without Comments

**Note**: Videos with **zero comments** are automatically hidden from the list

**Why**: This page focuses on videos with discussion activity

**To see all videos** (including those without comments): Use the Channels List or other views

---

## Keyboard Shortcuts

*(None currently implemented - all interactions are click-based)*

---

## Troubleshooting

### Issue: Page loads slowly

**Solution**:
- Pagination displays 500 videos per page - normal load time is < 2 seconds
- If slower, check your internet connection

### Issue: Search returns no results

**Solution**:
- Check spelling of keywords
- Try searching for part of the name (e.g., "Climate" instead of "Climate Change Documentary")
- Remember: Only videos with comments are shown

### Issue: Sort doesn't seem to work

**Solution**:
- Look for the arrow indicator (▼ or ▲) on the column header
- Click again to toggle sort direction
- Refresh the page if the indicator doesn't update

---

## Tips & Best Practices

1. **Combine Search + Sort**: Search for a topic, then sort by comment count to find the most discussed videos on that topic

2. **Use Date Range Navigation**: Click on Last Comment Time to automatically filter comments to relevant time period

3. **Bookmark Search URLs**: URLs with search/sort parameters can be bookmarked for quick access:
   ```
   https://your-domain.com/videos?search=climate&sort=actual_comment_count&direction=desc
   ```

4. **Start Broad, Then Narrow**: Start with no filters to see all videos, then use search to narrow down

---

## Related Features

- **Comments List**: View and analyze individual comments
- **Channels List**: View channel-level statistics
- **Import**: Add new videos and comments to the system

---

## Need Help?

If you encounter issues not covered in this guide, contact your system administrator or refer to the [full documentation](./spec.md).
