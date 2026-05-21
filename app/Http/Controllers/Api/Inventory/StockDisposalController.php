<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockDisposalRequest;
use App\Http\Requests\Inventory\UpdateStockDisposalRequest;
use App\Repositories\Contracts\Inventory\StockDisposalRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class StockDisposalController extends Controller
{
    use ApiResponseTrait;

    protected StockDisposalRepositoryInterface $stockDisposalRepository;

    public function __construct(StockDisposalRepositoryInterface $stockDisposalRepository)
    {
        $this->stockDisposalRepository = $stockDisposalRepository;
    }

    public function index(): JsonResponse
    {
        $disposals = $this->stockDisposalRepository->all();
        $disposals->load(['warehouse', 'creator']);

        return $this->successResponse($disposals, 'Stock disposals retrieved successfully');
    }

    public function store(StoreStockDisposalRequest $request): JsonResponse
    {
        $disposal = $this->stockDisposalRepository->create($request->validated());

        return $this->successResponse($disposal, 'Stock disposal draft created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $disposal = $this->stockDisposalRepository->findOrFail($id);
        $disposal->load(['warehouse', 'creator', 'approver', 'items.product']);

        return $this->successResponse($disposal, 'Stock disposal details retrieved successfully');
    }

    public function update(UpdateStockDisposalRequest $request, $id): JsonResponse
    {
        $disposal = $this->stockDisposalRepository->update($id, $request->validated());

        return $this->successResponse($disposal, 'Stock disposal updated/processed successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->stockDisposalRepository->delete($id);

        return $this->successResponse(null, 'Stock disposal draft deleted successfully');
    }
}
