<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Services\PasswordService;
use Illuminate\Support\Facades\Auth;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete login flow authenticates verified user.
     */
    public function test_complete_login_flow_authenticates_verified_user(): void
    {
        $passwordService = new PasswordService();

        $user = User::factory()->create([
            'email' => 'verified@example.com',
            'password' => $passwordService->hashPassword('ValidPass123!'),
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'verified@example.com',
            'password' => 'ValidPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify user is authenticated
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test login with incorrect password fails.
     */
    public function test_login_with_incorrect_password_fails(): void
    {
        $passwordService = new PasswordService();

        User::factory()->create([
            'email' => 'wrongpass@example.com',
            'password' => $passwordService->hashPassword('CorrectPass123!'),
            'is_email_verified' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'WrongPass123!',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);

        $this->assertGuest();
    }

    /**
     * Test login with unverified email fails.
     */
    public function test_login_with_unverified_email_fails(): void
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
            ->assertJson(['success' => false]);

        $this->assertGuest();
    }

    /**
     * Test login with non-existent email fails.
     */
    public function test_login_with_nonexistent_email_fails(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'SomePass123!',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);

        $this->assertGuest();
    }

    /**
     * Test login with remember me option persists session.
     */
    public function test_login_with_remember_me_persists_session(): void
    {
        $passwordService = new PasswordService();

        $user = User::factory()->create([
            'email' => 'remember@example.com',
            'password' => $passwordService->hashPassword('RememberPass123!'),
            'is_email_verified' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'remember@example.com',
            'password' => 'RememberPass123!',
            'remember' => true,
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);

        // Verify remember token is set
        $user->refresh();
        $this->assertNotNull($user->remember_token);
    }

    /**
     * Test login without remember me does not set remember token.
     */
    public function test_login_without_remember_me_does_not_set_token(): void
    {
        $passwordService = new PasswordService();

        $user = User::factory()->create([
            'email' => 'noremember@example.com',
            'password' => $passwordService->hashPassword('NoRememberPass123!'),
            'is_email_verified' => true,
            'remember_token' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'noremember@example.com',
            'password' => 'NoRememberPass123!',
            'remember' => false,
        ]);

        $this->assertAuthenticatedAs($user);

        // Remember token should still be null
        $user->refresh();
        $this->assertNull($user->remember_token);
    }

    /**
     * Test login validates required fields.
     */
    public function test_login_validates_required_fields(): void
    {
        // Missing email
        $response = $this->postJson('/api/auth/login', [
            'password' => 'SomePass123!',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Missing password
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Missing both
        $response = $this->postJson('/api/auth/login', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Test successful login returns user data.
     */
    public function test_successful_login_returns_user_data(): void
    {
        $passwordService = new PasswordService();

        $user = User::factory()->create([
            'email' => 'userdata@example.com',
            'name' => 'Test User',
            'password' => $passwordService->hashPassword('UserPass123!'),
            'is_email_verified' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'userdata@example.com',
            'password' => 'UserPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => 'userdata@example.com',
                        'name' => 'Test User',
                    ],
                ],
            ]);
    }

    /**
     * Test logout invalidates session.
     */
    public function test_logout_invalidates_session(): void
    {
        $passwordService = new PasswordService();

        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => $passwordService->hashPassword('LogoutPass123!'),
            'is_email_verified' => true,
        ]);

        // Login first
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'logout@example.com',
            'password' => 'LogoutPass123!',
        ]);

        $loginResponse->assertStatus(200);

        // For logout test, we need to authenticate the user explicitly since
        // postJson() doesn't maintain sessions between requests
        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        // Now logout
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $this->assertGuest();
    }
}
