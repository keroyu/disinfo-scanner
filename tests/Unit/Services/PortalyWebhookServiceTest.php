<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\PaymentProduct;
use App\Models\PaymentLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PortalyWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

/**
 * T012: Unit tests for PortalyWebhookService signature verification
 */
class PortalyWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PortalyWebhookService $service;
    protected string $webhookSecret = 'test-secret-key-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->service = new PortalyWebhookService(new PaymentService());
    }

    protected function setWebhookSecret(string $secret): void
    {
        Setting::setValue('portaly_webhook_secret', Crypt::encryptString($secret));
    }

    protected function generateSignature(array $data, string $secret): string
    {
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $dataJson, $secret);
    }

    protected function getValidPayload(): array
    {
        return [
            'data' => [
                'id' => 'test-order-' . uniqid(),
                'amount' => 190,
                'netTotal' => 171,
                'currency' => 'TWD',
                'productId' => '07eMToUCpzTcsg8zKSDM',
                'paymentMethod' => 'tappay',
                'createdAt' => '2024-01-31T07:42:32.151Z',
                'customerData' => [
                    'email' => 'user@example.com',
                    'name' => 'User Name',
                    'phone' => '',
                    'customFields' => [],
                ],
            ],
            'event' => 'paid',
            'timestamp' => '2024-01-31T07:42:32.151Z',
        ];
    }

    public function test_verify_signature_with_valid_signature(): void
    {
        $this->setWebhookSecret($this->webhookSecret);
        $encryptedSecret = Setting::getValue('portaly_webhook_secret');

        $data = ['id' => 'test-order-001', 'amount' => 190];
        $signature = $this->generateSignature($data, $this->webhookSecret);

        $result = $this->service->verifySignature($data, $signature, $encryptedSecret);

        $this->assertTrue($result);
    }

    public function test_verify_signature_with_invalid_signature(): void
    {
        $this->setWebhookSecret($this->webhookSecret);
        $encryptedSecret = Setting::getValue('portaly_webhook_secret');

        $data = ['id' => 'test-order-001', 'amount' => 190];
        $invalidSignature = 'invalid-signature';

        $result = $this->service->verifySignature($data, $invalidSignature, $encryptedSecret);

        $this->assertFalse($result);
    }

    public function test_verify_signature_with_wrong_secret(): void
    {
        $this->setWebhookSecret($this->webhookSecret);
        $encryptedSecret = Setting::getValue('portaly_webhook_secret');

        $data = ['id' => 'test-order-001', 'amount' => 190];
        $signatureWithWrongSecret = $this->generateSignature($data, 'wrong-secret');

        $result = $this->service->verifySignature($data, $signatureWithWrongSecret, $encryptedSecret);

        $this->assertFalse($result);
    }

    public function test_process_returns_error_when_secret_not_configured(): void
    {
        $payload = $this->getValidPayload();
        $signature = 'any-signature';

        $result = $this->service->process($payload, $signature);

        $this->assertFalse($result['success']);
        $this->assertEquals(PaymentLog::STATUS_SETTINGS_NOT_CONFIGURED, $result['status']);
    }

    public function test_process_returns_error_for_invalid_signature(): void
    {
        $this->setWebhookSecret($this->webhookSecret);

        $payload = $this->getValidPayload();
        $invalidSignature = 'invalid-signature';

        $result = $this->service->process($payload, $invalidSignature);

        $this->assertFalse($result['success']);
        $this->assertEquals(PaymentLog::STATUS_SIGNATURE_INVALID, $result['status']);
    }

    public function test_process_returns_duplicate_for_same_order_id(): void
    {
        $this->setWebhookSecret($this->webhookSecret);

        // Create a product and user first
        $product = PaymentProduct::factory()->create([
            'portaly_product_id' => '07eMToUCpzTcsg8zKSDM',
            'status' => 'active',
            'duration_days' => 30,
        ]);

        $user = User::factory()->create(['email' => 'user@example.com']);

        $payload = $this->getValidPayload();
        $payload['data']['id'] = 'duplicate-order-id';
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        // First call
        $result1 = $this->service->process($payload, $signature);
        $this->assertEquals(PaymentLog::STATUS_SUCCESS, $result1['status']);

        // Second call with same order ID
        $result2 = $this->service->process($payload, $signature);
        $this->assertTrue($result2['success']);
        $this->assertEquals(PaymentLog::STATUS_DUPLICATE, $result2['status']);
    }

    public function test_process_logs_refund_without_action(): void
    {
        $this->setWebhookSecret($this->webhookSecret);

        $payload = $this->getValidPayload();
        $payload['event'] = 'refund';
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $result = $this->service->process($payload, $signature);

        $this->assertTrue($result['success']);
        $this->assertEquals(PaymentLog::STATUS_REFUND, $result['status']);
    }

    public function test_process_returns_product_not_found_for_unknown_product(): void
    {
        $this->setWebhookSecret($this->webhookSecret);

        $payload = $this->getValidPayload();
        $payload['data']['productId'] = 'unknown-product-id';
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $result = $this->service->process($payload, $signature);

        $this->assertTrue($result['success']);
        $this->assertEquals(PaymentLog::STATUS_PRODUCT_NOT_FOUND, $result['status']);
    }

    public function test_process_returns_product_inactive_for_disabled_product(): void
    {
        $this->setWebhookSecret($this->webhookSecret);

        PaymentProduct::factory()->create([
            'portaly_product_id' => '07eMToUCpzTcsg8zKSDM',
            'status' => 'inactive',
        ]);

        $payload = $this->getValidPayload();
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $result = $this->service->process($payload, $signature);

        $this->assertTrue($result['success']);
        $this->assertEquals(PaymentLog::STATUS_PRODUCT_INACTIVE, $result['status']);
    }

    public function test_process_returns_user_not_found_for_unknown_email(): void
    {
        $this->setWebhookSecret($this->webhookSecret);

        PaymentProduct::factory()->create([
            'portaly_product_id' => '07eMToUCpzTcsg8zKSDM',
            'status' => 'active',
            'duration_days' => 30,
        ]);

        $payload = $this->getValidPayload();
        $payload['data']['customerData']['email'] = 'unknown@example.com';
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $result = $this->service->process($payload, $signature);

        $this->assertTrue($result['success']);
        $this->assertEquals(PaymentLog::STATUS_USER_NOT_FOUND, $result['status']);
    }

    public function test_process_email_matching_is_case_insensitive(): void
    {
        $this->setWebhookSecret($this->webhookSecret);

        PaymentProduct::factory()->create([
            'portaly_product_id' => '07eMToUCpzTcsg8zKSDM',
            'status' => 'active',
            'duration_days' => 30,
        ]);

        User::factory()->create(['email' => 'User@EXAMPLE.com']);

        $payload = $this->getValidPayload();
        $payload['data']['customerData']['email'] = 'user@example.com';
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $result = $this->service->process($payload, $signature);

        $this->assertTrue($result['success']);
        $this->assertEquals(PaymentLog::STATUS_SUCCESS, $result['status']);
    }
}
