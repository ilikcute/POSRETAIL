<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreStoreRequest;
use App\Http\Requests\Master\UpdateStoreRequest;
use App\Repositories\Contracts\Master\StoreRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    use ApiResponseTrait;

    protected StoreRepositoryInterface $storeRepository;

    public function __construct(StoreRepositoryInterface $storeRepository)
    {
        $this->storeRepository = $storeRepository;
    }

    public function index(): JsonResponse
    {
        $stores = $this->storeRepository->all();

        return $this->successResponse($stores, 'Stores retrieved successfully');
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        $store = $this->storeRepository->create($request->validated());

        return $this->successResponse($store, 'Store created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $store = $this->storeRepository->findOrFail($id); // Langsung throw 404 jika tidak ketemu

        return $this->successResponse($store, 'Store retrieved successfully');
    }

    public function update(UpdateStoreRequest $request, $id): JsonResponse
    {
        // Repository akan throw 404 otomatis jika ID tidak ketemu
        $store = $this->storeRepository->update($id, $request->validated());

        return $this->successResponse($store, 'Store updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        // Repository akan throw 404 otomatis jika ID tidak ketemu
        $this->storeRepository->delete($id);

        return $this->successResponse(null, 'Store deleted successfully');
    }
}
