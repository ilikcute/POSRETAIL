<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Finance\Account;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Assets (Aset)
        Account::create([
            'code' => '1101',
            'name' => 'Kas Toko / Petty Cash',
            'type' => 'asset',
            'balance' => 200000, // Saldo Awal laci kasir
            'description' => 'Kas tunai harian di laci mesin kasir',
        ]);

        Account::create([
            'code' => '1102',
            'name' => 'Bank Mandiri Toko',
            'type' => 'asset',
            'balance' => 10000000,
            'description' => 'Rekening utama bank operasional toko',
        ]);

        Account::create([
            'code' => '1201',
            'name' => 'Persediaan Barang Dagang',
            'type' => 'asset',
            'balance' => 5000000,
            'description' => 'Nilai aset persediaan produk retail',
        ]);

        // 2. Liabilities (Kewajiban)
        Account::create([
            'code' => '2101',
            'name' => 'Hutang Dagang',
            'type' => 'liability',
            'balance' => 0,
            'description' => 'Kewajiban pembayaran atas pembelian barang dagang ke supplier',
        ]);

        // 3. Equity (Modal)
        Account::create([
            'code' => '3101',
            'name' => 'Modal Disetor',
            'type' => 'equity',
            'balance' => 15200000,
            'description' => 'Modal awal pemilik usaha',
        ]);

        // 4. Revenues (Pendapatan)
        Account::create([
            'code' => '4101',
            'name' => 'Pendapatan Penjualan Retail',
            'type' => 'revenue',
            'balance' => 0,
            'description' => 'Pendapatan utama dari penjualan kasir POS',
        ]);

        Account::create([
            'code' => '4201',
            'name' => 'Pendapatan Lain-lain (Non-Retail)',
            'type' => 'revenue',
            'balance' => 0,
            'description' => 'Pendapatan dari penjualan kardus bekas/sampah supplier',
        ]);

        // 5. Expenses (Beban)
        Account::create([
            'code' => '5101',
            'name' => 'Harga Pokok Penjualan (HPP)',
            'type' => 'expense',
            'balance' => 0,
            'description' => 'Beban pokok produk retail yang terjual',
        ]);

        Account::create([
            'code' => '5201',
            'name' => 'Beban Listrik Toko',
            'type' => 'expense',
            'balance' => 0,
            'description' => 'Beban pembayaran token/tagihan listrik bulanan',
        ]);

        Account::create([
            'code' => '5202',
            'name' => 'Beban Kerusakan & Selisih Persediaan',
            'type' => 'expense',
            'balance' => 0,
            'description' => 'Beban kerugian akibat penyusutan, kerusakan, dan selisih stock opname',
        ]);
    }
}
