<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Customer;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::create([
            'name' => 'John Doe (Member VIP)',
            'email' => 'john.doe@example.com',
            'phone' => '081234567890',
            'address' => 'Jl. Sudirman No. 1, Jakarta',
            'member_code' => 'MBR-VIP-001',
            'point_balance' => 5000,
            'is_active' => true,
        ]);

        Customer::create([
            'name' => 'Jane Smith (Regular)',
            'email' => 'jane.smith@example.com',
            'phone' => '081987654321',
            'address' => 'Jl. Thamrin No. 2, Jakarta',
            'member_code' => 'MBR-REG-002',
            'point_balance' => 150,
            'is_active' => true,
        ]);

        // Generate 18 random customers
        Customer::factory(18)->create();
    }
}
