<?php

namespace Database\Factories\Master;

use App\Models\Master\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'description' => $this->faker->catchPhrase(),
            'is_active' => true,
        ];
    }
}
