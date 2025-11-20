<?php

namespace Tests\Contract;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Role;

/**
 * Contract Test: Password Change Endpoint
 *
 * Tests the API contract for password change functionality
 * Validates request/response formats and status codes
 */
class PasswordChangeContractTest extends TestCase
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
     * T045: Contract test for password change endpoint - successful change
     */
    public function it_accepts_valid_password_change_request()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('123456'), // Default password
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);

        $regularRole = Role::where('slug', 'regular-member')->first();
        $user->roles()->attach($regularRole->id);

        $response = $this->actingAs($user)->postJson('/api/auth/password/change', [
            'current_password' => '123456',
            'new_password' => 'NewSecure@Pass123',
            'new_password_confirmation' => 'NewSecure@Pass123',
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
     * T045: Contract test - validates current password required
     */
    public function it_requires_current_password()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/auth/password/change', [
            'new_password' => 'NewSecure@Pass123',
            'new_password_confirmation' => 'NewSecure@Pass123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /**
     * @test
     * T045: Contract test - validates new password required
     */
    public function it_requires_new_password()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/auth/password/change', [
            'current_password' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /**
     * @test
     * T045: Contract test - validates password confirmation matches
     */
    public function it_requires_password_confirmation_match()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/auth/password/change', [
            'current_password' => '123456',
            'new_password' => 'NewSecure@Pass123',
            'new_password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /**
     * @test
     * T045: Contract test - rejects weak passwords
     */
    public function it_rejects_weak_passwords()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $weakPasswords = [
            'short',           // Too short
            'alllowercase',    // No uppercase, number, or special char
            'ALLUPPERCASE',    // No lowercase, number, or special char
            'NoNumbers!',      // No numbers
            'NoSpecial123',    // No special characters
        ];

        foreach ($weakPasswords as $weakPassword) {
            $response = $this->actingAs($user)->postJson('/api/auth/password/change', [
                'current_password' => '123456',
                'new_password' => $weakPassword,
                'new_password_confirmation' => $weakPassword,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['new_password']);
        }
    }

    /**
     * @test
     * T045: Contract test - rejects incorrect current password
     */
    public function it_rejects_incorrect_current_password()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('correct_password'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/auth/password/change', [
            'current_password' => 'wrong_password',
            'new_password' => 'NewSecure@Pass123',
            'new_password_confirmation' => 'NewSecure@Pass123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * @test
     * T045: Contract test - requires authentication
     */
    public function it_requires_authentication()
    {
        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => '123456',
            'new_password' => 'NewSecure@Pass123',
            'new_password_confirmation' => 'NewSecure@Pass123',
        ]);

        $response->assertStatus(401);
    }
}
