<?php

namespace Database\Factories\Master;

use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Product;
use App\Models\Master\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::inRandomOrder()->first()->id ?? Category::factory(),
            'brand_id' => Brand::inRandomOrder()->first()->id ?? Brand::factory(),
            'unit_id' => Unit::inRandomOrder()->first()->id ?? Unit::factory(),
            'code' => 'PRD-'.$this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->words(3, true),
            'sku' => strtoupper($this->faker->unique()->lexify('SKU-??????')),
            'barcode' => $this->faker->unique()->ean13(),
            'cost_price' => $this->faker->numberBetween(10000, 50000),
            'price' => $this->faker->numberBetween(55000, 100000),
            'wholesale_price' => $this->faker->numberBetween(51000, 54000),
            'description' => $this->faker->sentence(),
            'image_path' => null,
            'is_active' => true,
        ];
    }
}
