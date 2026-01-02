<?php

namespace Database\Factories;

use App\Models\PaymentProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentProduct>
 */
class PaymentProductFactory extends Factory
{
    protected $model = PaymentProduct::class;

    public function definition(): array
    {
        $productId = $this->faker->unique()->uuid();

        return [
            'name' => $this->faker->randomElement(['30天高級會員', '年度會員', '90天高級會員']),
            'portaly_product_id' => $productId,
            'portaly_url' => 'https://portaly.cc/kyontw/product/' . $productId,
            'price' => $this->faker->randomElement([190, 500, 1800]),
            'currency' => 'TWD',
            'duration_days' => $this->faker->randomElement([30, 90, 365]),
            'action_type' => 'extend_premium',
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }
}
