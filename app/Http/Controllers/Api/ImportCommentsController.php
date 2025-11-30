<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\YoutubeApiClient;
use App\Services\CommentImportService;
use App\Services\ChannelTagManager;
use App\Services\UrtubeApiMetadataService;
use App\Services\ApiQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

// ===============================
// == Y-API (AUTHORITATIVE) ==
// == YouTube Official API ==
// == DO NOT USE U-API HERE ==
// ===============================
//
// SYSTEM ARCHITECTURE:
// 本系統共有 2 種 API 導入方式：
// 1. Y-API = YouTube 官方 API (此文件)
// 2. U-API = 第三方 urtubeapi，只取得 YouTube 留言的 JSON
//
// 此控制器僅處理 Y-API (YouTube 官方 API) 相關功能

class ImportCommentsController extends Controller
{
    private YoutubeApiClient $youtubeClient;
    private CommentImportService $importService;
    private ChannelTagManager $tagManager;
    private UrtubeApiMetadataService $metadataService;
    private ApiQuotaService $quotaService;

    public function __construct(
        YoutubeApiClient $youtubeClient,
        CommentImportService $importService,
        ChannelTagManager $tagManager,
        UrtubeApiMetadataService $metadataService,
        ApiQuotaService $quotaService
    ) {
        $this->youtubeClient = $youtubeClient;
        $this->importService = $importService;
        $this->tagManager = $tagManager;
        $this->metadataService = $metadataService;
        $this->quotaService = $quotaService;
    }

