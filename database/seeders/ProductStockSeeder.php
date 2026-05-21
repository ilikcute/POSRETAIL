<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory\ProductStock;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use App\Models\Master\Rack;

class ProductStockSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::first();
        $rack = Rack::first();
        $product = Product::where('code', 'PRD-001')->first();

        if ($warehouse && $product) {
            ProductStock::create([
                'product_id' => $product->id,
                'product_variant_id' => null, // Stok Induk (jika tidak pakai varian)
                'warehouse_id' => $warehouse->id,
                'rack_id' => $rack ? $rack->id : null,
                'qty' => 150.50,
                'min_qty' => 10,
            ]);
        }

        ProductStock::factory(20)->create();
    }
}
