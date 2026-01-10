<?php

namespace Tests\Unit\Models;

use App\Models\PaymentLog;
use App\Models\PaymentProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T034: Unit tests for PaymentProduct model methods
 */
class PaymentProductTest extends TestCase
{
    use RefreshDatabase;

    // ========== Scope Tests ==========

    public function test_active_scope_returns_only_active_products(): void
    {
        PaymentProduct::factory()->active()->create(['name' => 'Active 1']);
        PaymentProduct::factory()->active()->create(['name' => 'Active 2']);
        PaymentProduct::factory()->inactive()->create(['name' => 'Inactive 1']);

        $activeProducts = PaymentProduct::active()->get();

        $this->assertCount(2, $activeProducts);
        $this->assertTrue($activeProducts->contains('name', 'Active 1'));
        $this->assertTrue($activeProducts->contains('name', 'Active 2'));
        $this->assertFalse($activeProducts->contains('name', 'Inactive 1'));
    }

    // ========== Status Tests ==========

    public function test_is_active_returns_true_for_active_product(): void
    {
        $product = PaymentProduct::factory()->active()->create();

        $this->assertTrue($product->isActive());
    }

    public function test_is_active_returns_false_for_inactive_product(): void
    {
        $product = PaymentProduct::factory()->inactive()->create();

        $this->assertFalse($product->isActive());
    }

    // ========== Formatted Attribute Tests ==========

    public function test_formatted_price_returns_correct_format(): void
    {
        $product = PaymentProduct::factory()->create(['price' => 1800]);

        $this->assertEquals('NT$ 1,800', $product->formatted_price);
    }

    public function test_formatted_price_handles_small_values(): void
    {
        $product = PaymentProduct::factory()->create(['price' => 99]);

        $this->assertEquals('NT$ 99', $product->formatted_price);
    }

    public function test_formatted_duration_returns_correct_format(): void
    {
        $product = PaymentProduct::factory()->create(['duration_days' => 30]);

        $this->assertEquals('30 天', $product->formatted_duration);
    }

    public function test_formatted_duration_returns_dash_for_null(): void
    {
        $product = PaymentProduct::factory()->create(['duration_days' => null]);

        $this->assertEquals('-', $product->formatted_duration);
    }

    // ========== Static Finder Tests ==========

    public function test_find_active_by_portaly_id_returns_active_product(): void
    {
        $product = PaymentProduct::factory()->active()->create([
            'portaly_product_id' => 'test-id-123',
        ]);

        $found = PaymentProduct::findActiveByPortalyId('test-id-123');

        $this->assertNotNull($found);
        $this->assertEquals($product->id, $found->id);
    }

    public function test_find_active_by_portaly_id_returns_null_for_inactive(): void
    {
        PaymentProduct::factory()->inactive()->create([
            'portaly_product_id' => 'inactive-id',
        ]);

        $found = PaymentProduct::findActiveByPortalyId('inactive-id');

        $this->assertNull($found);
    }

    public function test_find_active_by_portaly_id_returns_null_for_not_found(): void
    {
        $found = PaymentProduct::findActiveByPortalyId('nonexistent-id');

        $this->assertNull($found);
    }

    public function test_find_by_portaly_id_returns_product_regardless_of_status(): void
    {
        $product = PaymentProduct::factory()->inactive()->create([
            'portaly_product_id' => 'any-id',
        ]);

        $found = PaymentProduct::findByPortalyId('any-id');

        $this->assertNotNull($found);
        $this->assertEquals($product->id, $found->id);
    }

    public function test_find_by_portaly_id_returns_null_for_not_found(): void
    {
        $found = PaymentProduct::findByPortalyId('nonexistent');

        $this->assertNull($found);
    }

    // ========== Relationship Tests ==========

    public function test_payment_logs_relationship(): void
    {
        $product = PaymentProduct::factory()->create();

        // Create some payment logs for this product
        PaymentLog::factory()->count(3)->create([
            'product_id' => $product->id,
        ]);

        // Create a log for a different product
        $otherProduct = PaymentProduct::factory()->create();
        PaymentLog::factory()->create(['product_id' => $otherProduct->id]);

        $this->assertCount(3, $product->paymentLogs);
        $this->assertInstanceOf(PaymentLog::class, $product->paymentLogs->first());
    }

    // ========== Soft Delete Tests ==========

    public function test_soft_delete_sets_deleted_at(): void
    {
        $product = PaymentProduct::factory()->create();
        $productId = $product->id;

        $product->delete();

        $this->assertSoftDeleted('payment_products', ['id' => $productId]);
    }

    public function test_soft_deleted_products_excluded_from_query(): void
    {
        $activeProduct = PaymentProduct::factory()->create(['name' => 'Active']);
        $deletedProduct = PaymentProduct::factory()->create(['name' => 'Deleted']);
        $deletedProduct->delete();

        $products = PaymentProduct::all();

        $this->assertCount(1, $products);
        $this->assertEquals('Active', $products->first()->name);
    }

    public function test_with_trashed_includes_deleted(): void
    {
        $activeProduct = PaymentProduct::factory()->create();
        $deletedProduct = PaymentProduct::factory()->create();
        $deletedProduct->delete();

        $products = PaymentProduct::withTrashed()->get();

        $this->assertCount(2, $products);
    }

    // ========== Fillable Tests ==========

    public function test_mass_assignment_works_for_fillable_fields(): void
    {
        $product = PaymentProduct::create([
            'name' => '測試商品',
            'portaly_product_id' => 'mass-assign-test',
            'portaly_url' => 'https://portaly.cc/test',
            'price' => 500,
            'currency' => 'TWD',
            'duration_days' => 90,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $this->assertEquals('測試商品', $product->name);
        $this->assertEquals('mass-assign-test', $product->portaly_product_id);
        $this->assertEquals(500, $product->price);
    }

    // ========== Cast Tests ==========

    public function test_price_is_cast_to_integer(): void
    {
        $product = PaymentProduct::factory()->create(['price' => 190]);

        $this->assertIsInt($product->price);
    }

    public function test_duration_days_is_cast_to_integer(): void
    {
        $product = PaymentProduct::factory()->create(['duration_days' => 30]);

        $this->assertIsInt($product->duration_days);
    }
}
