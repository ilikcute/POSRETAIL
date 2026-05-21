<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductStockRequest;
use App\Http\Requests\Inventory\UpdateProductStockRequest;
use App\Repositories\Contracts\Inventory\ProductStockRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ProductStockController extends Controller
{
    use ApiResponseTrait;

    protected ProductStockRepositoryInterface $productStockRepository;

    public function __construct(ProductStockRepositoryInterface $productStockRepository)
    {
        $this->productStockRepository = $productStockRepository;
    }

    public function index(): JsonResponse
    {
        $stocks = $this->productStockRepository->all();

        // Eager load jika menggunakan query langsung dari model, atau return raw
        return $this->successResponse($stocks, 'Product stocks retrieved successfully');
    }

    public function store(StoreProductStockRequest $request): JsonResponse
    {
        $stock = $this->productStockRepository->create($request->validated());

        return $this->successResponse($stock, 'Product stock created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $stock = $this->productStockRepository->findOrFail($id);
        $stock->load(['product', 'variant', 'warehouse', 'rack']);

        return $this->successResponse($stock, 'Product stock retrieved successfully');
    }

    public function update(UpdateProductStockRequest $request, $id): JsonResponse
    {
        $stock = $this->productStockRepository->update($id, $request->validated());

        return $this->successResponse($stock, 'Product stock updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->productStockRepository->delete($id);

        return $this->successResponse(null, 'Product stock deleted successfully');
    }
}
