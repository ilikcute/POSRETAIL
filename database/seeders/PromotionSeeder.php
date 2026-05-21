<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sales\Promotion;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        Promotion::create([
            'code' => 'GRANDOPENING26',
            'name' => 'Diskon Grand Opening 20%',
            'description' => 'Diskon 20% untuk semua transaksi minimal Rp 100.000',
            'type' => 'percentage',
            'value' => 20,
            'min_purchase_amount' => 100000,
            'max_discount_amount' => 50000,
            'start_date' => now()->startOfDay(),
            'end_date' => now()->addMonths(1)->endOfDay(),
            'is_active' => true,
        ]);

        Promotion::create([
            'code' => 'CASHBACK50K',
            'name' => 'Potongan Langsung 50 Ribu',
            'description' => 'Potongan tunai Rp 50.000 untuk transaksi minimal Rp 500.000',
            'type' => 'fixed_amount',
            'value' => 50000,
            'min_purchase_amount' => 500000,
            'start_date' => now()->startOfDay(),
            'end_date' => now()->addMonths(1)->endOfDay(),
            'is_active' => true,
        ]);

        Promotion::factory(5)->create();
    }
}
