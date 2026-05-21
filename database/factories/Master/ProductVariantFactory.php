<?php

namespace Database\Factories\Master;

use App\Models\Master\Product;
use App\Models\Master\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::inRandomOrder()->first()->id ?? Product::factory(),
            'name' => 'Ukuran '.$this->faker->randomElement(['S', 'M', 'L', 'XL']).' - '.$this->faker->colorName(),
            'sku' => strtoupper($this->faker->unique()->lexify('VAR-??????')),
            'barcode' => $this->faker->unique()->ean13(),
            'cost_price' => $this->faker->randomElement([null, $this->faker->numberBetween(10000, 50000)]),
            'price' => $this->faker->randomElement([null, $this->faker->numberBetween(55000, 100000)]),
            'wholesale_price' => $this->faker->randomElement([null, $this->faker->numberBetween(51000, 54000)]),
            'is_active' => true,
        ];
    }
}
