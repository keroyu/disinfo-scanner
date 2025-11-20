<?php

namespace Tests\Contract;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class RegistrationContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test registration endpoint accepts valid email.
     */
    public function test_registration_endpoint_accepts_valid_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'newuser@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'email',
                    'verification_sent',
                    'expires_in_hours',
                ],
            ]);
    }

    /**
     * Test registration endpoint rejects duplicate email.
     */
    public function test_registration_endpoint_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'existing@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }

    /**
     * Test registration endpoint rejects invalid email format.
     */
    public function test_registration_endpoint_rejects_invalid_email_format(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration endpoint requires email field.
     */
    public function test_registration_endpoint_requires_email_field(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration response includes all required fields.
     */
    public function test_registration_response_includes_required_fields(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.email', 'test@example.com')
            ->assertJsonPath('data.verification_sent', true)
            ->assertJsonPath('data.expires_in_hours', 24);
    }

    /**
     * Test registration endpoint returns correct HTTP status codes.
     */
    public function test_registration_endpoint_returns_correct_status_codes(): void
    {
        // Success case
        $response = $this->postJson('/api/auth/register', [
            'email' => 'success@example.com',
        ]);
        $response->assertStatus(201);

        // Validation error case
        $response = $this->postJson('/api/auth/register', [
            'email' => 'invalid-email',
        ]);
        $response->assertStatus(422);

        // Duplicate email case
        User::factory()->create(['email' => 'duplicate@example.com']);
        $response = $this->postJson('/api/auth/register', [
            'email' => 'duplicate@example.com',
        ]);
        $response->assertStatus(422);
    }
}
