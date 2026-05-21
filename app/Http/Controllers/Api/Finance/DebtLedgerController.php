<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;

use App\Models\Purchase\Purchase;
use App\Models\Sales\Sale;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalItem;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DebtLedgerController extends Controller
{
    use ApiResponseTrait;

    /**
     * Helper: Dapatkan atau buat Akun Piutang Dagang jika belum ada
     */
    protected function getOrCreateReceivableAccount()
    {
        $account = Account::where('code', '1103')->first();
        if (!$account) {
            $account = Account::create([
                'code' => '1103',
                'name' => 'Piutang Dagang',
                'type' => 'asset',
                'balance' => 0.0,
                'description' => 'Hak tagih pembayaran atas penjualan kredit kepada pelanggan',
            ]);
        }
        return $account;
    }

    /**
     * 1. GET /api/debt/ap-ledger
     * Laporan Buku Besar Utang Dagang (Accounts Payable Ledger)
     */
    public function apLedger(Request $request): JsonResponse
    {
        $supplierId = $request->query('supplier_id');

        $debts = Purchase::where('type', 'purchase')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('status', 'completed')
            ->with(['supplier', 'warehouse'])
            ->when($supplierId, function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId);
            })
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($purchase) {
                $grandTotal = (float)$purchase->grand_total;
                $amountPaid = (float)$purchase->amount_paid;
                $outstanding = max(0.0, $grandTotal - $amountPaid);
                
                $dueDate = $purchase->due_date ? Carbon::parse($purchase->due_date) : null;
                $ageDays = $dueDate ? max(0, $dueDate->diffInDays(now(), false)) : 0;
                $isOverdue = $dueDate ? now()->greaterThan($dueDate) : false;

                return [
                    'purchase_id' => $purchase->id,
                    'reference_no' => $purchase->reference_no,
                    'supplier_name' => $purchase->supplier->name ?? 'Supplier',
                    'warehouse_name' => $purchase->warehouse->name ?? 'Gudang',
                    'purchase_date' => $purchase->created_at->format('Y-m-d'),
                    'due_date' => $purchase->due_date,
                    'grand_total' => $grandTotal,
                    'amount_paid' => $amountPaid,
                    'outstanding_debt' => $outstanding,
                    'days_outstanding' => $ageDays,
                    'status' => $isOverdue ? 'OVERDUE' : 'ACTIVE',
                ];
            });

        $summary = [
            'total_invoices_outstanding' => $debts->count(),
            'total_debt_value' => $debts->sum('grand_total'),
            'total_amount_paid' => $debts->sum('amount_paid'),
            'total_outstanding_balance' => $debts->sum('outstanding_debt'),
        ];

        return $this->successResponse([
            'summary' => $summary,
            'ledger' => $debts
        ], 'Accounts Payable (AP) Ledger retrieved successfully');
    }

    /**
     * 2. GET /api/debt/ap-aging
     * Laporan Umur Utang Dagang (AP Aging Report)
     */
    public function apAging(Request $request): JsonResponse
    {
        $debts = Purchase::where('type', 'purchase')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('status', 'completed')
            ->get();

        $agingBuckets = [
            'current' => 0.0,      // 0 - 30 hari
            'aging_31_60' => 0.0,  // 31 - 60 hari
            'aging_61_90' => 0.0,  // 61 - 90 hari
            'over_90' => 0.0,      // > 90 hari
        ];

        foreach ($debts as $purchase) {
            $outstanding = (float)$purchase->grand_total - (float)$purchase->amount_paid;
            if ($outstanding <= 0) continue;

            $date = Carbon::parse($purchase->created_at);
            $ageDays = max(0, $date->diffInDays(now()));

            if ($ageDays <= 30) {
                $agingBuckets['current'] += $outstanding;
            } elseif ($ageDays <= 60) {
                $agingBuckets['aging_31_60'] += $outstanding;
            } elseif ($ageDays <= 90) {
                $agingBuckets['aging_61_90'] += $outstanding;
            } else {
                $agingBuckets['over_90'] += $outstanding;
            }
        }

        $totalAging = array_sum($agingBuckets);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'total_outstanding_ap' => $totalAging,
            'buckets' => $agingBuckets,
            'percentage' => [
                'current' => $totalAging > 0 ? round(($agingBuckets['current'] / $totalAging) * 100, 2) . '%' : '0%',
                'aging_31_60' => $totalAging > 0 ? round(($agingBuckets['aging_31_60'] / $totalAging) * 100, 2) . '%' : '0%',
                'aging_61_90' => $totalAging > 0 ? round(($agingBuckets['aging_61_90'] / $totalAging) * 100, 2) . '%' : '0%',
                'over_90' => $totalAging > 0 ? round(($agingBuckets['over_90'] / $totalAging) * 100, 2) . '%' : '0%',
            ]
        ];

        return $this->successResponse($report, 'Accounts Payable (AP) Aging Report generated successfully');
    }

    /**
     * 3. POST /api/debt/pay-ap
     * Pembayaran Utang Dagang (Menjurnal Otomatis!)
     */
    public function payAp(Request $request): JsonResponse
    {
        $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'payment_amount' => 'required|numeric|min:1',
            'bank_account_code' => 'required|exists:accounts,code', // misal Kas Toko 1101 atau Bank 1102
            'notes' => 'nullable|string',
        ]);

        $purchaseId = $request->input('purchase_id');
        $paymentAmount = (float)$request->input('payment_amount');
        $bankAccountCode = $request->input('bank_account_code');
        $notes = $request->input('notes') ?? 'Pembayaran Utang Dagang';

        $result = DB::transaction(function () use ($purchaseId, $paymentAmount, $bankAccountCode, $notes) {
            $purchase = Purchase::findOrFail($purchaseId);
            $grandTotal = (float)$purchase->grand_total;
            $currentPaid = (float)$purchase->amount_paid;
            $outstanding = max(0.0, $grandTotal - $currentPaid);

            if ($paymentAmount > $outstanding) {
                return [
                    'success' => false,
                    'message' => "Jumlah pembayaran (Rp " . number_format($paymentAmount, 0, ',', '.') . ") melebihi sisa utang (Rp " . number_format($outstanding, 0, ',', '.') . ")"
                ];
            }

            // 1. Update Nominal Terbayar & Status Pembayaran di Pembelian
            $newPaid = $currentPaid + $paymentAmount;
            $purchase->amount_paid = $newPaid;
            
            if ($newPaid >= $grandTotal) {
                $purchase->payment_status = 'paid';
            } else {
                $purchase->payment_status = 'partial';
            }
            $purchase->save();

            // 2. POSTING DOUBLE-ENTRY JOURNAL ENTRY
            // Akun Debit: 2101 (Utang Dagang) -> Liability berkurang
            // Akun Kredit: bank_account_code (Kas/Bank) -> Asset berkurang
            $apAccount = Account::where('code', '2101')->firstOrFail();
            $cashAccount = Account::where('code', $bankAccountCode)->firstOrFail();

            $journalRef = 'JV-AP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
            
            $journal = JournalEntry::create([
                'reference_no' => $journalRef,
                'transaction_date' => now()->toDateString(),
                'description' => "{$notes} [Ref: {$purchase->reference_no}]",
                'created_by' => auth()->id() ?? 1,
            ]);

            // Debet Utang Dagang
            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $apAccount->id,
                'debit' => $paymentAmount,
                'credit' => 0.0,
            ]);
            $apAccount->decrement('balance', $paymentAmount);

            // Kredit Kas / Bank
            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $cashAccount->id,
                'debit' => 0.0,
                'credit' => $paymentAmount,
            ]);
            $cashAccount->decrement('balance', $paymentAmount);

            return [
                'success' => true,
                'data' => [
                    'purchase_id' => $purchase->id,
                    'reference_no' => $purchase->reference_no,
                    'new_amount_paid' => $newPaid,
                    'outstanding_debt' => max(0.0, $grandTotal - $newPaid),
                    'payment_status' => $purchase->payment_status,
                    'journal_entry' => $journalRef,
                ]
            ];
        });

        if ($result['success']) {
            return $this->successResponse($result['data'], 'Accounts Payable payment processed and journalized successfully');
        }

        return $this->errorResponse($result['message'], 400);
    }

    /**
     * 4. GET /api/debt/ar-ledger
     * Laporan Buku Besar Piutang Dagang (Accounts Receivable Ledger)
     */
    public function arLedger(Request $request): JsonResponse
    {
        $customerId = $request->query('customer_id');

        $receivables = Sale::where('status', 'completed')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->with(['customer', 'warehouse'])
            ->when($customerId, function ($q) use ($customerId) {
                $q->where('customer_id', $customerId);
            })
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($sale) {
                $grandTotal = (float)$sale->grand_total;
                $amountPaid = (float)$sale->amount_paid;
                $outstanding = max(0.0, $grandTotal - $amountPaid);

                $dueDate = $sale->due_date ? Carbon::parse($sale->due_date) : null;
                $ageDays = $dueDate ? max(0, $dueDate->diffInDays(now(), false)) : 0;
                $isOverdue = $dueDate ? now()->greaterThan($dueDate) : false;

                return [
                    'sale_id' => $sale->id,
                    'invoice_no' => $sale->invoice_no,
                    'customer_name' => $sale->customer->name ?? 'Non-Member (Credit)',
                    'warehouse_name' => $sale->warehouse->name ?? 'Gudang Toko',
                    'sale_date' => $sale->created_at->format('Y-m-d'),
                    'due_date' => $sale->due_date,
                    'grand_total' => $grandTotal,
                    'amount_paid' => $amountPaid,
                    'outstanding_receivable' => $outstanding,
                    'days_outstanding' => $ageDays,
                    'status' => $isOverdue ? 'OVERDUE' : 'ACTIVE',
                ];
            });

        $summary = [
            'total_invoices_outstanding' => $receivables->count(),
            'total_receivable_value' => $receivables->sum('grand_total'),
            'total_amount_received' => $receivables->sum('amount_paid'),
            'total_outstanding_balance' => $receivables->sum('outstanding_receivable'),
        ];

        return $this->successResponse([
            'summary' => $summary,
            'ledger' => $receivables
        ], 'Accounts Receivable (AR) Ledger retrieved successfully');
    }

    /**
     * 5. GET /api/debt/ar-aging
     * Laporan Umur Piutang Dagang (AR Aging Report)
     */
    public function arAging(Request $request): JsonResponse
    {
        $receivables = Sale::where('status', 'completed')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->get();

        $agingBuckets = [
            'current' => 0.0,      // 0 - 30 hari
            'aging_31_60' => 0.0,  // 31 - 60 hari
            'aging_61_90' => 0.0,  // 61 - 90 hari
            'over_90' => 0.0,      // > 90 hari
        ];

        foreach ($receivables as $sale) {
            $outstanding = (float)$sale->grand_total - (float)$sale->amount_paid;
            if ($outstanding <= 0) continue;

            $date = Carbon::parse($sale->created_at);
            $ageDays = max(0, $date->diffInDays(now()));

            if ($ageDays <= 30) {
                $agingBuckets['current'] += $outstanding;
            } elseif ($ageDays <= 60) {
                $agingBuckets['aging_31_60'] += $outstanding;
            } elseif ($ageDays <= 90) {
                $agingBuckets['aging_61_90'] += $outstanding;
            } else {
                $agingBuckets['over_90'] += $outstanding;
            }
        }

        $totalAging = array_sum($agingBuckets);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'total_outstanding_ar' => $totalAging,
            'buckets' => $agingBuckets,
            'percentage' => [
                'current' => $totalAging > 0 ? round(($agingBuckets['current'] / $totalAging) * 100, 2) . '%' : '0%',
                'aging_31_60' => $totalAging > 0 ? round(($agingBuckets['aging_31_60'] / $totalAging) * 100, 2) . '%' : '0%',
                'aging_61_90' => $totalAging > 0 ? round(($agingBuckets['aging_61_90'] / $totalAging) * 100, 2) . '%' : '0%',
                'over_90' => $totalAging > 0 ? round(($agingBuckets['over_90'] / $totalAging) * 100, 2) . '%' : '0%',
            ]
        ];

        return $this->successResponse($report, 'Accounts Receivable (AR) Aging Report generated successfully');
    }

    /**
     * 6. POST /api/debt/receive-ar
     * Pelunasan Piutang Pelanggan (Menjurnal Otomatis!)
     */
    public function receiveAr(Request $request): JsonResponse
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'payment_amount' => 'required|numeric|min:1',
            'bank_account_code' => 'required|exists:accounts,code', // misal Kas Toko 1101 atau Bank 1102
            'notes' => 'nullable|string',
        ]);

        $saleId = $request->input('sale_id');
        $paymentAmount = (float)$request->input('payment_amount');
        $bankAccountCode = $request->input('bank_account_code');
        $notes = $request->input('notes') ?? 'Penerimaan Piutang Pelanggan';

        $result = DB::transaction(function () use ($saleId, $paymentAmount, $bankAccountCode, $notes) {
            $sale = Sale::findOrFail($saleId);
            $grandTotal = (float)$sale->grand_total;
            $currentPaid = (float)$sale->amount_paid;
            $outstanding = max(0.0, $grandTotal - $currentPaid);

            if ($paymentAmount > $outstanding) {
                return [
                    'success' => false,
                    'message' => "Jumlah pelunasan (Rp " . number_format($paymentAmount, 0, ',', '.') . ") melebihi sisa piutang (Rp " . number_format($outstanding, 0, ',', '.') . ")"
                ];
            }

            // 1. Update Nominal Terbayar & Status Pembayaran di Penjualan
            $newPaid = $currentPaid + $paymentAmount;
            $sale->amount_paid = $newPaid;
            
            if ($newPaid >= $grandTotal) {
                $sale->payment_status = 'paid';
            } else {
                $sale->payment_status = 'partial';
            }
            $sale->save();

            // 2. POSTING DOUBLE-ENTRY JOURNAL ENTRY
            // Akun Debit: bank_account_code (Kas/Bank) -> Asset bertambah
            // Akun Kredit: 1103 (Piutang Dagang) -> Asset berkurang
            $cashAccount = Account::where('code', $bankAccountCode)->firstOrFail();
            $arAccount = $this->getOrCreateReceivableAccount();

            $journalRef = 'JV-AR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
            
            $journal = JournalEntry::create([
                'reference_no' => $journalRef,
                'transaction_date' => now()->toDateString(),
                'description' => "{$notes} [Ref: {$sale->invoice_no}]",
                'created_by' => auth()->id() ?? 1,
            ]);

            // Debet Kas / Bank
            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $cashAccount->id,
                'debit' => $paymentAmount,
                'credit' => 0.0,
            ]);
            $cashAccount->increment('balance', $paymentAmount);

            // Kredit Piutang Dagang
            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $arAccount->id,
                'debit' => 0.0,
                'credit' => $paymentAmount,
            ]);
            $arAccount->decrement('balance', $paymentAmount);

            return [
                'success' => true,
                'data' => [
                    'sale_id' => $sale->id,
                    'invoice_no' => $sale->invoice_no,
                    'new_amount_paid' => $newPaid,
                    'outstanding_receivable' => max(0.0, $grandTotal - $newPaid),
                    'payment_status' => $sale->payment_status,
                    'journal_entry' => $journalRef,
                ]
            ];
        });

        if ($result['success']) {
            return $this->successResponse($result['data'], 'Accounts Receivable receipt processed and journalized successfully');
        }

        return $this->errorResponse($result['message'], 400);
    }
}
