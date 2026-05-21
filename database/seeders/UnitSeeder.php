<?php

namespace Database\Seeders;

use App\Models\Master\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Pieces', 'short_name' => 'PCS'],
            ['name' => 'Kilogram', 'short_name' => 'KG'],
            ['name' => 'Gram', 'short_name' => 'GR'],
            ['name' => 'Liter', 'short_name' => 'LTR'],
            ['name' => 'MiliLiter', 'short_name' => 'ML'],
            ['name' => 'Box', 'short_name' => 'BOX'],
            ['name' => 'Karton', 'short_name' => 'KRTN'],
        ];

        foreach ($units as $unit) {
            Unit::create([
                'name' => $unit['name'],
                'short_name' => $unit['short_name'],
                'is_active' => true,
            ]);
        }
    }
}
