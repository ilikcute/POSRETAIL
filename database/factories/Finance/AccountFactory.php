<?php

namespace Database\Factories\Finance;

use App\Models\Finance\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('######'),
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['asset', 'liability', 'equity', 'revenue', 'expense']),
            'balance' => $this->faker->randomFloat(2, 0, 1000000),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
