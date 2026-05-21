<?php

namespace Database\Factories\Master;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = \App\Models\Master\Supplier::class;

    public function definition(): array
    {
        return [
            'code' => 'SUP-' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->name(),
            'company' => $this->faker->company(),
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'is_active' => true,
        ];
    }
}
