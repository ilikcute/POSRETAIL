<?php

namespace App\Http\Controllers\Api\Finance;

use App\Exceptions\CashTransactionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreCashTransactionRequest;
use App\Http\Requests\Finance\UpdateCashTransactionRequest;
use App\Models\Finance\CashTransaction;
use App\Repositories\Contracts\Finance\CashTransactionRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CashTransactionController extends Controller
{
    use ApiResponseTrait;

    protected CashTransactionRepositoryInterface $cashTransactionRepository;

    public function __construct(CashTransactionRepositoryInterface $cashTransactionRepository)
    {
        $this->cashTransactionRepository = $cashTransactionRepository;
    }

    /**
     * Display a listing of cash transactions.
     */
    public function index(): JsonResponse
    {
        try {
            $query = CashTransaction::with(['store', 'shift', 'creator']);

            // Filter by store
            if (request()->has('store_id') && request()->input('store_id') !== '') {
                $query->where('store_id', request()->input('store_id'));
            }

            // Filter by type (in/out)
            if (request()->has('type') && request()->input('type') !== '') {
                $query->where('type', request()->input('type'));
            }

            // Filter by payment method
            if (request()->has('payment_method') && request()->input('payment_method') !== '') {
                $query->where('payment_method', request()->input('payment_method'));
            }

            // Filter by shift
            if (request()->has('shift_id') && request()->input('shift_id') !== '') {
                $query->where('shift_id', request()->input('shift_id'));
            }

            // Search by category or description
            if (request()->has('search') && request()->input('search') !== '') {
                $search = request()->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('category', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $transactions = $query->orderBy('id', 'desc')->paginate(request()->input('per_page', 15));

            return $this->successResponse($transactions, 'Cash transactions retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('CashTransactionController::index failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Gagal mengambil data transaksi kas: '.$e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created cash transaction.
     */
    public function store(StoreCashTransactionRequest $request): JsonResponse
    {
        try {
            $transaction = $this->cashTransactionRepository->create($request->validated());
            $transaction->load(['store', 'shift', 'creator']);

            return $this->successResponse($transaction, 'Cash transaction created successfully', 201);
        } catch (CashTransactionException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Throwable $e) {
            Log::error('CashTransactionController::store failed', [
                'error' => $e->getMessage(),
                'payload' => $request->validated(),
            ]);

            return $this->errorResponse('Gagal membuat transaksi kas: '.$e->getMessage(), 500);
        }
    }

    /**
     * Display the specified cash transaction.
     *
     * @param  int  $id
     */
    public function show($id): JsonResponse
    {
        try {
            $transaction = $this->cashTransactionRepository->findOrFail($id);
            $transaction->load(['store', 'shift', 'creator']);

            return $this->successResponse($transaction, 'Cash transaction retrieved successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Transaksi kas tidak ditemukan.', 404);
        }
    }

    /**
     * Update the specified cash transaction.
     *
     * @param  int  $id
     */
    public function update(UpdateCashTransactionRequest $request, $id): JsonResponse
    {
        try {
            $transaction = $this->cashTransactionRepository->update($id, $request->validated());
            $transaction->load(['store', 'shift', 'creator']);

            return $this->successResponse($transaction, 'Cash transaction updated successfully');
        } catch (CashTransactionException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Throwable $e) {
            Log::error('CashTransactionController::update failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'payload' => $request->validated(),
            ]);

            return $this->errorResponse('Gagal memperbarui transaksi kas: '.$e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified cash transaction from storage.
     *
     * @param  int  $id
     */
    public function destroy($id): JsonResponse
    {
        try {
            $this->cashTransactionRepository->delete($id);

            return $this->successResponse(null, 'Cash transaction deleted successfully');
        } catch (CashTransactionException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Throwable $e) {
            Log::error('CashTransactionController::destroy failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Gagal menghapus transaksi kas: '.$e->getMessage(), 500);
        }
    }
}
