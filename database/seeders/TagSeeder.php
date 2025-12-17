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
                'description' => 'Pan-White camp (Taiwan People\'s Party, Ko Wen-je supporters)',
                'color' => 'sky-400',
            ],
            [
                'code' => 'pan-blue',
                'name' => '泛藍',
                'description' => 'Pan-Blue camp (Kuomintang, related parties)',
                'color' => 'blue-600',
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
            [
                'code' => 'editorial',
                'name' => '社論',
                'description' => 'Editorial/Opinion content',
                'color' => 'purple-600',
            ],
            [
                'code' => 'control-group',
                'name' => '對照組',
                'description' => 'Control group for comparison',
                'color' => 'gray-500',
            ],
            [
                'code' => 'foreigner',
                'name' => '外國人',
                'description' => 'Foreign/International content or perspective',
                'color' => 'purple-500',
            ],
        ];

        foreach ($tags as $tag) {
            \App\Models\Tag::updateOrCreate(
                ['code' => $tag['code']],
                $tag
            );
        }
    }
}
