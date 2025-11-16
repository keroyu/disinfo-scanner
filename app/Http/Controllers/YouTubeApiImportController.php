<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\YouTubeApiService;
use App\Services\CommentImportService;
use App\Models\Video;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Author;
use App\Exceptions\YouTubeApiException;
use App\Exceptions\InvalidVideoIdException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class YouTubeApiImportController extends Controller
{
    private YouTubeApiService $youtubeApiService;
    private CommentImportService $commentImportService;

    public function __construct(YouTubeApiService $youtubeApiService, CommentImportService $commentImportService)
    {
        $this->youtubeApiService = $youtubeApiService;
        $this->commentImportService = $commentImportService;
    }

    /**
     * GET /api/youtube-import/show-form
     * Display the import form
     */
    public function showForm(): \Illuminate\View\View
    {
        return view('comments.import-modal');
    }

    /**
     * POST /api/youtube-import/metadata
     * Fetch and return metadata for a new video
     * Returns title, channel name, and action required
     */
    public function getMetadata(Request $request): JsonResponse
    {
        $request->validate([
            'video_url' => 'required|string',
        ]);

        $videoUrl = $request->input('video_url');
        $videoId = null;

        try {
            // Extract video ID from URL
            $videoId = $this->extractVideoId($videoUrl);

            // Check if video already exists
            $video = Video::where('video_id', $videoId)->first();

            if ($video) {
                // Video exists - proceed to preview
                return response()->json([
                    'success' => true,
                    'status' => 'existing_video',
                    'data' => [
                        'video_id' => $videoId,
                        'next_action' => 'show_preview',
                    ],
                ]);
            }

            // New video - fetch metadata
            $result = $this->commentImportService->importNewVideo($videoId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 500);
            }

            return response()->json($result);
        } catch (InvalidVideoIdException $e) {
            Log::warning('Invalid video URL', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => '無效的YouTube URL格式',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error getting metadata', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => '無法獲取視頻元數據: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/youtube-import/preview
     * Fetch preview comments (5 samples) without persisting to DB
     */
    public function getPreview(Request $request): JsonResponse
    {
        $request->validate([
            'video_url' => 'required|string',
        ]);

        $videoUrl = $request->input('video_url');

        try {
            // Extract video ID from URL
            $videoId = $this->extractVideoId($videoUrl);

            // Fetch preview
            $result = $this->commentImportService->fetchPreview($videoId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 500);
            }

            return response()->json($result);
        } catch (InvalidVideoIdException $e) {
            Log::warning('Invalid video URL in preview', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => '無效的YouTube URL格式',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error fetching preview', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => '無法獲取預覽評論: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/youtube-import/confirm-import
     * Execute full import after user confirms
     * Wraps workflow in database transaction for atomicity
     */
    public function confirmImport(Request $request): JsonResponse
    {
        $request->validate([
            'video_url' => 'required|string',
            'metadata' => 'sometimes|array',
        ]);

        $videoUrl = $request->input('video_url');
        $videoMetadata = $request->input('metadata');

        try {
            // Extract video ID
            $videoId = $this->extractVideoId($videoUrl);

            // Execute full import with transaction
            $result = $this->commentImportService->executeFullImport(
                $videoId,
                $videoMetadata
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'status' => $result['status'],
                    'error' => $result['error'],
                ], 500);
            }

            return response()->json($result);
        } catch (InvalidVideoIdException $e) {
            Log::warning('Invalid video URL in import', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => '無效的YouTube URL格式',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error during import confirmation', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => '導入評論失敗: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract video ID from YouTube URL
     */
    private function extractVideoId(string $url): string
    {
        // Match various YouTube URL formats
        $patterns = [
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/^([a-zA-Z0-9_-]{11})$/', // Direct video ID
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        throw new InvalidVideoIdException("Could not extract video ID from URL: {$url}");
    }
}
