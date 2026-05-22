<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockDisposalRequest;
use App\Http\Requests\Inventory\UpdateStockDisposalRequest;
use App\Repositories\Contracts\Inventory\StockDisposalRepositoryInterface;
use App\Exceptions\InventoryException;
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
        try {
            $disposals = $this->stockDisposalRepository->all();
            $disposals->load(['warehouse', 'creator']);

            return $this->successResponse($disposals, 'Stock disposals retrieved successfully');
        } catch (InventoryException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memuat stock disposal.', 500);
        }
    }

    public function store(StoreStockDisposalRequest $request): JsonResponse
    {
        try {
            $disposal = $this->stockDisposalRepository->create($request->validated());

            return $this->successResponse($disposal, 'Stock disposal draft created successfully', 201);
        } catch (InventoryException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal membuat draft stock disposal.', 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $disposal = $this->stockDisposalRepository->findOrFail($id);
            $disposal->load(['warehouse', 'creator', 'approver', 'items.product', 'items.productVariant']);

            return $this->successResponse($disposal, 'Stock disposal details retrieved successfully');
        } catch (InventoryException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memuat detail stock disposal.', 500);
        }
    }

    public function update(UpdateStockDisposalRequest $request, $id): JsonResponse
    {
        try {
            $disposal = $this->stockDisposalRepository->update($id, $request->validated());

            return $this->successResponse($disposal, 'Stock disposal updated/processed successfully');
        } catch (InventoryException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memperbarui stock disposal.', 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $this->stockDisposalRepository->delete($id);

            return $this->successResponse(null, 'Stock disposal draft deleted successfully');
        } catch (InventoryException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus stock disposal.', 500);
        }
    }
}
