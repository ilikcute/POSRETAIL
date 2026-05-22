<?php

namespace App\Http\Controllers\Api\Finance;

use App\Exceptions\FinancialReportException;
use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\JournalItem;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
        try {
            $validator = Validator::make($request->all(), [
                'end_date' => 'nullable|date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                throw new FinancialReportException('Parameter tanggal tidak valid.', $validator->errors()->toArray());
            }

            $endDate = $request->query('end_date') ?? Carbon::now()->toDateString();

            $reportData = DB::transaction(function () use ($endDate) {
                // Ambil semua akun aktif dengan shared lock untuk konsistensi data
                $accounts = Account::where('is_active', true)->sharedLock()->get();

                // Dapatkan sum debit & credit setelah end_date
                $afterEndItems = JournalItem::whereHas('entry', function ($query) use ($endDate) {
                    $query->where('transaction_date', '>', $endDate);
                })
                    ->select('account_id', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
                    ->groupBy('account_id')
                    ->sharedLock()
                    ->get()
                    ->keyBy('account_id');

                // Sesuaikan saldo akun secara retrospektif ke posisi end_date
                $adjustedAccounts = $accounts->map(function ($account) use ($afterEndItems) {
                    $after = $afterEndItems->get($account->id);
                    $debitAfter = $after ? (float) $after->total_debit : 0.0;
                    $creditAfter = $after ? (float) $after->total_credit : 0.0;

                    if ($account->type === 'asset' || $account->type === 'expense') {
                        $historicalBalance = (float) $account->balance - ($debitAfter - $creditAfter);
                    } else {
                        $historicalBalance = (float) $account->balance - ($creditAfter - $debitAfter);
                    }

                    $account->historical_balance = $historicalBalance;

                    return $account;
                });

                $assets = $adjustedAccounts->where('type', 'asset')->values();
                $liabilities = $adjustedAccounts->where('type', 'liability')->values();
                $equityAccounts = $adjustedAccounts->where('type', 'equity')->values();

                // Hitung Laba Berjalan (Net Income) dari Laba Rugi untuk dimasukkan ke bagian Ekuitas
                $totalRevenue = $adjustedAccounts->where('type', 'revenue')->sum('historical_balance');
                $totalExpense = $adjustedAccounts->where('type', 'expense')->sum('historical_balance');
                $currentEarnings = $totalRevenue - $totalExpense;

                // Jumlahkan total per kategori
                $totalAssets = $assets->sum('historical_balance');
                $totalLiabilities = $liabilities->sum('historical_balance');
                $totalEquityBase = $equityAccounts->sum('historical_balance');
                $totalEquity = $totalEquityBase + $currentEarnings;

                // Cek Keseimbangan Neraca
                $rightSide = $totalLiabilities + $totalEquity;
                $discrepancy = $totalAssets - $rightSide;
                $isBalanced = abs($discrepancy) < 0.01;

                return [
                    'metadata' => [
                        'report_name' => 'Laporan Neraca (Balance Sheet)',
                        'generated_at' => now()->toIso8601String(),
                        'end_date' => $endDate,
                        'currency' => 'IDR',
                        'is_balanced' => $isBalanced,
                        'discrepancy' => round($discrepancy, 2),
                    ],
                    'assets' => [
                        'items' => $assets->map(fn ($acc) => [
                            'code' => $acc->code,
                            'name' => $acc->name,
                            'balance' => round($acc->historical_balance, 2),
                        ]),
                        'total' => round($totalAssets, 2),
                    ],
                    'liabilities' => [
                        'items' => $liabilities->map(fn ($acc) => [
                            'code' => $acc->code,
                            'name' => $acc->name,
                            'balance' => round($acc->historical_balance, 2),
                        ]),
                        'total' => round($totalLiabilities, 2),
                    ],
                    'equity' => [
                        'items' => $equityAccounts->map(fn ($acc) => [
                            'code' => $acc->code,
                            'name' => $acc->name,
                            'balance' => round($acc->historical_balance, 2),
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
            });

            return $this->successResponse($reportData, 'Laporan neraca berhasil dibuat');
        } catch (FinancialReportException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Throwable $e) {
            Log::error('FinancialReportController::balanceSheet failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Gagal membuat laporan neraca: '.$e->getMessage(), 500);
        }
    }

    /**
     * Laporan Laba Rugi (Profit & Loss / Income Statement)
     * Mengukur omzet pendapatan dikurangi beban pengeluaran.
     */
    public function profitLoss(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                throw new FinancialReportException('Parameter tanggal tidak valid.', $validator->errors()->toArray());
            }

            $startDate = $request->query('start_date') ?? Carbon::now()->startOfMonth()->toDateString();
            $endDate = $request->query('end_date') ?? Carbon::now()->toDateString();

            $reportData = DB::transaction(function () use ($startDate, $endDate) {
                $accounts = Account::where('is_active', true)->sharedLock()->get();

                // Ambil total debit/credit per akun untuk transaksi di periode terpilih
                $periodItems = JournalItem::whereHas('entry', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('transaction_date', [$startDate, $endDate]);
                })
                    ->select('account_id', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
                    ->groupBy('account_id')
                    ->sharedLock()
                    ->get()
                    ->keyBy('account_id');

                $revenues = $accounts->where('type', 'revenue')->map(function ($acc) use ($periodItems) {
                    $item = $periodItems->get($acc->id);
                    $debit = $item ? (float) $item->total_debit : 0.0;
                    $credit = $item ? (float) $item->total_credit : 0.0;
                    $acc->period_balance = $credit - $debit;

                    return $acc;
                })->values();

                $expenses = $accounts->where('type', 'expense')->map(function ($acc) use ($periodItems) {
                    $item = $periodItems->get($acc->id);
                    $debit = $item ? (float) $item->total_debit : 0.0;
                    $credit = $item ? (float) $item->total_credit : 0.0;
                    $acc->period_balance = $debit - $credit;

                    return $acc;
                })->values();

                $totalRevenue = $revenues->sum('period_balance');
                $totalExpense = $expenses->sum('period_balance');
                $netProfit = $totalRevenue - $totalExpense;

                return [
                    'metadata' => [
                        'report_name' => 'Laporan Laba Rugi (Profit & Loss Statement)',
                        'generated_at' => now()->toIso8601String(),
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'currency' => 'IDR',
                    ],
                    'revenues' => [
                        'items' => $revenues->map(fn ($acc) => [
                            'code' => $acc->code,
                            'name' => $acc->name,
                            'balance' => round($acc->period_balance, 2),
                        ]),
                        'total' => round($totalRevenue, 2),
                    ],
                    'expenses' => [
                        'items' => $expenses->map(fn ($acc) => [
                            'code' => $acc->code,
                            'name' => $acc->name,
                            'balance' => round($acc->period_balance, 2),
                        ]),
                        'total' => round($totalExpense, 2),
                    ],
                    'summary' => [
                        'total_revenue' => round($totalRevenue, 2),
                        'total_expense' => round($totalExpense, 2),
                        'net_profit' => round($netProfit, 2),
                    ],
                ];
            });

            return $this->successResponse($reportData, 'Laporan laba rugi berhasil dibuat');
        } catch (FinancialReportException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Throwable $e) {
            Log::error('FinancialReportController::profitLoss failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Gagal membuat laporan laba rugi: '.$e->getMessage(), 500);
        }
    }

    /**
     * Laporan Arus Kas (Statement of Cash Flows) - Direct Method
     * Menganalisis perputaran uang tunai & bank riil dari Jurnal Umum.
     */
    public function cashFlow(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                throw new FinancialReportException('Parameter tanggal tidak valid.', $validator->errors()->toArray());
            }

            $startDate = $request->query('start_date') ?? Carbon::now()->startOfMonth()->toDateString();
            $endDate = $request->query('end_date') ?? Carbon::now()->toDateString();

            $reportData = DB::transaction(function () use ($startDate, $endDate) {
                // Ambil akun kas & bank
                $cashAccounts = Account::whereIn('code', ['1101', '1102'])->sharedLock()->get();
                $cashAccountIds = $cashAccounts->pluck('id')->toArray();

                // Ambil semua Journal Items kas/bank dalam periode
                $cashJournalItems = JournalItem::whereIn('account_id', $cashAccountIds)
                    ->whereHas('entry', function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('transaction_date', [$startDate, $endDate]);
                    })
                    ->with(['entry.items.account'])
                    ->sharedLock()
                    ->get();

                $operatingInflow = 0.0;
                $operatingOutflowExpense = 0.0;
                $operatingOutflowSupplier = 0.0;
                $financingInflow = 0.0;
                $financingOutflow = 0.0;

                foreach ($cashJournalItems as $item) {
                    $isDebit = $item->debit > 0;
                    $cashAmount = $isDebit ? (float) $item->debit : (float) $item->credit;

                    // Filter item lawan dengan tanda berlawanan
                    $oppositeItems = $item->entry->items->filter(function ($opp) use ($isDebit) {
                        return $isDebit ? ($opp->credit > 0) : ($opp->debit > 0);
                    });

                    $totalOppositeAmount = 0.0;
                    foreach ($oppositeItems as $opp) {
                        $totalOppositeAmount += $isDebit ? (float) $opp->credit : (float) $opp->debit;
                    }

                    if ($totalOppositeAmount <= 0) {
                        $totalOppositeAmount = $cashAmount;
                    }

                    foreach ($oppositeItems as $opp) {
                        // Lewati jika item lawan adalah akun kas lain (untuk mengabaikan transfer kas antar akun)
                        if (in_array($opp->account_id, $cashAccountIds)) {
                            continue;
                        }

                        $oppAmount = $isDebit ? (float) $opp->credit : (float) $opp->debit;
                        if ($oppAmount <= 0) {
                            continue;
                        }

                        // Alokasikan kas secara proporsional ke item lawan ini
                        $allocatedAmount = ($oppAmount / $totalOppositeAmount) * $cashAmount;
                        $oppType = $opp->account->type;
                        $oppCode = $opp->account->code;

                        if ($isDebit) {
                            if ($oppType === 'revenue') {
                                $operatingInflow += $allocatedAmount;
                            } elseif ($oppType === 'equity') {
                                $financingInflow += $allocatedAmount;
                            } elseif ($oppCode === '1201' || $oppCode === '2101' || $oppCode === '1103') {
                                $operatingInflow += $allocatedAmount;
                            } else {
                                $operatingInflow += $allocatedAmount;
                            }
                        } else {
                            if ($oppType === 'expense') {
                                $operatingOutflowExpense += $allocatedAmount;
                            } elseif ($oppCode === '1201' || $oppCode === '2101' || $oppCode === '1103') {
                                $operatingOutflowSupplier += $allocatedAmount;
                            } elseif ($oppType === 'equity') {
                                $financingOutflow += $allocatedAmount;
                            } else {
                                $operatingOutflowExpense += $allocatedAmount;
                            }
                        }
                    }
                }

                $netOperatingCash = $operatingInflow - $operatingOutflowExpense - $operatingOutflowSupplier;
                $netFinancingCash = $financingInflow - $financingOutflow;
                $netCashIncrease = $netOperatingCash + $netFinancingCash;

                // Hitung saldo kas historis di awal & akhir periode
                $endingCashBalance = 0.0;
                $afterEndCashItems = JournalItem::whereIn('account_id', $cashAccountIds)
                    ->whereHas('entry', function ($query) use ($endDate) {
                        $query->where('transaction_date', '>', $endDate);
                    })
                    ->select('account_id', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
                    ->groupBy('account_id')
                    ->sharedLock()
                    ->get()
                    ->keyBy('account_id');

                foreach ($cashAccounts as $cashAccount) {
                    $after = $afterEndCashItems->get($cashAccount->id);
                    $debitAfter = $after ? (float) $after->total_debit : 0.0;
                    $creditAfter = $after ? (float) $after->total_credit : 0.0;

                    $endingCashBalance += (float) $cashAccount->balance - ($debitAfter - $creditAfter);
                }

                $beginningCash = $endingCashBalance - $netCashIncrease;

                return [
                    'metadata' => [
                        'report_name' => 'Laporan Arus Kas (Statement of Cash Flows)',
                        'method' => 'Direct Method (Metode Langsung)',
                        'generated_at' => now()->toIso8601String(),
                        'start_date' => $startDate,
                        'end_date' => $endDate,
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
                        'ending_cash_balance' => round($endingCashBalance, 2),
                    ],
                ];
            });

            return $this->successResponse($reportData, 'Laporan arus kas berhasil dibuat');
        } catch (FinancialReportException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Throwable $e) {
            Log::error('FinancialReportController::cashFlow failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Gagal membuat laporan arus kas: '.$e->getMessage(), 500);
        }
    }
}
