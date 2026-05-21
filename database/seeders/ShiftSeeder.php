<?php

namespace Database\Seeders;

use App\Models\Master\Station;
use App\Models\Sales\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $station = Station::first();

        if ($station) {
            Shift::create([
                'user_id' => 1,
                'station_id' => $station->id,
                'start_time' => now()->subHours(8),
                'end_time' => now()->subMinutes(10),
                'starting_cash' => 200000,
                'total_sales' => 96000,
                'total_discount' => 24000,
                'expected_cash' => 266000, // Uang awal + Sales (96.000) + Inflow (20.000) - Outflow (50.000)
                'actual_cash' => 266000,
                'difference_cash' => 0,
                'expected_qris' => 0,
                'actual_qris' => 0,
                'difference_qris' => 0,
                'expected_card' => 0,
                'actual_card' => 0,
                'difference_card' => 0,
                'status' => 'closed',
                'notes' => 'Shift Pagi Berjalan Lancar dengan penyesuaian Petty Cash',
            ]);

            // Shift aktif sekarang
            Shift::create([
                'user_id' => 1,
                'station_id' => $station->id,
                'start_time' => now(),
                'starting_cash' => 200000,
                'expected_cash' => 200000,
                'status' => 'open',
                'notes' => 'Shift Sore Baru Dibuka',
            ]);
        }
    }
}
