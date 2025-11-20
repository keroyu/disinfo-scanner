<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\EmailVerificationToken;
use Illuminate\Support\Facades\Mail;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake(); // Prevent actual email sending during tests
    }

    /**
     * Test complete registration flow creates user and sends verification email.
     */
    public function test_complete_registration_flow_creates_user_and_sends_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'newuser@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'is_email_verified' => false,
            'has_default_password' => true,
        ]);

        // Verify verification token was created
        $this->assertDatabaseHas('email_verification_tokens', [
            'email' => 'newuser@example.com',
        ]);

        // Verify user has regular_member role
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertTrue($user->roles()->where('name', 'regular_member')->exists());
    }

    /**
     * Test registration with existing email fails.
     */
    public function test_registration_with_existing_email_fails(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'existing@example.com',
        ]);

        $response->assertStatus(422);

        // Verify no duplicate user was created
        $this->assertEquals(1, User::where('email', 'existing@example.com')->count());
    }

    /**
     * Test registration assigns default password.
     */
    public function test_registration_assigns_default_password(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'defaultpass@example.com',
        ]);

        $user = User::where('email', 'defaultpass@example.com')->first();

        $this->assertTrue($user->has_default_password);
        $this->assertNotNull($user->password);
    }

    /**
     * Test registration creates verification token with correct expiration.
     */
    public function test_registration_creates_token_with_correct_expiration(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'tokentest@example.com',
        ]);

        $token = EmailVerificationToken::where('email', 'tokentest@example.com')->first();

        $this->assertNotNull($token);
        $this->assertNull($token->used_at);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertTrue($token->expires_at->diffInHours(now()) >= 23); // At least 23 hours
    }

    /**
     * Test registration respects rate limiting.
     */
    public function test_registration_respects_rate_limiting(): void
    {
        $email = 'ratelimit@example.com';

        // Register user first time
        $this->postJson('/api/auth/register', ['email' => $email]);

        // Try to register again immediately (should fail due to duplicate)
        $this->postJson('/api/auth/register', ['email' => $email])->assertStatus(422);

        // Try to resend verification 4 times in quick succession
        for ($i = 0; $i < 4; $i++) {
            EmailVerificationToken::create([
                'email' => $email,
                'token' => hash('sha256', "token-{$i}"),
                'created_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
        }

        // 5th attempt should be rate limited (3 per hour limit)
        $tokens = EmailVerificationToken::where('email', $email)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $this->assertGreaterThanOrEqual(3, $tokens);
    }

    /**
     * Test registration with invalid email format fails validation.
     */
    public function test_registration_with_invalid_email_fails_validation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration without email fails validation.
     */
    public function test_registration_without_email_fails_validation(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
