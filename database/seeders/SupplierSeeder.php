<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Supplier;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Budi Santoso',
            'company' => 'PT Distributor Indofood Sukses',
            'email' => 'distributor.indofood@example.com',
            'phone' => '021-12345678',
            'address' => 'Kawasan Industri Pulogadung, Jakarta',
            'is_active' => true,
        ]);

        Supplier::create([
            'code' => 'SUP-002',
            'name' => 'Andi Wijaya',
            'company' => 'CV Sumber Makmur',
            'email' => 'sumber.makmur@example.com',
            'phone' => '022-87654321',
            'address' => 'Jl. Soekarno Hatta, Bandung',
            'is_active' => true,
        ]);

        Supplier::factory(10)->create();
    }
}
