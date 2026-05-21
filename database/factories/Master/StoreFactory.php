<?php

namespace Database\Factories\Master;

use App\Models\Master\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Retail',
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'tax_number' => $this->faker->numerify('##.###.###.#-###.###'),
            'header_text' => 'Selamat Datang di '.$this->faker->company(),
            'footer_text' => 'Terima Kasih Telah Berbelanja!',
            'print_settings' => [
                'paper_width' => 80,
                'show_logo' => true,
            ],
            'is_active' => true,
        ];
    }
}
