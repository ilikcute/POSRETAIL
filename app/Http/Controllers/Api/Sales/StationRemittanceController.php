<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SubmitRemittanceRequest;
use App\Models\Finance\CashTransaction;
use App\Models\Sales\Sale;
use App\Models\Sales\Shift;
use App\Repositories\Contracts\Finance\AccountRepositoryInterface;
use App\Repositories\Contracts\Finance\JournalEntryRepositoryInterface;
use App\Repositories\Contracts\Sales\ShiftRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StationRemittanceController extends Controller
{
    use ApiResponseTrait;

    protected ShiftRepositoryInterface $shiftRepo;

    protected AccountRepositoryInterface $accountRepo;

    protected JournalEntryRepositoryInterface $journalRepo;

    public function __construct(
        ShiftRepositoryInterface $shiftRepo,
        AccountRepositoryInterface $accountRepo,
        JournalEntryRepositoryInterface $journalRepo
    ) {
        $this->shiftRepo = $shiftRepo;
        $this->accountRepo = $accountRepo;
        $this->journalRepo = $journalRepo;
    }

    /**
     * Helper: Hitung kalkulasi rincian nominal penutupan drawer
     */
    protected function calculateShiftExpectedBalances(Shift $shift): array
    {
        $startingCash = (float) $shift->starting_cash;

        // Total Penjualan Tunai (Cash Sales)
        $cashSales = Sale::where('shift_id', $shift->id)
            ->where('payment_method', 'cash')
            ->where('status', 'completed')
            ->sum('grand_total');

        // Total Penjualan QRIS (QRIS Sales)
        $qrisSales = Sale::where('shift_id', $shift->id)
            ->where('payment_method', 'qris')
            ->where('status', 'completed')
            ->sum('grand_total');

        // Total Penjualan Kartu Debit/Kredit (Card Sales)
        $cardSales = Sale::where('shift_id', $shift->id)
            ->where('payment_method', 'card')
            ->where('status', 'completed')
            ->sum('grand_total');

        // Petty Cash Masuk (Cash IN)
        $cashIn = CashTransaction::where('shift_id', $shift->id)
            ->where('type', 'in')
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Petty Cash Keluar termasuk Setor Tengah (Cash OUT)
        $cashOut = CashTransaction::where('shift_id', $shift->id)
            ->where('type', 'out')
            ->where('payment_method', 'cash')
            ->sum('amount');

        $expectedCash = $startingCash + $cashSales + $cashIn - $cashOut;

        return [
            'starting_cash' => $startingCash,
            'cash_sales' => (float) $cashSales,
            'qris_sales' => (float) $qrisSales,
            'card_sales' => (float) $cardSales,
            'cash_in' => (float) $cashIn,
            'cash_out' => (float) $cashOut,
            'expected_cash' => max(0.0, $expectedCash),
            'expected_qris' => (float) $qrisSales,
            'expected_card' => (float) $cardSales,
        ];
    }

    /**
     * 1. GET /api/remittance/summary/{shiftId}
     * Dapatkan Lembar Summary Reconcile Drawer Sebelum Ditutup
     */
    public function getSummary(int $shiftId): JsonResponse
    {
        $shift = $this->shiftRepo->findOrFail($shiftId);

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
                ],
            ], 'This shift is already closed');
        }

        $expected = $this->calculateShiftExpectedBalances($shift);

        $response = [
            'shift' => [
                'id' => $shift->id,
                'cashier_name' => $shift->user->name ?? 'Kasir',
                'station_name' => $shift->station->name ?? 'Stasiun',
                'status' => 'OPEN',
                'start_time' => $shift->start_time,
            ],
            'expected_balances' => $expected,
        ];

        return $this->successResponse($response, 'Active shift summary for reconciliation retrieved successfully');
    }

    /**
     * 2. POST /api/remittance/submit
     * Submit Setoran Kasir & Tutup Shift (Double-Entry Jurnal Selisih Kasir!)
     */
    public function submitRemittance(SubmitRemittanceRequest $request): JsonResponse
    {
        $shiftId = $request->input('shift_id');
        $actualCash = (float) $request->input('actual_cash');
        $actualQris = (float) $request->input('actual_qris');
        $actualCard = (float) $request->input('actual_card');
        $notes = $request->input('notes') ?? 'Setoran Tutup Kasir Multi-Station';

        $shift = $this->shiftRepo->findOrFail($shiftId);

        if ($shift->status === 'closed') {
            return $this->errorResponse('Gagal! Shift kasir stasiun ini sudah dalam keadaan ditutup.', 400);
        }

        $result = DB::transaction(function () use ($shift, $actualCash, $actualQris, $actualCard, $notes) {

            $expected = $this->calculateShiftExpectedBalances($shift);

            // Hitung selisih kas / QRIS / Card
            $diffCash = $actualCash - $expected['expected_cash'];
            $diffQris = $actualQris - $expected['expected_qris'];
            $diffCard = $actualCard - $expected['expected_card'];

            // 1. UPDATE DOKUMEN SHIFT MENJADI CLOSED (menggunakan repository!)
            $this->shiftRepo->update($shift->id, [
                'end_time' => now(),
                'expected_cash' => $expected['expected_cash'],
                'actual_cash' => $actualCash,
                'difference_cash' => $diffCash,
                'expected_qris' => $expected['expected_qris'],
                'actual_qris' => $actualQris,
                'difference_qris' => $diffQris,
                'expected_card' => $expected['expected_card'],
                'actual_card' => $actualCard,
                'difference_card' => $diffCard,
                'status' => 'closed',
                'notes' => $notes,
            ]);

            // 2. PENJURNALAN GANDA AKUNTANSI & REKONSILIASI (menggunakan repository!)
            $drawerAccount = $this->accountRepo->all()->where('code', '1101')->first();
            $safeAccount = $this->accountRepo->all()->where('code', '1102')->first();

            if (! $drawerAccount || ! $safeAccount) {
                throw new \Exception('Akun perkiraan kas tidak dikonfigurasi dengan benar.');
            }

            $shortageAccount = $this->accountRepo->all()->where('code', '5999')->first();
            if (! $shortageAccount) {
                $shortageAccount = $this->accountRepo->create([
                    'code' => '5999',
                    'name' => 'Beban Selisih Kurang Kas Kasir',
                    'type' => 'expense',
                    'balance' => 0.0,
                ]);
            }

            $overageAccount = $this->accountRepo->all()->where('code', '6199')->first();
            if (! $overageAccount) {
                $overageAccount = $this->accountRepo->create([
                    'code' => '6199',
                    'name' => 'Pendapatan Selisih Lebih Kas Kasir',
                    'type' => 'revenue',
                    'balance' => 0.0,
                ]);
            }

            $journalItems = [
                [
                    'account_id' => $safeAccount->id,
                    'debit' => $actualCash,
                    'credit' => 0.0,
                ],
                [
                    'account_id' => $drawerAccount->id,
                    'debit' => 0.0,
                    'credit' => $expected['expected_cash'],
                ],
            ];

            if ($diffCash < 0) {
                $journalItems[] = [
                    'account_id' => $shortageAccount->id,
                    'debit' => abs($diffCash),
                    'credit' => 0.0,
                ];
            } elseif ($diffCash > 0) {
                $journalItems[] = [
                    'account_id' => $overageAccount->id,
                    'debit' => 0.0,
                    'credit' => $diffCash,
                ];
            }

            $journal = $this->journalRepo->create([
                'transaction_date' => now()->toDateString(),
                'description' => "Rekonsiliasi Tutup Kasir | Stasiun: {$shift->station->name} | Selisih Kas: Rp ".number_format($diffCash, 0, ',', '.'),
                'items' => $journalItems,
            ]);

            return [
                'shift_id' => $shift->id,
                'cashier_name' => $shift->user->name ?? 'Kasir',
                'station_name' => $shift->station->name ?? 'Stasiun',
                'reconciliation' => [
                    'expected_cash' => $expected['expected_cash'],
                    'actual_cash' => $actualCash,
                    'difference_cash' => $diffCash,
                    'status' => $diffCash == 0 ? 'BALANCE' : ($diffCash > 0 ? 'OVERAGE (Selisih Lebih)' : 'SHORTAGE (Selisih Kurang)'),
                ],
                'journal_entry' => $journal->reference_no,
            ];
        });

        return $this->successResponse($result, 'Cashier remittance and reconciliation completed successfully');
    }
}
