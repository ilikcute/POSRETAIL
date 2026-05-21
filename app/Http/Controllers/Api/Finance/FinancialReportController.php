<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\JournalItem;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialReportController extends Controller
{
    use ApiResponseTrait;

    /**
     * Laporan Neraca (Balance Sheet)
     * Mengukur posisi Aset, Kewajiban, dan Ekuitas.
     * Persamaan: Aset = Kewajiban + Ekuitas
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        // 1. Ambil semua akun aktif
        $accounts = Account::where('is_active', true)->get();

        // 2. Kelompokkan berdasarkan tipe akun
        $assets = $accounts->where('type', 'asset')->values();
        $liabilities = $accounts->where('type', 'liability')->values();
        $equityAccounts = $accounts->where('type', 'equity')->values();

        // 3. Hitung Laba Berjalan (Net Income) dari Laba Rugi untuk dimasukkan ke bagian Ekuitas
        $totalRevenue = $accounts->where('type', 'revenue')->sum('balance');
        $totalExpense = $accounts->where('type', 'expense')->sum('balance');
        $currentEarnings = $totalRevenue - $totalExpense; // Pendapatan - Beban

        // 4. Jumlahkan total per kategori
        $totalAssets = $assets->sum('balance');
        $totalLiabilities = $liabilities->sum('balance');
        $totalEquityBase = $equityAccounts->sum('balance');
        $totalEquity = $totalEquityBase + $currentEarnings;

        // 5. Cek Keseimbangan Neraca (Balance Verification)
        $rightSide = $totalLiabilities + $totalEquity;
        $discrepancy = $totalAssets - $rightSide;
        $isBalanced = abs($discrepancy) < 0.01;

        // 6. Format Response Terstruktur & Cantik
        $reportData = [
            'metadata' => [
                'report_name' => 'Laporan Neraca (Balance Sheet)',
                'generated_at' => now()->toIso8601String(),
                'currency' => 'IDR',
                'is_balanced' => $isBalanced,
                'discrepancy' => round($discrepancy, 2),
            ],
            'assets' => [
                'items' => $assets->map(fn ($acc) => [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'balance' => $acc->balance,
                ]),
                'total' => round($totalAssets, 2),
            ],
            'liabilities' => [
                'items' => $liabilities->map(fn ($acc) => [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'balance' => $acc->balance,
                ]),
                'total' => round($totalLiabilities, 2),
            ],
            'equity' => [
                'items' => $equityAccounts->map(fn ($acc) => [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'balance' => $acc->balance,
                ])->concat([
                    [
                        'code' => '3999',
                        'name' => 'Laba Tahun Berjalan (Net Income)',
                        'balance' => round($currentEarnings, 2),
                    ],
                ]),
                'total' => round($totalEquity, 2),
            ],
            'summary' => [
                'total_assets' => round($totalAssets, 2),
                'total_liabilities_and_equity' => round($rightSide, 2),
            ],
        ];

        return $this->successResponse($reportData, 'Laporan neraca berhasil dibuat');
    }

    /**
     * Laporan Laba Rugi (Profit & Loss / Income Statement)
     * Mengukur omzet pendapatan dikurangi beban pengeluaran.
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $accounts = Account::where('is_active', true)->get();

        $revenues = $accounts->where('type', 'revenue')->values();
        $expenses = $accounts->where('type', 'expense')->values();

        $totalRevenue = $revenues->sum('balance');
        $totalExpense = $expenses->sum('balance');
        $netProfit = $totalRevenue - $totalExpense;

        $reportData = [
            'metadata' => [
                'report_name' => 'Laporan Laba Rugi (Profit & Loss Statement)',
                'generated_at' => now()->toIso8601String(),
                'currency' => 'IDR',
            ],
            'revenues' => [
                'items' => $revenues->map(fn ($acc) => [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'balance' => $acc->balance,
                ]),
                'total' => round($totalRevenue, 2),
            ],
            'expenses' => [
                'items' => $expenses->map(fn ($acc) => [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'balance' => $acc->balance,
                ]),
                'total' => round($totalExpense, 2),
            ],
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_expense' => round($totalExpense, 2),
                'net_profit' => round($netProfit, 2),
            ],
        ];

        return $this->successResponse($reportData, 'Laporan laba rugi berhasil dibuat');
    }

    /**
     * Laporan Arus Kas (Statement of Cash Flows) - Direct Method
     * Menganalisis perputaran uang tunai & bank riil dari Jurnal Umum.
     */
    public function cashFlow(Request $request): JsonResponse
    {
        $cashAccounts = Account::whereIn('code', ['1101', '1102'])->pluck('id')->toArray();

        // Ambil semua Journal Items yang menyangkut akun Kas/Bank
        $cashJournalItems = JournalItem::whereIn('account_id', $cashAccounts)
            ->with(['entry.items.account'])
            ->get();

        $operatingInflow = 0;
        $operatingOutflowExpense = 0;
        $operatingOutflowSupplier = 0;
        $financingInflow = 0;
        $financingOutflow = 0;

        foreach ($cashJournalItems as $item) {
            $isDebit = $item->debit > 0;
            $amount = $isDebit ? $item->debit : $item->credit;

            // Cari item lawan dalam jurnal entry yang sama
            $oppositeItems = $item->entry->items->where('account_id', '!=', $item->account_id);

            foreach ($oppositeItems as $opp) {
                $oppType = $opp->account->type;
                $oppCode = $opp->account->code;

                if ($isDebit) {
                    // Kas Masuk (Debit Kas/Bank)
                    if ($oppType === 'revenue') {
                        $operatingInflow += $amount;
                    } elseif ($oppType === 'equity') {
                        $financingInflow += $amount;
                    } elseif ($oppCode === '1201' || $oppCode === '2101') {
                        // Refund dari supplier atau pengurangan piutang
                        $operatingInflow += $amount;
                    }
                } else {
                    // Kas Keluar (Kredit Kas/Bank)
                    if ($oppType === 'expense') {
                        $operatingOutflowExpense += $amount;
                    } elseif ($oppCode === '1201' || $oppCode === '2101') {
                        // Pembelian barang dagangan ke supplier (1201) / bayar hutang (2101)
                        $operatingOutflowSupplier += $amount;
                    } elseif ($oppType === 'equity') {
                        $financingOutflow += $amount;
                    }
                }
            }
        }

        $netOperatingCash = $operatingInflow - $operatingOutflowExpense - $operatingOutflowSupplier;
        $netFinancingCash = $financingInflow - $financingOutflow;
        $netCashIncrease = $netOperatingCash + $netFinancingCash;

        $currentCashBalance = Account::whereIn('code', ['1101', '1102'])->sum('balance');
        $beginningCash = $currentCashBalance - $netCashIncrease;

        $reportData = [
            'metadata' => [
                'report_name' => 'Laporan Arus Kas (Statement of Cash Flows)',
                'method' => 'Direct Method (Metode Langsung)',
                'generated_at' => now()->toIso8601String(),
                'currency' => 'IDR',
            ],
            'operating_activities' => [
                'cash_inflow_customers' => round($operatingInflow, 2),
                'cash_outflow_suppliers' => round($operatingOutflowSupplier, 2),
                'cash_outflow_expenses' => round($operatingOutflowExpense, 2),
                'net_cash_from_operating' => round($netOperatingCash, 2),
            ],
            'investing_activities' => [
                'net_cash_from_investing' => 0.00,
            ],
            'financing_activities' => [
                'cash_inflow_owners' => round($financingInflow, 2),
                'cash_outflow_owners' => round($financingOutflow, 2),
                'net_cash_from_financing' => round($netFinancingCash, 2),
            ],
            'summary' => [
                'beginning_cash_balance' => round($beginningCash, 2),
                'net_cash_increase' => round($netCashIncrease, 2),
                'ending_cash_balance' => round($currentCashBalance, 2),
            ],
        ];

        return $this->successResponse($reportData, 'Laporan arus kas berhasil dibuat');
    }
}
