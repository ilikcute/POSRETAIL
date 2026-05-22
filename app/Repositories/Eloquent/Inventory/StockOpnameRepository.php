<?php

namespace App\Repositories\Eloquent\Inventory;

use App\Exceptions\StockOpnameException;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockOpname;
use App\Models\Master\Product;
use App\Repositories\Contracts\Inventory\StockOpnameRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockOpnameRepository extends BaseRepository implements StockOpnameRepositoryInterface
{
    public function __construct(StockOpname $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $items = $attributes['items'];
            unset($attributes['items']);

            $attributes['reference_no'] = 'SO-'.date('Ymd').'-'.str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $attributes['created_by'] = auth()->id() ?? 1;
            $attributes['status'] = 'draft';

            $stockOpname = parent::create($attributes);

            $processedItems = [];
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Cari stock terdaftar saat ini di sistem
                $stock = ProductStock::where('warehouse_id', $stockOpname->warehouse_id)
                    ->where('product_id', $item['product_id'])
                    ->where('product_variant_id', $item['product_variant_id'] ?? null)
                    ->first();

                $systemQty = $stock ? $stock->qty : 0;
                $physicalQty = $item['physical_qty'];
                $discrepancy = $physicalQty - $systemQty;
                $unitCost = $product->cost_price;
                $discrepancyValue = $discrepancy * $unitCost;

                $processedItems[] = [
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'system_qty' => $systemQty,
                    'physical_qty' => $physicalQty,
                    'discrepancy' => $discrepancy,
                    'unit_cost' => $unitCost,
                    'discrepancy_value' => $discrepancyValue,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $stockOpname->items()->createMany($processedItems);

            return $stockOpname->load('items');
        });
    }

    public function update(int $id, array $attributes): Model
    {
        return DB::transaction(function () use ($id, $attributes) {
            $stockOpname = $this->findOrFail($id);
            $oldStatus = $stockOpname->status;

            if ($oldStatus !== 'draft') {
                throw new StockOpnameException("Cannot update stock opname that is already {$oldStatus}.");
            }

            $newStatus = $attributes['status'] ?? $oldStatus;

            // 1. Process and update items if provided
            if (isset($attributes['items'])) {
                // Delete existing items
                $stockOpname->items()->delete();

                $processedItems = [];
                foreach ($attributes['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);

                    // Cari stock terdaftar saat ini di sistem
                    $stock = ProductStock::where('warehouse_id', $stockOpname->warehouse_id)
                        ->where('product_id', $item['product_id'])
                        ->where('product_variant_id', $item['product_variant_id'] ?? null)
                        ->first();

                    $systemQty = $stock ? $stock->qty : 0;
                    $physicalQty = $item['physical_qty'];
                    $discrepancy = $physicalQty - $systemQty;
                    $unitCost = $product->cost_price;
                    $discrepancyValue = $discrepancy * $unitCost;

                    $processedItems[] = [
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'] ?? null,
                        'system_qty' => $systemQty,
                        'physical_qty' => $physicalQty,
                        'discrepancy' => $discrepancy,
                        'unit_cost' => $unitCost,
                        'discrepancy_value' => $discrepancyValue,
                        'notes' => $item['notes'] ?? null,
                    ];
                }

                $stockOpname->items()->createMany($processedItems);

                // Reload items so the relation contains the new items
                $stockOpname->load('items');
                unset($attributes['items']);
            }

            // 2. Handle status transitions
            if ($newStatus === 'approved') {
                if (! $stockOpname->relationLoaded('items')) {
                    $stockOpname->load('items');
                }

                if ($stockOpname->items->isEmpty()) {
                    throw new StockOpnameException('Cannot approve a stock opname with no items.');
                }

                $this->approveStockOpname($stockOpname);
                $attributes['approved_by'] = auth()->id() ?? 1;
                $attributes['approved_at'] = now();
            } elseif ($newStatus === 'cancelled') {
                $attributes['status'] = 'cancelled';
            }

            return parent::update($id, $attributes)->load('items');
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $stockOpname = $this->findOrFail($id);
            if ($stockOpname->status !== 'draft') {
                throw new StockOpnameException('Only draft stock opnames can be deleted.');
            }
            // Hapus items terlebih dahulu secara aman
            $stockOpname->items()->delete();

            return parent::delete($id);
        });
    }

    protected function approveStockOpname(StockOpname $stockOpname)
    {
        // 1. Update Stok Fisik di Warehouse
        foreach ($stockOpname->items as $item) {
            $stock = ProductStock::where('warehouse_id', $stockOpname->warehouse_id)
                ->where('product_id', $item->product_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->first();

            if ($stock) {
                $stock->qty = $item->physical_qty;
                $stock->save();
            } else {
                ProductStock::create([
                    'warehouse_id' => $stockOpname->warehouse_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'qty' => $item->physical_qty,
                ]);
            }
        }

        // 2. Buat Jurnal Penyesuaian Akuntansi Otomatis
        $totalDiscrepancyValue = $stockOpname->items->sum('discrepancy_value');

        if ($totalDiscrepancyValue != 0) {
            $inventoryAccount = Account::where('code', '1201')->first(); // Aset Persediaan
            $adjustmentExpenseAccount = Account::where('code', '5202')->first(); // Beban Selisih Stock
            $adjustmentRevenueAccount = Account::where('code', '4201')->first(); // Pendapatan Lain-lain

            if (! $inventoryAccount || ! $adjustmentExpenseAccount || ! $adjustmentRevenueAccount) {
                return;
            }

            $entry = JournalEntry::create([
                'reference_no' => 'JV-SO-'.str_pad($stockOpname->id, 6, '0', STR_PAD_LEFT),
                'transaction_date' => now()->format('Y-m-d'),
                'description' => 'Penyesuaian Akuntansi atas Selisih Stock Opname #'.$stockOpname->reference_no,
                'created_by' => auth()->id() ?? 1,
            ]);

            if ($totalDiscrepancyValue > 0) {
                // Kelebihan stok fisik (Surplus) -> Persediaan bertambah (Debet), Pendapatan Lain bertambah (Kredit)
                $entry->items()->create([
                    'account_id' => $inventoryAccount->id,
                    'debit' => $totalDiscrepancyValue,
                    'credit' => 0,
                ]);
                $inventoryAccount->balance += $totalDiscrepancyValue;
                $inventoryAccount->save();

                $entry->items()->create([
                    'account_id' => $adjustmentRevenueAccount->id,
                    'debit' => 0,
                    'credit' => $totalDiscrepancyValue,
                ]);
                $adjustmentRevenueAccount->balance += $totalDiscrepancyValue;
                $adjustmentRevenueAccount->save();

            } else {
                // Kekurangan stok fisik (Defisit/Susut) -> Beban bertambah (Debet), Persediaan berkurang (Kredit)
                $absLoss = abs($totalDiscrepancyValue);

                $entry->items()->create([
                    'account_id' => $adjustmentExpenseAccount->id,
                    'debit' => $absLoss,
                    'credit' => 0,
                ]);
                $adjustmentExpenseAccount->balance += $absLoss;
                $adjustmentExpenseAccount->save();

                $entry->items()->create([
                    'account_id' => $inventoryAccount->id,
                    'debit' => 0,
                    'credit' => $absLoss,
                ]);
                $inventoryAccount->balance -= $absLoss;
                $inventoryAccount->save();
            }
        }
    }
}
