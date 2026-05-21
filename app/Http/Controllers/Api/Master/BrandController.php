<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreBrandRequest;
use App\Http\Requests\Master\UpdateBrandRequest;
use App\Repositories\Contracts\Master\BrandRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    use ApiResponseTrait;

    protected BrandRepositoryInterface $brandRepository;

    public function __construct(BrandRepositoryInterface $brandRepository)
    {
        $this->brandRepository = $brandRepository;
    }

    public function index(): JsonResponse
    {
        $brands = $this->brandRepository->all();

        return $this->successResponse($brands, 'Brands retrieved successfully');
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = $this->brandRepository->create($request->validated());

        return $this->successResponse($brand, 'Brand created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $brand = $this->brandRepository->findOrFail($id);

        return $this->successResponse($brand, 'Brand retrieved successfully');
    }

    public function update(UpdateBrandRequest $request, $id): JsonResponse
    {
        $brand = $this->brandRepository->update($id, $request->validated());

        return $this->successResponse($brand, 'Brand updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->brandRepository->delete($id);

        return $this->successResponse(null, 'Brand deleted successfully');
    }
}
