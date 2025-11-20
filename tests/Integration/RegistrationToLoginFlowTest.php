<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\EmailVerificationToken;
use App\Services\EmailVerificationService;
use App\Services\PasswordService;
use Illuminate\Support\Facades\Mail;

/**
 * Integration Test: Complete Registration to Login Flow (T042)
 *
 * This test validates the entire user journey:
 * 1. User registers with email
 * 2. System sends verification email
 * 3. User clicks verification link
 * 4. User logs in successfully
 */
class RegistrationToLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * Test complete registration-to-login flow end-to-end.
     *
     * @group integration
     */
    public function test_complete_registration_to_login_flow(): void
    {
        $email = 'integration@example.com';

        // ===== STEP 1: User Registration =====
        $registrationResponse = $this->postJson('/api/auth/register', [
            'email' => $email,
        ]);

        $registrationResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => $email,
                    'verification_sent' => true,
                    'expires_in_hours' => 24,
                ],
            ]);

        // Verify user was created
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user, 'User should be created');
        $this->assertFalse($user->is_email_verified, 'User should not be verified yet');
        $this->assertTrue($user->has_default_password, 'User should have default password');

        // Verify user has regular_member role
        $this->assertTrue(
            $user->roles()->where('name', 'regular_member')->exists(),
            'User should have regular_member role'
        );

        // ===== STEP 2: Verification Token Generated =====
        $token = EmailVerificationToken::where('email', $email)->first();
        $this->assertNotNull($token, 'Verification token should be created');
        $this->assertNull($token->used_at, 'Token should not be used yet');
        $this->assertTrue($token->expires_at->isFuture(), 'Token should not be expired');

        // ===== STEP 3: Attempt Login Before Verification (Should Fail) =====
        $passwordService = new PasswordService();
        $loginBeforeVerification = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => PasswordService::DEFAULT_PASSWORD,
        ]);

        $loginBeforeVerification->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertGuest('User should not be authenticated before email verification');

        // ===== STEP 4: Email Verification =====
        // Simulate clicking verification link
        $service = new EmailVerificationService();

        // Get the raw token (in real scenario, this would be in the email)
        $rawToken = $token->token; // For testing, we'll generate a proper token

        // Generate a valid raw token for testing
        $testRawToken = \Illuminate\Support\Str::random(64);
        $token->token = hash('sha256', $testRawToken);
        $token->save();

        $verificationResponse = $this->getJson(
            "/api/auth/verify-email?email={$email}&token={$testRawToken}"
        );

        $verificationResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify user is now verified
        $user->refresh();
        $this->assertTrue($user->is_email_verified, 'User should be verified');
        $this->assertNotNull($user->email_verified_at, 'Verification timestamp should be set');

        // Verify token is marked as used
        $token->refresh();
        $this->assertNotNull($token->used_at, 'Token should be marked as used');

        // ===== STEP 5: Successful Login After Verification =====
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => PasswordService::DEFAULT_PASSWORD,
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => $email,
                        'is_email_verified' => true,
                        'has_default_password' => true,
                    ],
                ],
            ]);

        $this->assertAuthenticatedAs($user, 'User should be authenticated');

        // ===== STEP 6: Access Authenticated Endpoint =====
        $meResponse = $this->getJson('/api/auth/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => $email,
                    ],
                ],
            ]);

        // ===== STEP 7: Logout =====
        $logoutResponse = $this->postJson('/api/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertGuest('User should be logged out');
    }

    /**
     * Test registration flow with "remember me" option.
     *
     * @group integration
     */
    public function test_registration_flow_with_remember_me(): void
    {
        $email = 'remember@example.com';

        // Register
        $this->postJson('/api/auth/register', ['email' => $email]);

        // Verify
        $user = User::where('email', $email)->first();
        $token = EmailVerificationToken::where('email', $email)->first();

        $testRawToken = \Illuminate\Support\Str::random(64);
        $token->token = hash('sha256', $testRawToken);
        $token->save();

        $this->getJson("/api/auth/verify-email?email={$email}&token={$testRawToken}");

        // Login with remember me
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => PasswordService::DEFAULT_PASSWORD,
            'remember' => true,
        ]);

        $loginResponse->assertStatus(200);

        // Verify remember token is set
        $user->refresh();
        $this->assertNotNull($user->remember_token, 'Remember token should be set');
    }

    /**
     * Test multiple users can register and login independently.
     *
     * @group integration
     */
    public function test_multiple_users_registration_and_login(): void
    {
        $users = [
            'user1@example.com',
            'user2@example.com',
            'user3@example.com',
        ];

        foreach ($users as $email) {
            // Register each user
            $this->postJson('/api/auth/register', ['email' => $email])
                ->assertStatus(201);

            // Verify each user
            $user = User::where('email', $email)->first();
            $token = EmailVerificationToken::where('email', $email)->first();

            $testRawToken = \Illuminate\Support\Str::random(64);
            $token->token = hash('sha256', $testRawToken);
            $token->save();

            $this->getJson("/api/auth/verify-email?email={$email}&token={$testRawToken}")
                ->assertStatus(200);

            // Login each user
            $this->postJson('/api/auth/login', [
                'email' => $email,
                'password' => PasswordService::DEFAULT_PASSWORD,
            ])->assertStatus(200);

            // Logout to test next user
            $this->postJson('/api/auth/logout');
        }

        // Verify all users exist
        $this->assertEquals(3, User::whereIn('email', $users)->count());
    }

    /**
     * Test registration flow handles validation errors gracefully.
     *
     * @group integration
     */
    public function test_registration_flow_handles_validation_errors(): void
    {
        // Invalid email format
        $this->postJson('/api/auth/register', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Missing email
        $this->postJson('/api/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Duplicate registration
        $email = 'duplicate@example.com';
        $this->postJson('/api/auth/register', ['email' => $email])
            ->assertStatus(201);

        $this->postJson('/api/auth/register', ['email' => $email])
            ->assertStatus(422);
    }
}
