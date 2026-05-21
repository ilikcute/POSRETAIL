<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreWarehouseRequest;
use App\Http\Requests\Master\UpdateWarehouseRequest;
use App\Repositories\Contracts\Master\WarehouseRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    use ApiResponseTrait;

    protected WarehouseRepositoryInterface $warehouseRepository;

    public function __construct(WarehouseRepositoryInterface $warehouseRepository)
    {
        $this->warehouseRepository = $warehouseRepository;
    }

    public function index(): JsonResponse
    {
        $warehouses = $this->warehouseRepository->all();

        return $this->successResponse($warehouses, 'Warehouses retrieved successfully');
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = $this->warehouseRepository->create($request->validated());

        return $this->successResponse($warehouse, 'Warehouse created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $warehouse = $this->warehouseRepository->findOrFail($id);

        return $this->successResponse($warehouse, 'Warehouse retrieved successfully');
    }

    public function update(UpdateWarehouseRequest $request, $id): JsonResponse
    {
        $warehouse = $this->warehouseRepository->update($id, $request->validated());

        return $this->successResponse($warehouse, 'Warehouse updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->warehouseRepository->delete($id);

        return $this->successResponse(null, 'Warehouse deleted successfully');
    }
}
