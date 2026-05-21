<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StorePromotionRequest;
use App\Http\Requests\Sales\UpdatePromotionRequest;
use App\Models\Sales\Promotion;
use App\Repositories\Contracts\Sales\PromotionRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

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

    public function store(StorePromotionRequest $request): JsonResponse|RedirectResponse
    {
        $promotion = $this->promotionRepository->create($request->validated());

        if ($request->wantsJson()) {
            return $this->successResponse($promotion, 'Promotion created successfully', 201);
        }

        return redirect()->back()->with('success', 'Promotion created successfully');
    }

    public function show(Promotion $promotion): JsonResponse|RedirectResponse
    {
        if (request()->wantsJson()) {
            return $this->successResponse($promotion, 'Promotion retrieved successfully');
        }

        return redirect()->back();
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse|RedirectResponse
    {
        $updated = $this->promotionRepository->update($promotion->id, $request->validated());

        if ($request->wantsJson()) {
            return $this->successResponse($updated, 'Promotion updated successfully');
        }

        return redirect()->back()->with('success', 'Promotion updated successfully');
    }

    public function destroy(Promotion $promotion): JsonResponse|RedirectResponse
    {
        $this->promotionRepository->delete($promotion->id);

        if (request()->wantsJson()) {
            return $this->successResponse(null, 'Promotion deleted successfully');
        }

        return redirect()->back()->with('success', 'Promotion deleted successfully');
    }
}
