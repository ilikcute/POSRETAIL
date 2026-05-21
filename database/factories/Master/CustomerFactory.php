<?php

namespace Database\Factories\Master;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = \App\Models\Master\Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->phoneNumber(),
            'address' => $this->faker->address(),
            'member_code' => 'MBR-' . strtoupper($this->faker->unique()->bothify('?????-#####')),
            'point_balance' => $this->faker->numberBetween(0, 1000),
            'is_active' => true,
        ];
    }
}
