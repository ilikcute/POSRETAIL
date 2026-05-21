<?php

namespace App\Repositories\Eloquent\Purchase;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Purchase\Purchase;
use App\Models\Inventory\ProductStock;
use App\Repositories\Contracts\Purchase\PurchaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaseRepository extends BaseRepository implements PurchaseRepositoryInterface
{
    public function __construct(Purchase $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $items = $attributes['items'];
            unset($attributes['items']);

            $paymentMethod = $attributes['payment_method'] ?? 'cash';
            unset($attributes['payment_method']);

            // Auto-generate Reference Number
            $prefix = match ($attributes['type']) {
                'order' => 'PO',
                'purchase' => 'PI',
                'return' => 'PR',
                default => 'DOC',
            };
            $attributes['reference_no'] = $prefix . '-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $attributes['created_by'] = auth()->id() ?? 1; // Default 1 for seeding/testing

            // Calculate Totals
            $totalItems = 0;
            $totalAmount = 0;
            $totalTax = 0;

            $processedItems = [];
            foreach ($items as $item) {
                $qty = $item['qty'];
                $unitCost = $item['unit_cost'];
                $discount = $item['discount'] ?? 0;
                $tax = $item['tax'] ?? 0;
                
                $subtotal = ($qty * $unitCost) - $discount + $tax;

                $totalItems += $qty;
                $totalAmount += ($qty * $unitCost);
                $totalTax += $tax;

                $processedItems[] = array_merge($item, [
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                ]);
            }

            $attributes['total_items'] = $totalItems;
            $attributes['total_amount'] = $totalAmount;
            $attributes['tax_amount'] = $totalTax;
            
            $discountAmount = $attributes['discount_amount'] ?? 0;
            $shippingCost = $attributes['shipping_cost'] ?? 0;
            
            $attributes['grand_total'] = ($totalAmount + $totalTax + $shippingCost) - $discountAmount;

            $purchase = parent::create($attributes);

            $purchase->items()->createMany($processedItems);

            // Update Stock & Post Journal IF type is purchase/return and status is received
            if ($purchase->type === 'purchase' && $purchase->status === 'received') {
                $this->updateStock($purchase, 'add');
                $this->postPurchaseJournalEntry($purchase, $paymentMethod);
            } elseif ($purchase->type === 'return' && $purchase->status === 'received') {
                $this->updateStock($purchase, 'subtract');
                $this->postPurchaseJournalEntry($purchase, $paymentMethod);
            }

            return $purchase->load('items');
        });
    }

    private function updateStock(Purchase $purchase, string $operation)
    {
        foreach ($purchase->items as $item) {
            $stock = ProductStock::firstOrCreate([
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $purchase->warehouse_id,
                // Rak dilewati dulu atau bisa dilogikakan lebih dalam nanti
            ]);

            if ($operation === 'add') {
                $stock->qty += $item->qty;
            } elseif ($operation === 'subtract') {
                $stock->qty -= $item->qty;
            }
            
            $stock->save();
        }
    }

    protected function postPurchaseJournalEntry(Purchase $purchase, string $paymentMethod = 'cash')
    {
        $cashAccount = \App\Models\Finance\Account::where('code', '1101')->first();
        $bankAccount = \App\Models\Finance\Account::where('code', '1102')->first();
        $inventoryAccount = \App\Models\Finance\Account::where('code', '1201')->first();
        $payableAccount = \App\Models\Finance\Account::where('code', '2101')->first();

        if (!$cashAccount || !$bankAccount || !$inventoryAccount || !$payableAccount) {
            return;
        }

        // Tentukan Akun Kredit / Pendanaan
        if ($purchase->payment_status === 'paid') {
            $creditAccount = ($paymentMethod === 'cash') ? $cashAccount : $bankAccount;
        } else {
            // Belum lunas / Tempo -> Kredit Hutang Dagang
            $creditAccount = $payableAccount;
        }

        $entry = \App\Models\Finance\JournalEntry::create([
            'reference_no' => 'JV-PURC-' . str_pad($purchase->id, 6, '0', STR_PAD_LEFT),
            'transaction_date' => now()->format('Y-m-d'),
            'description' => ($purchase->type === 'purchase' ? 'Jurnal Penerimaan Barang Supplier - Ref #' : 'Jurnal Retur Pembelian Supplier - Ref #') . $purchase->reference_no,
            'created_by' => auth()->id() ?? 1,
        ]);

        if ($purchase->type === 'purchase') {
            // DEBET: Persediaan Barang Dagang (Aset Bertambah)
            $entry->items()->create([
                'account_id' => $inventoryAccount->id,
                'debit' => $purchase->grand_total,
                'credit' => 0,
            ]);
            $inventoryAccount->balance += $purchase->grand_total;
            $inventoryAccount->save();

            // KREDIT: Kas/Bank/Hutang (Aset Berkurang / Kewajiban Bertambah)
            $entry->items()->create([
                'account_id' => $creditAccount->id,
                'debit' => 0,
                'credit' => $purchase->grand_total,
            ]);
            
            if ($creditAccount->type === 'asset') {
                $creditAccount->balance -= $purchase->grand_total;
            } else {
                // Hutang Dagang (Liability) bertambah saldo kreditnya
                $creditAccount->balance += $purchase->grand_total;
            }
            $creditAccount->save();
        } else {
            // RETUR PEMBELIAN
            // DEBET: Kas/Bank/Hutang (Refund tunai / Pengurangan hutang)
            $entry->items()->create([
                'account_id' => $creditAccount->id,
                'debit' => $purchase->grand_total,
                'credit' => 0,
            ]);

            if ($creditAccount->type === 'asset') {
                $creditAccount->balance += $purchase->grand_total;
            } else {
                $creditAccount->balance -= $purchase->grand_total;
            }
            $creditAccount->save();

            // KREDIT: Persediaan Barang Dagang (Aset berkurang karena dikembalikan)
            $entry->items()->create([
                'account_id' => $inventoryAccount->id,
                'debit' => 0,
                'credit' => $purchase->grand_total,
            ]);
            $inventoryAccount->balance -= $purchase->grand_total;
            $inventoryAccount->save();
        }
    }
}
