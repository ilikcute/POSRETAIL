<?php

namespace Database\Seeders;

use App\Models\Master\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Makanan Ringan',
            'Minuman Dingin',
            'Kebutuhan Sehari-hari',
            'Peralatan Mandi',
            'Sembako',
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category,
                'description' => 'Kategori produk '.strtolower($category),
                'is_active' => true,
            ]);
        }
    }
}
