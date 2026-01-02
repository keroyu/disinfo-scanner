<?php

namespace Tests\Feature\Admin;

use App\Models\PaymentLog;
use App\Models\PaymentProduct;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T052: Feature tests for Admin Payment Logs (US4)
 */
class PaymentLogTest extends TestCase
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

    public function test_admin_can_access_payment_logs(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/payment-logs');

        $response->assertStatus(200);
        $response->assertSee('付款紀錄');
    }

    public function test_regular_user_cannot_access_payment_logs(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->get('/admin/payment-logs');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/admin/payment-logs');

        $response->assertRedirect('/auth/login');
    }

    // ========== Index Tests ==========

    public function test_index_displays_payment_logs(): void
    {
        $admin = $this->createAdmin();
        $product = PaymentProduct::factory()->create();
        $log1 = PaymentLog::factory()->create([
            'customer_email' => 'user1@example.com',
            'product_id' => $product->id,
            'status' => PaymentLog::STATUS_SUCCESS,
        ]);
        $log2 = PaymentLog::factory()->create([
            'customer_email' => 'user2@example.com',
            'status' => PaymentLog::STATUS_USER_NOT_FOUND,
        ]);

        $response = $this->actingAs($admin)->get('/admin/payment-logs');

        $response->assertStatus(200);
        $response->assertSee('user1@example.com');
        $response->assertSee('user2@example.com');
    }

    public function test_index_shows_log_status_badges(): void
    {
        $admin = $this->createAdmin();
        PaymentLog::factory()->create(['status' => PaymentLog::STATUS_SUCCESS]);
        PaymentLog::factory()->create(['status' => PaymentLog::STATUS_USER_NOT_FOUND]);

        $response = $this->actingAs($admin)->get('/admin/payment-logs');

        $response->assertStatus(200);
        $response->assertSee('成功');
        $response->assertSee('用戶未找到');
    }

    public function test_index_shows_empty_state_when_no_logs(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/payment-logs');

        $response->assertStatus(200);
        $response->assertSee('目前沒有付款紀錄');
    }

    // ========== Filtering Tests ==========

    public function test_filter_by_status(): void
    {
        $admin = $this->createAdmin();
        PaymentLog::factory()->create([
            'customer_email' => 'success@example.com',
            'status' => PaymentLog::STATUS_SUCCESS,
        ]);
        PaymentLog::factory()->create([
            'customer_email' => 'failed@example.com',
            'status' => PaymentLog::STATUS_USER_NOT_FOUND,
        ]);

        $response = $this->actingAs($admin)->get('/admin/payment-logs?status=success');

        $response->assertStatus(200);
        $response->assertSee('success@example.com');
        $response->assertDontSee('failed@example.com');
    }

    public function test_filter_by_email(): void
    {
        $admin = $this->createAdmin();
        PaymentLog::factory()->create(['customer_email' => 'john@example.com']);
        PaymentLog::factory()->create(['customer_email' => 'jane@test.com']);

        $response = $this->actingAs($admin)->get('/admin/payment-logs?email=john');

        $response->assertStatus(200);
        $response->assertSee('john@example.com');
        $response->assertDontSee('jane@test.com');
    }

    public function test_filter_by_product(): void
    {
        $admin = $this->createAdmin();
        $product1 = PaymentProduct::factory()->create(['name' => 'Product A']);
        $product2 = PaymentProduct::factory()->create(['name' => 'Product B']);
        PaymentLog::factory()->create([
            'customer_email' => 'product-a@example.com',
            'product_id' => $product1->id,
        ]);
        PaymentLog::factory()->create([
            'customer_email' => 'product-b@example.com',
            'product_id' => $product2->id,
        ]);

        $response = $this->actingAs($admin)->get("/admin/payment-logs?product_id={$product1->id}");

        $response->assertStatus(200);
        $response->assertSee('product-a@example.com');
        $response->assertDontSee('product-b@example.com');
    }

    public function test_filter_by_date_range(): void
    {
        $admin = $this->createAdmin();
        PaymentLog::factory()->create([
            'customer_email' => 'old@example.com',
            'created_at' => now()->subDays(10),
        ]);
        PaymentLog::factory()->create([
            'customer_email' => 'recent@example.com',
            'created_at' => now()->subDay(),
        ]);

        $dateFrom = now()->subDays(5)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');
        $response = $this->actingAs($admin)->get("/admin/payment-logs?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertStatus(200);
        $response->assertSee('recent@example.com');
        $response->assertDontSee('old@example.com');
    }

    // ========== Pagination Tests ==========

    public function test_index_has_pagination(): void
    {
        $admin = $this->createAdmin();
        // Create 25 logs (default per_page is 20)
        PaymentLog::factory()->count(25)->create();

        $response = $this->actingAs($admin)->get('/admin/payment-logs');

        $response->assertStatus(200);
        // Should have pagination links
        $response->assertSee('page=2');
    }

    // ========== Show Detail Tests ==========

    public function test_admin_can_view_log_detail(): void
    {
        $admin = $this->createAdmin();
        $product = PaymentProduct::factory()->create(['name' => 'Test Product']);
        $log = PaymentLog::factory()->create([
            'customer_email' => 'detail@example.com',
            'product_id' => $product->id,
            'amount' => 190,
            'status' => PaymentLog::STATUS_SUCCESS,
        ]);

        $response = $this->actingAs($admin)->get("/admin/payment-logs/{$log->id}");

        $response->assertStatus(200);
        $response->assertSee('detail@example.com');
        $response->assertSee('Test Product');
    }

    public function test_log_detail_shows_raw_payload(): void
    {
        $admin = $this->createAdmin();
        $log = PaymentLog::factory()->create([
            'raw_payload' => [
                'event' => 'paid',
                'data' => ['id' => 'test-order-123'],
            ],
        ]);

        $response = $this->actingAs($admin)->get("/admin/payment-logs/{$log->id}");

        $response->assertStatus(200);
        $response->assertSee('test-order-123');
    }

    public function test_regular_user_cannot_view_log_detail(): void
    {
        $user = $this->createRegularUser();
        $log = PaymentLog::factory()->create();

        $response = $this->actingAs($user)->get("/admin/payment-logs/{$log->id}");

        $response->assertStatus(403);
    }

    // ========== Timezone Display Tests ==========

    public function test_log_displays_gmt8_timezone(): void
    {
        $admin = $this->createAdmin();
        // Create a log with a specific UTC time
        $log = PaymentLog::factory()->create([
            'created_at' => '2025-12-31 10:00:00', // 10:00 UTC = 18:00 GMT+8
        ]);

        $response = $this->actingAs($admin)->get('/admin/payment-logs');

        $response->assertStatus(200);
        // Should display in GMT+8 format
        $response->assertSee('18:00');
    }
}
