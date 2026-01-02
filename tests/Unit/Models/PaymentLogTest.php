<?php

namespace Tests\Unit\Models;

use App\Models\PaymentLog;
use App\Models\PaymentProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T053: Unit tests for PaymentLog model (US4)
 */
class PaymentLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    // ========== Relationship Tests ==========

    public function test_belongs_to_product(): void
    {
        $product = PaymentProduct::factory()->create();
        $log = PaymentLog::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(PaymentProduct::class, $log->product);
        $this->assertEquals($product->id, $log->product->id);
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $log = PaymentLog::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $log->user);
        $this->assertEquals($user->id, $log->user->id);
    }

    public function test_product_can_be_null(): void
    {
        $log = PaymentLog::factory()->create(['product_id' => null]);

        $this->assertNull($log->product);
    }

    public function test_user_can_be_null(): void
    {
        $log = PaymentLog::factory()->create(['user_id' => null]);

        $this->assertNull($log->user);
    }

    // ========== Accessor Tests ==========

    public function test_formatted_amount_accessor(): void
    {
        $log = PaymentLog::factory()->create([
            'amount' => 1990,
            'currency' => 'TWD',
        ]);

        $this->assertEquals('TWD 1,990', $log->formatted_amount);
    }

    public function test_status_badge_class_for_success(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_SUCCESS]);

        $this->assertStringContainsString('green', $log->status_badge_class);
    }

    public function test_status_badge_class_for_user_not_found(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_USER_NOT_FOUND]);

        $this->assertStringContainsString('yellow', $log->status_badge_class);
    }

    public function test_status_badge_class_for_signature_invalid(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_SIGNATURE_INVALID]);

        $this->assertStringContainsString('red', $log->status_badge_class);
    }

    public function test_status_badge_class_for_refund(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_REFUND]);

        $this->assertStringContainsString('purple', $log->status_badge_class);
    }

    public function test_status_label_for_success(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_SUCCESS]);

        $this->assertEquals('成功', $log->status_label);
    }

    public function test_status_label_for_user_not_found(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_USER_NOT_FOUND]);

        $this->assertEquals('用戶未找到', $log->status_label);
    }

    public function test_status_label_for_product_not_found(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_PRODUCT_NOT_FOUND]);

        $this->assertEquals('商品未找到', $log->status_label);
    }

    public function test_status_label_for_product_inactive(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_PRODUCT_INACTIVE]);

        $this->assertEquals('商品已停用', $log->status_label);
    }

    public function test_status_label_for_signature_invalid(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_SIGNATURE_INVALID]);

        $this->assertEquals('簽名無效', $log->status_label);
    }

    public function test_status_label_for_duplicate(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_DUPLICATE]);

        $this->assertEquals('重複訂單', $log->status_label);
    }

    public function test_status_label_for_refund(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_REFUND]);

        $this->assertEquals('退款', $log->status_label);
    }

    public function test_status_label_for_settings_not_configured(): void
    {
        $log = PaymentLog::factory()->create(['status' => PaymentLog::STATUS_SETTINGS_NOT_CONFIGURED]);

        $this->assertEquals('設定未配置', $log->status_label);
    }

    // ========== Static Method Tests ==========

    public function test_order_exists_returns_true_for_existing_order(): void
    {
        $log = PaymentLog::factory()->create(['order_id' => 'existing-order-123']);

        $this->assertTrue(PaymentLog::orderExists('existing-order-123'));
    }

    public function test_order_exists_returns_false_for_non_existing_order(): void
    {
        $this->assertFalse(PaymentLog::orderExists('non-existing-order'));
    }

    // ========== Scope Tests ==========

    public function test_scope_status_filters_correctly(): void
    {
        PaymentLog::factory()->create(['status' => PaymentLog::STATUS_SUCCESS]);
        PaymentLog::factory()->create(['status' => PaymentLog::STATUS_USER_NOT_FOUND]);

        $results = PaymentLog::status(PaymentLog::STATUS_SUCCESS)->get();

        $this->assertCount(1, $results);
        $this->assertEquals(PaymentLog::STATUS_SUCCESS, $results->first()->status);
    }

    public function test_scope_email_filters_correctly(): void
    {
        PaymentLog::factory()->create(['customer_email' => 'john@example.com']);
        PaymentLog::factory()->create(['customer_email' => 'jane@test.com']);

        $results = PaymentLog::email('john')->get();

        $this->assertCount(1, $results);
        $this->assertStringContainsString('john', $results->first()->customer_email);
    }

    public function test_scope_date_range_filters_correctly(): void
    {
        PaymentLog::factory()->create(['created_at' => now()->subDays(10)]);
        PaymentLog::factory()->create(['created_at' => now()->subDay()]);

        $results = PaymentLog::dateRange(
            now()->subDays(5)->format('Y-m-d'),
            now()->format('Y-m-d')
        )->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_date_range_with_null_from(): void
    {
        PaymentLog::factory()->create(['created_at' => now()->subDays(10)]);
        PaymentLog::factory()->create(['created_at' => now()->subDay()]);

        $results = PaymentLog::dateRange(null, now()->subDays(5)->format('Y-m-d'))->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_date_range_with_null_to(): void
    {
        PaymentLog::factory()->create(['created_at' => now()->subDays(10)]);
        PaymentLog::factory()->create(['created_at' => now()->subDay()]);

        $results = PaymentLog::dateRange(now()->subDays(5)->format('Y-m-d'), null)->get();

        $this->assertCount(1, $results);
    }

    // ========== Cast Tests ==========

    public function test_raw_payload_is_cast_to_array(): void
    {
        $payload = ['event' => 'paid', 'data' => ['id' => '123']];
        $log = PaymentLog::factory()->create(['raw_payload' => $payload]);

        $log->refresh();

        $this->assertIsArray($log->raw_payload);
        $this->assertEquals('paid', $log->raw_payload['event']);
    }

    public function test_amount_is_cast_to_integer(): void
    {
        $log = PaymentLog::factory()->create(['amount' => '190']);

        $log->refresh();

        $this->assertIsInt($log->amount);
    }

    public function test_created_at_is_cast_to_datetime(): void
    {
        $log = PaymentLog::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $log->created_at);
    }
}
