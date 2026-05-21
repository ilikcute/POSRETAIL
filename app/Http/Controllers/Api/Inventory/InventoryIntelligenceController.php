<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Master\Product;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryIntelligenceController extends Controller
{
    use ApiResponseTrait;

    /**
     * 1. Modul Peringatan Otomatis Stok (Stock Alerts)
     * Mendeteksi item yang mendekati Stock-Out (Low Stock) dan barang menumpuk (Over Stock)
     */
    public function stockAlerts(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');

        // A. AMBIL PRODUK LOW STOCK (Stok < Reorder Point)
        $lowStockQuery = Product::with(['unit', 'category', 'stocks'])
            ->where('is_active', true);

        $lowStock = $lowStockQuery->get()->map(function ($product) use ($warehouseId) {
            $currentQty = $product->stocks()
                ->when($warehouseId, function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                })
                ->sum('qty');

            return [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'category' => $product->category->name ?? 'Retail',
                'unit' => $product->unit->name ?? 'pcs',
                'current_stock' => (float) $currentQty,
                'reorder_point' => (float) $product->reorder_point,
                'safety_stock' => (float) $product->safety_stock,
                'status' => $currentQty <= $product->safety_stock ? 'CRITICAL (Stock Out Risk)' : 'WARNING (Need Reorder)',
            ];
        })->filter(function ($item) {
            // Tampilkan jika stok saat ini berada di bawah atau sama dengan reorder point
            return $item['current_stock'] <= $item['reorder_point'];
        })->values();

        // B. AMBIL PRODUK OVER STOCK (Stok > Safety Stock * 4)
        $overStock = Product::with(['unit', 'category', 'stocks'])
            ->where('is_active', true)
            ->get()->map(function ($product) use ($warehouseId) {
                $currentQty = $product->stocks()
                    ->when($warehouseId, function ($q) use ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId);
                    })
                    ->sum('qty');

                $overLimit = max(50.0, (float) $product->safety_stock * 4); // Batas aman overstock

                return [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'category' => $product->category->name ?? 'Retail',
                    'unit' => $product->unit->name ?? 'pcs',
                    'current_stock' => (float) $currentQty,
                    'safety_stock' => (float) $product->safety_stock,
                    'overstock_limit' => $overLimit,
                    'status' => 'OVERSTOCK (Capital Trap)',
                ];
            })->filter(function ($item) {
                // Tampilkan jika stok melebihi batas overstock limit
                return $item['current_stock'] > $item['overstock_limit'];
            })->values();

        $alerts = [
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'warehouse_filter' => $warehouseId ? 'Warehouse ID: '.$warehouseId : 'All Warehouses',
            ],
            'summary' => [
                'total_low_stock_items' => $lowStock->count(),
                'total_over_stock_items' => $overStock->count(),
            ],
            'alerts' => [
                'under_stocked' => $lowStock,
                'over_stocked' => $overStock,
            ],
        ];

        return $this->successResponse($alerts, 'Inventory stock alerts generated successfully');
    }

    /**
     * 2. Modul Analisis Produk Paling Laris / Laku (Best Sellers)
     * Mengurutkan produk terlaris berdasarkan kuantitas penjualan dan kontribusi omset
     */
    public function bestSellers(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', now()->subDays(30)->startOfDay()->toDateTimeString());
        $endDate = $request->query('end_date', now()->endOfDay()->toDateTimeString());
        $limit = (int) $request->query('limit', 10);

        // Ambil penjualan terlaris dari tabel sale_items
        $bestSellers = SaleItem::select(
            'product_id',
            DB::raw('SUM(qty) as total_qty_sold'),
            DB::raw('SUM(subtotal) as total_revenue'),
            DB::raw('SUM(subtotal - (qty * cost_price)) as total_profit')
        )
            ->whereHas('sale', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'completed')
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('product_id')
            ->orderBy('total_qty_sold', 'desc')
            ->limit($limit)
            ->with(['product.unit', 'product.category'])
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'code' => $item->product->code,
                    'name' => $item->product->name,
                    'category' => $item->product->category->name ?? 'Retail',
                    'unit' => $item->product->unit->name ?? 'pcs',
                    'qty_sold' => (float) $item->total_qty_sold,
                    'revenue' => (float) $item->total_revenue,
                    'estimated_profit' => (float) $item->total_profit,
                ];
            });

        $report = [
            'metadata' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'limit' => $limit,
            ],
            'summary' => [
                'total_items_sold' => $bestSellers->sum('qty_sold'),
                'total_revenue_generated' => $bestSellers->sum('revenue'),
                'total_estimated_profit' => $bestSellers->sum('estimated_profit'),
            ],
            'best_sellers' => $bestSellers,
        ];

        return $this->successResponse($report, 'Best selling products analyzed successfully');
    }

    /**
     * 3. Modul Analisis Kinerja Promosi (Promo-Driven Performance)
     * Mengukur efisiensi promosi, omset yang didorong promo, serta barang terlaris berdasarkan promo
     */
    public function promoPerformance(Request $request): JsonResponse
    {
        // Kumpulkan data performa kupon/promosi aktif
        $performance = Sale::whereNotNull('promotion_id')
            ->where('status', 'completed')
            ->select(
                'promotion_id',
                DB::raw('COUNT(id) as total_uses'),
                DB::raw('SUM(discount_amount) as total_discounts_given'),
                DB::raw('SUM(grand_total) as total_sales_volume')
            )
            ->groupBy('promotion_id')
            ->with(['promotion'])
            ->get()
            ->map(function ($saleGroup) {
                // Cari produk paling laku yang dibeli menggunakan promo ini
                $topProducts = SaleItem::select('product_id', DB::raw('SUM(qty) as total_qty'))
                    ->whereHas('sale', function ($q) use ($saleGroup) {
                        $q->where('promotion_id', $saleGroup->promotion_id)
                            ->where('status', 'completed');
                    })
                    ->groupBy('product_id')
                    ->orderBy('total_qty', 'desc')
                    ->limit(3)
                    ->with('product')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'name' => $item->product->name,
                            'qty' => (float) $item->total_qty,
                        ];
                    });

                return [
                    'promo_id' => $saleGroup->promotion_id,
                    'code' => $saleGroup->promotion->code,
                    'name' => $saleGroup->promotion->name,
                    'total_transactions_used' => $saleGroup->total_uses,
                    'discounts_given' => (float) $saleGroup->total_discounts_given,
                    'sales_volume' => (float) $saleGroup->total_sales_volume,
                    'efficiency_ratio' => $saleGroup->total_sales_volume > 0
                        ? round(($saleGroup->total_discounts_given / $saleGroup->total_sales_volume) * 100, 2).'%'
                        : '0%', // Berapa persen nilai diskon dibanding nilai belanja
                    'top_sold_products' => $topProducts,
                ];
            });

        $report = [
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => [
                'total_promotions_analyzed' => $performance->count(),
                'total_discounts_incurred' => $performance->sum('discounts_given'),
                'total_revenue_from_promo' => $performance->sum('sales_volume'),
            ],
            'promotions_performance' => $performance,
        ];

        return $this->successResponse($report, 'Promotion driven performance analyzed successfully');
    }
}
