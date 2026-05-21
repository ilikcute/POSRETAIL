<?php

namespace Database\Seeders;

use App\Models\Master\Station;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat station utama/default
        Station::create([
            'name' => 'Kasir Utama',
            'ip_address' => '192.168.1.10',
            'location' => 'Lantai 1 - Front Desk',
            'is_active' => true,
        ]);

        // Buat tambahan station dummy
        Station::factory(2)->create();
    }
}
