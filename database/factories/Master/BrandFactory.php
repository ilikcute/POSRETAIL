<?php

namespace Database\Factories\Master;

use Illuminate\Database\Eloquent\Factories\Factory;

class BrandFactory extends Factory
{
    protected $model = \App\Models\Master\Brand::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'description' => $this->faker->catchPhrase(),
            'is_active' => true,
        ];
    }
}
