<?php

namespace Database\Factories;

use App\Models\PaymentLog;
use App\Models\PaymentProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentLog>
 */
class PaymentLogFactory extends Factory
{
    protected $model = PaymentLog::class;

    public function definition(): array
    {
        return [
            'order_id' => $this->faker->unique()->uuid(),
            'event_type' => 'paid',
            'product_id' => PaymentProduct::factory(),
            'portaly_product_id' => $this->faker->uuid(),
            'customer_email' => $this->faker->email(),
            'customer_name' => $this->faker->name(),
            'user_id' => User::factory(),
            'amount' => $this->faker->randomElement([190, 500, 1800]),
            'currency' => 'TWD',
            'net_total' => $this->faker->randomElement([171, 450, 1620]),
            'payment_method' => 'tappay',
            'status' => 'success',
            'raw_payload' => json_encode(['test' => true]),
            'trace_id' => (string) Str::uuid(),
            'processed_at' => now(),
            'created_at' => now(),
        ];
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'refund',
            'status' => 'refund',
        ]);
    }

    public function userNotFound(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'status' => 'user_not_found',
            'processed_at' => null,
        ]);
    }

    public function productNotFound(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => null,
            'status' => 'product_not_found',
            'processed_at' => null,
        ]);
    }
}
