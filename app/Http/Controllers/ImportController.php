<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ImportService;

class ImportController extends Controller
{
    protected $importService;

    public function __construct(ImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * POST /api/import - Start import process
     */
    public function store(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
        ]);

        try {
            $result = $this->importService->import($request->url);

            if ($result->requires_tags) {
                // New channel detected, need tagging
                return response()->json([
                    'success' => true,
                    'message' => '檢測到新頻道，請先設定標籤',
                    'data' => [
                        'import_id' => $result->import_id,
                        'channel_id' => $result->channel_id,
                        'channel_name' => $result->channel_name,
                        'requires_tags' => true,
                    ]
                ], 202);
            }

            // Existing channel, import completed
            return response()->json([
                'success' => true,
                'message' => '成功匯入',
                'data' => [
                    'stats' => [
                        'newly_added' => $result->newly_added,
                        'updated' => $result->updated,
                        'skipped' => $result->skipped,
                        'total_processed' => $result->total_processed,
                    ],
                    'new_channel' => false,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'import_error',
                'trace_id' => $e->getMessage() === '無法訪問 YouTube，請檢查網路連線或稍後再試' ? null : null,
            ], match (true) {
                str_contains($e->getMessage(), '請輸入') => 400,
                str_contains($e->getMessage(), '網址') => 400,
                str_contains($e->getMessage(), '無法') => 502,
                default => 500,
            });
        }
    }
}
