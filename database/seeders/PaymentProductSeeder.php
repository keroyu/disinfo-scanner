<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('payment_products')->updateOrInsert(
            ['portaly_product_id' => '07eMToUCpzTcsg8zKSDM'],
            [
                'name' => '30天高級會員',
                'portaly_product_id' => '07eMToUCpzTcsg8zKSDM',
                'portaly_url' => 'https://portaly.cc/kyontw/product/07eMToUCpzTcsg8zKSDM',
                'price' => 190,
                'currency' => 'TWD',
                'duration_days' => 30,
                'action_type' => 'extend_premium',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
