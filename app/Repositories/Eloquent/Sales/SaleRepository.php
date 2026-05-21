<?php

namespace App\Repositories\Eloquent\Sales;

use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Inventory\ProductStock;
use App\Models\Master\Customer;
use App\Models\Sales\LoyaltyTransaction;
use App\Models\Sales\Promotion;
use App\Models\Sales\Sale;
use App\Repositories\Contracts\Sales\SaleRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SaleRepository extends BaseRepository implements SaleRepositoryInterface
{
    public function __construct(Sale $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $items = $attributes['items'];
            unset($attributes['items']);

            // Auto-generate Invoice Number
            $attributes['invoice_no'] = 'INV-'.date('Ym').'-'.str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $attributes['created_by'] = auth()->id() ?? 1;

            // Calculate Totals
            $totalItems = 0;
            $totalAmount = 0;
            $totalTax = 0;

            $processedItems = [];
            foreach ($items as $item) {
                $qty = $item['qty'];
                $unitPrice = $item['unit_price'];
                $discount = $item['discount'] ?? 0;
                $tax = $item['tax'] ?? 0;

                $subtotal = ($qty * $unitPrice) - $discount + $tax;

                $totalItems += $qty;
                $totalAmount += ($qty * $unitPrice);
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

            // Logika Loyalty Points (Redeem)
            $pointsRedeemed = $attributes['points_redeemed'] ?? 0;
            $pointsDiscount = 0;
            if ($pointsRedeemed > 0 && ! empty($attributes['customer_id'])) {
                $customer = Customer::findOrFail($attributes['customer_id']);
                if ($customer->point_balance < $pointsRedeemed) {
                    throw new \Exception("Customer does not have enough loyalty points. Balance: {$customer->point_balance}");
                }
                // 1 poin = Rp 1
                $pointsDiscount = $pointsRedeemed * 1;
                $attributes['points_discount'] = $pointsDiscount;
            } else {
                $attributes['points_redeemed'] = 0;
                $attributes['points_discount'] = 0;
            }

            // Logika Promosi Otomatis dari Backend
            if (! empty($attributes['promotion_id'])) {
                $promotion = Promotion::where('is_active', true)
                    ->where(function ($query) {
                        $query->whereNull('start_date')
                            ->orWhere('start_date', '<=', now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('end_date')
                            ->orWhere('end_date', '>=', now());
                    })
                    ->find($attributes['promotion_id']);

                if ($promotion) {
                    // Cek minimal pembelian
                    if ($totalAmount >= $promotion->min_purchase_amount) {
                        if ($promotion->type === 'percentage') {
                            $calculatedDiscount = ($totalAmount * $promotion->value) / 100;
                            // Cek batas maksimal diskon jika ada
                            if ($promotion->max_discount_amount && $calculatedDiscount > $promotion->max_discount_amount) {
                                $calculatedDiscount = $promotion->max_discount_amount;
                            }
                            $discountAmount += $calculatedDiscount;
                        } elseif ($promotion->type === 'fixed_amount') {
                            $discountAmount += $promotion->value;
                        }
                    }
                }
            }

            $attributes['discount_amount'] = $discountAmount;

            $grandTotal = ($totalAmount + $totalTax) - $discountAmount - $pointsDiscount;
            $attributes['grand_total'] = $grandTotal < 0 ? 0 : $grandTotal;

            // Hitung poin didapat (Rp 10.000 = 100 poin)
            if (! empty($attributes['customer_id'])) {
                $attributes['points_earned'] = floor($attributes['grand_total'] / 10000) * 100;
            } else {
                $attributes['points_earned'] = 0;
            }

            $amountPaid = $attributes['amount_paid'] ?? 0;
            $attributes['change_amount'] = $amountPaid > $attributes['grand_total'] ? ($amountPaid - $attributes['grand_total']) : 0;

            $sale = parent::create($attributes);

            $sale->items()->createMany($processedItems);

            // Deduct Stock IF status is completed and post to Journal Accounting
            if ($sale->status === 'completed') {
                $this->updateStock($sale, 'subtract');
                $this->postSaleJournalEntry($sale);
                $this->processLoyaltyPoints($sale);
            }

            return $sale->load('items');
        });
    }

    public function update(int $id, array $attributes): Model
    {
        return DB::transaction(function () use ($id, $attributes) {
            $sale = $this->findOrFail($id);
            $oldStatus = $sale->status;
            $newStatus = $attributes['status'] ?? $oldStatus;

            // Logika void / restore stok & reverse Journal & reverse Loyalty
            if ($oldStatus === 'completed' && $newStatus === 'void') {
                $this->updateStock($sale, 'add');
                $this->reverseSaleJournalEntry($sale);
                $this->reverseLoyaltyPoints($sale);
            } elseif ($oldStatus === 'pending' && $newStatus === 'completed') {
                $this->updateStock($sale, 'subtract');
                $this->postSaleJournalEntry($sale);
                $this->processLoyaltyPoints($sale);
            }

            return parent::update($id, $attributes);
        });
    }

    private function updateStock(Sale $sale, string $operation)
    {
        foreach ($sale->items as $item) {
            $stock = ProductStock::firstOrCreate([
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $sale->warehouse_id,
            ]);

            if ($operation === 'add') {
                $stock->qty += $item->qty;
            } elseif ($operation === 'subtract') {
                $stock->qty -= $item->qty;
            }

            $stock->save();
        }
    }

    protected function postSaleJournalEntry(Sale $sale)
    {
        $cashAccount = Account::where('code', '1101')->first();
        $bankAccount = Account::where('code', '1102')->first();
        $salesAccount = Account::where('code', '4101')->first();
        $inventoryAccount = Account::where('code', '1201')->first();
        $hppAccount = Account::where('code', '5101')->first();

        if (! $cashAccount || ! $bankAccount || ! $salesAccount || ! $inventoryAccount || ! $hppAccount) {
            return;
        }

        $paymentAccount = ($sale->payment_method === 'cash') ? $cashAccount : $bankAccount;

        $totalCOGS = $sale->items->sum(function ($item) {
            return $item->qty * $item->cost_price;
        });

        $entry = JournalEntry::create([
            'reference_no' => 'JV-SALE-'.str_pad($sale->id, 6, '0', STR_PAD_LEFT),
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Jurnal Penjualan Otomatis POS - Nota #'.$sale->invoice_no,
            'created_by' => auth()->id() ?? 1,
        ]);

        // Debet: Kas/Bank
        $entry->items()->create([
            'account_id' => $paymentAccount->id,
            'debit' => $sale->grand_total,
            'credit' => 0,
        ]);
        $paymentAccount->balance += $sale->grand_total;
        $paymentAccount->save();

        // Kredit: Pendapatan Penjualan
        $entry->items()->create([
            'account_id' => $salesAccount->id,
            'debit' => 0,
            'credit' => $sale->grand_total,
        ]);
        $salesAccount->balance += $sale->grand_total;
        $salesAccount->save();

        if ($totalCOGS > 0) {
            // Debet: HPP
            $entry->items()->create([
                'account_id' => $hppAccount->id,
                'debit' => $totalCOGS,
                'credit' => 0,
            ]);
            $hppAccount->balance += $totalCOGS;
            $hppAccount->save();

            // Kredit: Persediaan
            $entry->items()->create([
                'account_id' => $inventoryAccount->id,
                'debit' => 0,
                'credit' => $totalCOGS,
            ]);
            $inventoryAccount->balance -= $totalCOGS;
            $inventoryAccount->save();
        }
    }

    protected function reverseSaleJournalEntry(Sale $sale)
    {
        $cashAccount = Account::where('code', '1101')->first();
        $bankAccount = Account::where('code', '1102')->first();
        $salesAccount = Account::where('code', '4101')->first();
        $inventoryAccount = Account::where('code', '1201')->first();
        $hppAccount = Account::where('code', '5101')->first();

        if (! $cashAccount || ! $bankAccount || ! $salesAccount || ! $inventoryAccount || ! $hppAccount) {
            return;
        }

        $paymentAccount = ($sale->payment_method === 'cash') ? $cashAccount : $bankAccount;

        $totalCOGS = $sale->items->sum(function ($item) {
            return $item->qty * $item->cost_price;
        });

        $entry = JournalEntry::create([
            'reference_no' => 'JV-REV-SALE-'.str_pad($sale->id, 6, '0', STR_PAD_LEFT),
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Jurnal Koreksi / Pembatalan Penjualan POS - Nota #'.$sale->invoice_no,
            'created_by' => auth()->id() ?? 1,
        ]);

        // Debet: Pendapatan Penjualan (dikurangi)
        $entry->items()->create([
            'account_id' => $salesAccount->id,
            'debit' => $sale->grand_total,
            'credit' => 0,
        ]);
        $salesAccount->balance -= $sale->grand_total;
        $salesAccount->save();

        // Kredit: Kas/Bank (dikurangi)
        $entry->items()->create([
            'account_id' => $paymentAccount->id,
            'debit' => 0,
            'credit' => $sale->grand_total,
        ]);
        $paymentAccount->balance -= $sale->grand_total;
        $paymentAccount->save();

        if ($totalCOGS > 0) {
            // Debet: Persediaan (dikembalikan)
            $entry->items()->create([
                'account_id' => $inventoryAccount->id,
                'debit' => $totalCOGS,
                'credit' => 0,
            ]);
            $inventoryAccount->balance += $totalCOGS;
            $inventoryAccount->save();

            // Kredit: HPP (dikurangi)
            $entry->items()->create([
                'account_id' => $hppAccount->id,
                'debit' => 0,
                'credit' => $totalCOGS,
            ]);
            $hppAccount->balance -= $totalCOGS;
            $hppAccount->save();
        }
    }

    protected function processLoyaltyPoints(Sale $sale)
    {
        if ($sale->customer_id) {
            $customer = Customer::findOrFail($sale->customer_id);

            // 1. Catat Poin yang Ditukarkan (Redeem)
            if ($sale->points_redeemed > 0) {
                $customer->point_balance -= $sale->points_redeemed;
                LoyaltyTransaction::create([
                    'customer_id' => $sale->customer_id,
                    'sale_id' => $sale->id,
                    'type' => 'redeem',
                    'points' => -$sale->points_redeemed,
                    'amount' => $sale->points_discount,
                    'description' => "Penukaran {$sale->points_redeemed} poin untuk potongan belanja POS",
                    'created_by' => auth()->id() ?? 1,
                ]);
            }

            // 2. Catat Poin yang Didapatkan (Earn)
            if ($sale->points_earned > 0) {
                $customer->point_balance += $sale->points_earned;
                LoyaltyTransaction::create([
                    'customer_id' => $sale->customer_id,
                    'sale_id' => $sale->id,
                    'type' => 'earn',
                    'points' => $sale->points_earned,
                    'amount' => round($sale->points_earned / 100, 2), // Cashback equivalent
                    'description' => 'Akumulasi poin belanja POS - Nota #'.$sale->invoice_no,
                    'created_by' => auth()->id() ?? 1,
                ]);
            }

            $customer->save();
        }
    }

    protected function reverseLoyaltyPoints(Sale $sale)
    {
        if ($sale->customer_id) {
            $customer = Customer::findOrFail($sale->customer_id);

            // 1. Kembalikan Poin yang Dibelanjakan (Refund ke Customer)
            if ($sale->points_redeemed > 0) {
                $customer->point_balance += $sale->points_redeemed;
                LoyaltyTransaction::create([
                    'customer_id' => $sale->customer_id,
                    'sale_id' => $sale->id,
                    'type' => 'adjust',
                    'points' => $sale->points_redeemed,
                    'amount' => $sale->points_discount,
                    'description' => "Pengembalian {$sale->points_redeemed} poin karena Nota #".$sale->invoice_no.' void',
                    'created_by' => auth()->id() ?? 1,
                ]);
            }

            // 2. Cabut Poin yang Didapatkan (Batalkan Poin)
            if ($sale->points_earned > 0) {
                $customer->point_balance -= $sale->points_earned;

                if ($customer->point_balance < 0) {
                    $customer->point_balance = 0;
                }

                LoyaltyTransaction::create([
                    'customer_id' => $sale->customer_id,
                    'sale_id' => $sale->id,
                    'type' => 'adjust',
                    'points' => -$sale->points_earned,
                    'amount' => round($sale->points_earned / 100, 2),
                    'description' => "Pencabutan {$sale->points_earned} poin karena Nota #".$sale->invoice_no.' void',
                    'created_by' => auth()->id() ?? 1,
                ]);
            }

            $customer->save();
        }
    }
}
