<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\RemittanceAlreadyClosedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SubmitRemittanceRequest;
use App\Models\Finance\Account;
use App\Models\Finance\CashTransaction;
use App\Models\Sales\Shift;
use App\Repositories\Contracts\Finance\AccountRepositoryInterface;
use App\Repositories\Contracts\Finance\JournalEntryRepositoryInterface;
use App\Repositories\Contracts\Sales\ShiftRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StationRemittanceController extends Controller
{
    use ApiResponseTrait;

    /**
     * Inject dependencies via PHP 8 constructor property promotion.
     */
    public function __construct(
        protected ShiftRepositoryInterface $shiftRepo,
        protected AccountRepositoryInterface $accountRepo,
        protected JournalEntryRepositoryInterface $journalRepo,
    ) {}

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Find or auto-create an account by code.
     * Uses eager-loaded collection to avoid N+1 queries.
     *
     * @param  array{code: string, name: string, type: string}  $definition
     */
    private function resolveAccount(
        Collection $accounts,
        array $definition
    ): Account {
        $account = $accounts->firstWhere('code', $definition['code']);

        if (! $account) {
            $account = $this->accountRepo->create([
                'code' => $definition['code'],
                'name' => $definition['name'],
                'type' => $definition['type'],
                'balance' => 0.0,
            ]);
        }

        return $account;
    }

    // =========================================================================
    // 1. GET /api/remittance/summary/{shiftId}
    // =========================================================================

    /**
     * Return the live reconciliation summary for an open shift.
     * For a closed shift, returns the final stored reconciliation data.
     */
    public function getSummary(int $shiftId): JsonResponse
    {
        /** @var Shift $shift */
        $shift = $this->shiftRepo->findOrFail($shiftId);

        // Eager-load relations to avoid N+1
        $shift->loadMissing(['user', 'station', 'sales']);

        // Already-closed shift: return stored final values
        if ($shift->status === 'closed') {
            return $this->successResponse([
                'shift' => [
                    'id' => $shift->id,
                    'cashier_name' => $shift->user->name ?? 'Kasir',
                    'station_name' => $shift->station->name ?? 'Stasiun',
                    'status' => 'CLOSED',
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                ],
                'final_reconciliation' => [
                    'expected_cash' => (float) $shift->expected_cash,
                    'actual_cash' => (float) $shift->actual_cash,
                    'difference_cash' => (float) $shift->difference_cash,
                    'expected_qris' => (float) $shift->expected_qris,
                    'actual_qris' => (float) $shift->actual_qris,
                    'difference_qris' => (float) $shift->difference_qris,
                    'expected_card' => (float) $shift->expected_card,
                    'actual_card' => (float) $shift->actual_card,
                    'difference_card' => (float) $shift->difference_card,
                    'total_sales' => (float) $shift->total_sales,
                    'total_discount' => (float) $shift->total_discount,
                ],
            ], 'Shift ini sudah dalam status CLOSED. Menampilkan hasil rekonsiliasi final.');
        }

        // Active open shift: compute live balances from sales data
        $completedSales = $shift->sales->where('status', 'completed');

        $cashSales = $completedSales->where('payment_method', 'cash')->sum('grand_total');
        $qrisSales = $completedSales->where('payment_method', 'qris')->sum('grand_total');
        $cardSales = $completedSales
            ->whereIn('payment_method', ['card', 'debit', 'credit'])
            ->sum('grand_total');

        $totalSales = $completedSales->sum('grand_total');
        $totalDiscount = $completedSales->sum('discount_amount');
        $totalTransactions = $completedSales->count();

        // Petty cash movements from CashTransaction
        $cashMovements = CashTransaction::where('shift_id', $shift->id)->get();
        $cashIn = $cashMovements->where('type', 'in')->where('payment_method', 'cash')->sum('amount');
        $cashOut = $cashMovements->where('type', 'out')->where('payment_method', 'cash')->sum('amount');

        $expectedCash = max(0.0, (float) $shift->starting_cash + $cashSales + $cashIn - $cashOut);

        return $this->successResponse([
            'shift' => [
                'id' => $shift->id,
                'cashier_name' => $shift->user->name ?? 'Kasir',
                'station_name' => $shift->station->name ?? 'Stasiun',
                'status' => 'OPEN',
                'start_time' => $shift->start_time,
            ],
            'live_balances' => [
                'starting_cash' => (float) $shift->starting_cash,
                'cash_sales' => (float) $cashSales,
                'qris_sales' => (float) $qrisSales,
                'card_sales' => (float) $cardSales,
                'cash_in' => (float) $cashIn,
                'cash_out' => (float) $cashOut,
                'expected_cash' => $expectedCash,
                'expected_qris' => (float) $qrisSales,
                'expected_card' => (float) $cardSales,
                'total_sales' => (float) $totalSales,
                'total_discount' => (float) $totalDiscount,
                'total_transactions' => $totalTransactions,
            ],
        ], 'Live shift summary retrieved successfully.');
    }

    // =========================================================================
    // 2. POST /api/remittance/submit
    // =========================================================================

    /**
     * Submit cashier end-of-shift remittance.
     *
     * Flow (all inside DB::transaction):
     *  1. Guard against double-close via RemittanceAlreadyClosedException.
     *  2. Delegate shift close + reconciliation calculation to ShiftRepository::closeShift().
     *  3. Resolve GL accounts (1101 Drawer, 1102 Safe, 5999 Shortage, 6199 Overage).
     *  4. Build double-entry journal items for the cash handover.
     *  5. Post the journal entry via JournalEntryRepository.
     *  6. Return structured reconciliation result.
     */
    public function submitRemittance(SubmitRemittanceRequest $request): JsonResponse
    {
        $shiftId = (int) $request->input('shift_id');
        $actualCash = (float) $request->input('actual_cash');
        $actualQris = (float) $request->input('actual_qris');
        $actualCard = (float) $request->input('actual_card');
        $notes = $request->input('notes') ?? 'Setoran Tutup Kasir';

        /** @var Shift $shift */
        $shift = $this->shiftRepo->findOrFail($shiftId);
        $shift->loadMissing(['user', 'station']);

        try {
            if ($shift->status === 'closed') {
                throw new RemittanceAlreadyClosedException(
                    "Gagal! Shift kasir stasiun {$shift->station?->name} sudah ditutup pada {$shift->end_time}."
                );
            }

            $result = DB::transaction(function () use (
                $shift,
                $actualCash,
                $actualQris,
                $actualCard,
                $notes,
            ) {
                // 1. Close shift and compute reconciliation via repository
                $closedShift = $this->shiftRepo->closeShift($shift->id, [
                    'actual_cash' => $actualCash,
                    'actual_qris' => $actualQris,
                    'actual_card' => $actualCard,
                    'notes' => $notes,
                ]);

                $diffCash = (float) $closedShift->difference_cash;
                $diffQris = (float) $closedShift->difference_qris;
                $diffCard = (float) $closedShift->difference_card;

                // 2. Resolve required GL accounts (single query for all)
                $allAccounts = $this->accountRepo->all();

                $drawerAccount = $this->resolveAccount($allAccounts, [
                    'code' => '1101',
                    'name' => 'Kas Laci POS',
                    'type' => 'asset',
                ]);

                $safeAccount = $this->resolveAccount($allAccounts, [
                    'code' => '1102',
                    'name' => 'Bank POS / Brankas Utama',
                    'type' => 'asset',
                ]);

                $shortageAccount = $this->resolveAccount($allAccounts, [
                    'code' => '5999',
                    'name' => 'Beban Selisih Kurang Kas Kasir',
                    'type' => 'expense',
                ]);

                $overageAccount = $this->resolveAccount($allAccounts, [
                    'code' => '6199',
                    'name' => 'Pendapatan Selisih Lebih Kas Kasir',
                    'type' => 'revenue',
                ]);

                // 3. Build double-entry journal items
                //    Debit Safe (cash received), Credit Drawer (expected to hand over)
                $journalItems = [
                    [
                        'account_id' => $safeAccount->id,
                        'debit' => $actualCash,
                        'credit' => 0.0,
                    ],
                    [
                        'account_id' => $drawerAccount->id,
                        'debit' => 0.0,
                        'credit' => (float) $closedShift->expected_cash,
                    ],
                ];

                // Shortage: cashier handed over less than expected → Expense Debit
                if ($diffCash < 0) {
                    $journalItems[] = [
                        'account_id' => $shortageAccount->id,
                        'debit' => abs($diffCash),
                        'credit' => 0.0,
                    ];
                }

                // Overage: cashier handed over more than expected → Revenue Credit
                if ($diffCash > 0) {
                    $journalItems[] = [
                        'account_id' => $overageAccount->id,
                        'debit' => 0.0,
                        'credit' => $diffCash,
                    ];
                }

                $stationName = $shift->station?->name ?? 'Stasiun';
                $formattedDiff = number_format(abs($diffCash), 0, ',', '.');
                $diffLabel = $diffCash == 0 ? 'BALANCE' : ($diffCash > 0 ? "Selisih Lebih +Rp {$formattedDiff}" : "Selisih Kurang -Rp {$formattedDiff}");

                // 4. Post journal entry via repository
                $journal = $this->journalRepo->create([
                    'transaction_date' => now()->toDateString(),
                    'description' => "Rekonsiliasi Tutup Kasir | Stasiun: {$stationName} | {$diffLabel}",
                    'items' => $journalItems,
                ]);

                return [
                    'shift_id' => $closedShift->id,
                    'cashier_name' => $shift->user?->name ?? 'Kasir',
                    'station_name' => $stationName,
                    'status' => 'closed',
                    'end_time' => $closedShift->end_time,
                    'reconciliation' => [
                        'expected_cash' => (float) $closedShift->expected_cash,
                        'actual_cash' => $actualCash,
                        'difference_cash' => $diffCash,
                        'expected_qris' => (float) $closedShift->expected_qris,
                        'actual_qris' => $actualQris,
                        'difference_qris' => $diffQris,
                        'expected_card' => (float) $closedShift->expected_card,
                        'actual_card' => $actualCard,
                        'difference_card' => $diffCard,
                        'total_sales' => (float) $closedShift->total_sales,
                        'total_discount' => (float) $closedShift->total_discount,
                        'cash_status' => match (true) {
                            $diffCash == 0 => 'BALANCE',
                            $diffCash > 0 => 'OVERAGE',
                            default => 'SHORTAGE',
                        },
                        'cash_status_label' => match (true) {
                            $diffCash == 0 => 'Kas Seimbang (Balance)',
                            $diffCash > 0 => "Selisih Lebih Kas (Overage +Rp {$formattedDiff})",
                            default => "Selisih Kurang Kas (Shortage -Rp {$formattedDiff})",
                        },
                    ],
                    'journal_reference' => $journal->reference_no,
                ];
            });

            return $this->successResponse($result, 'Rekonsiliasi dan setoran kasir berhasil diproses.');

        } catch (RemittanceAlreadyClosedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('StationRemittanceController::submitRemittance failed', [
                'shift_id' => $shiftId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Terjadi kesalahan internal saat memproses rekonsiliasi. Silakan coba lagi.',
                500
            );
        }
    }
}
