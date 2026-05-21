<?php

namespace Database\Seeders;

use App\Models\Master\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::create([
            'code' => 'WH-MAIN',
            'name' => 'Gudang Pusat',
            'address' => 'Kawasan Industri Utama, Jakarta',
            'is_main' => true,
            'is_active' => true,
        ]);

        Warehouse::factory(2)->create();
    }
}
