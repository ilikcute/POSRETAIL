<?php

namespace Database\Factories\Master;

use App\Models\Master\Rack;
use App\Models\Master\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class RackFactory extends Factory
{
    protected $model = Rack::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::inRandomOrder()->first()->id ?? Warehouse::factory(),
            'code' => 'RCK-'.$this->faker->unique()->numberBetween(100, 999),
            'name' => 'Rak '.$this->faker->regexify('[A-Z]{1}[0-9]{2}'),
            'description' => 'Lorong '.$this->faker->numberBetween(1, 10),
            'sort_order' => $this->faker->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
