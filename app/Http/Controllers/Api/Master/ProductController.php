<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreProductRequest;
use App\Http\Requests\Master\UpdateProductRequest;
use App\Repositories\Contracts\Master\ProductRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    use ApiResponseTrait;

    protected ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function index(): JsonResponse
    {
        // Untuk tabel transaksi/master yang punya relasi, disarankan pakai load.
        // Berhubung kita pakai BaseRepository, kita perlu expand base repo untuk with(),
        // Namun untuk sementara all() cukup, atau kita ambil dari model langsung khusus untuk index yang kompleks.
        // Untuk menjaga clean arsitektur, $this->productRepository->all() sudah cukup,
        // tapi kita bisa improve dengan mengambil relasi jika diperlukan.
        $products = $this->productRepository->all();
        // Untuk me-load relasi: $products->load(['category', 'brand', 'unit']);

        return $this->successResponse($products, 'Products retrieved successfully');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productRepository->create($request->validated());

        return $this->successResponse($product, 'Product created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);
        $product->load(['category', 'brand', 'unit']); // Load relasi untuk detail

        return $this->successResponse($product, 'Product retrieved successfully');
    }

    public function update(UpdateProductRequest $request, $id): JsonResponse
    {
        $product = $this->productRepository->update($id, $request->validated());

        return $this->successResponse($product, 'Product updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->productRepository->delete($id);

        return $this->successResponse(null, 'Product deleted successfully');
    }
}
