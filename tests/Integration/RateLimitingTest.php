<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\EmailVerificationToken;
use App\Services\EmailVerificationService;
use Illuminate\Support\Facades\Mail;

/**
 * Integration Test: Rate Limiting Verification (T044)
 *
 * This test validates that rate limiting prevents abuse of:
 * - Verification email requests (3 per hour)
 * - Password reset requests (3 per hour)
 * - Login attempts (configurable)
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * Test verification email rate limiting (3 per hour).
     *
     * @group integration
     * @group ratelimit
     */
    public function test_verification_email_rate_limiting(): void
    {
        $email = 'ratelimit@example.com';

        // First registration should succeed
        $response1 = $this->postJson('/api/auth/register', ['email' => $email]);
        $response1->assertStatus(201);

        // Create additional tokens to simulate multiple resend attempts
        for ($i = 0; $i < 3; $i++) {
            EmailVerificationToken::create([
                'email' => $email,
                'token' => hash('sha256', "token-{$i}"),
                'created_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
        }

        // Check rate limit using service
        $service = new EmailVerificationService();
        $rateLimitCheck = $service->checkRateLimit($email);

        $this->assertFalse($rateLimitCheck['allowed'], 'Should be rate limited');
        $this->assertStringContainsString('上限', $rateLimitCheck['message']);
        $this->assertEquals(3600, $rateLimitCheck['retry_after']);
    }

    /**
     * Test resend verification respects rate limiting.
     *
     * @group integration
     * @group ratelimit
     */
    public function test_resend_verification_respects_rate_limit(): void
    {
        $email = 'resendlimit@example.com';

        // Register user
        $this->postJson('/api/auth/register', ['email' => $email])
            ->assertStatus(201);

        // Create 2 more tokens within the hour
        for ($i = 0; $i < 2; $i++) {
            EmailVerificationToken::create([
                'email' => $email,
                'token' => hash('sha256', "resend-token-{$i}"),
                'created_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
        }

        // This should be the 4th attempt within an hour - should fail
        $response = $this->postJson('/api/auth/verify-email/resend', ['email' => $email]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test rate limit resets after time window.
     *
     * @group integration
     * @group ratelimit
     */
    public function test_rate_limit_resets_after_time_window(): void
    {
        $email = 'resetwindow@example.com';

        // Create 3 old tokens (more than 1 hour ago)
        for ($i = 0; $i < 3; $i++) {
            EmailVerificationToken::create([
                'email' => $email,
                'token' => hash('sha256', "old-token-{$i}"),
                'created_at' => now()->subHours(2),
                'expires_at' => now()->subHour(),
            ]);
        }

        // New registration should succeed because old tokens are outside the 1-hour window
        $service = new EmailVerificationService();
        $rateLimitCheck = $service->checkRateLimit($email);

        $this->assertTrue($rateLimitCheck['allowed'], 'Should not be rate limited after time window');
    }

    /**
     * Test rate limiting is per-email (different emails have independent limits).
     *
     * @group integration
     * @group ratelimit
     */
    public function test_rate_limiting_is_per_email(): void
    {
        $email1 = 'user1@example.com';
        $email2 = 'user2@example.com';

        // User 1 hits rate limit
        for ($i = 0; $i < 3; $i++) {
            EmailVerificationToken::create([
                'email' => $email1,
                'token' => hash('sha256', "user1-token-{$i}"),
                'created_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
        }

        $service = new EmailVerificationService();

        // User 1 should be rate limited
        $rateLimitCheck1 = $service->checkRateLimit($email1);
        $this->assertFalse($rateLimitCheck1['allowed']);

        // User 2 should NOT be rate limited
        $rateLimitCheck2 = $service->checkRateLimit($email2);
        $this->assertTrue($rateLimitCheck2['allowed']);
    }

    /**
     * Test rate limit counter accuracy.
     *
     * @group integration
     * @group ratelimit
     */
    public function test_rate_limit_counter_accuracy(): void
    {
        $email = 'counter@example.com';
        $service = new EmailVerificationService();

        // Check initial state (0 requests)
        $check0 = $service->checkRateLimit($email);
        $this->assertTrue($check0['allowed']);

        // Add 1 token
        EmailVerificationToken::create([
            'email' => $email,
            'token' => hash('sha256', 'token-1'),
            'created_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        $check1 = $service->checkRateLimit($email);
        $this->assertTrue($check1['allowed']);

        // Add 2 more tokens (total 3)
        for ($i = 2; $i <= 3; $i++) {
            EmailVerificationToken::create([
                'email' => $email,
                'token' => hash('sha256', "token-{$i}"),
                'created_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
        }

        // Should be at limit now
        $check3 = $service->checkRateLimit($email);
        $this->assertFalse($check3['allowed']);
    }

    /**
     * Test registration endpoint returns 429 when rate limited.
     *
     * @group integration
     * @group ratelimit
     */
    public function test_registration_endpoint_returns_429_when_rate_limited(): void
    {
        $email = 'endpoint429@example.com';

        // First registration
        $this->postJson('/api/auth/register', ['email' => $email])
            ->assertStatus(201);

        // Simulate rate limit by creating additional tokens
        for ($i = 0; $i < 3; $i++) {
            EmailVerificationToken::create([
                'email' => $email,
                'token' => hash('sha256', "limit-token-{$i}"),
                'created_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
        }

        // Delete the user to allow re-registration attempt
        User::where('email', $email)->delete();

        // Attempt to register again - should be rate limited
        $response = $this->postJson('/api/auth/register', ['email' => $email]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test rate limit message is in Traditional Chinese.
     *
     * @group integration
     * @group ratelimit
     */
    public function test_rate_limit_message_is_in_traditional_chinese(): void
    {
        $email = 'chinese@example.com';

        // Simulate rate limit
        for ($i = 0; $i < 3; $i++) {
            EmailVerificationToken::create([
                'email' => $email,
                'token' => hash('sha256', "chinese-token-{$i}"),
                'created_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
        }

        $service = new EmailVerificationService();
        $rateLimitCheck = $service->checkRateLimit($email);

        $this->assertFalse($rateLimitCheck['allowed']);
        $this->assertStringContainsString('上限', $rateLimitCheck['message']);
        $this->assertStringContainsString('請稍後', $rateLimitCheck['message']);
    }

    /**
     * Test rate limiting prevents automated attacks.
     *
     * @group integration
     * @group ratelimit
     * @group security
     */
    public function test_rate_limiting_prevents_automated_attacks(): void
    {
        $targetEmail = 'attack@example.com';

        // Simulate automated attack (10 rapid requests)
        $successCount = 0;
        $blockedCount = 0;

        for ($i = 0; $i < 10; $i++) {
            $service = new EmailVerificationService();
            $check = $service->checkRateLimit($targetEmail);

            if ($check['allowed']) {
                $successCount++;
                // Simulate token creation
                EmailVerificationToken::create([
                    'email' => $targetEmail,
                    'token' => hash('sha256', "attack-token-{$i}"),
                    'created_at' => now(),
                    'expires_at' => now()->addHours(24),
                ]);
            } else {
                $blockedCount++;
            }
        }

        // Should allow maximum 3 requests, block the rest
        $this->assertEquals(3, $successCount, 'Should allow exactly 3 requests');
        $this->assertEquals(7, $blockedCount, 'Should block 7 requests');
    }
}
