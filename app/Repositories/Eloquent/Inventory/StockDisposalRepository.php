<?php

namespace App\Repositories\Eloquent\Inventory;

use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockDisposal;
use App\Models\Master\Product;
use App\Repositories\Contracts\Inventory\StockDisposalRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockDisposalRepository extends BaseRepository implements StockDisposalRepositoryInterface
{
    public function __construct(StockDisposal $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $items = $attributes['items'];
            unset($attributes['items']);

            $attributes['reference_no'] = 'SD-'.date('Ymd').'-'.str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $attributes['created_by'] = auth()->id() ?? 1;
            $attributes['status'] = 'draft';

            $stockDisposal = parent::create($attributes);

            $processedItems = [];
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty = $item['qty'];
                $unitCost = $product->cost_price;
                $subtotal = $qty * $unitCost;

                $processedItems[] = [
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $stockDisposal->items()->createMany($processedItems);

            return $stockDisposal->load('items');
        });
    }

    public function update(int $id, array $attributes): Model
    {
        return DB::transaction(function () use ($id, $attributes) {
            $stockDisposal = $this->findOrFail($id);
            $oldStatus = $stockDisposal->status;
            $newStatus = $attributes['status'] ?? $oldStatus;

            // Jika disetujui (Approve Stock Disposal)
            if ($oldStatus === 'draft' && $newStatus === 'approved') {
                $this->approveStockDisposal($stockDisposal);
                $attributes['approved_by'] = auth()->id() ?? 1;
                $attributes['approved_at'] = now();
            } elseif ($oldStatus === 'draft' && $newStatus === 'cancelled') {
                $stockDisposal->status = 'cancelled';
                $stockDisposal->save();
            }

            if (isset($attributes['items'])) {
                unset($attributes['items']);
            }

            return parent::update($id, $attributes)->load('items');
        });
    }

    protected function approveStockDisposal(StockDisposal $stockDisposal)
    {
        // 1. Kurangi Stok Barang yang Dimusnahkan dari Gudang
        foreach ($stockDisposal->items as $item) {
            $stock = ProductStock::where('warehouse_id', $stockDisposal->warehouse_id)
                ->where('product_id', $item->product_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->first();

            if ($stock) {
                $stock->qty -= $item->qty;
                if ($stock->qty < 0) {
                    $stock->qty = 0; // Kuantitas stok tidak boleh minus
                }
                $stock->save();
            } else {
                // Buat record stok kosong
                ProductStock::create([
                    'warehouse_id' => $stockDisposal->warehouse_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'qty' => 0,
                ]);
            }
        }

        // 2. Posting ke Jurnal Akuntansi (Debet Beban Kerusakan/Pemusnahan, Kredit Persediaan Barang Dagang)
        $totalDisposalCost = $stockDisposal->items->sum('subtotal');

        if ($totalDisposalCost > 0) {
            $inventoryAccount = Account::where('code', '1201')->first(); // Aset Persediaan
            $disposalExpenseAccount = Account::where('code', '5202')->first(); // Beban Kerusakan & Selisih

            if (! $inventoryAccount || ! $disposalExpenseAccount) {
                return;
            }

            $entry = JournalEntry::create([
                'reference_no' => 'JV-SD-'.str_pad($stockDisposal->id, 6, '0', STR_PAD_LEFT),
                'transaction_date' => now()->format('Y-m-d'),
                'description' => 'Pemusnahan Stok Barang ('.$stockDisposal->reason.') - Ref #'.$stockDisposal->reference_no,
                'created_by' => auth()->id() ?? 1,
            ]);

            // Debet: Beban Kerusakan & Selisih Persediaan (Bertambah)
            $entry->items()->create([
                'account_id' => $disposalExpenseAccount->id,
                'debit' => $totalDisposalCost,
                'credit' => 0,
            ]);
            $disposalExpenseAccount->balance += $totalDisposalCost;
            $disposalExpenseAccount->save();

            // Kredit: Persediaan Barang Dagang (Berkurang)
            $entry->items()->create([
                'account_id' => $inventoryAccount->id,
                'debit' => 0,
                'credit' => $totalDisposalCost,
            ]);
            $inventoryAccount->balance -= $totalDisposalCost;
            $inventoryAccount->save();
        }
    }
}
