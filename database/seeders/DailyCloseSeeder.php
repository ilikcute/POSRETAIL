<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sales\DailyClose;
use App\Models\Master\Store;

class DailyCloseSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::first();

        if ($store) {
            DailyClose::create([
                'store_id' => $store->id,
                'close_date' => now()->subDay()->format('Y-m-d'),
                'total_sales' => 500000,
                'total_purchases' => 1000000,
                'total_cash_sales' => 400000,
                'total_non_cash_sales' => 100000,
                'total_shift_difference' => 0,
                'closed_by' => 1,
                'status' => 'completed',
                'notes' => 'EOD Kemarin sukses diselesaikan',
            ]);
        }
    }
}
