<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Warehouse;
use App\Models\Master\Product;
use App\Repositories\Contracts\Inventory\StockDisposalRepositoryInterface;

class StockDisposalSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::first();
        $product = Product::first();

        if ($warehouse && $product) {
            $stockDisposalRepository = app(StockDisposalRepositoryInterface::class);

            // Buat Draft Pemusnahan Barang (Misalnya 3 barang expired)
            $disposalDraft = $stockDisposalRepository->create([
                'warehouse_id' => $warehouse->id,
                'disposal_date' => now()->format('Y-m-d'),
                'reason' => 'Kedaluwarsa (Expired)',
                'notes' => 'Pemusnahan stok susu kaleng kadaluwarsa di gudang belakang',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'qty' => 3,
                        'notes' => 'Penyok & Kedaluwarsa sejak minggu lalu',
                    ]
                ]
            ]);

            // Setujui (Approve) agar terposting ke Akuntansi dan mengurangi stok riil gudang!
            $stockDisposalRepository->update($disposalDraft->id, [
                'status' => 'approved',
            ]);
        }
    }
}
