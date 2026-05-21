<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Product;
use App\Models\Master\Category;
use App\Models\Master\Brand;
use App\Models\Master\Unit;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('name', 'Makanan Ringan')->first();
        $brand = Brand::where('name', 'Indofood')->first();
        $unit = Unit::where('short_name', 'PCS')->first();

        if ($category && $brand && $unit) {
            Product::create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'unit_id' => $unit->id,
                'code' => 'PRD-001',
                'name' => 'Chitato Rasa Sapi Panggang 68gr',
                'sku' => 'CHT-SAPI-68',
                'barcode' => '8991234567890',
                'cost_price' => 8000,
                'price' => 12000,
                'wholesale_price' => 11000,
                'description' => 'Keripik kentang chitato',
                'is_active' => true,
            ]);

            Product::create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'unit_id' => $unit->id,
                'code' => 'PRD-002',
                'name' => 'Indomie Goreng Spesial',
                'sku' => 'IND-GRG-SPS',
                'barcode' => '8990987654321',
                'cost_price' => 2500,
                'price' => 3500,
                'wholesale_price' => 3200,
                'description' => 'Mie instan goreng',
                'is_active' => true,
            ]);
        }

        // Generate 10 random products
        Product::factory(10)->create();
    }
}
