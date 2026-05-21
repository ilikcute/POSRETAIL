<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreMonthEndRequest;
use App\Repositories\Contracts\Finance\MonthEndRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class MonthEndController extends Controller
{
    use ApiResponseTrait;

    protected MonthEndRepositoryInterface $monthEndRepository;

    public function __construct(MonthEndRepositoryInterface $monthEndRepository)
    {
        $this->monthEndRepository = $monthEndRepository;
    }

    public function index(): JsonResponse
    {
        $closes = $this->monthEndRepository->all();

        return $this->successResponse($closes, 'Month ends retrieved successfully');
    }

    // Melakukan EOM (End of Month)
    public function store(StoreMonthEndRequest $request): JsonResponse
    {
        $close = $this->monthEndRepository->create($request->validated());

        return $this->successResponse($close, 'Month end completed successfully (EOM Done)', 201);
    }

    public function show($id): JsonResponse
    {
        $close = $this->monthEndRepository->findOrFail($id);
        $close->load(['store', 'closedBy']);

        return $this->successResponse($close, 'Month end details retrieved successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->monthEndRepository->delete($id);

        return $this->successResponse(null, 'Month end report deleted successfully');
    }
}
