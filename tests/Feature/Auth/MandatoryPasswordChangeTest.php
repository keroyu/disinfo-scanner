<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

/**
 * Feature Test: Mandatory Password Change
 *
 * Tests User Story 2: Newly verified users must change default password
 * before accessing the platform
 */
class MandatoryPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    /**
     * @test
     * T047: User with default password is redirected to password change page
     */
    public function user_with_default_password_must_change_password_before_platform_access()
    {
        $user = User::factory()->create([
            'email' => 'newuser@example.com',
            'password' => bcrypt('123456'),
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        // Attempt to access platform homepage after login
        $response = $this->actingAs($user)->get('/');

        // Should be redirected to mandatory password change page
        $response->assertRedirect('/auth/mandatory-password-change');
    }

    /**
     * @test
     * T047: User with default password cannot bypass mandatory change
     */
    public function user_cannot_bypass_mandatory_password_change()
    {
        $user = User::factory()->create([
            'email' => 'newuser@example.com',
            'password' => bcrypt('123456'),
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        // Try to access various pages
        $protectedRoutes = [
            '/',
            '/videos',
            '/channels',
            '/comments',
            '/settings',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->actingAs($user)->get($route);
            $response->assertRedirect('/auth/mandatory-password-change');
        }
    }

    /**
     * @test
     * T047: User successfully changes default password
     */
    public function user_can_successfully_change_default_password()
    {
        $user = User::factory()->create([
            'email' => 'newuser@example.com',
            'password' => bcrypt('123456'),
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        // Change password
        $response = $this->actingAs($user)->postJson('/api/auth/password/change', [
            'current_password' => '123456',
            'new_password' => 'NewSecure@Pass123',
            'new_password_confirmation' => 'NewSecure@Pass123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify must_change_password flag is now false
        $user->refresh();
        $this->assertFalse($user->must_change_password);

        // Verify new password works
        $this->assertTrue(Hash::check('NewSecure@Pass123', $user->password));
    }

    /**
     * @test
     * T047: User with changed password can access platform
     */
    public function user_with_changed_password_can_access_platform()
    {
        $user = User::factory()->create([
            'email' => 'newuser@example.com',
            'password' => bcrypt('SecurePassword@123'),
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        // Should be able to access platform pages
        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);

        $response = $this->actingAs($user)->get('/videos');
        $response->assertStatus(200);
    }

    /**
     * @test
     * T047: Password change validates password strength
     */
    public function password_change_rejects_weak_passwords()
    {
        $user = User::factory()->create([
            'email' => 'newuser@example.com',
            'password' => bcrypt('123456'),
            'email_verified_at' => now(),
            'must_change_password' => true,
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

            // Verify password wasn't changed
            $user->refresh();
            $this->assertTrue($user->must_change_password);
        }
    }

    /**
     * @test
     * T047: Mandatory password change page is accessible
     */
    public function mandatory_password_change_page_is_accessible()
    {
        $user = User::factory()->create([
            'email' => 'newuser@example.com',
            'password' => bcrypt('123456'),
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->get('/auth/mandatory-password-change');
        $response->assertStatus(200);
    }

    /**
     * @test
     * T047: Admin account with default password also requires change
     */
    public function admin_with_default_password_must_change_password()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('DefaultAdmin123'),
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);

        $adminRole = Role::where('name', 'administrator')->first();
        $user->roles()->attach($adminRole->id);

        // Admin should also be redirected
        $response = $this->actingAs($user)->get('/');
        $response->assertRedirect('/auth/mandatory-password-change');
    }

    /**
     * @test
     * T047: User who has already changed password is not prompted again
     */
    public function user_not_prompted_to_change_password_on_subsequent_logins()
    {
        $user = User::factory()->create([
            'email' => 'returninguser@example.com',
            'password' => bcrypt('SecurePassword@123'),
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $regularRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($regularRole->id);

        // Login multiple times
        for ($i = 0; $i < 3; $i++) {
            $this->post('/api/auth/login', [
                'email' => 'returninguser@example.com',
                'password' => 'SecurePassword@123',
            ]);

            $response = $this->actingAs($user)->get('/');
            $response->assertStatus(200);
            $response->assertDontSee('mandatory-password-change');
        }
    }
}
