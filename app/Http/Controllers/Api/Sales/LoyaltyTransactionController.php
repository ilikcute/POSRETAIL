<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\LoyaltyBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreLoyaltyTransactionRequest;
use App\Http\Requests\Sales\UpdateLoyaltyTransactionRequest;
use App\Models\Master\Customer;
use App\Models\Sales\LoyaltyTransaction;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoyaltyTransactionController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/loyalty-transactions
     * Ledger poin customer dengan eager-load untuk menghindari N+1.
     */
    public function index(): JsonResponse
    {
        $transactions = LoyaltyTransaction::query()
            ->with([
                'customer:id,name,member_code,phone,point_balance,is_active',
                'sale:id,invoice_no,grand_total,status',
                'creator:id,name,email',
            ])
            ->latest()
            ->limit(300)
            ->get();

        return $this->successResponse($transactions, 'Loyalty points transactions retrieved successfully');
    }

    /**
     * POST /api/loyalty-transactions
     * Memproses earn/redeem/adjust secara atomic dan mengunci saldo customer.
     */
    public function store(StoreLoyaltyTransactionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $transaction = DB::transaction(function () use ($validated): LoyaltyTransaction {
                /** @var Customer $customer */
                $customer = Customer::query()
                    ->whereKey((int) $validated['customer_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $customer->is_active) {
                    throw new LoyaltyBalanceException(
                        'Customer loyalty tidak aktif.',
                        [
                            'customer_id' => $customer->id,
                            'current_balance' => (int) $customer->point_balance,
                        ]
                    );
                }

                $points = $this->normalizePoints($validated['type'], (int) $validated['points']);
                $currentBalance = (int) $customer->point_balance;
                $newBalance = $currentBalance + $points;

                if ($newBalance < 0) {
                    throw new LoyaltyBalanceException(
                        'Saldo poin customer tidak mencukupi untuk transaksi loyalty ini.',
                        [
                            'customer_id' => $customer->id,
                            'current_balance' => $currentBalance,
                            'requested_points' => abs($points),
                            'balance_after_transaction' => $newBalance,
                        ]
                    );
                }

                $transaction = LoyaltyTransaction::query()->create([
                    'customer_id' => $customer->id,
                    'sale_id' => $validated['sale_id'] ?? null,
                    'type' => $validated['type'],
                    'points' => $points,
                    'amount' => $this->resolveAmount($validated['type'], $points, $validated['amount'] ?? null),
                    'description' => $validated['description'],
                    'created_by' => auth()->id(),
                ]);

                $customer->forceFill([
                    'point_balance' => $newBalance,
                ])->save();

                return $transaction->load([
                    'customer:id,name,member_code,phone,point_balance,is_active',
                    'sale:id,invoice_no,grand_total,status',
                    'creator:id,name,email',
                ]);
            });

            return $this->successResponse($transaction, 'Loyalty points transaction processed successfully', 201);
        } catch (LoyaltyBalanceException $e) {
            return $this->errorResponse($e->getMessage(), 422, [
                'points' => [
                    'Saldo saat ini hanya '.number_format((int) ($e->contextData()['current_balance'] ?? 0), 0, ',', '.').' poin.',
                ],
                'loyalty' => $e->contextData(),
            ]);
        } catch (\Throwable $e) {
            Log::error('LoyaltyTransactionController::store failed', [
                'customer_id' => $validated['customer_id'] ?? null,
                'type' => $validated['type'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Terjadi kesalahan internal saat memproses transaksi loyalty.', 500);
        }
    }

    public function show(int $loyalty_transaction): JsonResponse
    {
        $loyaltyTransaction = LoyaltyTransaction::query()->findOrFail($loyalty_transaction);
        $loyaltyTransaction->load([
            'customer:id,name,member_code,phone,point_balance,is_active',
            'sale:id,invoice_no,grand_total,status',
            'creator:id,name,email',
        ]);

        return $this->successResponse($loyaltyTransaction, 'Loyalty points transaction details retrieved successfully');
    }

    /**
     * Ledger poin bersifat audit trail. Update hanya mengubah deskripsi, bukan saldo.
     */
    public function update(UpdateLoyaltyTransactionRequest $request, int $loyalty_transaction): JsonResponse
    {
        $loyaltyTransaction = LoyaltyTransaction::query()->findOrFail($loyalty_transaction);
        $loyaltyTransaction->forceFill([
            'description' => $request->validated()['description'],
        ])->save();

        $loyaltyTransaction->load([
            'customer:id,name,member_code,phone,point_balance,is_active',
            'sale:id,invoice_no,grand_total,status',
            'creator:id,name,email',
        ]);

        return $this->successResponse($loyaltyTransaction, 'Loyalty points transaction note updated successfully');
    }

    /**
     * Hindari penghapusan ledger karena akan memutus audit saldo customer.
     */
    public function destroy(int $loyalty_transaction): JsonResponse
    {
        return $this->errorResponse(
            'Transaksi loyalty tidak dapat dihapus. Gunakan transaksi adjust untuk koreksi saldo.',
            409
        );
    }

    private function normalizePoints(string $type, int $points): int
    {
        return match ($type) {
            'earn' => abs($points),
            'redeem' => -abs($points),
            default => $points,
        };
    }

    private function resolveAmount(string $type, int $points, mixed $amount): float
    {
        if ($amount !== null && $amount !== '') {
            return (float) $amount;
        }

        return match ($type) {
            'earn' => round(abs($points) / 100, 2),
            'redeem' => (float) abs($points),
            default => 0.0,
        };
    }
}
