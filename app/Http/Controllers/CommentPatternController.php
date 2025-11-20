<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\CommentPatternService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CommentPatternController extends Controller
{
    protected $commentPatternService;

    public function __construct(CommentPatternService $commentPatternService)
    {
        $this->commentPatternService = $commentPatternService;
    }

    /**
     * Get pattern statistics for a video
     *
     * GET /api/videos/{videoId}/pattern-statistics
     */
    public function getPatternStatistics(string $videoId): JsonResponse
    {
        try {
            // Verify video exists
            $video = Video::find($videoId);
            if (!$video) {
                return response()->json([
                    'error' => [
                        'type' => 'VideoNotFound',
                        'message' => 'Video not found',
                        'details' => ['video_id' => $videoId]
                    ]
                ], 404);
            }

            $statistics = $this->commentPatternService->getPatternStatistics($videoId);

            return response()->json([
                'video_id' => $videoId,
                'patterns' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting pattern statistics', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => [
                    'type' => 'ServerError',
                    'message' => 'Failed to retrieve pattern statistics',
                    'details' => ['error' => $e->getMessage()]
                ]
            ], 500);
        }
    }

    /**
     * Get comments by pattern with pagination
     *
     * GET /api/videos/{videoId}/comments?pattern=repeat&offset=0&limit=100
     */
    public function getCommentsByPattern(Request $request, string $videoId): JsonResponse
    {
        try {
            // Verify video exists
            $video = Video::find($videoId);
            if (!$video) {
                return response()->json([
                    'error' => [
                        'type' => 'VideoNotFound',
                        'message' => 'Video not found',
                        'details' => ['video_id' => $videoId]
                    ]
                ], 404);
            }

            // Validate request parameters
            $validated = $request->validate([
                'pattern' => ['required', Rule::in(['all', 'top_liked', 'repeat', 'night_time', 'aggressive', 'simplified_chinese'])],
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1|max:100'
            ]);

            $pattern = $validated['pattern'];
            $offset = $validated['offset'] ?? 0;
            $limit = $validated['limit'] ?? 100;

            $result = $this->commentPatternService->getCommentsByPattern($videoId, $pattern, $offset, $limit);

            return response()->json([
                'video_id' => $videoId,
                'pattern' => $pattern,
                'offset' => $offset,
                'limit' => $limit,
                'comments' => $result['comments'],
                'has_more' => $result['has_more'],
                'total' => $result['total']
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => [
                    'type' => 'ValidationError',
                    'message' => 'Invalid request parameters',
                    'details' => $e->errors()
                ]
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error getting comments by pattern', [
                'video_id' => $videoId,
                'pattern' => $request->input('pattern'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => [
                    'type' => 'ServerError',
                    'message' => 'Failed to retrieve comments',
                    'details' => ['error' => $e->getMessage()]
                ]
            ], 500);
        }
    }
}