    /**
     * Check video and channel existence, return preview data
     * POST /api/comments/check
     *
     * @API: Y
     * @PURPOSE: Check video/channel existence and validate comment availability for Y-API import
     */
    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_url' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVALID_REQUEST',
                'message' => '請求參數有誤',
                'details' => $validator->errors(),
            ], 400);
        }

        $videoUrl = $request->input('video_url');

        try {
            // Extract video ID from URL
            $videoId = $this->youtubeClient->extractVideoId($videoUrl);

            if (!$videoId) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'INVALID_URL',
                    'message' => '無法解析的 YouTube URL',
                    'details' => '支援格式：https://youtu.be/{video_id} 或 https://www.youtube.com/watch?v={video_id}',
                ], 400);
            }

            Log::info('Checking video existence', ['video_id' => $videoId]);

            // Check if video already exists in database
            if ($this->importService->checkVideoExists($videoId)) {
                return response()->json([
                    'status' => 'video_exists',
                    'message' => '影片已建檔，請利用更新功能導入留言',
                ], 200);
            }

            // Get video metadata from YouTube API
            $videoMetadata = $this->youtubeClient->getVideoMetadata($videoId);

            if (!$videoMetadata) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'VIDEO_NOT_FOUND',
                    'message' => '找不到該影片',
                    'details' => '該影片可能已被刪除、設為私密，或不允許評論。',
                ], 404);
            }

            // Scrape metadata from YouTube page to get correct published_at
            // YouTube API's publishedAt is sometimes incorrect, so we use web scraping as the authoritative source
            $scrapedMetadata = $this->metadataService->scrapeMetadata($videoId);
            if ($scrapedMetadata['publishedAt']) {
                $videoMetadata['published_at'] = $scrapedMetadata['publishedAt'];
                Log::info('Using scraped published_at instead of YouTube API', [
                    'video_id' => $videoId,
                    'scraped_time' => $scrapedMetadata['publishedAt'],
                ]);
            }

            // Check if video has comments
            if (!isset($videoMetadata['comment_count']) || $videoMetadata['comment_count'] === 0) {
                return response()->json([
                    'status' => 'no_comments',
                    'message' => '此影片沒有留言',
                    'details' => '該影片目前沒有任何留言可以導入。',
                    'video_title' => $videoMetadata['title'] ?? '',
                    'channel_title' => $videoMetadata['channel_title'] ?? '',
                ], 200);
            }

            // Get preview comments (latest 5)
            $previewComments = $this->youtubeClient->getPreviewComments($videoId, 5);

            // Check if channel exists
            $channelExists = $this->importService->checkChannelExists($videoMetadata['channel_id']);

            if ($channelExists) {
                // Scenario 2: New video + Existing channel
                $channel = \App\Models\Channel::where('channel_id', $videoMetadata['channel_id'])->first();
                $existingTags = $this->tagManager->getChannelTags($channel);

                // Convert preview comments' published_at to Asia/Taipei
                foreach ($previewComments as &$comment) {
                    if (isset($comment['published_at'])) {
                        $comment['published_at'] = \Carbon\Carbon::parse($comment['published_at'])
                            ->setTimezone('Asia/Taipei')
                            ->format('Y-m-d H:i:s');
                    }
                }

                $response = [
                    'status' => 'new_video_existing_channel',
                    'channel_id' => $videoMetadata['channel_id'],
                    'channel_title' => $videoMetadata['channel_title'],
                    'video_title' => $videoMetadata['title'],
                    'video_published_at' => \Carbon\Carbon::parse($videoMetadata['published_at'])
                        ->setTimezone('UTC')
                        ->format('Y-m-d H:i:s') . ' (UTC)',
                    'comment_count_total' => $videoMetadata['comment_count'],
                    'preview_comments' => $previewComments,
                    'existing_channel_tags' => $existingTags,
                ];

                // Add warning if comment count exceeds 2500 for first import
                if ($videoMetadata['comment_count'] > 2500) {
                    $response['import_limit_warning'] = '新影片首次導入最多只會導入最新的 2500 則留言（從新到舊排序），如留言過多，限於 API 限制，無法獲取最早留言。';
                    $response['will_import_count'] = 2500;
                }

                return response()->json($response, 200);
            } else {
                // Scenario 3: New video + New channel
                $availableTags = $this->tagManager->getAllTags();

                // Convert preview comments' published_at to Asia/Taipei
                foreach ($previewComments as &$comment) {
                    if (isset($comment['published_at'])) {
                        $comment['published_at'] = \Carbon\Carbon::parse($comment['published_at'])
                            ->setTimezone('Asia/Taipei')
                            ->format('Y-m-d H:i:s');
                    }
                }

                $response = [
                    'status' => 'new_video_new_channel',
                    'channel_id' => $videoMetadata['channel_id'],
                    'channel_title' => $videoMetadata['channel_title'],
                    'video_title' => $videoMetadata['title'],
                    'video_published_at' => \Carbon\Carbon::parse($videoMetadata['published_at'])
                        ->setTimezone('UTC')
                        ->format('Y-m-d H:i:s') . ' (UTC)',
                    'comment_count_total' => $videoMetadata['comment_count'],
                    'preview_comments' => $previewComments,
                    'available_tags' => $availableTags,
                ];

                // Add warning if comment count exceeds 2500 for first import
                if ($videoMetadata['comment_count'] > 2500) {
                    $response['import_limit_warning'] = '新影片首次導入最多只會導入最新的 2500 則留言（從新到舊排序），如留言過多，限於 API 限制，無法獲取最早留言。';
                    $response['will_import_count'] = 2500;
                }

                return response()->json($response, 200);
            }
        } catch (\Google\Service\Exception $e) {
            Log::error('YouTube API error during check', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $canRetry = in_array($e->getCode(), [429, 500, 502, 503]);

            return response()->json([
                'status' => 'error',
                'code' => 'API_ERROR',
                'message' => 'YouTube API 請求失敗',
                'details' => $canRetry ? '配額已用盡或網路超時。請稍候後重試。' : $e->getMessage(),
                'can_retry' => $canRetry,
                'retry_after_seconds' => $canRetry ? 60 : null,
            ], $e->getCode() === 429 ? 429 : 502);
        } catch (\Exception $e) {
            Log::error('Error during video check', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'INTERNAL_ERROR',
                'message' => '系統錯誤',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import comments for a video
     * POST /api/comments/import
     *
     * @API: Y
     * @PURPOSE: Execute full comment import using Y-API (YouTube Official API)
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_url' => 'required|string',
            'scenario' => 'required|in:new_video_existing_channel,new_video_new_channel',
            'channel_tags' => 'array',
            'channel_tags.*' => 'integer|exists:tags,tag_id',
            'import_replies' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVALID_REQUEST',
                'message' => '請求參數有誤',
                'details' => $validator->errors(),
            ], 400);
        }

        $videoUrl = $request->input('video_url');
        $scenario = $request->input('scenario');
        $channelTags = $request->input('channel_tags', []);
        $importReplies = $request->input('import_replies', true);

        // Validate tag requirements
        if ($scenario === 'new_video_new_channel') {
            if (empty($channelTags)) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'TAG_VALIDATION_FAILED',
                    'message' => '標籤驗證失敗',
                    'details' => '新頻道必須至少選擇一個標籤',
                ], 422);
            }
        }

        try {
            Log::info('Starting comment import', [
                'video_url' => $videoUrl,
                'scenario' => $scenario,
                'channel_tags' => $channelTags,
            ]);

            $result = $this->importService->performFullImport(
                $videoUrl,
                $scenario,
                $channelTags,
                $importReplies
            );

            // Increment API quota usage after successful import (Y-API)
            $user = auth()->user();
            if ($user) {
                $this->quotaService->incrementUsage($user);
                Log::info('Y-API quota incremented after successful import', [
                    'user_id' => $user->id,
                    'video_url' => $videoUrl,
                ]);
            }

            return response()->json($result, 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVALID_URL',
                'message' => '無法解析的 YouTube URL',
            ], 400);
        } catch (\Google\Service\Exception $e) {
            Log::error('YouTube API error during import', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $canRetry = in_array($e->getCode(), [429, 500, 502, 503]);

            return response()->json([
                'status' => 'error',
                'code' => 'API_ERROR',
                'message' => 'YouTube API 請求失敗',
                'details' => '無法取得完整留言清單。',
                'can_retry' => $canRetry,
                'retry_after_seconds' => $canRetry ? 60 : null,
            ], 502);
        } catch (\Exception $e) {
            Log::error('Import failed - transaction rolled back', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'IMPORT_FAILED',
                'message' => '導入失敗，未做任何資料庫變更',
                'details' => '資料庫事務回滾成功。請檢查網路連線或稍候後重試。',
                'can_retry' => true,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ], 500);
        }
    }
}
