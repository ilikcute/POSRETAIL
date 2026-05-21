<?php

namespace Database\Factories\Master;

use App\Models\Master\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word().' '.$this->faker->randomNumber(2),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
