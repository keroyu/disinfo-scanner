<?php

namespace Tests\Feature;

use App\Mail\PaymentConfirmationMail;
use App\Models\PaymentProduct;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * T082: Test email is sent after successful payment webhook processing
     */
    public function test_email_sent_after_successful_payment(): void
    {
        Mail::fake();

        // Create user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'is_email_verified' => true,
        ]);
        $user->assignRole('regular_member');

        // Create product
        $product = PaymentProduct::create([
            'name' => '30天高級會員',
            'portaly_product_id' => 'test-product-001',
            'portaly_url' => 'https://portaly.cc/kyontw/product/test-product-001',
            'price' => 190,
            'currency' => 'TWD',
            'duration_days' => 30,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        // Configure webhook secret
        $secret = 'test-secret-key';
        Setting::setValue('portaly_webhook_secret', Crypt::encryptString($secret));

        // Create webhook payload
        $payload = [
            'event' => 'paid',
            'data' => [
                'id' => 'order-' . time(),
                'productId' => 'test-product-001',
                'amount' => 190,
                'currency' => 'TWD',
                'customerData' => [
                    'email' => 'test@example.com',
                    'name' => 'Test User',
                ],
            ],
        ];

        $dataJson = json_encode($payload['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $dataJson, $secret);

        // Send webhook request
        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'status' => 'success']);

        // Assert email was sent
        Mail::assertSent(PaymentConfirmationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * T083: Test email contains all required content
     */
    public function test_email_contains_required_content(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'is_email_verified' => true,
            'premium_expires_at' => now()->addDays(30),
        ]);

        $product = PaymentProduct::create([
            'name' => '30天高級會員',
            'portaly_product_id' => 'test-product-001',
            'portaly_url' => 'https://portaly.cc/kyontw/product/test-product-001',
            'price' => 190,
            'currency' => 'TWD',
            'duration_days' => 30,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $mail = new PaymentConfirmationMail($user, $product);

        // Test envelope (subject)
        $envelope = $mail->envelope();
        $this->assertStringContainsString('付款成功', $envelope->subject);
        $this->assertStringContainsString('30天高級會員', $envelope->subject);

        // Test content contains required information
        $rendered = $mail->render();

        // 1. Product name
        $this->assertStringContainsString('30天高級會員', $rendered);

        // 2. Expiry date
        $expiryDate = $user->premium_expires_at->format('Y-m-d');
        $this->assertStringContainsString($expiryDate, $rendered);

        // 3. Re-login reminder
        $this->assertStringContainsString('重新登入', $rendered);

        // 4. Point system guide reference
        $this->assertStringContainsString('積分系統說明', $rendered);

        // 5. Support email
        $this->assertStringContainsString('themustbig+ds@gmail.com', $rendered);

        // 6. Site link button
        $this->assertStringContainsString('前往網站', $rendered);

        // 7. Copyright with company name
        $this->assertStringContainsString('投好壯壯有限公司', $rendered);
    }

    /**
     * Test email is sent for extended membership (existing premium member)
     */
    public function test_email_shows_extended_expiry_date_for_existing_member(): void
    {
        Mail::fake();

        // Create premium user with existing expiry
        $existingExpiry = now()->addDays(10);
        $user = User::factory()->create([
            'email' => 'premium@example.com',
            'is_email_verified' => true,
            'premium_expires_at' => $existingExpiry,
        ]);
        $user->assignRole('premium_member');

        $product = PaymentProduct::create([
            'name' => '30天高級會員',
            'portaly_product_id' => 'test-product-002',
            'portaly_url' => 'https://portaly.cc/kyontw/product/test-product-002',
            'price' => 190,
            'currency' => 'TWD',
            'duration_days' => 30,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $secret = 'test-secret-key';
        Setting::setValue('portaly_webhook_secret', Crypt::encryptString($secret));

        $payload = [
            'event' => 'paid',
            'data' => [
                'id' => 'order-extend-' . time(),
                'productId' => 'test-product-002',
                'amount' => 190,
                'currency' => 'TWD',
                'customerData' => [
                    'email' => 'premium@example.com',
                    'name' => 'Premium User',
                ],
            ],
        ];

        $dataJson = json_encode($payload['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $dataJson, $secret);

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Verify email was sent with extended expiry date
        Mail::assertSent(PaymentConfirmationMail::class, function ($mail) use ($user, $existingExpiry) {
            // Email should be sent to the user
            if (!$mail->hasTo($user->email)) {
                return false;
            }

            // New expiry should be 40 days from now (10 + 30)
            $expectedExpiry = $existingExpiry->copy()->addDays(30);
            $rendered = $mail->render();

            return str_contains($rendered, $expectedExpiry->format('Y-m-d'));
        });
    }

    /**
     * Test no email sent when user not found
     */
    public function test_no_email_sent_when_user_not_found(): void
    {
        Mail::fake();

        $product = PaymentProduct::create([
            'name' => '30天高級會員',
            'portaly_product_id' => 'test-product-003',
            'portaly_url' => 'https://portaly.cc/kyontw/product/test-product-003',
            'price' => 190,
            'currency' => 'TWD',
            'duration_days' => 30,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $secret = 'test-secret-key';
        Setting::setValue('portaly_webhook_secret', Crypt::encryptString($secret));

        $payload = [
            'event' => 'paid',
            'data' => [
                'id' => 'order-no-user-' . time(),
                'productId' => 'test-product-003',
                'amount' => 190,
                'currency' => 'TWD',
                'customerData' => [
                    'email' => 'nonexistent@example.com',
                    'name' => 'Unknown User',
                ],
            ],
        ];

        $dataJson = json_encode($payload['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $dataJson, $secret);

        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'user_not_found']);

        // No email should be sent
        Mail::assertNotSent(PaymentConfirmationMail::class);
    }

    /**
     * Test payment processing continues even if email fails
     */
    public function test_payment_not_blocked_by_email_failure(): void
    {
        // This test validates that the payment is processed even if email sending fails
        // The actual implementation uses try-catch to handle email failures gracefully

        Mail::fake();
        Mail::shouldReceive('to->send')->andThrow(new \Exception('Email service unavailable'));

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'is_email_verified' => true,
        ]);
        $user->assignRole('regular_member');

        $product = PaymentProduct::create([
            'name' => '30天高級會員',
            'portaly_product_id' => 'test-product-004',
            'portaly_url' => 'https://portaly.cc/kyontw/product/test-product-004',
            'price' => 190,
            'currency' => 'TWD',
            'duration_days' => 30,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $secret = 'test-secret-key';
        Setting::setValue('portaly_webhook_secret', Crypt::encryptString($secret));

        $payload = [
            'event' => 'paid',
            'data' => [
                'id' => 'order-email-fail-' . time(),
                'productId' => 'test-product-004',
                'amount' => 190,
                'currency' => 'TWD',
                'customerData' => [
                    'email' => 'test@example.com',
                    'name' => 'Test User',
                ],
            ],
        ];

        $dataJson = json_encode($payload['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $dataJson, $secret);

        // Payment should still succeed even if email fails
        $response = $this->postJson('/api/webhooks/portaly', $payload, [
            'X-Portaly-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'status' => 'success']);

        // User should still be upgraded
        $user->refresh();
        $this->assertNotNull($user->premium_expires_at);
    }
}
