<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /** @test */
    public function web_login_redirects_to_index_page_after_successful_login()
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Test@1234'),
            'is_email_verified' => true,
            'has_default_password' => false, // Not using default password
        ]);
        $user->roles()->attach($role);

        $response = $this->post(route('login.submit'), [
            'email' => 'test@example.com',
            'password' => 'Test@1234',
        ]);

        $response->assertRedirect(route('import.index'));
        $response->assertSessionHas('status', '登入成功！歡迎回來');
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function web_login_redirects_to_password_change_if_user_has_default_password()
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('123456'), // Default password
            'is_email_verified' => true,
            'has_default_password' => true,
        ]);
        $user->roles()->attach($role);

        $response = $this->post(route('login.submit'), [
            'email' => 'test@example.com',
            'password' => '123456',
        ]);

        $response->assertRedirect(route('password.mandatory'));
        $response->assertSessionHas('status', '首次登入需要更改預設密碼');
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function web_login_redirects_back_with_errors_on_invalid_credentials()
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Test@1234'),
            'is_email_verified' => true,
        ]);
        $user->roles()->attach($role);

        $response = $this->post(route('login.submit'), [
            'email' => 'test@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** @test */
    public function api_login_returns_json_response()
    {
        $role = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Test@1234'),
            'is_email_verified' => true,
            'has_default_password' => false,
        ]);
        $user->roles()->attach($role);

        $response = $this->postJson(route('login.submit'), [
            'email' => 'test@example.com',
            'password' => 'Test@1234',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => '登入成功',
        ]);
        $this->assertAuthenticatedAs($user);
    }
}
