<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockDisposalItem;
use App\Models\Inventory\StockOpnameItem;
use App\Models\Master\Product;
use App\Models\Purchase\PurchaseItem;
use App\Models\Sales\SaleItem;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use ApiResponseTrait;

    /**
     * Ringkasan Stok Seluruh Barang per Gudang
     */
    public function stockStatus(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');

        $query = ProductStock::with(['product.category', 'productVariant', 'warehouse']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stocks = $query->get()->map(function ($stock) {
            return [
                'warehouse_name' => $stock->warehouse->name,
                'product_code' => $stock->product->code,
                'product_name' => $stock->product->name,
                'variant_name' => $stock->productVariant ? $stock->productVariant->name : '-',
                'category_name' => $stock->product->category->name,
                'current_qty' => round($stock->qty, 2),
                'price' => round($stock->product->price, 2),
                'cost_price' => round($stock->product->cost_price, 2),
            ];
        });

        return $this->successResponse($stocks, 'Inventory stock status retrieved successfully');
    }

    /**
     * Kartu Stok (Stock Card Ledger) - Riwayat Keluar Masuk Barang
     */
    public function stockCard(Request $request, $productId): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');
        $product = Product::findOrFail($productId);

        $movements = collect();

        // 1. Pembelian (Purchase Items - Received)
        $purchases = PurchaseItem::where('product_id', $productId)
            ->whereHas('purchase', function ($query) use ($warehouseId) {
                $query->where('status', 'received');
                if ($warehouseId) {
                    $query->where('warehouse_id', $warehouseId);
                }
            })
            ->with(['purchase.warehouse'])
            ->get();

        foreach ($purchases as $item) {
            $movements->push([
                'date' => $item->purchase->created_at->toIso8601String(),
                'timestamp' => $item->purchase->created_at->timestamp,
                'reference_no' => $item->purchase->reference_no,
                'warehouse' => $item->purchase->warehouse->name,
                'type' => 'Pembelian Masuk',
                'qty_in' => (float) $item->qty,
                'qty_out' => 0.0,
                'unit_cost' => (float) $item->unit_cost,
                'description' => $item->purchase->notes ?? 'Penerimaan stok dari supplier',
            ]);
        }

        // 2. Penjualan (Sale Items - Completed)
        $sales = SaleItem::where('product_id', $productId)
            ->whereHas('sale', function ($query) use ($warehouseId) {
                $query->where('status', 'completed');
                if ($warehouseId) {
                    $query->where('warehouse_id', $warehouseId);
                }
            })
            ->with(['sale.warehouse'])
            ->get();

        foreach ($sales as $item) {
            $movements->push([
                'date' => $item->sale->created_at->toIso8601String(),
                'timestamp' => $item->sale->created_at->timestamp,
                'reference_no' => $item->sale->invoice_no,
                'warehouse' => $item->sale->warehouse->name,
                'type' => 'Penjualan Keluar',
                'qty_in' => 0.0,
                'qty_out' => (float) $item->qty,
                'unit_cost' => (float) $item->cost_price,
                'description' => 'Penjualan POS ke pelanggan',
            ]);
        }

        // 3. Stock Opname (Approved)
        $opnames = StockOpnameItem::where('product_id', $productId)
            ->whereHas('stockOpname', function ($query) use ($warehouseId) {
                $query->where('status', 'approved');
                if ($warehouseId) {
                    $query->where('warehouse_id', $warehouseId);
                }
            })
            ->with(['stockOpname.warehouse'])
            ->get();

        foreach ($opnames as $item) {
            $isSurplus = $item->discrepancy > 0;
            $movements->push([
                'date' => $item->stockOpname->approved_at->toIso8601String(),
                'timestamp' => $item->stockOpname->approved_at->timestamp,
                'reference_no' => $item->stockOpname->reference_no,
                'warehouse' => $item->stockOpname->warehouse->name,
                'type' => 'Stock Opname',
                'qty_in' => $isSurplus ? (float) $item->discrepancy : 0.0,
                'qty_out' => ! $isSurplus ? (float) abs($item->discrepancy) : 0.0,
                'unit_cost' => (float) $item->unit_cost,
                'description' => $item->notes ?? 'Penyesuaian hasil audit fisik',
            ]);
        }

        // 4. Stock Disposal (Approved)
        $disposals = StockDisposalItem::where('product_id', $productId)
            ->whereHas('stockDisposal', function ($query) use ($warehouseId) {
                $query->where('status', 'approved');
                if ($warehouseId) {
                    $query->where('warehouse_id', $warehouseId);
                }
            })
            ->with(['stockDisposal.warehouse'])
            ->get();

        foreach ($disposals as $item) {
            $movements->push([
                'date' => $item->stockDisposal->approved_at->toIso8601String(),
                'timestamp' => $item->stockDisposal->approved_at->timestamp,
                'reference_no' => $item->stockDisposal->reference_no,
                'warehouse' => $item->stockDisposal->warehouse->name,
                'type' => 'Pumusnahan Stok',
                'qty_in' => 0.0,
                'qty_out' => (float) $item->qty,
                'unit_cost' => (float) $item->unit_cost,
                'description' => 'Pemusnahan barang rusak ('.$item->stockDisposal->reason.')',
            ]);
        }

        // Urutkan berdasarkan waktu transaksi tertua -> terbaru
        $sortedMovements = $movements->sortBy('timestamp')->values();

        // Hitung saldo berjalan (Running Balance)
        $runningBalance = 0.0;
        $ledger = $sortedMovements->map(function ($m) use (&$runningBalance) {
            $runningBalance += ($m['qty_in'] - $m['qty_out']);
            $m['running_balance'] = round($runningBalance, 2);

            return $m;
        });

        $responseData = [
            'product' => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'current_stock' => round($product->stocks()->sum('qty'), 2),
            ],
            'stock_ledger' => $ledger,
        ];

        return $this->successResponse($responseData, 'Product stock card retrieved successfully');
    }

    /**
     * Laporan Nilai Aset Persediaan (Inventory Valuation Report)
     */
    public function valuation(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');

        $query = ProductStock::with(['product.category', 'warehouse']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $valuationData = $query->get()->map(function ($stock) {
            $value = $stock->qty * $stock->product->cost_price;

            return [
                'warehouse_name' => $stock->warehouse->name,
                'product_code' => $stock->product->code,
                'product_name' => $stock->product->name,
                'category_name' => $stock->product->category->name,
                'current_qty' => round($stock->qty, 2),
                'cost_price' => round($stock->product->cost_price, 2),
                'valuation_value' => round($value, 2),
            ];
        });

        $grandTotalValuation = $valuationData->sum('valuation_value');

        // Ambil pembanding dari saldo akun Buku Besar Persediaan "1201"
        $accountingInventoryAccount = Account::where('code', '1201')->first();
        $ledgerBalance = $accountingInventoryAccount ? (float) $accountingInventoryAccount->balance : 0.0;

        $report = [
            'metadata' => [
                'report_name' => 'Laporan Penilaian Nilai Aset Persediaan (Inventory Valuation Report)',
                'generated_at' => now()->toIso8601String(),
                'currency' => 'IDR',
                'valuation_method' => 'Weighted Average / FIFO (Cost-Based)',
            ],
            'summary' => [
                'total_products_tracked' => $valuationData->count(),
                'total_physical_quantity' => round($valuationData->sum('current_qty'), 2),
                'total_inventory_valuation' => round($grandTotalValuation, 2),
                'general_ledger_balance' => round($ledgerBalance, 2),
                'valuation_difference' => round($grandTotalValuation - $ledgerBalance, 2), // Wajib 0 untuk membuktikan integritas total!
            ],
            'details' => $valuationData,
        ];

        return $this->successResponse($report, 'Inventory valuation report retrieved successfully');
    }
}
