<?php

namespace Database\Seeders;

use App\Models\Master\Customer;
use App\Models\Master\Product;
use App\Models\Master\Station;
use App\Models\Master\Store;
use App\Models\Master\Warehouse;
use App\Models\Sales\Promotion;
use App\Models\Sales\Shift;
use App\Repositories\Contracts\Sales\SaleRepositoryInterface;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::first();
        $station = Station::first();
        $warehouse = Warehouse::first();
        $product = Product::first();
        $shift = Shift::first();
        $customer = Customer::first();

        if ($store && $station && $warehouse && $product && $shift) {
            $saleRepository = app(SaleRepositoryInterface::class);
            $promotion = Promotion::where('code', 'GRANDOPENING26')->first();

            $qty = 10;

            // Penjualan Retail POS completed -> Mempengaruhi Stok & Jurnal Akuntansi!
            $saleRepository->create([
                'store_id' => $store->id,
                'station_id' => $station->id,
                'shift_id' => $shift->id,
                'warehouse_id' => $warehouse->id,
                'customer_id' => $customer ? $customer->id : null,
                'promotion_id' => $promotion ? $promotion->id : null,
                'payment_method' => 'cash',
                'amount_paid' => 100000,
                'status' => 'completed',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'qty' => $qty,
                        'unit_price' => $product->price,
                        'cost_price' => $product->cost_price,
                    ],
                ],
                'notes' => 'Penjualan pertama kasir dengan Promo Grand Opening',
            ]);
        }
    }
}
