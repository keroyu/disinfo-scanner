<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\PaymentProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * T025-T026: Feature tests for user upgrade page
 */
class UpgradePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/upgrade');

        $response->assertRedirect('/auth/login');
    }

    public function test_authenticated_user_can_access_upgrade_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/upgrade');

        $response->assertStatus(200);
    }

    public function test_upgrade_page_displays_active_products(): void
    {
        $user = User::factory()->create();

        PaymentProduct::factory()->create([
            'name' => '30天高級會員',
            'price' => 190,
            'duration_days' => 30,
            'status' => 'active',
        ]);

        PaymentProduct::factory()->create([
            'name' => '年度會員',
            'price' => 1800,
            'duration_days' => 365,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/upgrade');

        $response->assertStatus(200);
        $response->assertSee('30天高級會員');
        $response->assertSee('年度會員');
        $response->assertSee('NT$ 190');
        $response->assertSee('NT$ 1,800');
        $response->assertSee('30 天');
        $response->assertSee('365 天');
    }

    public function test_upgrade_page_does_not_display_inactive_products(): void
    {
        $user = User::factory()->create();

        PaymentProduct::factory()->create([
            'name' => '已停用商品',
            'price' => 100,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($user)->get('/upgrade');

        $response->assertStatus(200);
        $response->assertDontSee('已停用商品');
    }

    public function test_upgrade_page_displays_email_warning(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/upgrade');

        $response->assertStatus(200);
        $response->assertSee('務必輸入與本站相同的 Email 帳號');
    }

    public function test_upgrade_page_shows_empty_state_when_no_products(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/upgrade');

        $response->assertStatus(200);
        $response->assertSee('目前沒有可購買的商品');
    }

    public function test_upgrade_page_contains_portaly_links(): void
    {
        $user = User::factory()->create();

        $product = PaymentProduct::factory()->create([
            'name' => '測試商品',
            'portaly_url' => 'https://portaly.cc/kyontw/product/test123',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/upgrade');

        $response->assertStatus(200);
        $response->assertSee('https://portaly.cc/kyontw/product/test123');
        $response->assertSee('立即購買');
    }

    public function test_upgrade_page_sorts_products_by_price_ascending(): void
    {
        $user = User::factory()->create();

        PaymentProduct::factory()->create([
            'name' => '貴商品',
            'price' => 1000,
            'status' => 'active',
        ]);

        PaymentProduct::factory()->create([
            'name' => '便宜商品',
            'price' => 100,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/upgrade');

        $response->assertStatus(200);
        // Cheap product should appear before expensive product
        $content = $response->getContent();
        $cheapPos = strpos($content, '便宜商品');
        $expensivePos = strpos($content, '貴商品');
        $this->assertLessThan($expensivePos, $cheapPos);
    }
}
