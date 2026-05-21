<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Brand;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'Indofood',
            'Unilever',
            'Nestle',
            'Wings',
            'Coca-Cola',
            'Danone'
        ];

        foreach ($brands as $brand) {
            Brand::create([
                'name' => $brand,
                'description' => 'Merek resmi ' . $brand,
                'is_active' => true,
            ]);
        }
    }
}
