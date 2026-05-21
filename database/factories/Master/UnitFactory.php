<?php

namespace Database\Factories\Master;

use App\Models\Master\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'name' => 'Unit '.$this->faker->word(),
            'short_name' => strtoupper($this->faker->unique()->lexify('???')),
            'is_active' => true,
        ];
    }
}
