<?php

namespace Database\Factories\Finance;

use App\Models\Auth\User;
use App\Models\Finance\CashTransaction;
use App\Models\Master\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashTransaction>
 */
class CashTransactionFactory extends Factory
{
    protected $model = CashTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'shift_id' => null,
            'type' => $this->faker->randomElement(['in', 'out']),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'category' => $this->faker->randomElement(['operasional', 'listrik', 'atk', 'pendapatan_lain']),
            'payment_method' => 'cash',
            'description' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
