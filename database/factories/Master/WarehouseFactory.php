<?php

namespace Database\Factories\Master;

use App\Models\Master\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'code' => 'WH-'.$this->faker->unique()->numberBetween(100, 999),
            'name' => 'Gudang '.$this->faker->city(),
            'address' => $this->faker->address(),
            'is_main' => false,
            'is_active' => true,
        ];
    }
}
