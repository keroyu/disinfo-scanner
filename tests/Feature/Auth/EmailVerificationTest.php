<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\EmailVerificationToken;
use App\Services\EmailVerificationService;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete email verification flow marks user as verified.
     */
    public function test_complete_verification_flow_marks_user_as_verified(): void
    {
        // Create unverified user
        $user = User::factory()->create([
            'email' => 'verify@example.com',
            'is_email_verified' => false,
        ]);

        // Generate verification token
        $service = new EmailVerificationService();
        $token = $service->generateToken($user->email);

        // Verify email
        $response = $this->getJson("/api/auth/verify-email?email={$user->email}&token={$token->raw_token}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Check user is verified
        $user->refresh();
        $this->assertTrue($user->is_email_verified);
        $this->assertNotNull($user->email_verified_at);

        // Check token is marked as used
        $token->refresh();
        $this->assertNotNull($token->used_at);
    }

    /**
     * Test verification with invalid token fails.
     */
    public function test_verification_with_invalid_token_fails(): void
    {
        $user = User::factory()->create([
            'email' => 'invalid@example.com',
            'is_email_verified' => false,
        ]);

        $response = $this->getJson("/api/auth/verify-email?email={$user->email}&token=invalid-token");

        $response->assertStatus(400)
            ->assertJson(['success' => false]);

        // User should still be unverified
        $user->refresh();
        $this->assertFalse($user->is_email_verified);
    }

    /**
     * Test verification with expired token fails.
     */
    public function test_verification_with_expired_token_fails(): void
    {
        $user = User::factory()->create([
            'email' => 'expired@example.com',
            'is_email_verified' => false,
        ]);

        // Create expired token
        $rawToken = 'expired-token-123';
        EmailVerificationToken::create([
            'email' => $user->email,
            'token' => hash('sha256', $rawToken),
            'created_at' => now()->subHours(25),
            'expires_at' => now()->subHour(),
            'used_at' => null,
        ]);

        $response = $this->getJson("/api/auth/verify-email?email={$user->email}&token={$rawToken}");

        $response->assertStatus(400)
            ->assertJson(['success' => false]);

        // User should still be unverified
        $user->refresh();
        $this->assertFalse($user->is_email_verified);
    }

    /**
     * Test verification with already used token fails.
     */
    public function test_verification_with_used_token_fails(): void
    {
        $user = User::factory()->create([
            'email' => 'used@example.com',
            'is_email_verified' => false,
        ]);

        $service = new EmailVerificationService();
        $token = $service->generateToken($user->email);

        // Use token once
        $this->getJson("/api/auth/verify-email?email={$user->email}&token={$token->raw_token}");

        // Try to use again
        $response = $this->getJson("/api/auth/verify-email?email={$user->email}&token={$token->raw_token}");

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    /**
     * Test verification without email parameter fails.
     */
    public function test_verification_without_email_parameter_fails(): void
    {
        $response = $this->getJson('/api/auth/verify-email?token=some-token');

        $response->assertStatus(422);
    }

    /**
     * Test verification without token parameter fails.
     */
    public function test_verification_without_token_parameter_fails(): void
    {
        $response = $this->getJson('/api/auth/verify-email?email=test@example.com');

        $response->assertStatus(422);
    }

    /**
     * Test verification with mismatched email and token fails.
     */
    public function test_verification_with_mismatched_email_fails(): void
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        $service = new EmailVerificationService();
        $token = $service->generateToken($user1->email);

        // Try to verify user2 with user1's token
        $response = $this->getJson("/api/auth/verify-email?email={$user2->email}&token={$token->raw_token}");

        $response->assertStatus(400);

        // Both users should remain unverified
        $user1->refresh();
        $user2->refresh();
        $this->assertFalse($user1->is_email_verified);
        $this->assertFalse($user2->is_email_verified);
    }

    /**
     * Test token cleanup removes expired tokens.
     */
    public function test_token_cleanup_removes_expired_tokens(): void
    {
        // Create old expired token
        EmailVerificationToken::create([
            'email' => 'old@example.com',
            'token' => hash('sha256', 'old-token'),
            'created_at' => now()->subDays(8),
            'expires_at' => now()->subDays(7)->subHour(),
            'used_at' => null,
        ]);

        // Create recent token
        EmailVerificationToken::create([
            'email' => 'recent@example.com',
            'token' => hash('sha256', 'recent-token'),
            'created_at' => now()->subHour(),
            'expires_at' => now()->addHours(23),
            'used_at' => null,
        ]);

        $service = new EmailVerificationService();
        $deletedCount = $service->cleanupExpiredTokens();

        $this->assertGreaterThan(0, $deletedCount);
        $this->assertDatabaseMissing('email_verification_tokens', ['email' => 'old@example.com']);
        $this->assertDatabaseHas('email_verification_tokens', ['email' => 'recent@example.com']);
    }
}
