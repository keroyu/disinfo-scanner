<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * T044: Feature tests for Admin Payment Settings (US3)
 */
class PaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    protected function createAdmin(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $adminRole = Role::where('name', 'administrator')->first();
        $user->roles()->attach($adminRole);
        return $user;
    }

    protected function createRegularUser(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $memberRole = Role::where('name', 'regular_member')->first();
        $user->roles()->attach($memberRole);
        return $user;
    }

    // ========== Authorization Tests ==========

    public function test_admin_can_access_payment_settings(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/payment-settings');

        $response->assertStatus(200);
        $response->assertSee('付款設定');
    }

    public function test_regular_user_cannot_access_payment_settings(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->get('/admin/payment-settings');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/admin/payment-settings');

        $response->assertRedirect('/auth/login');
    }

    // ========== View Settings Tests ==========

    public function test_settings_page_displays_webhook_url(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/payment-settings');

        $response->assertStatus(200);
        $response->assertSee('Webhook URL');
        $response->assertSee('/api/webhooks/portaly');
    }

    public function test_settings_page_shows_secret_configured_status(): void
    {
        $admin = $this->createAdmin();
        // Set encrypted secret
        Setting::setValue('portaly_webhook_secret', Crypt::encryptString('test-secret-key-12345'));

        $response = $this->actingAs($admin)->get('/admin/payment-settings');

        $response->assertStatus(200);
        $response->assertSee('已設定');
    }

    public function test_settings_page_shows_not_configured_when_no_secret(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/payment-settings');

        $response->assertStatus(200);
        $response->assertSee('尚未設定');
    }

    // ========== Update Settings Tests ==========

    public function test_admin_can_update_webhook_secret(): void
    {
        $admin = $this->createAdmin();
        $newSecret = 'new-webhook-secret-key-12345';

        $response = $this->actingAs($admin)->post('/admin/payment-settings', [
            'portaly_webhook_secret' => $newSecret,
        ]);

        $response->assertRedirect('/admin/payment-settings');
        $response->assertSessionHas('success');

        // Verify secret is stored encrypted
        $storedValue = Setting::getValue('portaly_webhook_secret');
        $this->assertNotNull($storedValue);
        $this->assertNotEquals($newSecret, $storedValue); // Should be encrypted
        $this->assertEquals($newSecret, Crypt::decryptString($storedValue)); // Decrypted should match
    }

    public function test_update_validates_minimum_secret_length(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/payment-settings', [
            'portaly_webhook_secret' => 'short',
        ]);

        $response->assertSessionHasErrors('portaly_webhook_secret');
    }

    public function test_update_validates_secret_required(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/payment-settings', [
            'portaly_webhook_secret' => '',
        ]);

        $response->assertSessionHasErrors('portaly_webhook_secret');
    }

    public function test_regular_user_cannot_update_settings(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->post('/admin/payment-settings', [
            'portaly_webhook_secret' => 'test-secret-key-12345',
        ]);

        $response->assertStatus(403);
    }

    // ========== Audit Logging Tests ==========

    public function test_settings_update_creates_audit_log(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post('/admin/payment-settings', [
            'portaly_webhook_secret' => 'new-secret-key-12345',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $admin->id,
            'action_type' => 'payment_settings_updated',
            'resource_type' => 'payment_settings',
        ]);
    }

    public function test_audit_log_does_not_contain_secret_value(): void
    {
        $admin = $this->createAdmin();
        $secret = 'secret-should-not-be-logged';

        $this->actingAs($admin)->post('/admin/payment-settings', [
            'portaly_webhook_secret' => $secret,
        ]);

        $auditLog = AuditLog::where('action_type', 'payment_settings_updated')->first();
        $this->assertNotNull($auditLog);
        $this->assertStringNotContainsString($secret, json_encode($auditLog->changes ?? []));
    }

    // ========== Secret Masking Tests ==========

    public function test_settings_page_masks_existing_secret(): void
    {
        $admin = $this->createAdmin();
        Setting::setValue('portaly_webhook_secret', Crypt::encryptString('my-actual-secret'));

        $response = $this->actingAs($admin)->get('/admin/payment-settings');

        $response->assertStatus(200);
        // Should not expose the actual secret
        $response->assertDontSee('my-actual-secret');
    }
}
