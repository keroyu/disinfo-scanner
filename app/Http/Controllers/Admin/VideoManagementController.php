<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\EscapesLikeQueries;
use App\Http\Requests\Admin\VideoUpdateRequest;
use App\Http\Requests\Admin\BatchDeleteRequest;
use App\Services\VideoManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Admin Video Management Controller (012-admin-video-management)
 *
 * Handles CRUD operations for videos in the admin panel:
 * - List videos with pagination, search, and sorting
 * - View single video details
 * - Update video metadata (title, published_at)
 * - Delete single video with cascade
 * - Batch delete multiple videos
 */
class VideoManagementController extends Controller
{
    use EscapesLikeQueries;

    protected VideoManagementService $videoService;

    public function __construct(VideoManagementService $videoService)
    {
        $this->videoService = $videoService;
    }

    /**
     * T013 [US1]: List all videos with pagination, search, and sorting
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->input('per_page', 20), 100);
        $page = $request->input('page', 1);
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        // Escape search string for LIKE queries (T007)
        $escapedSearch = $search ? $this->escapeLikeString($search) : null;

        $result = $this->videoService->listVideos(
            perPage: $perPage,
            page: $page,
            search: $escapedSearch,
            sortBy: $sortBy,
            sortDir: $sortDir
        );

        return response()->json($result);
    }

    /**
     * T027 [US2]: Get single video details for editing
     *
     * @param string $videoId
     * @return JsonResponse
     */
    public function show(string $videoId): JsonResponse
    {
        $video = $this->videoService->getVideo($videoId);

        if (!$video) {
            return response()->json([
                'message' => '找不到此影片，可能已被刪除'
            ], 404);
        }

        return response()->json([
            'data' => $video
        ]);
    }

    /**
     * T028 [US2]: Update video metadata (title, published_at)
     *
     * @param VideoUpdateRequest $request
     * @param string $videoId
     * @return JsonResponse
     */
    public function update(VideoUpdateRequest $request, string $videoId): JsonResponse
    {
        $result = $this->videoService->updateVideo(
            videoId: $videoId,
            data: $request->validated(),
            adminId: auth()->id()
        );

        if (!$result) {
            return response()->json([
                'message' => '找不到此影片，可能已被刪除'
            ], 404);
        }

        return response()->json([
            'message' => '影片資料已更新',
            'data' => $result
        ]);
    }

    /**
     * T041 [US3]: Get comment count for delete confirmation preview
     *
     * @param string $videoId
     * @return JsonResponse
     */
    public function commentCount(string $videoId): JsonResponse
    {
        $count = $this->videoService->getCommentCount($videoId);

        if ($count === null) {
            return response()->json([
                'message' => '找不到此影片，可能已被刪除'
            ], 404);
        }

        return response()->json([
            'video_id' => $videoId,
            'comment_count' => $count
        ]);
    }

    /**
     * T042 [US3]: Delete single video with cascade
     *
     * @param string $videoId
     * @return JsonResponse
     */
    public function destroy(string $videoId): JsonResponse
    {
        $result = $this->videoService->deleteVideo(
            videoId: $videoId,
            adminId: auth()->id()
        );

        if (!$result) {
            return response()->json([
                'message' => '找不到此影片，可能已被刪除'
            ], 404);
        }

        return response()->json([
            'message' => '影片及相關留言已刪除',
            'deleted_comments' => $result['deleted_comments']
        ]);
    }

    /**
     * T054 [US4]: Batch delete multiple videos
     *
     * @param BatchDeleteRequest $request
     * @return JsonResponse
     */
    public function batchDelete(BatchDeleteRequest $request): JsonResponse
    {
        $videoIds = $request->validated()['video_ids'];

        $result = $this->videoService->batchDeleteVideos(
            videoIds: $videoIds,
            adminId: auth()->id()
        );

        return response()->json([
            'message' => sprintf('已刪除 %d 部影片及相關留言', $result['deleted_videos']),
            'deleted_videos' => $result['deleted_videos'],
            'deleted_comments' => $result['deleted_comments']
        ]);
    }
}
