<?php

namespace Database\Seeders;

use App\Models\Master\Product;
use App\Models\Master\Rack;
use App\Models\Master\Warehouse;
use Illuminate\Database\Seeder;

class RackSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil Warehouse Pertama (Main Warehouse)
        $mainWarehouse = Warehouse::first();

        if ($mainWarehouse) {
            $rack = Rack::create([
                'warehouse_id' => $mainWarehouse->id,
                'code' => 'RCK-MAIN-A1',
                'name' => 'Rak Makanan Ringan & Sembako',
                'description' => 'Lorong A, Baris 1',
                'sort_order' => 1,
                'is_active' => true,
            ]);

            // Hubungkan beberapa produk ke rak ini dengan data PLANOGRAM rinci!
            $chitato = Product::where('code', 'PRD-001')->first();
            $indomie = Product::where('code', 'PRD-002')->first();

            if ($chitato && $indomie) {
                // Chitato di Letakkan di Shelf Level 2 (Tengah), Posisi 1, 3 Facings, Max 15 unit
                $rack->products()->attach($chitato->id, [
                    'shelf_level' => 2,
                    'position_order' => 1,
                    'facing' => 3,
                    'max_capacity' => 15,
                ]);

                // Indomie diletakkan di Shelf Level 1 (Bawah), Posisi 1, 4 Facings, Max 20 unit
                $rack->products()->attach($indomie->id, [
                    'shelf_level' => 1,
                    'position_order' => 1,
                    'facing' => 4,
                    'max_capacity' => 20,
                ]);
            }

            // Generate beberapa dummy rak
            Rack::factory(4)->create();
        }
    }
}
