<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreCashTransactionRequest;
use App\Http\Requests\Finance\UpdateCashTransactionRequest;
use App\Repositories\Contracts\Finance\CashTransactionRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class CashTransactionController extends Controller
{
    use ApiResponseTrait;

    protected CashTransactionRepositoryInterface $cashTransactionRepository;

    public function __construct(CashTransactionRepositoryInterface $cashTransactionRepository)
    {
        $this->cashTransactionRepository = $cashTransactionRepository;
    }

    public function index(): JsonResponse
    {
        $transactions = $this->cashTransactionRepository->all();

        return $this->successResponse($transactions, 'Cash transactions retrieved successfully');
    }

    public function store(StoreCashTransactionRequest $request): JsonResponse
    {
        $transaction = $this->cashTransactionRepository->create($request->validated());

        return $this->successResponse($transaction, 'Cash transaction created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $transaction = $this->cashTransactionRepository->findOrFail($id);
        $transaction->load(['store', 'shift', 'creator']);

        return $this->successResponse($transaction, 'Cash transaction retrieved successfully');
    }

    public function update(UpdateCashTransactionRequest $request, $id): JsonResponse
    {
        $transaction = $this->cashTransactionRepository->update($id, $request->validated());

        return $this->successResponse($transaction, 'Cash transaction updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->cashTransactionRepository->delete($id);

        return $this->successResponse(null, 'Cash transaction deleted successfully');
    }
}
