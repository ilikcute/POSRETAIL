<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Warehouse;
use App\Models\Master\Product;
use App\Models\Inventory\ProductStock;
use App\Repositories\Contracts\Inventory\StockOpnameRepositoryInterface;

class StockOpnameSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::first();
        $product = Product::first();

        if ($warehouse && $product) {
            $stockOpnameRepository = app(StockOpnameRepositoryInterface::class);

            // 1. Ambil stok terdaftar sebelum stock opname
            $stock = ProductStock::where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->first();

            $currentQty = $stock ? $stock->qty : 0;

            // Buat Draft Stock Opname (Misalkan kita hitung fisik ternyata HILANG/KURANG 2 item)
            $physicalQty = $currentQty - 2;

            if ($physicalQty < 0) {
                $physicalQty = 0;
            }

            $opnameDraft = $stockOpnameRepository->create([
                'warehouse_id' => $warehouse->id,
                'opname_date' => now()->format('Y-m-d'),
                'notes' => 'Audit persediaan bulanan - ditemukan selisih barang pecah',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'physical_qty' => $physicalQty,
                        'notes' => 'Barang pecah di rak belakang',
                    ]
                ]
            ]);

            // 2. Setujui (Approve) agar terposting ke Akuntansi dan mengurangi stok riil!
            $stockOpnameRepository->update($opnameDraft->id, [
                'status' => 'approved',
            ]);
        }
    }
}
