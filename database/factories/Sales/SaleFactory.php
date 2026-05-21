<?php

namespace Database\Factories\Sales;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Master\Store;
use App\Models\Master\Station;
use App\Models\Master\Warehouse;

class SaleFactory extends Factory
{
    protected $model = \App\Models\Sales\Sale::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::inRandomOrder()->first()->id ?? Store::factory(),
            'station_id' => Station::inRandomOrder()->first()->id ?? Station::factory(),
            'warehouse_id' => Warehouse::inRandomOrder()->first()->id ?? Warehouse::factory(),
            'created_by' => 1,
            'invoice_no' => 'INV-202605-' . str_pad($this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'status' => 'completed',
            'payment_method' => 'cash',
            'total_items' => 0,
            'total_amount' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'grand_total' => 0,
            'amount_paid' => 0,
            'change_amount' => 0,
        ];
    }
}
