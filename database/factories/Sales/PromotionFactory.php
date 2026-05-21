<?php

namespace Database\Factories\Sales;

use App\Models\Sales\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('PROMO?????')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['percentage', 'fixed_amount']),
            'value' => $this->faker->numberBetween(5, 50000),
            'min_purchase_amount' => $this->faker->numberBetween(0, 100000),
            'max_discount_amount' => null,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'is_active' => true,
        ];
    }
}
