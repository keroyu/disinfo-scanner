<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentLog;
use App\Services\PortalyWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalyWebhookController extends Controller
{
    protected PortalyWebhookService $webhookService;

    public function __construct(PortalyWebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle incoming Portaly webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Portaly-Signature', '');
        $payload = $request->all();

        // Get raw content for signature verification
        $rawContent = $request->getContent();
        $rawPayload = json_decode($rawContent, true);
        $rawDataJson = isset($rawPayload['data']) ? json_encode($rawPayload['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $result = $this->webhookService->process($payload, $signature, $rawDataJson);

        // Map status to HTTP response code
        $httpCode = $this->getHttpCode($result['status'], $result['success']);

        return response()->json([
            'success' => $result['success'],
            'status' => $result['status'],
            'message' => $result['message'],
            'trace_id' => $result['trace_id'],
        ] + ($result['success'] ? [] : ['error' => $result['message']]), $httpCode);
    }

    protected function getHttpCode(string $status, bool $success): int
    {
        return match ($status) {
            PaymentLog::STATUS_SIGNATURE_INVALID => 401,
            PaymentLog::STATUS_SETTINGS_NOT_CONFIGURED => 503,
            default => 200,
        };
    }
}
