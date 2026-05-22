<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Exceptions\InventoryException;
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
        $stocks->load(['product', 'variant', 'warehouse', 'rack']);

        return $this->successResponse($stocks, 'Product stocks retrieved successfully');
    }

    public function store(StoreProductStockRequest $request): JsonResponse
    {
        try {
            $stock = $this->productStockRepository->create($request->validated());

            return $this->successResponse($stock, 'Product stock created successfully', 201);
        } catch (InventoryException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan saat membuat stok produk. ' . $e->getMessage(), 500);
        }
    }

    public function show($id): JsonResponse
    {
        $stock = $this->productStockRepository->findOrFail($id);
        $stock->load(['product', 'variant', 'warehouse', 'rack']);

        return $this->successResponse($stock, 'Product stock retrieved successfully');
    }

    public function update(UpdateProductStockRequest $request, $id): JsonResponse
    {
        try {
            $stock = $this->productStockRepository->update($id, $request->validated());

            return $this->successResponse($stock, 'Product stock updated successfully');
        } catch (InventoryException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan saat memperbarui stok produk. ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $this->productStockRepository->delete($id);

            return $this->successResponse(null, 'Product stock deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan saat menghapus stok produk. ' . $e->getMessage(), 500);
        }
    }
}
