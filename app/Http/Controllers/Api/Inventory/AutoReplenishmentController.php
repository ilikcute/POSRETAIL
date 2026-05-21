<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;

use App\Models\Master\Product;
use App\Models\Master\Supplier;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use App\Models\Master\Warehouse;
use App\Models\Master\Store;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutoReplenishmentController extends Controller
{
    use ApiResponseTrait;

    /**
     * Dapatkan saran pengadaan otomatis (Suggestions)
     */
    public function suggestions(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');
        $storeId = $request->query('store_id') ?? Store::first()->id;

        // Ambil produk aktif yang stoknya kurang dari reorder_point
        $products = Product::with(['unit', 'category', 'stocks', 'racks'])
            ->where('is_active', true)
            ->get();

        $suggestions = collect();

        foreach ($products as $product) {
            $currentStock = $product->stocks()
                ->when($warehouseId, function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                })
                ->sum('qty');

            // Cek apakah stok di bawah atau sama dengan reorder point
            if ($currentStock <= $product->reorder_point) {
                
                // 1. CARI LAST SUPPLIER LOOKUP (AI Smart Sourcing!)
                $lastPurchaseItem = PurchaseItem::where('product_id', $product->id)
                    ->whereHas('purchase', function ($q) {
                        $q->where('status', 'received');
                    })
                    ->orderBy('id', 'desc')
                    ->with('purchase.supplier')
                    ->first();

                $supplier = null;
                if ($lastPurchaseItem && $lastPurchaseItem->purchase && $lastPurchaseItem->purchase->supplier) {
                    $supplier = $lastPurchaseItem->purchase->supplier;
                } else {
                    $supplier = Supplier::first(); // Fallback ke supplier pertama jika belum pernah beli
                }

                if (!$supplier) {
                    continue; // Skip jika tidak ada supplier sama sekali di database
                }

                // 2. HITUNG TARGET KUANTITAS REPLENISHMENT
                // Ambil kapasitas display rak maksimum dari planogram
                $maxRackCapacity = $product->racks->sum(function($r) {
                    return $r->pivot->max_capacity ?? 0;
                });

                $targetStock = $maxRackCapacity > 0 ? $maxRackCapacity : max(50.0, (float)$product->safety_stock * 3);
                $suggestedQty = (float)max(0, $targetStock - $currentStock);

                // Jika suggested qty kurang dari 5, genapkan ke kelipatan minimum (misal 10)
                if ($suggestedQty > 0 && $suggestedQty < 10) {
                    $suggestedQty = 10.0;
                }

                if ($suggestedQty > 0) {
                    $suggestions->push([
                        'product_id' => $product->id,
                        'code' => $product->code,
                        'name' => $product->name,
                        'category' => $product->category->name ?? 'Retail',
                        'unit' => $product->unit->name ?? 'pcs',
                        'current_stock' => (float)$currentStock,
                        'reorder_point' => (float)$product->reorder_point,
                        'max_rack_capacity' => $maxRackCapacity,
                        'suggested_qty' => $suggestedQty,
                        'cost_price' => (float)$product->cost_price,
                        'estimated_subtotal' => $suggestedQty * (float)$product->cost_price,
                        'supplier' => [
                            'id' => $supplier->id,
                            'name' => $supplier->name,
                            'code' => $supplier->code,
                        ]
                    ]);
                }
            }
        }

        // Kelompokkan saran berdasarkan Supplier untuk representasi draf PO
        $groupedSuggestions = $suggestions->groupBy('supplier.id')->map(function ($items, $supplierId) {
            $firstItem = $items->first();
            return [
                'supplier_id' => (int)$supplierId,
                'supplier_name' => $firstItem['supplier']['name'],
                'supplier_code' => $firstItem['supplier']['code'],
                'total_items_to_order' => $items->count(),
                'total_qty_to_order' => $items->sum('suggested_qty'),
                'estimated_grand_total' => $items->sum('estimated_subtotal'),
                'items' => $items->map(function ($item) {
                    return collect($item)->except('supplier')->toArray();
                })->values()
            ];
        })->values();

        $response = [
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'target_warehouse_id' => $warehouseId ? (int)$warehouseId : Warehouse::first()->id,
                'store_id' => (int)$storeId,
            ],
            'summary' => [
                'total_suppliers_involved' => $groupedSuggestions->count(),
                'total_unique_products_understocked' => $suggestions->count(),
                'estimated_total_investment' => $suggestions->sum('estimated_subtotal'),
            ],
            'suggestions_by_supplier' => $groupedSuggestions
        ];

        return $this->successResponse($response, 'Auto-replenishment suggestions generated successfully');
    }

    /**
     * Setujui saran dan buat draf Purchase Order (Smart PO Drafts) secara massal
     */
    public function createDrafts(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'suppliers' => 'required|array',
            'suppliers.*.supplier_id' => 'required|exists:suppliers,id',
            'suppliers.*.items' => 'required|array|min:1',
            'suppliers.*.items.*.product_id' => 'required|exists:products,id',
            'suppliers.*.items.*.qty' => 'required|numeric|min:1',
            'suppliers.*.items.*.cost_price' => 'required|numeric|min:0',
        ]);

        $storeId = $request->input('store_id');
        $warehouseId = $request->input('warehouse_id');
        $userId = auth()->id() ?? 1; // Fallback ke superadmin jika tanpa session token saat uji coba

        $createdPOs = [];

        DB::transaction(function () use ($request, $storeId, $warehouseId, $userId, &$createdPOs) {
            foreach ($request->input('suppliers') as $supData) {
                $supplierId = $supData['supplier_id'];
                
                // Genereate unique PO reference number
                $refNo = 'PO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

                $totalItems = 0;
                $totalAmount = 0.0;

                // 1. Buat Header PO (status = pending, type = order)
                $purchase = Purchase::create([
                    'store_id' => $storeId,
                    'supplier_id' => $supplierId,
                    'warehouse_id' => $warehouseId,
                    'created_by' => $userId,
                    'reference_no' => $refNo,
                    'type' => 'order', // Purchase Order (Rencana PO)
                    'status' => 'pending', // Berstatus pending, menunggu persetujuan / pengiriman supplier
                    'payment_status' => 'unpaid',
                    'total_items' => 0,
                    'total_amount' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'shipping_cost' => 0,
                    'grand_total' => 0,
                    'notes' => 'Otomatis dibuat oleh Modul Auto-Replenishment POSRETAIL',
                ]);

                // 2. Buat PO Detail Items
                foreach ($supData['items'] as $itemData) {
                    $subtotal = $itemData['qty'] * $itemData['cost_price'];
                    
                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $itemData['product_id'],
                        'qty' => $itemData['qty'],
                        'unit_cost' => $itemData['cost_price'],
                        'subtotal' => $subtotal,
                    ]);

                    $totalItems += $itemData['qty'];
                    $totalAmount += $subtotal;
                }

                // 3. Update nominal total pada Header PO
                $purchase->update([
                    'total_items' => $totalItems,
                    'total_amount' => $totalAmount,
                    'grand_total' => $totalAmount, // assuming no tax/shipping for draft PO
                ]);

                $purchase->load(['supplier', 'warehouse']);

                $createdPOs[] = [
                    'purchase_id' => $purchase->id,
                    'reference_no' => $purchase->reference_no,
                    'supplier_name' => $purchase->supplier->name,
                    'warehouse_name' => $purchase->warehouse->name,
                    'total_items' => $totalItems,
                    'grand_total' => $totalAmount,
                    'status' => $purchase->status,
                ];
            }
        });

        return $this->successResponse($createdPOs, 'Smart PO drafts created successfully', 201);
    }
}
