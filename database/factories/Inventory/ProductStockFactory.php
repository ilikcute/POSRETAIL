<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\ProductStock;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductStockFactory extends Factory
{
    protected $model = ProductStock::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::inRandomOrder()->first()->id ?? Product::factory(),
            'product_variant_id' => null, // Biarkan null secara default untuk factory
            'warehouse_id' => Warehouse::inRandomOrder()->first()->id ?? Warehouse::factory(),
            'rack_id' => null,
            'qty' => $this->faker->numberBetween(10, 500),
            'min_qty' => $this->faker->numberBetween(5, 20),
        ];
    }
}
