<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;

use App\Http\Requests\Sales\StorePromotionRequest;
use App\Http\Requests\Sales\UpdatePromotionRequest;
use App\Repositories\Contracts\Sales\PromotionRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class PromotionController extends Controller
{
    use ApiResponseTrait;

    protected PromotionRepositoryInterface $promotionRepository;

    public function __construct(PromotionRepositoryInterface $promotionRepository)
    {
        $this->promotionRepository = $promotionRepository;
    }

    public function index(): JsonResponse
    {
        $promotions = $this->promotionRepository->all();
        return $this->successResponse($promotions, 'Promotions retrieved successfully');
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $promotion = $this->promotionRepository->create($request->validated());
        return $this->successResponse($promotion, 'Promotion created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $promotion = $this->promotionRepository->findOrFail($id);
        return $this->successResponse($promotion, 'Promotion retrieved successfully');
    }

    public function update(UpdatePromotionRequest $request, $id): JsonResponse
    {
        $promotion = $this->promotionRepository->update($id, $request->validated());
        return $this->successResponse($promotion, 'Promotion updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->promotionRepository->delete($id);
        return $this->successResponse(null, 'Promotion deleted successfully');
    }
}
