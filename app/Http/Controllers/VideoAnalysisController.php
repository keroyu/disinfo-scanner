<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\CommentDensityAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class VideoAnalysisController extends Controller
{
    protected CommentDensityAnalysisService $commentDensityService;

    public function __construct(CommentDensityAnalysisService $service)
    {
        $this->commentDensityService = $service;
    }

    /**
     * Show the analysis page for a specific video
     */
    public function showAnalysisPage(string $videoId): View
    {
        $video = Video::with('channel')->findOrFail($videoId);

        $breadcrumbs = [
            ['label' => '首頁', 'url' => url('/')],
            ['label' => '影片列表', 'url' => route('videos.index')],
            ['label' => '影片分析']
        ];

        return view('videos.analysis', compact('video', 'breadcrumbs'));
    }

    /**
     * API endpoint: Get comment density data for chart rendering
     */
    public function getCommentDensityData(string $videoId): JsonResponse
    {
        try {
            // Validate video exists
            $video = Video::findOrFail($videoId);

            // Call service to get both datasets (hourly + daily)
            $densityData = $this->commentDensityService->getCommentDensityData(
                $videoId,
                $video->published_at
            );

            // Add video context to response
            $response = [
                'video_id' => $video->video_id,
                'video_title' => $video->title,
                'video_published_at' => $video->published_at->setTimezone('Asia/Taipei')->toIso8601String(),
                'hourly_data' => $densityData['hourly_data'],
                'daily_data' => $densityData['daily_data'],
                'metadata' => $densityData['metadata']
            ];

            return response()->json($response);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => [
                    'type' => 'NotFound',
                    'message' => 'Video not found',
                    'details' => [
                        'video_id' => $videoId
                    ]
                ]
            ], 404);

        } catch (\Illuminate\Database\QueryException $e) {
            $traceId = \Illuminate\Support\Str::uuid()->toString();

            Log::error('Database query failed in comment density API', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'video_id' => $videoId
            ]);

            return response()->json([
                'error' => [
                    'type' => 'DatabaseQueryException',
                    'message' => 'Database query failed: ' . $e->getMessage(),
                    'details' => [
                        'trace_id' => $traceId,
                        'sql' => $e->getSql(),
                        'timestamp' => now('Asia/Taipei')->toIso8601String()
                    ]
                ]
            ], 500);

        } catch (\Exception $e) {
            $traceId = \Illuminate\Support\Str::uuid()->toString();

            Log::error('Unexpected error in comment density API', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
                'video_id' => $videoId
            ]);

            return response()->json([
                'error' => [
                    'type' => 'InternalServerError',
                    'message' => 'An unexpected error occurred: ' . $e->getMessage(),
                    'details' => [
                        'trace_id' => $traceId,
                        'timestamp' => now('Asia/Taipei')->toIso8601String()
                    ]
                ]
            ], 500);
        }
    }
}
