<?php

namespace App\Services;

use App\Models\Video;
use App\Models\Comment;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Video Management Service (012-admin-video-management)
 *
 * Handles business logic for admin video operations:
 * - List videos with pagination, search, and sorting
 * - Get single video details
 * - Update video metadata
 * - Delete video with cascade
 * - Batch delete videos
 */
class VideoManagementService
{
    /**
     * T014 [US1]: List videos with pagination, search, and sorting
     *
     * @param int $perPage
     * @param int $page
     * @param string|null $search
     * @param string $sortBy
     * @param string $sortDir
     * @return array
     */
    public function listVideos(
        int $perPage = 20,
        int $page = 1,
        ?string $search = null,
        string $sortBy = 'created_at',
        string $sortDir = 'desc'
    ): array {
        // Build query with comment stats and channel eager loading (T006)
        $query = Video::query()
            ->forAdminList();

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhereHas('channel', function ($channelQuery) use ($search) {
                      $channelQuery->where('channel_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Validate and apply sorting
        $allowedSortColumns = ['title', 'channel_name', 'published_at', 'actual_comment_count', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        // Handle channel_name sorting via join
        if ($sortBy === 'channel_name') {
            $query->join('channels', 'videos.channel_id', '=', 'channels.channel_id')
                  ->orderBy('channels.channel_name', $sortDir)
                  ->select('videos.*');
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        // Paginate
        $videos = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform response
        return [
            'data' => $videos->map(function ($video) {
                return $this->transformVideoForList($video);
            }),
            'meta' => [
                'current_page' => $videos->currentPage(),
                'last_page' => $videos->lastPage(),
                'per_page' => $videos->perPage(),
                'total' => $videos->total(),
            ],
        ];
    }

    /**
     * T029 [US2]: Get single video details
     *
     * @param string $videoId
     * @return array|null
     */
    public function getVideo(string $videoId): ?array
    {
        $video = Video::query()
            ->forAdminList()
            ->where('video_id', $videoId)
            ->first();

        if (!$video) {
            return null;
        }

        return $this->transformVideoForDetail($video);
    }

    /**
     * T030 [US2]: Update video metadata with audit logging
     *
     * @param string $videoId
     * @param array $data
     * @param int $adminId
     * @return array|null
     */
    public function updateVideo(string $videoId, array $data, int $adminId): ?array
    {
        $video = Video::find($videoId);

        if (!$video) {
            return null;
        }

        // Capture old values for audit log
        $oldValues = [
            'title' => $video->title,
            'published_at' => $video->published_at?->toIso8601String(),
        ];

        // Update video
        $video->title = $data['title'];
        $video->published_at = $data['published_at'];
        $video->save();

        // Log the update (FR-018)
        AuditLog::log(
            actionType: 'video_edit',
            description: sprintf(
                '管理員編輯了影片 "%s" (%s) 的資料',
                $video->title,
                $video->video_id
            ),
            userId: null,
            adminId: $adminId,
            resourceType: 'video',
            resourceId: null, // video_id is string, store in changes
            changes: [
                'video_id' => $video->video_id,
                'old' => $oldValues,
                'new' => [
                    'title' => $video->title,
                    'published_at' => $video->published_at?->toIso8601String(),
                ],
            ]
        );

        // Reload with relationships and return
        $video = Video::query()
            ->forAdminList()
            ->where('video_id', $videoId)
            ->first();

        return $this->transformVideoForDetail($video);
    }

    /**
     * T043 [US3]: Get comment count for a video
     *
     * @param string $videoId
     * @return int|null
     */
    public function getCommentCount(string $videoId): ?int
    {
        $video = Video::find($videoId);

        if (!$video) {
            return null;
        }

        return Comment::where('video_id', $videoId)->count();
    }

    /**
     * T044 [US3]: Delete video with cascade and audit logging
     *
     * @param string $videoId
     * @param int $adminId
     * @return array|null
     */
    public function deleteVideo(string $videoId, int $adminId): ?array
    {
        $video = Video::find($videoId);

        if (!$video) {
            return null;
        }

        // Count comments before deletion (for response and audit log)
        $commentCount = Comment::where('video_id', $videoId)->count();

        // Capture video info for audit log
        $videoInfo = [
            'video_id' => $video->video_id,
            'title' => $video->title,
            'channel_id' => $video->channel_id,
            'comment_count' => $commentCount,
        ];

        // Delete video (CASCADE will delete comments via database foreign key)
        $video->delete();

        // Log the deletion (FR-018)
        AuditLog::log(
            actionType: 'video_delete',
            description: sprintf(
                '管理員刪除了影片 "%s" (%s) 及其 %d 則留言',
                $videoInfo['title'],
                $videoInfo['video_id'],
                $commentCount
            ),
            userId: null,
            adminId: $adminId,
            resourceType: 'video',
            resourceId: null,
            changes: $videoInfo
        );

        return [
            'deleted_comments' => $commentCount,
        ];
    }

    /**
     * T055 [US4]: Batch delete videos with transaction and audit logging
     *
     * @param array $videoIds
     * @param int $adminId
     * @return array
     */
    public function batchDeleteVideos(array $videoIds, int $adminId): array
    {
        $deletedVideos = 0;
        $deletedComments = 0;
        $videoInfos = [];

        return DB::transaction(function () use ($videoIds, $adminId, &$deletedVideos, &$deletedComments, &$videoInfos) {
            // Gather info and counts before deletion
            $videos = Video::whereIn('video_id', $videoIds)->get();

            foreach ($videos as $video) {
                $commentCount = Comment::where('video_id', $video->video_id)->count();
                $deletedComments += $commentCount;

                $videoInfos[] = [
                    'video_id' => $video->video_id,
                    'title' => $video->title,
                    'comment_count' => $commentCount,
                ];
            }

            $deletedVideos = $videos->count();

            // Delete videos (CASCADE handles comments)
            Video::whereIn('video_id', $videoIds)->delete();

            // Log the batch deletion (FR-018)
            AuditLog::log(
                actionType: 'video_batch_delete',
                description: sprintf(
                    '管理員批次刪除了 %d 部影片及 %d 則留言',
                    $deletedVideos,
                    $deletedComments
                ),
                userId: null,
                adminId: $adminId,
                resourceType: 'video',
                resourceId: null,
                changes: [
                    'video_ids' => $videoIds,
                    'total_videos' => $deletedVideos,
                    'total_comments' => $deletedComments,
                    'videos' => $videoInfos,
                ]
            );

            return [
                'deleted_videos' => $deletedVideos,
                'deleted_comments' => $deletedComments,
            ];
        });
    }

    /**
     * Transform video model for list response
     *
     * @param Video $video
     * @return array
     */
    private function transformVideoForList(Video $video): array
    {
        return [
            'video_id' => $video->video_id,
            'title' => $video->title,
            'channel_id' => $video->channel_id,
            'channel_name' => $video->channel?->channel_name ?? '未知頻道',
            'published_at' => $video->published_at?->toIso8601String(),
            'actual_comment_count' => (int) ($video->actual_comment_count ?? 0),
            'created_at' => $video->created_at?->toIso8601String(),
        ];
    }

    /**
     * Transform video model for detail response (includes updated_at)
     *
     * @param Video $video
     * @return array
     */
    private function transformVideoForDetail(Video $video): array
    {
        $data = $this->transformVideoForList($video);
        $data['updated_at'] = $video->updated_at?->toIso8601String();
        return $data;
    }
}
