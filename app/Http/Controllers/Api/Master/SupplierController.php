<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSupplierRequest;
use App\Http\Requests\Master\UpdateSupplierRequest;
use App\Repositories\Contracts\Master\SupplierRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    use ApiResponseTrait;

    protected SupplierRepositoryInterface $supplierRepository;

    public function __construct(SupplierRepositoryInterface $supplierRepository)
    {
        $this->supplierRepository = $supplierRepository;
    }

    public function index(): JsonResponse
    {
        $suppliers = $this->supplierRepository->all();

        return $this->successResponse($suppliers, 'Suppliers retrieved successfully');
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $this->supplierRepository->create($request->validated());

        return $this->successResponse($supplier, 'Supplier created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $supplier = $this->supplierRepository->findOrFail($id);

        return $this->successResponse($supplier, 'Supplier retrieved successfully');
    }

    public function update(UpdateSupplierRequest $request, $id): JsonResponse
    {
        $supplier = $this->supplierRepository->update($id, $request->validated());

        return $this->successResponse($supplier, 'Supplier updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->supplierRepository->delete($id);

        return $this->successResponse(null, 'Supplier deleted successfully');
    }
}
