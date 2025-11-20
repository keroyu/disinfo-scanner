<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\EmailVerificationService;
use Carbon\Carbon;

class EmailVerificationTokenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test token expires after 24 hours.
     */
    public function test_token_expires_after_24_hours(): void
    {
        $service = new EmailVerificationService();
        $token = $service->generateToken('test@example.com');

        // Token should expire in 24 hours
        $expectedExpiration = now()->addHours(24);
        $actualExpiration = $token->expires_at;

        // Allow 1 minute tolerance for test execution time
        $this->assertTrue(
            abs($expectedExpiration->diffInSeconds($actualExpiration)) < 60,
            "Token expiration time should be 24 hours from creation"
        );
    }

    /**
     * Test token validation detects expired tokens.
     */
    public function test_token_validation_detects_expired_tokens(): void
    {
        $service = new EmailVerificationService();

        // Simulate expired token by creating it with past expiration
        $token = $service->generateToken('expired@example.com');
        $token->expires_at = Carbon::now()->subHour();
        $token->save();

        $validation = $service->validateToken('expired@example.com', $token->raw_token);

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('過期', $validation['message']);
    }

    /**
     * Test token validation detects used tokens.
     */
    public function test_token_validation_detects_used_tokens(): void
    {
        $service = new EmailVerificationService();
        $token = $service->generateToken('used@example.com');

        // Mark token as used
        $service->markTokenAsUsed($token);

        $validation = $service->validateToken('used@example.com', $token->raw_token);

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('已被使用', $validation['message']);
    }

    /**
     * Test token validation accepts valid tokens.
     */
    public function test_token_validation_accepts_valid_tokens(): void
    {
        $service = new EmailVerificationService();
        $token = $service->generateToken('valid@example.com');

        $validation = $service->validateToken('valid@example.com', $token->raw_token);

        $this->assertTrue($validation['valid']);
        $this->assertEquals('驗證成功', $validation['message']);
    }

    /**
     * Test token validation rejects invalid token format.
     */
    public function test_token_validation_rejects_invalid_format(): void
    {
        $service = new EmailVerificationService();

        $validation = $service->validateToken('test@example.com', 'invalid-token-123');

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('無效', $validation['message']);
    }

    /**
     * Test token hashing is secure (SHA-256).
     */
    public function test_token_hashing_is_secure(): void
    {
        $service = new EmailVerificationService();
        $token = $service->generateToken('hash@example.com');

        // Raw token should be 64 characters
        $this->assertEquals(64, strlen($token->raw_token));

        // Stored token should be SHA-256 hash (64 hex characters)
        $this->assertEquals(64, strlen($token->token));
        $this->assertTrue(ctype_xdigit($token->token));

        // Raw token and stored token should be different
        $this->assertNotEquals($token->raw_token, $token->token);

        // Stored token should match SHA-256 of raw token
        $this->assertEquals(hash('sha256', $token->raw_token), $token->token);
    }

    /**
     * Test tokens are unique.
     */
    public function test_tokens_are_unique(): void
    {
        $service = new EmailVerificationService();

        $token1 = $service->generateToken('user1@example.com');
        $token2 = $service->generateToken('user2@example.com');
        $token3 = $service->generateToken('user1@example.com'); // Same email as token1

        // All tokens should be different
        $this->assertNotEquals($token1->token, $token2->token);
        $this->assertNotEquals($token1->token, $token3->token);
        $this->assertNotEquals($token2->token, $token3->token);
    }

    /**
     * Test marking token as used sets timestamp.
     */
    public function test_marking_token_as_used_sets_timestamp(): void
    {
        $service = new EmailVerificationService();
        $token = $service->generateToken('mark@example.com');

        $this->assertNull($token->used_at);

        $service->markTokenAsUsed($token);

        $token->refresh();
        $this->assertNotNull($token->used_at);
        $this->assertTrue($token->used_at->isToday());
    }
}
