<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UrtubeApiImportService;
use Illuminate\Support\Facades\Log;

class UrtubeApiConfirmationController extends Controller
{
    protected $importService;

    public function __construct(UrtubeApiImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * POST /api/import/confirm - Confirm and execute import
     * Writes all data atomically to database
     *
     * @param Request $request {import_id, tags}
     * @return \Illuminate\Http\JsonResponse 200 on success, 422 on validation error
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'import_id' => 'required|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
        ]);

        try {
            // Call confirmImport with import_id and tags
            $result = $this->importService->confirmImport(
                $request->import_id,
                $request->tags
            );

            // Return 200 OK with statistics
            return response()->json([
                'success' => true,
                'message' => '成功匯入',
                'data' => [
                    'stats' => [
                        'newly_added' => $result->newly_added,
                        'updated' => $result->updated,
                        'skipped' => $result->skipped,
                        'total_processed' => $result->total_processed,
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            // Return 422 Unprocessable Entity with error message
            $errorCode = 'import_error';
            $statusCode = 422;

            // Map specific error messages
            if (str_contains($e->getMessage(), '至少選擇')) {
                $errorCode = 'validation_error';
            } elseif (str_contains($e->getMessage(), '過期')) {
                $errorCode = 'import_expired';
            }

            Log::error('Import confirmation error', [
                'import_id' => $request->import_id,
                'error' => $e->getMessage(),
                'status' => $statusCode,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $errorCode,
            ], $statusCode);
        }
    }

    /**
     * POST /api/import/cancel - Cancel import without writing to database
     * Clears cached import data
     *
     * @param Request $request {import_id}
     * @return \Illuminate\Http\JsonResponse 200 on success
     */
    public function cancel(Request $request)
    {
        $request->validate([
            'import_id' => 'required|string',
        ]);

        try {
            // This is handled by ChannelTaggingService
            // We need access to it through ImportService (we'll create a helper)
            $reflection = new \ReflectionProperty($this->importService, 'channelTaggingService');
            $reflection->setAccessible(true);
            $channelTaggingService = $reflection->getValue($this->importService);

            // Clear the pending import from cache
            $channelTaggingService->clearPendingImport($request->import_id);

            Log::info('Import cancelled', [
                'import_id' => $request->import_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '已取消匯入',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Import cancellation error', [
                'import_id' => $request->import_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'cancel_error',
            ], 500);
        }
    }
}
