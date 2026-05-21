<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;

use App\Http\Requests\Master\StoreProductVariantRequest;
use App\Http\Requests\Master\UpdateProductVariantRequest;
use App\Repositories\Contracts\Master\ProductVariantRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ProductVariantController extends Controller
{
    use ApiResponseTrait;

    protected ProductVariantRepositoryInterface $productVariantRepository;

    public function __construct(ProductVariantRepositoryInterface $productVariantRepository)
    {
        $this->productVariantRepository = $productVariantRepository;
    }

    public function index(): JsonResponse
    {
        $variants = $this->productVariantRepository->all();
        return $this->successResponse($variants, 'Product variants retrieved successfully');
    }

    public function store(StoreProductVariantRequest $request): JsonResponse
    {
        $variant = $this->productVariantRepository->create($request->validated());
        return $this->successResponse($variant, 'Product variant created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $variant = $this->productVariantRepository->findOrFail($id);
        $variant->load('product');
        return $this->successResponse($variant, 'Product variant retrieved successfully');
    }

    public function update(UpdateProductVariantRequest $request, $id): JsonResponse
    {
        $variant = $this->productVariantRepository->update($id, $request->validated());
        return $this->successResponse($variant, 'Product variant updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->productVariantRepository->delete($id);
        return $this->successResponse(null, 'Product variant deleted successfully');
    }
}
