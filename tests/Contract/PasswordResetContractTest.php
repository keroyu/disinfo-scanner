<?php

namespace Tests\Contract;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

/**
 * Contract Test: Password Reset Endpoint
 *
 * Tests the API contract for password reset functionality
 * Validates request/response formats and status codes
 */
class PasswordResetContractTest extends TestCase
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
     * T046: Contract test for password reset request endpoint - successful request
     */
    public function it_accepts_valid_password_reset_request()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/password/reset/request', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * @test
     * T046: Contract test - validates email required
     */
    public function it_requires_email_for_reset_request()
    {
        $response = $this->postJson('/api/auth/password/reset/request', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * @test
     * T046: Contract test - validates email format
     */
    public function it_validates_email_format_for_reset_request()
    {
        $response = $this->postJson('/api/auth/password/reset/request', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * @test
     * T046: Contract test - handles non-existent email gracefully
     */
    public function it_handles_non_existent_email_without_revealing_existence()
    {
        $response = $this->postJson('/api/auth/password/reset/request', [
            'email' => 'nonexistent@example.com',
        ]);

        // Should return success even for non-existent email to prevent email enumeration
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * @test
     * T046: Contract test for password reset with token - successful reset
     */
    public function it_accepts_valid_password_reset_with_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        // Create password reset token using Laravel's built-in mechanism
        $token = app('auth.password.broker')->createToken($user);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'NewSecure@Pass123',
            'password_confirmation' => 'NewSecure@Pass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * @test
     * T046: Contract test - validates all required fields for reset
     */
    public function it_requires_all_fields_for_password_reset()
    {
        $response = $this->postJson('/api/auth/password/reset', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'token', 'password']);
    }

    /**
     * @test
     * T046: Contract test - validates password confirmation matches
     */
    public function it_requires_password_confirmation_for_reset()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = app('auth.password.broker')->createToken($user);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'NewSecure@Pass123',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * @test
     * T046: Contract test - rejects weak passwords
     */
    public function it_rejects_weak_passwords_for_reset()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = app('auth.password.broker')->createToken($user);

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
     * T046: Contract test - rejects invalid token
     */
    public function it_rejects_invalid_reset_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => 'invalid-token',
            'password' => 'NewSecure@Pass123',
            'password_confirmation' => 'NewSecure@Pass123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * @test
     * T046: Contract test - rejects expired token
     */
    public function it_rejects_expired_reset_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = app('auth.password.broker')->createToken($user);

        // Simulate token expiration by traveling forward in time
        $this->travel(25)->hours();

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'NewSecure@Pass123',
            'password_confirmation' => 'NewSecure@Pass123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
