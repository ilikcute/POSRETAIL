<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Store;
use App\Models\Sales\Shift;
use App\Repositories\Contracts\Finance\CashTransactionRepositoryInterface;

class CashTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::first();
        $shift = Shift::first();

        if ($store && $shift) {
            $cashTransactionRepository = app(CashTransactionRepositoryInterface::class);

            // Uang Keluar (Beban Listrik) -> Mempengaruhi Jurnal
            $cashTransactionRepository->create([
                'store_id' => $store->id,
                'shift_id' => $shift->id,
                'type' => 'out',
                'amount' => 50000,
                'category' => 'listrik',
                'payment_method' => 'cash',
                'description' => 'Pembayaran Token Listrik Darurat Toko',
            ]);

            // Uang Masuk (Penjualan Kardus) -> Mempengaruhi Jurnal
            $cashTransactionRepository->create([
                'store_id' => $store->id,
                'shift_id' => $shift->id,
                'type' => 'in',
                'amount' => 20000,
                'category' => 'penjualan_kardus',
                'payment_method' => 'cash',
                'description' => 'Hasil penjualan kardus pembungkus supplier',
            ]);
        }
    }
}
