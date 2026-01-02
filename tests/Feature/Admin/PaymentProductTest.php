<?php

namespace Tests\Feature\Admin;

use App\Models\PaymentProduct;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T033: Feature tests for Admin Payment Product Management (US2)
 */
class PaymentProductTest extends TestCase
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

    public function test_admin_can_access_payment_products_index(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/payment-products');

        $response->assertStatus(200);
        $response->assertSee('付款商品');
    }

    public function test_regular_user_cannot_access_payment_products(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->get('/admin/payment-products');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/admin/payment-products');

        $response->assertRedirect('/auth/login');
    }

    // ========== Index Tests ==========

    public function test_index_displays_all_products(): void
    {
        $admin = $this->createAdmin();
        $product1 = PaymentProduct::factory()->create(['name' => '30天高級會員']);
        $product2 = PaymentProduct::factory()->create(['name' => '年度會員']);

        $response = $this->actingAs($admin)->get('/admin/payment-products');

        $response->assertStatus(200);
        $response->assertSee('30天高級會員');
        $response->assertSee('年度會員');
    }

    public function test_index_shows_product_status(): void
    {
        $admin = $this->createAdmin();
        PaymentProduct::factory()->active()->create(['name' => '啟用商品']);
        PaymentProduct::factory()->inactive()->create(['name' => '停用商品']);

        $response = $this->actingAs($admin)->get('/admin/payment-products');

        $response->assertStatus(200);
        $response->assertSee('啟用商品');
        $response->assertSee('停用商品');
    }

    public function test_index_does_not_show_deleted_products(): void
    {
        $admin = $this->createAdmin();
        $product = PaymentProduct::factory()->create(['name' => '已刪除商品']);
        $product->delete(); // soft delete

        $response = $this->actingAs($admin)->get('/admin/payment-products');

        $response->assertDontSee('已刪除商品');
    }

    // ========== Create Tests ==========

    public function test_admin_can_access_create_form(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/payment-products/create');

        $response->assertStatus(200);
        $response->assertSee('新增付款商品');
    }

    public function test_admin_can_create_product(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/payment-products', [
            'name' => '測試商品',
            'portaly_product_id' => 'test-product-123',
            'portaly_url' => 'https://portaly.cc/kyontw/product/test-product-123',
            'price' => 299,
            'currency' => 'TWD',
            'duration_days' => 30,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $response->assertRedirect('/admin/payment-products');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payment_products', [
            'name' => '測試商品',
            'portaly_product_id' => 'test-product-123',
            'price' => 299,
        ]);
    }

    public function test_create_validation_requires_name(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/payment-products', [
            'name' => '',
            'portaly_product_id' => 'test-123',
            'portaly_url' => 'https://portaly.cc/kyontw/product/test-123',
            'price' => 299,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_create_validation_requires_unique_portaly_product_id(): void
    {
        $admin = $this->createAdmin();
        PaymentProduct::factory()->create(['portaly_product_id' => 'existing-id']);

        $response = $this->actingAs($admin)->post('/admin/payment-products', [
            'name' => '新商品',
            'portaly_product_id' => 'existing-id',
            'portaly_url' => 'https://portaly.cc/kyontw/product/existing-id',
            'price' => 299,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('portaly_product_id');
    }

    public function test_create_validation_requires_valid_url(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/payment-products', [
            'name' => '測試商品',
            'portaly_product_id' => 'test-123',
            'portaly_url' => 'not-a-valid-url',
            'price' => 299,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('portaly_url');
    }

    public function test_create_validation_requires_positive_price(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/payment-products', [
            'name' => '測試商品',
            'portaly_product_id' => 'test-123',
            'portaly_url' => 'https://portaly.cc/kyontw/product/test-123',
            'price' => 0,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('price');
    }

    // ========== Edit Tests ==========

    public function test_admin_can_access_edit_form(): void
    {
        $admin = $this->createAdmin();
        $product = PaymentProduct::factory()->create();

        $response = $this->actingAs($admin)->get("/admin/payment-products/{$product->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('編輯付款商品');
        $response->assertSee($product->name);
    }

    public function test_admin_can_update_product(): void
    {
        $admin = $this->createAdmin();
        $product = PaymentProduct::factory()->create(['price' => 190]);

        $response = $this->actingAs($admin)->put("/admin/payment-products/{$product->id}", [
            'name' => '更新後商品',
            'portaly_product_id' => $product->portaly_product_id,
            'portaly_url' => $product->portaly_url,
            'price' => 250,
            'currency' => 'TWD',
            'duration_days' => 30,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $response->assertRedirect('/admin/payment-products');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payment_products', [
            'id' => $product->id,
            'name' => '更新後商品',
            'price' => 250,
        ]);
    }

    public function test_update_allows_same_portaly_product_id(): void
    {
        $admin = $this->createAdmin();
        $product = PaymentProduct::factory()->create(['portaly_product_id' => 'same-id']);

        $response = $this->actingAs($admin)->put("/admin/payment-products/{$product->id}", [
            'name' => '更新名稱',
            'portaly_product_id' => 'same-id', // Same ID should be allowed
            'portaly_url' => $product->portaly_url,
            'price' => 299,
            'currency' => 'TWD',
            'duration_days' => 30,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $response->assertRedirect('/admin/payment-products');
        $response->assertSessionHas('success');
    }

    public function test_update_prevents_duplicate_portaly_product_id(): void
    {
        $admin = $this->createAdmin();
        $product1 = PaymentProduct::factory()->create(['portaly_product_id' => 'id-1']);
        $product2 = PaymentProduct::factory()->create(['portaly_product_id' => 'id-2']);

        $response = $this->actingAs($admin)->put("/admin/payment-products/{$product2->id}", [
            'name' => '更新',
            'portaly_product_id' => 'id-1', // Trying to use product1's ID
            'portaly_url' => $product2->portaly_url,
            'price' => 299,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('portaly_product_id');
    }

    // ========== Delete Tests ==========

    public function test_admin_can_delete_product(): void
    {
        $admin = $this->createAdmin();
        $product = PaymentProduct::factory()->create();

        $response = $this->actingAs($admin)->delete("/admin/payment-products/{$product->id}");

        $response->assertRedirect('/admin/payment-products');
        $response->assertSessionHas('success');

        // Soft delete - record exists but has deleted_at
        $this->assertSoftDeleted('payment_products', ['id' => $product->id]);
    }

    public function test_delete_is_soft_delete(): void
    {
        $admin = $this->createAdmin();
        $product = PaymentProduct::factory()->create();
        $productId = $product->id;

        $this->actingAs($admin)->delete("/admin/payment-products/{$product->id}");

        // Record still exists in database
        $this->assertDatabaseHas('payment_products', ['id' => $productId]);
        // But deleted_at is not null
        $deletedProduct = PaymentProduct::withTrashed()->find($productId);
        $this->assertNotNull($deletedProduct->deleted_at);
    }

    // ========== Toggle Status Tests ==========

    public function test_admin_can_toggle_product_status_to_inactive(): void
    {
        $admin = $this->createAdmin();
        $product = PaymentProduct::factory()->active()->create();

        $response = $this->actingAs($admin)->patch("/admin/payment-products/{$product->id}/toggle-status");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $product->refresh();
        $this->assertEquals('inactive', $product->status);
    }

    public function test_admin_can_toggle_product_status_to_active(): void
    {
        $admin = $this->createAdmin();
        $product = PaymentProduct::factory()->inactive()->create();

        $response = $this->actingAs($admin)->patch("/admin/payment-products/{$product->id}/toggle-status");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $product->refresh();
        $this->assertEquals('active', $product->status);
    }

    // ========== Authorization for CRUD ==========

    public function test_regular_user_cannot_create_product(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->post('/admin/payment-products', [
            'name' => '測試',
            'portaly_product_id' => 'test',
            'portaly_url' => 'https://example.com',
            'price' => 100,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_update_product(): void
    {
        $user = $this->createRegularUser();
        $product = PaymentProduct::factory()->create();

        $response = $this->actingAs($user)->put("/admin/payment-products/{$product->id}", [
            'name' => '更新',
            'portaly_product_id' => $product->portaly_product_id,
            'portaly_url' => $product->portaly_url,
            'price' => 100,
            'action_type' => 'extend_premium',
            'status' => 'active',
        ]);

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_delete_product(): void
    {
        $user = $this->createRegularUser();
        $product = PaymentProduct::factory()->create();

        $response = $this->actingAs($user)->delete("/admin/payment-products/{$product->id}");

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_toggle_status(): void
    {
        $user = $this->createRegularUser();
        $product = PaymentProduct::factory()->create();

        $response = $this->actingAs($user)->patch("/admin/payment-products/{$product->id}/toggle-status");

        $response->assertStatus(403);
    }
}
