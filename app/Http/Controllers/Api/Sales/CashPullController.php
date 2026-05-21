<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;

use App\Http\Requests\Sales\ExecuteCashPullRequest;
use App\Models\Sales\Shift;
use App\Models\Sales\Sale;
use App\Models\Finance\CashTransaction;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalItem;
use App\Repositories\Contracts\Master\StationRepositoryInterface;
use App\Repositories\Contracts\Sales\ShiftRepositoryInterface;
use App\Repositories\Contracts\Finance\CashTransactionRepositoryInterface;
use App\Repositories\Contracts\Finance\AccountRepositoryInterface;
use App\Repositories\Contracts\Finance\JournalEntryRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CashPullController extends Controller
{
    use ApiResponseTrait;

    protected StationRepositoryInterface $stationRepo;
    protected ShiftRepositoryInterface $shiftRepo;
    protected CashTransactionRepositoryInterface $cashTxRepo;
    protected AccountRepositoryInterface $accountRepo;
    protected JournalEntryRepositoryInterface $journalRepo;

    public function __construct(
        StationRepositoryInterface $stationRepo,
        ShiftRepositoryInterface $shiftRepo,
        CashTransactionRepositoryInterface $cashTxRepo,
        AccountRepositoryInterface $accountRepo,
        JournalEntryRepositoryInterface $journalRepo
    ) {
        $this->stationRepo = $stationRepo;
        $this->shiftRepo = $shiftRepo;
        $this->cashTxRepo = $cashTxRepo;
        $this->accountRepo = $accountRepo;
        $this->journalRepo = $journalRepo;
    }

    /**
     * Helper: Dapatkan saldo kas aktual di laci kasir stasiun saat ini
     */
    protected function calculateDrawerCash(Shift $shift): float
    {
        $startingCash = (float)$shift->starting_cash;

        // Total Penjualan Tunai
        $cashSales = Sale::where('shift_id', $shift->id)
            ->where('payment_method', 'cash')
            ->where('status', 'completed')
            ->sum('grand_total');

        // Uang Masuk Kas Laci (Petty Cash IN)
        $cashIn = CashTransaction::where('shift_id', $shift->id)
            ->where('type', 'in')
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Uang Keluar Kas Laci (Petty Cash OUT termasuk setor tengah sebelumnya)
        $cashOut = CashTransaction::where('shift_id', $shift->id)
            ->where('type', 'out')
            ->where('payment_method', 'cash')
            ->sum('amount');

        return max(0.0, $startingCash + (float)$cashSales + (float)$cashIn - (float)$cashOut);
    }

    /**
     * 1. GET /api/cash-pull/check/{stationId}
     * Cek Saldo Kas Drawer & Status Batas Aman (Alert Trigger)
     */
    public function checkDrawerLimit(int $stationId): JsonResponse
    {
        $station = $this->stationRepo->findOrFail($stationId);
        
        $shift = Shift::where('station_id', $stationId)
            ->where('status', 'open')
            ->orderBy('id', 'desc')
            ->first();

        if (!$shift) {
            return $this->successResponse([
                'station_id' => $station->id,
                'station_name' => $station->name,
                'has_active_shift' => false,
                'status' => 'NO_ACTIVE_SHIFT',
                'message' => 'Stasiun kasir ini tidak memiliki shift aktif yang sedang berjalan.',
            ], 'Station shift status retrieved successfully');
        }

        $currentCash = $this->calculateDrawerCash($shift);
        $safetyLimit = (float)$station->drawer_safety_limit;
        $isAlertTriggered = $currentCash >= $safetyLimit;

        $suggestedKeepFloat = 500000.00;
        $suggestedPull = max(0.0, $currentCash - $suggestedKeepFloat);

        if ($currentCash <= $suggestedKeepFloat) {
            $suggestedPull = 0.0;
        }

        $response = [
            'station' => [
                'id' => $station->id,
                'name' => $station->name,
                'location' => $station->location,
                'drawer_safety_limit' => $safetyLimit,
            ],
            'active_shift' => [
                'shift_id' => $shift->id,
                'cashier_id' => $shift->user_id,
                'cashier_name' => $shift->user->name ?? 'Kasir',
                'start_time' => $shift->start_time,
            ],
            'cash_drawer_status' => [
                'current_cash_in_drawer' => $currentCash,
                'is_alert_triggered' => $isAlertTriggered,
                'status' => $isAlertTriggered ? 'ALERT_TRIGGERED (Wajib Setor Tengah!)' : 'SAFE (Aman)',
                'suggested_pull_amount' => $suggestedPull,
                'remaining_cash_if_pulled' => $isAlertTriggered ? $suggestedKeepFloat : $currentCash,
            ]
        ];

        return $this->successResponse($response, 'Cash drawer safety check completed successfully');
    }

    /**
     * 2. POST /api/cash-pull/execute
     * Eksekusi Penarikan "Setor Tengah" Kas Laci ke Brankas (Jurnal Ganda Otomatis!)
     */
    public function executeCashPull(ExecuteCashPullRequest $request): JsonResponse
    {
        $stationId = $request->input('station_id');
        $pullAmount = (float)$request->input('pull_amount');
        $supervisorId = $request->input('supervisor_id');
        $notes = $request->input('notes') ?? 'Setor Tengah Kas Laci ke Brankas Utama';

        $station = $this->stationRepo->findOrFail($stationId);

        $shift = Shift::where('station_id', $stationId)
            ->where('status', 'open')
            ->orderBy('id', 'desc')
            ->first();

        if (!$shift) {
            return $this->errorResponse('Gagal eksekusi! Tidak ada shift aktif untuk stasiun kasir ini.', 400);
        }

        $currentCash = $this->calculateDrawerCash($shift);

        if ($pullAmount > $currentCash) {
            return $this->errorResponse(
                "Gagal eksekusi! Nominal penarikan (Rp " . number_format($pullAmount, 0, ',', '.') . 
                ") melebihi jumlah uang fisik kas yang terdeteksi di laci (Rp " . number_format($currentCash, 0, ',', '.') . ").",
                422
            );
        }

        $result = DB::transaction(function () use ($shift, $pullAmount, $supervisorId, $notes, $station, $currentCash) {
            
            // 1. Rekam CashTransaction bertipe OUT (menggunakan repository!)
            $cashTx = $this->cashTxRepo->create([
                'store_id' => \App\Models\Master\Store::first()->id,
                'shift_id' => $shift->id,
                'type' => 'out',
                'amount' => $pullAmount,
                'category' => 'setor_tengah',
                'payment_method' => 'cash',
                'description' => "{$notes} [Petugas Otoritas: " . auth()->user()->name . "]",
                'created_by' => $supervisorId,
            ]);

            // 2. Kurangi Saldo expected_cash pada Shift (menggunakan repository!)
            $updatedExpectedCash = max(0.0, (float)$shift->expected_cash - $pullAmount);
            $this->shiftRepo->update($shift->id, [
                'expected_cash' => $updatedExpectedCash
            ]);

            // 3. POSTING DOUBLE-ENTRY JOURNAL (menggunakan repository!)
            $drawerAccount = $this->accountRepo->all()->where('code', '1101')->first();
            $safeAccount = $this->accountRepo->all()->where('code', '1102')->first();

            if (!$drawerAccount || !$safeAccount) {
                throw new \Exception("Akun perkiraan kas tidak dikonfigurasi dengan benar.");
            }

            $journal = $this->journalRepo->create([
                'transaction_date' => now()->toDateString(),
                'description' => "{$notes} | Stasiun: {$station->name} | Otoritas: " . auth()->user()->name,
                'created_by' => $supervisorId,
                'items' => [
                    [
                        'account_id' => $safeAccount->id,
                        'debit' => $pullAmount,
                        'credit' => 0.0,
                    ],
                    [
                        'account_id' => $drawerAccount->id,
                        'debit' => 0.0,
                        'credit' => $pullAmount,
                    ]
                ]
            ]);

            return [
                'cash_transaction_id' => $cashTx->id,
                'station_name' => $station->name,
                'pulled_amount' => $pullAmount,
                'remaining_cash_in_drawer' => max(0.0, $currentCash - $pullAmount),
                'journal_entry' => $journal->reference_no,
            ];
        });

        return $this->successResponse($result, 'Cash pull / Setor tengah processed and journalized successfully');
    }
}
