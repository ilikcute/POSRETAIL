<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockOpnameRequest;
use App\Http\Requests\Inventory\UpdateStockOpnameRequest;
use App\Repositories\Contracts\Inventory\StockOpnameRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class StockOpnameController extends Controller
{
    use ApiResponseTrait;

    protected StockOpnameRepositoryInterface $stockOpnameRepository;

    public function __construct(StockOpnameRepositoryInterface $stockOpnameRepository)
    {
        $this->stockOpnameRepository = $stockOpnameRepository;
    }

    public function index(): JsonResponse
    {
        $opnames = $this->stockOpnameRepository->all();
        $opnames->load(['warehouse', 'creator']);

        return $this->successResponse($opnames, 'Stock opnames retrieved successfully');
    }

    public function store(StoreStockOpnameRequest $request): JsonResponse
    {
        $opname = $this->stockOpnameRepository->create($request->validated());

        return $this->successResponse($opname, 'Stock opname created as draft successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $opname = $this->stockOpnameRepository->findOrFail($id);
        $opname->load(['warehouse', 'creator', 'approver', 'items.product']);

        return $this->successResponse($opname, 'Stock opname details retrieved successfully');
    }

    public function update(UpdateStockOpnameRequest $request, $id): JsonResponse
    {
        $opname = $this->stockOpnameRepository->update($id, $request->validated());

        return $this->successResponse($opname, 'Stock opname updated/processed successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->stockOpnameRepository->delete($id);

        return $this->successResponse(null, 'Stock opname draft deleted successfully');
    }
}
