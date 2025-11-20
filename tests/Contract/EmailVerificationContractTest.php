<?php

namespace Tests\Contract;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\EmailVerificationToken;
use App\Services\EmailVerificationService;

class EmailVerificationContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test email verification endpoint accepts valid token.
     */
    public function test_verification_endpoint_accepts_valid_token(): void
    {
        $service = new EmailVerificationService();
        $token = $service->generateToken('test@example.com');

        $response = $this->getJson("/api/auth/verify-email?email=test@example.com&token={$token->raw_token}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }

    /**
     * Test verification endpoint rejects invalid token.
     */
    public function test_verification_endpoint_rejects_invalid_token(): void
    {
        $response = $this->getJson('/api/auth/verify-email?email=test@example.com&token=invalid-token-12345');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test verification endpoint rejects expired token.
     */
    public function test_verification_endpoint_rejects_expired_token(): void
    {
        $token = EmailVerificationToken::create([
            'email' => 'test@example.com',
            'token' => hash('sha256', 'expired-token'),
            'created_at' => now()->subHours(25), // 25 hours ago
            'expires_at' => now()->subHour(), // Expired
            'used_at' => null,
        ]);

        $response = $this->getJson('/api/auth/verify-email?email=test@example.com&token=expired-token');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test verification endpoint rejects already used token.
     */
    public function test_verification_endpoint_rejects_used_token(): void
    {
        $service = new EmailVerificationService();
        $token = $service->generateToken('test@example.com');

        // Mark token as used
        $service->markTokenAsUsed($token);

        $response = $this->getJson("/api/auth/verify-email?email=test@example.com&token={$token->raw_token}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test verification endpoint requires email parameter.
     */
    public function test_verification_endpoint_requires_email_parameter(): void
    {
        $response = $this->getJson('/api/auth/verify-email?token=some-token');

        $response->assertStatus(422);
    }

    /**
     * Test verification endpoint requires token parameter.
     */
    public function test_verification_endpoint_requires_token_parameter(): void
    {
        $response = $this->getJson('/api/auth/verify-email?email=test@example.com');

        $response->assertStatus(422);
    }

    /**
     * Test verification response includes correct structure.
     */
    public function test_verification_response_includes_correct_structure(): void
    {
        $service = new EmailVerificationService();
        $token = $service->generateToken('verify@example.com');

        $response = $this->getJson("/api/auth/verify-email?email=verify@example.com&token={$token->raw_token}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }
}
