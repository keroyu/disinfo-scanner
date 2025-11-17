<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\YoutubeApiClient;
use App\Services\CommentImportService;
use App\Services\ChannelTagManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ImportCommentsController extends Controller
{
    private YoutubeApiClient $youtubeClient;
    private CommentImportService $importService;
    private ChannelTagManager $tagManager;

    public function __construct(
        YoutubeApiClient $youtubeClient,
        CommentImportService $importService,
        ChannelTagManager $tagManager
    ) {
        $this->youtubeClient = $youtubeClient;
        $this->importService = $importService;
        $this->tagManager = $tagManager;
    }

    /**
     * Check video and channel existence, return preview data
     * POST /api/comments/check
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

            // Get preview comments (latest 5)
            $previewComments = $this->youtubeClient->getPreviewComments($videoId, 5);

            // Check if channel exists
            $channelExists = $this->importService->checkChannelExists($videoMetadata['channel_id']);

            if ($channelExists) {
                // Scenario 2: New video + Existing channel
                $channel = \App\Models\Channel::where('channel_id', $videoMetadata['channel_id'])->first();
                $existingTags = $this->tagManager->getChannelTags($channel);

                return response()->json([
                    'status' => 'new_video_existing_channel',
                    'channel_id' => $videoMetadata['channel_id'],
                    'channel_title' => $videoMetadata['channel_title'],
                    'video_title' => $videoMetadata['title'],
                    'video_published_at' => $videoMetadata['published_at'],
                    'comment_count_total' => $videoMetadata['comment_count'],
                    'preview_comments' => $previewComments,
                    'existing_channel_tags' => $existingTags,
                ], 200);
            } else {
                // Scenario 3: New video + New channel
                $availableTags = $this->tagManager->getAllTags();

                return response()->json([
                    'status' => 'new_video_new_channel',
                    'channel_id' => $videoMetadata['channel_id'],
                    'channel_title' => $videoMetadata['channel_title'],
                    'video_title' => $videoMetadata['title'],
                    'video_published_at' => $videoMetadata['published_at'],
                    'comment_count_total' => $videoMetadata['comment_count'],
                    'preview_comments' => $previewComments,
                    'available_tags' => $availableTags,
                ], 200);
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
