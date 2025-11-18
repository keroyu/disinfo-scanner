<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VideoIncrementalUpdateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class VideoUpdateController extends Controller
{
    public function __construct(
        private VideoIncrementalUpdateService $updateService
    ) {}

    /**
     * Preview new comments for a video
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_id' => 'required|string|max:11|exists:videos,video_id',
            'video_title' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->updateService->getPreview($validated['video_id']);

            if ($result['new_comment_count'] === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'No new comments found',
                    'data' => $result,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Video preview error', [
                'video_id' => $validated['video_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute incremental import of new comments
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_id' => 'required|string|max:11|exists:videos,video_id',
        ]);

        try {
            $result = $this->updateService->executeImport($validated['video_id']);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Video import error', [
                'video_id' => $validated['video_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
