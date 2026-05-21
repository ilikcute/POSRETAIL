<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Store;
use App\Models\Master\Supplier;
use App\Models\Master\Warehouse;
use App\Models\Master\Product;
use App\Repositories\Contracts\Purchase\PurchaseRepositoryInterface;

class PurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::first();
        $supplier = Supplier::first();
        $warehouse = Warehouse::first();
        $product = Product::first();

        if ($store && $supplier && $warehouse && $product) {
            $purchaseRepository = app(PurchaseRepositoryInterface::class);

            // 1. PO Order (Tidak merubah stok & jurnal)
            $purchaseRepository->create([
                'store_id' => $store->id,
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'order',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'qty' => 10,
                        'unit_cost' => $product->cost_price,
                    ]
                ],
                'notes' => 'Rencana pembelian awal bulan (PO)',
            ]);

            // 2. Direct Purchase / Received (Mempengaruhi Stok & Jurnal Akuntansi!)
            $purchaseRepository->create([
                'store_id' => $store->id,
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'purchase',
                'status' => 'received',
                'payment_status' => 'paid',
                'payment_method' => 'bank', // Bayar via Bank
                'items' => [
                    [
                        'product_id' => $product->id,
                        'qty' => 50, // Tambah stok 50 item
                        'unit_cost' => $product->cost_price,
                    ]
                ],
                'notes' => 'Pembelian persediaan barang masuk bulanan',
            ]);
        }
    }
}
