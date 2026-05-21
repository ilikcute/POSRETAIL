<?php

namespace Database\Factories\Master;

use App\Models\Master\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Station>
 */
class StationFactory extends Factory
{
    protected $model = Station::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Kasir '.$this->faker->unique()->numberBetween(1, 10),
            'ip_address' => $this->faker->localIpv4(),
            'location' => 'Lantai '.$this->faker->numberBetween(1, 3),
            'is_active' => true,
        ];
    }
}
