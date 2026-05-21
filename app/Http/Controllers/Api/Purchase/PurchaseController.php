<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use App\Repositories\Contracts\Purchase\PurchaseRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class PurchaseController extends Controller
{
    use ApiResponseTrait;

    protected PurchaseRepositoryInterface $purchaseRepository;

    public function __construct(PurchaseRepositoryInterface $purchaseRepository)
    {
        $this->purchaseRepository = $purchaseRepository;
    }

    public function index(): JsonResponse
    {
        $purchases = $this->purchaseRepository->all();

        return $this->successResponse($purchases, 'Purchases retrieved successfully');
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $purchase = $this->purchaseRepository->create($request->validated());

        return $this->successResponse($purchase, 'Purchase document created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $purchase = $this->purchaseRepository->findOrFail($id);
        $purchase->load(['items.product', 'supplier', 'warehouse', 'creator']);

        return $this->successResponse($purchase, 'Purchase document retrieved successfully');
    }

    // Fungsi update sengaja tidak menyertakan full stock re-calculation untuk menyederhanakan kode dasar.
    // Di aplikasi nyata, update invoice yang sudah "received" membutuhkan revert stock logika.
    public function update(UpdatePurchaseRequest $request, $id): JsonResponse
    {
        $purchase = $this->purchaseRepository->update($id, $request->except('items')); // Menyederhanakan update

        return $this->successResponse($purchase, 'Purchase document updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->purchaseRepository->delete($id);

        return $this->successResponse(null, 'Purchase document deleted successfully');
    }
}
