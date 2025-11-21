<?php

namespace App\Http\Controllers\Api\UApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\UrtubeApiImportService;
use App\Services\ChannelTaggingService;
use App\Models\Tag;

class TagSelectionController extends Controller
{
    protected $importService;
    protected $channelTaggingService;

    public function __construct(UrtubeApiImportService $importService, ChannelTaggingService $channelTaggingService)
    {
        $this->importService = $importService;
        $this->channelTaggingService = $channelTaggingService;
    }

    /**
     * POST /api/tags/select - Select tags and resume import
     */
    public function store(Request $request)
    {
        $request->validate([
            'import_id' => 'required|string',
            'channel_id' => 'required|string',
            'tags' => 'required|array|min:1',
            'tags.*' => 'string',
        ]);

        try {
            $this->channelTaggingService->selectTagsForChannel(
                $request->import_id,
                $request->channel_id,
                $request->tags
            );

            // Resume import
            $result = $this->importService->resumeImport($request->import_id);

            return response()->json([
                'success' => true,
                'message' => '成功匯入',
                'data' => [
                    'stats' => [
                        'newly_added' => $result->newly_added,
                        'updated' => $result->updated,
                        'skipped' => $result->skipped,
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'validation_error',
            ], 422);
        }
    }

    /**
     * GET /api/tags - Get available tags
     */
    public function index()
    {
        $tags = Tag::all(['tag_id', 'code', 'name', 'color', 'description'])->toArray();

        return response()->json([
            'success' => true,
            'data' => $tags
        ]);
    }
}
