<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            [
                'code' => 'pan-green',
                'name' => '泛綠',
                'description' => 'Pan-Green camp (Democratic Progressive Party)',
                'color' => 'green-500',
            ],
            [
                'code' => 'pan-white',
                'name' => '泛白',
                'description' => 'Pan-White camp (Kuomintang, People First Party)',
                'color' => 'blue-500',
            ],
            [
                'code' => 'pan-red',
                'name' => '泛紅',
                'description' => 'Pro-unification or Beijing-aligned',
                'color' => 'red-500',
            ],
            [
                'code' => 'anti-communist',
                'name' => '反共',
                'description' => 'Anti-communist/pro-independence',
                'color' => 'orange-500',
            ],
            [
                'code' => 'china-stance',
                'name' => '中國立場',
                'description' => 'Pro-China stance',
                'color' => 'rose-600',
            ],
        ];

        foreach ($tags as $tag) {
            \App\Models\Tag::create($tag);
        }
    }
}
