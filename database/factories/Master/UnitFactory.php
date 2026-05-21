<?php

namespace Database\Factories\Master;

use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = \App\Models\Master\Unit::class;

    public function definition(): array
    {
        return [
            'name' => 'Unit ' . $this->faker->word(),
            'short_name' => strtoupper($this->faker->unique()->lexify('???')),
            'is_active' => true,
        ];
    }
}
