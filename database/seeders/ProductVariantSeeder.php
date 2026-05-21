<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\ProductVariant;
use App\Models\Master\Product;

class ProductVariantSeeder extends Seeder
{
    public function run(): void
    {
        $product1 = Product::where('code', 'PRD-001')->first();
        
        if ($product1) {
            ProductVariant::create([
                'product_id' => $product1->id,
                'name' => 'Kemasan Ekonomis',
                'sku' => 'CHT-SAPI-EKO',
                'barcode' => '8991234567891',
                'cost_price' => 3000,
                'price' => 5000,
                'wholesale_price' => 4500,
                'is_active' => true,
            ]);

            ProductVariant::create([
                'product_id' => $product1->id,
                'name' => 'Kemasan Jumbo',
                'sku' => 'CHT-SAPI-JMB',
                'barcode' => '8991234567892',
                'cost_price' => 15000,
                'price' => 20000,
                'wholesale_price' => 18000,
                'is_active' => true,
            ]);
        }

        ProductVariant::factory(15)->create();
    }
}
