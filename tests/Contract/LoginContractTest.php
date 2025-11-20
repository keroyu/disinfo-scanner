<?php

namespace Tests\Contract;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Services\PasswordService;

class LoginContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test login endpoint accepts valid credentials.
     */
    public function test_login_endpoint_accepts_valid_credentials(): void
    {
        $passwordService = new PasswordService();

        User::factory()->create([
            'email' => 'user@example.com',
            'password' => $passwordService->hashPassword('ValidPass123!'),
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'ValidPass123!',
            'remember' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                ],
            ]);
    }

    /**
     * Test login endpoint rejects invalid password.
     */
    public function test_login_endpoint_rejects_invalid_password(): void
    {
        $passwordService = new PasswordService();

        User::factory()->create([
            'email' => 'user@example.com',
            'password' => $passwordService->hashPassword('CorrectPass123!'),
            'is_email_verified' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test login endpoint rejects unverified email.
     */
    public function test_login_endpoint_rejects_unverified_email(): void
    {
        $passwordService = new PasswordService();

        User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => $passwordService->hashPassword('ValidPass123!'),
            'is_email_verified' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'unverified@example.com',
            'password' => 'ValidPass123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test login endpoint rejects non-existent user.
     */
    public function test_login_endpoint_rejects_nonexistent_user(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'SomePass123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test login endpoint requires email field.
     */
    public function test_login_endpoint_requires_email_field(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'SomePass123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test login endpoint requires password field.
     */
    public function test_login_endpoint_requires_password_field(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test login response includes user data.
     */
    public function test_login_response_includes_user_data(): void
    {
        $passwordService = new PasswordService();

        User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => $passwordService->hashPassword('TestPass123!'),
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'testuser@example.com',
            'password' => 'TestPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.user.email', 'testuser@example.com')
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'email',
                        'name',
                    ],
                ],
            ]);
    }

    /**
     * Test login endpoint accepts optional remember parameter.
     */
    public function test_login_endpoint_accepts_remember_parameter(): void
    {
        $passwordService = new PasswordService();

        User::factory()->create([
            'email' => 'remember@example.com',
            'password' => $passwordService->hashPassword('RememberPass123!'),
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'remember@example.com',
            'password' => 'RememberPass123!',
            'remember' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
