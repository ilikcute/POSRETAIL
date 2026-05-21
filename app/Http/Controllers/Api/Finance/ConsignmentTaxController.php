<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;

use App\Models\Master\Product;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Purchase\Purchase;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalItem;
use App\Models\Master\Supplier;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ConsignmentTaxController extends Controller
{
    use ApiResponseTrait;

    /**
     * Helper: Dapatkan atau buat Akun Pajak & Konsinyasi jika belum ada
     */
    protected function getOrCreateAccounts()
    {
        $ppnMasukan = Account::where('code', '1104')->first();
        if (!$ppnMasukan) {
            $ppnMasukan = Account::create([
                'code' => '1104',
                'name' => 'PPN Masukan (VAT Input)',
                'type' => 'asset',
                'balance' => 0.0,
                'description' => 'Aset pajak pertambahan nilai dari pembelian ke supplier',
            ]);
        }

        $ppnKeluaran = Account::where('code', '2201')->first();
        if (!$ppnKeluaran) {
            $ppnKeluaran = Account::create([
                'code' => '2201',
                'name' => 'PPN Keluaran (VAT Output)',
                'type' => 'liability',
                'balance' => 0.0,
                'description' => 'Kewajiban pajak pertambahan nilai dari penjualan kasir POS',
            ]);
        }

        $hutangKonsinyasi = Account::where('code', '2102')->first();
        if (!$hutangKonsinyasi) {
            $hutangKonsinyasi = Account::create([
                'code' => '2102',
                'name' => 'Hutang Konsinyasi (Consignment Payable)',
                'type' => 'liability',
                'balance' => 0.0,
                'description' => 'Kewajiban pembayaran kepada supplier untuk barang konsinyasi yang laku terjual',
            ]);
        }

        return [
            'ppn_masukan' => $ppnMasukan,
            'ppn_keluaran' => $ppnKeluaran,
            'hutang_konsinyasi' => $hutangKonsinyasi,
        ];
    }

    /**
     * 1. GET /api/tax/reconciliation
     * Laporan Rekonsiliasi Pajak PPN (Masukan vs Keluaran)
     */
    public function taxReconciliation(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : now()->startOfMonth();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date'))->endOfDay() : now()->endOfMonth();

        $this->getOrCreateAccounts(); // Pastikan akun tersedia

        // 1. Hitung PPN Masukan dari Pembelian
        $purchases = Purchase::where('type', 'purchase')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalPurchaseAmount = 0.0;
        $totalVatInput = 0.0;

        foreach ($purchases as $purchase) {
            $totalPurchaseAmount += (float)$purchase->grand_total;
            // Jika tax_amount bernilai 0 tapi diasumsikan PPN 11% inclusive
            if ((float)$purchase->tax_amount > 0) {
                $totalVatInput += (float)$purchase->tax_amount;
            } else {
                // Rekonstruksi PPN 11% inclusive
                $net = (float)$purchase->grand_total / 1.11;
                $totalVatInput += (float)$purchase->grand_total - $net;
            }
        }

        // 2. Hitung PPN Keluaran dari Penjualan POS
        $sales = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalSalesAmount = 0.0;
        $totalVatOutput = 0.0;

        foreach ($sales as $sale) {
            $totalSalesAmount += (float)$sale->grand_total;
            if ((float)$sale->tax_amount > 0) {
                $totalVatOutput += (float)$sale->tax_amount;
            } else {
                // Rekonstruksi PPN 11% inclusive
                $net = (float)$sale->grand_total / 1.11;
                $totalVatOutput += (float)$sale->grand_total - $net;
            }
        }

        $netVatPayable = $totalVatOutput - $totalVatInput;

        $report = [
            'period' => [
                'start_date' => $startDate->toDateTimeString(),
                'end_date' => $endDate->toDateTimeString(),
            ],
            'vat_input_purchase' => [
                'gross_purchases' => $totalPurchaseAmount,
                'vat_input' => $totalVatInput,
            ],
            'vat_output_sales' => [
                'gross_sales' => $totalSalesAmount,
                'vat_output' => $totalVatOutput,
            ],
            'vat_position' => [
                'net_vat_payable' => $netVatPayable,
                'status' => $netVatPayable > 0 ? 'UNDERPAYMENT (Harus Bayar ke Negara)' : 'OVERPAYMENT (Kelebihan Bayar/Kompensasi)',
            ]
        ];

        return $this->successResponse($report, 'PPN Tax Reconciliation Report generated successfully');
    }

    /**
     * 2. GET /api/consignment/ledger
     * Laporan Buku Penjualan Konsinyasi per Supplier
     */
    public function consignmentLedger(Request $request): JsonResponse
    {
        $supplierId = $request->query('supplier_id');
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : now()->startOfMonth();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date'))->endOfDay() : now()->endOfMonth();

        $this->getOrCreateAccounts(); // Pastikan akun tersedia

        // Ambil penjualan item yang bertipe konsinyasi
        $items = SaleItem::where('purchase_type', 'consignment')
            ->whereHas('sale', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'completed')
                  ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->with(['product.brand', 'sale'])
            ->get();

        // Jika difilter supplier, kita cari produk yang disupply supplier tersebut
        // Karena di skema awal produk belong to category/brand, kita cari supplier terkait lewat pembelian historis produk
        $ledger = collect();

        foreach ($items as $item) {
            $product = $item->product;
            if (!$product) continue;

            // Dapatkan supplier untuk produk ini (dari history pembelian terakhir)
            $lastPurchaseItem = \App\Models\Purchase\PurchaseItem::where('product_id', $product->id)
                ->whereHas('purchase', function ($q) {
                    $q->where('status', 'received');
                })
                ->orderBy('id', 'desc')
                ->with('purchase.supplier')
                ->first();

            $supplier = $lastPurchaseItem->purchase->supplier ?? Supplier::first();
            if (!$supplier) continue;

            if ($supplierId && $supplier->id != $supplierId) {
                continue; // Skip jika filter supplier tidak cocok
            }

            // Hitung komisi toko (misal 20% komisi)
            $commissionRate = (float)($product->consignment_commission_fee > 0 ? $product->consignment_commission_fee : 20.00);
            $grossTotal = (float)$item->subtotal;
            $commissionAmount = $grossTotal * ($commissionRate / 100.0);
            $supplierPayable = $grossTotal - $commissionAmount;

            $ledger->push([
                'sale_item_id' => $item->id,
                'invoice_no' => $item->sale->invoice_no,
                'sold_at' => $item->sale->created_at->format('Y-m-d H:i:s'),
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'qty_sold' => (float)$item->qty,
                'price' => (float)$item->price,
                'gross_revenue' => $grossTotal,
                'commission_rate' => $commissionRate . '%',
                'store_commission' => $commissionAmount,
                'supplier_payable' => $supplierPayable,
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ]
            ]);
        }

        $summary = [
            'total_items_sold' => $ledger->sum('qty_sold'),
            'total_gross_revenue' => $ledger->sum('gross_revenue'),
            'total_store_commission' => $ledger->sum('store_commission'),
            'total_supplier_payable' => $ledger->sum('supplier_payable'),
        ];

        return $this->successResponse([
            'summary' => $summary,
            'ledger' => $ledger->values()
        ], 'Consignment Sales Ledger retrieved successfully');
    }

    /**
     * 3. POST /api/consignment/settle
     * Pembayaran Pelunasan Konsinyasi Ke Supplier (Menjurnal Otomatis!)
     */
    public function settleConsignment(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'bank_account_code' => 'required|exists:accounts,code', // misal 1101 Kas atau 1102 Bank
            'payment_amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
        ]);

        $supplierId = $request->input('supplier_id');
        $bankAccountCode = $request->input('bank_account_code');
        $paymentAmount = (float)$request->input('payment_amount');
        $notes = $request->input('notes') ?? 'Pelunasan Hutang Konsinyasi';

        $supplier = Supplier::findOrFail($supplierId);
        $accounts = $this->getOrCreateAccounts();

        $result = DB::transaction(function () use ($supplier, $bankAccountCode, $paymentAmount, $notes, $accounts) {
            // Posting Jurnal Pelunasan Konsinyasi
            // Debit: 2102 (Hutang Konsinyasi) -> Liability berkurang
            // Kredit: bank_account_code (Kas/Bank) -> Asset berkurang
            $consignmentAccount = $accounts['hutang_konsinyasi'];
            $cashAccount = Account::where('code', $bankAccountCode)->firstOrFail();

            $journalRef = 'JV-CONS-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));

            $journal = JournalEntry::create([
                'reference_no' => $journalRef,
                'transaction_date' => now()->toDateString(),
                'description' => "{$notes} ke [Supplier: {$supplier->name}]",
                'created_by' => auth()->id() ?? 1,
            ]);

            // Debet Hutang Konsinyasi
            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $consignmentAccount->id,
                'debit' => $paymentAmount,
                'credit' => 0.0,
            ]);
            $consignmentAccount->decrement('balance', $paymentAmount);

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
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'amount_paid' => $paymentAmount,
                    'bank_account' => $cashAccount->name,
                    'journal_entry' => $journalRef,
                ]
            ];
        });

        return $this->successResponse($result['data'], 'Consignment supplier settlement processed and journalized successfully');
    }
}
