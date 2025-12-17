<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller for Official YouTube API Import operations (T425, T430-T434).
 *
 * This controller handles video imports using the YouTube Data API v3.
 * All routes are protected by the CheckApiQuota middleware which ensures
 * only Premium Members and above can access this feature.
 */
class OfficialImportController extends Controller
{
    /**
     * Import video using Official YouTube API.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'video_url' => 'required|string|url',
        ]);

        $user = auth()->user();
        $videoUrl = $request->input('video_url');

        // Extract video ID from URL
        $videoId = $this->extractVideoId($videoUrl);

        if (!$videoId) {
            return response()->json([
                'error' => [
                    'type' => 'ValidationError',
                    'message' => '無效的 YouTube 影片網址',
                    'details' => [
                        'video_url' => $videoUrl,
                    ],
                ],
            ], 422);
        }

        // Check if user has YouTube API key configured
        if (!$user->youtube_api_key) {
            return response()->json([
                'error' => [
                    'type' => 'ConfigurationError',
                    'message' => '請先在設定頁面設定您的 YouTube API 金鑰',
                    'details' => [
                        'required' => 'youtube_api_key',
                        'settings_url' => route('settings.index'),
                    ],
                ],
            ], 400);
        }

        // Log import attempt
        Log::info('Official API import initiated', [
            'user_id' => $user->id,
            'video_id' => $videoId,
            'trace_id' => request()->header('X-Trace-ID', uniqid()),
        ]);

        // TODO: Implement actual YouTube API import logic
        // For now, return a placeholder response

        return response()->json([
            'success' => true,
            'message' => '影片匯入請求已接受',
            'data' => [
                'video_id' => $videoId,
                'status' => 'pending',
                'note' => '完整的 YouTube API 整合將在後續版本實作',
            ],
        ]);
    }

    /**
     * Extract YouTube video ID from URL.
     *
     * @param string $url
     * @return string|null
     */
    protected function extractVideoId(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([A-Za-z0-9_-]{11})/',
            '/youtu\.be\/([A-Za-z0-9_-]{11})/',
            '/youtube\.com\/embed\/([A-Za-z0-9_-]{11})/',
            '/youtube\.com\/v\/([A-Za-z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
