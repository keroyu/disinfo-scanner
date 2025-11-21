<?php

namespace App\Http\Controllers\Api\UApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\UrtubeApiImportService;

class ImportController extends Controller
{
    protected $importService;

    public function __construct(UrtubeApiImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * POST /api/import - Prepare import process (metadata scraping, no database write)
     * Always returns HTTP 202 (Accepted) with confirmation data
     */
    public function store(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
        ]);

        try {
            // Use prepareImport instead of import - this is read-only, no tags needed
            $result = $this->importService->prepareImport($request->url);

            // Return 202 Accepted with metadata for confirmation interface
            return response()->json([
                'success' => true,
                'message' => '影片資料已載入，請確認後匯入',
                'data' => [
                    'import_id' => $result->import_id,
                    'video_id' => $result->video_id,
                    'channel_id' => $result->channel_id,
                    'video_title' => $result->video_title,
                    'channel_name' => $result->channel_name,
                    'comment_count' => $result->comment_count,
                    'requires_tags' => $result->requires_tags,
                ]
            ], 202);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'import_error',
            ], match (true) {
                str_contains($e->getMessage(), '請輸入') => 400,
                str_contains($e->getMessage(), '網址') => 400,
                str_contains($e->getMessage(), '無法') => 502,
                default => 500,
            });
        }
    }
}
