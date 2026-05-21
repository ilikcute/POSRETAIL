<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;

use App\Http\Requests\Sales\StoreLoyaltyTransactionRequest;
use App\Http\Requests\Sales\UpdateLoyaltyTransactionRequest;
use App\Repositories\Contracts\Sales\LoyaltyTransactionRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class LoyaltyTransactionController extends Controller
{
    use ApiResponseTrait;

    protected LoyaltyTransactionRepositoryInterface $loyaltyRepository;

    public function __construct(LoyaltyTransactionRepositoryInterface $loyaltyRepository)
    {
        $this->loyaltyRepository = $loyaltyRepository;
    }

    public function index(): JsonResponse
    {
        $transactions = $this->loyaltyRepository->all();
        return $this->successResponse($transactions, 'Loyalty points transactions retrieved successfully');
    }

    public function store(StoreLoyaltyTransactionRequest $request): JsonResponse
    {
        $transaction = $this->loyaltyRepository->create($request->validated());
        return $this->successResponse($transaction, 'Loyalty points transaction processed successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $transaction = $this->loyaltyRepository->findOrFail($id);
        $transaction->load(['customer', 'sale']);
        return $this->successResponse($transaction, 'Loyalty points transaction details retrieved successfully');
    }

    public function update(UpdateLoyaltyTransactionRequest $request, $id): JsonResponse
    {
        $transaction = $this->loyaltyRepository->update($id, $request->validated());
        return $this->successResponse($transaction, 'Loyalty points transaction updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->loyaltyRepository->delete($id);
        return $this->successResponse(null, 'Loyalty points transaction deleted successfully');
    }
}
