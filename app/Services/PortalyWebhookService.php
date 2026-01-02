<?php

namespace App\Services;

use App\Models\PaymentLog;
use App\Models\PaymentProduct;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PortalyWebhookService
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Process a Portaly webhook request.
     *
     * @param array $payload Parsed webhook payload
     * @param string $signature X-Portaly-Signature header value
     * @param string|null $rawDataJson Raw JSON string of the data object for signature verification
     * @return array{success: bool, status: string, message: string, trace_id: string}
     */
    public function process(array $payload, string $signature, ?string $rawDataJson = null): array
    {
        $traceId = (string) Str::uuid();

        Log::info('Portaly webhook received', [
            'trace_id' => $traceId,
            'event' => $payload['event'] ?? 'unknown',
            'order_id' => $payload['data']['id'] ?? 'unknown',
        ]);

        // Check if webhook secret is configured
        $encryptedSecret = Setting::getValue('portaly_webhook_secret');
        if (!$encryptedSecret) {
            Log::error('Payment settings not configured', ['trace_id' => $traceId]);
            $this->logWebhook($payload, $traceId, PaymentLog::STATUS_SETTINGS_NOT_CONFIGURED);

            return [
                'success' => false,
                'status' => PaymentLog::STATUS_SETTINGS_NOT_CONFIGURED,
                'message' => 'Payment settings not configured',
                'trace_id' => $traceId,
            ];
        }

        // Verify signature
        // If rawDataJson is provided, use it directly for verification (more accurate)
        // Otherwise, re-encode the parsed data (for testing compatibility)
        if (!$this->verifySignature($payload['data'] ?? [], $signature, $encryptedSecret, $rawDataJson)) {
            Log::warning('Invalid webhook signature', ['trace_id' => $traceId]);
            $this->logWebhook($payload, $traceId, PaymentLog::STATUS_SIGNATURE_INVALID);

            return [
                'success' => false,
                'status' => PaymentLog::STATUS_SIGNATURE_INVALID,
                'message' => 'Invalid signature',
                'trace_id' => $traceId,
            ];
        }

        $data = $payload['data'];
        $orderId = $data['id'];
        $eventType = $payload['event'] ?? 'paid';

        // Check for duplicate (idempotency)
        if (PaymentLog::orderExists($orderId)) {
            Log::info('Duplicate webhook received', [
                'trace_id' => $traceId,
                'order_id' => $orderId,
            ]);

            return [
                'success' => true,
                'status' => PaymentLog::STATUS_DUPLICATE,
                'message' => 'Webhook already processed',
                'trace_id' => $traceId,
            ];
        }

        // Handle refund event
        if ($eventType === 'refund') {
            $this->logWebhook($payload, $traceId, PaymentLog::STATUS_REFUND);

            return [
                'success' => true,
                'status' => PaymentLog::STATUS_REFUND,
                'message' => 'Refund logged',
                'trace_id' => $traceId,
            ];
        }

        // Find product
        $portalyProductId = $data['productId'] ?? null;
        $product = $portalyProductId ? PaymentProduct::findByPortalyId($portalyProductId) : null;

        if (!$product) {
            Log::warning('Product not found', [
                'trace_id' => $traceId,
                'portaly_product_id' => $portalyProductId,
            ]);
            $this->logWebhook($payload, $traceId, PaymentLog::STATUS_PRODUCT_NOT_FOUND);

            return [
                'success' => true,
                'status' => PaymentLog::STATUS_PRODUCT_NOT_FOUND,
                'message' => 'Product not found',
                'trace_id' => $traceId,
            ];
        }

        if (!$product->isActive()) {
            Log::warning('Product is inactive', [
                'trace_id' => $traceId,
                'product_id' => $product->id,
            ]);
            $this->logWebhook($payload, $traceId, PaymentLog::STATUS_PRODUCT_INACTIVE, $product->id);

            return [
                'success' => true,
                'status' => PaymentLog::STATUS_PRODUCT_INACTIVE,
                'message' => 'Product is inactive',
                'trace_id' => $traceId,
            ];
        }

        // Find user by email (case-insensitive)
        $customerEmail = $data['customerData']['email'] ?? null;
        $user = $customerEmail ? User::whereRaw('LOWER(email) = ?', [strtolower($customerEmail)])->first() : null;

        if (!$user) {
            Log::warning('User not found', [
                'trace_id' => $traceId,
                'customer_email' => $customerEmail,
            ]);
            $this->logWebhook($payload, $traceId, PaymentLog::STATUS_USER_NOT_FOUND, $product->id);

            return [
                'success' => true,
                'status' => PaymentLog::STATUS_USER_NOT_FOUND,
                'message' => 'User not found',
                'trace_id' => $traceId,
            ];
        }

        // Execute product action
        $this->executeProductAction($product, $user, $traceId);

        // Log successful payment
        $this->logWebhook($payload, $traceId, PaymentLog::STATUS_SUCCESS, $product->id, $user->id);

        Log::info('Payment processed successfully', [
            'trace_id' => $traceId,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $orderId,
        ]);

        return [
            'success' => true,
            'status' => PaymentLog::STATUS_SUCCESS,
            'message' => 'Webhook processed',
            'trace_id' => $traceId,
        ];
    }

    /**
     * Verify the webhook signature using HMAC-SHA256.
     *
     * @param array $data Parsed data array (fallback for signature calculation)
     * @param string $signature Received signature from X-Portaly-Signature header
     * @param string $encryptedSecret Encrypted webhook secret from settings
     * @param string|null $rawDataJson Original raw JSON string of data (for accurate verification)
     */
    public function verifySignature(array $data, string $signature, string $encryptedSecret, ?string $rawDataJson = null): bool
    {
        try {
            $secret = Crypt::decryptString($encryptedSecret);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt webhook secret', ['error' => $e->getMessage()]);

            return false;
        }

        // Use raw JSON if provided, otherwise re-encode the parsed data
        $dataJson = $rawDataJson ?? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $calculatedSignature = hash_hmac('sha256', $dataJson, $secret);

        return hash_equals($calculatedSignature, $signature);
    }

    /**
     * Execute the product's defined action.
     */
    protected function executeProductAction(PaymentProduct $product, User $user, string $traceId): void
    {
        switch ($product->action_type) {
            case 'extend_premium':
                if ($product->duration_days) {
                    $this->paymentService->extendPremium($user, $product->duration_days, $traceId);
                }
                break;

            // Future action types can be added here
            default:
                Log::warning('Unknown action type', [
                    'trace_id' => $traceId,
                    'action_type' => $product->action_type,
                ]);
        }
    }

    /**
     * Log the webhook event to payment_logs table.
     */
    protected function logWebhook(
        array $payload,
        string $traceId,
        string $status,
        ?int $productId = null,
        ?int $userId = null
    ): void {
        $data = $payload['data'] ?? [];

        try {
            PaymentLog::create([
                'order_id' => $data['id'] ?? 'unknown-' . $traceId,
                'event_type' => $payload['event'] ?? 'paid',
                'product_id' => $productId,
                'portaly_product_id' => $data['productId'] ?? null,
                'customer_email' => $data['customerData']['email'] ?? 'unknown',
                'customer_name' => $data['customerData']['name'] ?? null,
                'user_id' => $userId,
                'amount' => $data['amount'] ?? 0,
                'currency' => $data['currency'] ?? 'TWD',
                'net_total' => $data['netTotal'] ?? null,
                'payment_method' => $data['paymentMethod'] ?? null,
                'status' => $status,
                'raw_payload' => $payload,
                'trace_id' => $traceId,
                'processed_at' => $status === PaymentLog::STATUS_SUCCESS ? now() : null,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Handle duplicate order_id (race condition)
            if ($e->getCode() === '23000') {
                Log::info('Duplicate order detected during insert', [
                    'trace_id' => $traceId,
                    'order_id' => $data['id'] ?? 'unknown',
                ]);
            } else {
                throw $e;
            }
        }
    }
}
