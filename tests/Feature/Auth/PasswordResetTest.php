<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use App\Mail\PasswordResetEmail;

/**
 * Feature Test: Password Reset Flow
 *
 * Tests User Story 2: Users can request password reset via email
 * and reset their password with a secure token
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * @test
     * T048: User can request password reset
     */
    public function user_can_request_password_reset()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/password/reset/request', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify email was sent
        Mail::assertSent(PasswordResetEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * @test
     * T048: Password reset email contains valid token
     */
    public function password_reset_email_contains_valid_token()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/auth/password/reset/request', [
            'email' => 'test@example.com',
        ]);

        Mail::assertSent(PasswordResetEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && !empty($mail->token);
        });
    }

    /**
     * @test
     * T048: User can reset password with valid token
     */
    public function user_can_reset_password_with_valid_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('OldPassword@123'),
        ]);

        // Create token
        $token = Password::broker()->createToken($user);

        // Reset password
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'NewSecure@Pass123',
            'password_confirmation' => 'NewSecure@Pass123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewSecure@Pass123', $user->password));
    }

    /**
     * @test
     * T048: Password reset enforces password strength requirements
     */
    public function password_reset_enforces_password_strength()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $token = Password::broker()->createToken($user);

        // Try weak password
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * @test
     * T048: Password reset rejects invalid token
     */
    public function password_reset_rejects_invalid_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => 'invalid-token-12345',
            'password' => 'NewSecure@Pass123',
            'password_confirmation' => 'NewSecure@Pass123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /**
     * @test
     * T048: Password reset token expires after 24 hours
     */
    public function password_reset_token_expires_after_24_hours()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $token = Password::broker()->createToken($user);

        // Travel 25 hours into future
        $this->travel(25)->hours();

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'NewSecure@Pass123',
            'password_confirmation' => 'NewSecure@Pass123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /**
     * @test
     * T048: Password reset invalidates token after use
     */
    public function password_reset_token_is_invalidated_after_use()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $token = Password::broker()->createToken($user);

        // Use token to reset password
        $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'NewSecure@Pass123',
            'password_confirmation' => 'NewSecure@Pass123',
        ]);

        // Try to use same token again
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'AnotherPassword@456',
            'password_confirmation' => 'AnotherPassword@456',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /**
     * @test
     * T048: Rate limiting prevents password reset abuse
     */
    public function password_reset_request_is_rate_limited()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        // Make 3 requests (allowed)
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/auth/password/reset/request', [
                'email' => 'test@example.com',
            ]);

            $response->assertStatus(200);
        }

        // 4th request should be rate limited
        $response = $this->postJson('/api/auth/password/reset/request', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(429); // Too Many Requests
    }

    /**
     * @test
     * T048: Password reset handles non-existent email gracefully
     */
    public function password_reset_handles_non_existent_email_gracefully()
    {
        // Should not reveal whether email exists
        $response = $this->postJson('/api/auth/password/reset/request', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * @test
     * T048: User can log in with new password after reset
     */
    public function user_can_login_with_new_password_after_reset()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('OldPassword@123'),
        ]);

        $regularRole = Role::where('slug', 'regular-member')->first();
        $user->roles()->attach($regularRole->id);

        // Reset password
        $token = Password::broker()->createToken($user);
        $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'NewSecure@Pass123',
            'password_confirmation' => 'NewSecure@Pass123',
        ]);

        // Try to login with new password
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'NewSecure@Pass123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * @test
     * T048: Password reset clears must_change_password flag
     */
    public function password_reset_clears_must_change_password_flag()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('123456'),
            'must_change_password' => true,
        ]);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'NewSecure@Pass123',
            'password_confirmation' => 'NewSecure@Pass123',
        ]);

        // Verify flag is cleared
        $user->refresh();
        $this->assertFalse($user->must_change_password);
    }
}
