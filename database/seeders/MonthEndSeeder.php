<?php

namespace Database\Seeders;

use App\Models\Finance\MonthEnd;
use App\Models\Master\Store;
use Illuminate\Database\Seeder;

class MonthEndSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::first();

        if ($store) {
            MonthEnd::create([
                'store_id' => $store->id,
                'month' => 4, // Bulan Lalu (April)
                'year' => 2026,
                'total_sales' => 15000000,
                'total_cost_of_goods_sold' => 9000000, // HPP
                'total_purchases' => 12000000,
                'gross_profit' => 6000000, // Untung kotor Rp 6 Juta
                'closed_by' => 1,
                'closed_at' => now()->subDays(19),
                'notes' => 'Penutupan buku April aman',
            ]);
        }
    }
}
