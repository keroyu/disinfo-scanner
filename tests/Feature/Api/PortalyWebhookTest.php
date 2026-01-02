<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\PaymentProduct;
use App\Models\PaymentLog;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

/**
 * T014: Integration tests for Portaly webhook endpoint
 */
class PortalyWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $webhookSecret = 'test-secret-key-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PaymentProductSeeder::class);
        $this->setWebhookSecret($this->webhookSecret);
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

    protected function getValidPayload(string $email = 'user@example.com'): array
    {
        return [
            'data' => [
                'id' => 'order-' . uniqid(),
                'amount' => 190,
                'netTotal' => 171,
                'currency' => 'TWD',
                'productId' => '07eMToUCpzTcsg8zKSDM',
                'paymentMethod' => 'tappay',
                'createdAt' => '2024-01-31T07:42:32.151Z',
                'customerData' => [
                    'email' => $email,
                    'name' => 'User Name',
                    'phone' => '',
                    'customFields' => [],
                ],
            ],
            'event' => 'paid',
            'timestamp' => '2024-01-31T07:42:32.151Z',
        ];
    }

    public function test_webhook_returns_401_for_missing_signature(): void
    {
        $payload = $this->getValidPayload();

        $response = $this->postJson('/api/webhooks/portaly', $payload);

        $response->assertStatus(401);
        $response->assertJson(['success' => false, 'error' => 'Invalid signature']);
    }

    public function test_webhook_returns_401_for_invalid_signature(): void
    {
        $payload = $this->getValidPayload();

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_returns_503_when_secret_not_configured(): void
    {
        // Remove the webhook secret
        Setting::where('key', 'portaly_webhook_secret')->delete();

        $payload = $this->getValidPayload();
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(503);
        $response->assertJson(['error' => 'Payment settings not configured']);
    }

    public function test_webhook_processes_valid_payment_successfully(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $payload = $this->getValidPayload('user@example.com');
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'success',
        ]);

        $user->refresh();
        $this->assertTrue($user->isPremium());
        $this->assertNotNull($user->premium_expires_at);

        // Verify log created
        $this->assertDatabaseHas('payment_logs', [
            'order_id' => $payload['data']['id'],
            'status' => 'success',
            'user_id' => $user->id,
        ]);
    }

    public function test_webhook_extends_existing_premium_from_expiry_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        $user = User::factory()->create([
            'email' => 'premium@example.com',
            'premium_expires_at' => Carbon::parse('2025-01-15 12:00:00'), // 14 days remaining
        ]);

        $payload = $this->getValidPayload('premium@example.com');
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $user->refresh();
        // Should extend 30 days from Jan 15, not Jan 1
        $expectedExpiry = Carbon::parse('2025-02-14 12:00:00');
        $this->assertTrue($user->premium_expires_at->diffInSeconds($expectedExpiry) < 5);

        Carbon::setTestNow();
    }

    public function test_webhook_handles_duplicate_order_idempotently(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $payload = $this->getValidPayload('user@example.com');
        $payload['data']['id'] = 'duplicate-test-order';
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        // First request
        $response1 = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);
        $response1->assertStatus(200);
        $response1->assertJson(['status' => 'success']);

        // Second request with same order ID
        $response2 = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);
        $response2->assertStatus(200);
        $response2->assertJson(['status' => 'duplicate']);

        // Should only have one log entry
        $this->assertEquals(1, PaymentLog::where('order_id', 'duplicate-test-order')->count());
    }

    public function test_webhook_returns_200_for_unknown_user(): void
    {
        $payload = $this->getValidPayload('unknown@example.com');
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'user_not_found']);
    }

    public function test_webhook_returns_200_for_unknown_product(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $payload = $this->getValidPayload('user@example.com');
        $payload['data']['productId'] = 'unknown-product-id';
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'product_not_found']);
    }

    public function test_webhook_returns_200_for_inactive_product(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        // Disable the product
        PaymentProduct::where('portaly_product_id', '07eMToUCpzTcsg8zKSDM')
            ->update(['status' => 'inactive']);

        $payload = $this->getValidPayload('user@example.com');
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'product_inactive']);
    }

    public function test_webhook_logs_refund_without_changing_user(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

        $expiryDate = Carbon::parse('2025-02-15 12:00:00');
        $user = User::factory()->create([
            'email' => 'refund@example.com',
            'premium_expires_at' => $expiryDate,
        ]);

        $payload = $this->getValidPayload('refund@example.com');
        $payload['event'] = 'refund';
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'refund']);

        // User's premium should NOT change
        $user->refresh();
        $this->assertTrue($user->premium_expires_at->diffInSeconds($expiryDate) < 5);

        Carbon::setTestNow();
    }

    public function test_webhook_matches_email_case_insensitively(): void
    {
        $user = User::factory()->create(['email' => 'User@EXAMPLE.COM']);

        $payload = $this->getValidPayload('user@example.com');
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $user->refresh();
        $this->assertTrue($user->isPremium());
    }

    public function test_webhook_includes_trace_id_in_response(): void
    {
        $payload = $this->getValidPayload();
        $signature = $this->generateSignature($payload['data'], $this->webhookSecret);

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertJsonStructure(['trace_id']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $response->json('trace_id')
        );
    }
}
