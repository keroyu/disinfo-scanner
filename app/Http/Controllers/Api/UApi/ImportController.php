<?php

namespace App\Http\Controllers\Api\UApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\UrtubeApiImportService;
use Carbon\Carbon;

class ImportController extends Controller
{
    protected $importService;

    /**
     * Cutoff date for non-admin U-API imports.
     * Non-admin users can only import videos published before this date.
     */
    protected const NON_ADMIN_CUTOFF_DATE = '2025-11-23';

    public function __construct(UrtubeApiImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * POST /api/import - Prepare import process (metadata scraping, no database write)
     * Always returns HTTP 202 (Accepted) with confirmation data
     *
     * Non-admin restriction: Can only import videos published before 2025-11-23
     */
    public function store(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
        ]);

        try {
            // Use prepareImport instead of import - this is read-only, no tags needed
            $result = $this->importService->prepareImport($request->url);

            // Check date restriction for non-admin users
            $user = $request->user();
            $isAdmin = $user->roles()->where('name', 'administrator')->exists();

            if (!$isAdmin) {
                // Use published_at, or fall back to earliest_comment_at
                $dateToCheck = $result->published_at ?? $result->earliest_comment_at ?? null;

                // If we can't determine any date, block non-admin users for safety
                if (empty($dateToCheck)) {
                    throw new \Exception('無法確認影片發布日期，僅管理員可匯入此影片');
                }

                try {
                    $videoDate = Carbon::parse($dateToCheck);
                } catch (\Exception $e) {
                    throw new \Exception('影片日期格式異常，僅管理員可匯入此影片');
                }

                $cutoffDate = Carbon::parse(self::NON_ADMIN_CUTOFF_DATE);

                // Use gt() - videos ON 2025-11-23 are allowed, only AFTER is blocked
                if ($videoDate->gt($cutoffDate)) {
                    throw new \Exception('此影片發布日期超過限制，僅管理員可匯入 2025-11-23 以後的影片');
                }
            }

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
                    'published_at' => $result->published_at,
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
                str_contains($e->getMessage(), '僅管理員') => 403,  // Must be before '無法' to catch '無法...僅管理員'
                str_contains($e->getMessage(), '超過限制') => 403,
                str_contains($e->getMessage(), '無法') => 422,
                default => 422,
            });
        }
    }
}
