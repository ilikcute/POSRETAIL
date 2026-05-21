<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreUnitRequest;
use App\Http\Requests\Master\UpdateUnitRequest;
use App\Repositories\Contracts\Master\UnitRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    use ApiResponseTrait;

    protected UnitRepositoryInterface $unitRepository;

    public function __construct(UnitRepositoryInterface $unitRepository)
    {
        $this->unitRepository = $unitRepository;
    }

    public function index(): JsonResponse
    {
        $units = $this->unitRepository->all();

        return $this->successResponse($units, 'Units retrieved successfully');
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = $this->unitRepository->create($request->validated());

        return $this->successResponse($unit, 'Unit created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $unit = $this->unitRepository->findOrFail($id);

        return $this->successResponse($unit, 'Unit retrieved successfully');
    }

    public function update(UpdateUnitRequest $request, $id): JsonResponse
    {
        $unit = $this->unitRepository->update($id, $request->validated());

        return $this->successResponse($unit, 'Unit updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->unitRepository->delete($id);

        return $this->successResponse(null, 'Unit deleted successfully');
    }
}
