<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreDailyCloseRequest;
use App\Repositories\Contracts\Sales\DailyCloseRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class DailyCloseController extends Controller
{
    use ApiResponseTrait;

    protected DailyCloseRepositoryInterface $dailyCloseRepository;

    public function __construct(DailyCloseRepositoryInterface $dailyCloseRepository)
    {
        $this->dailyCloseRepository = $dailyCloseRepository;
    }

    public function index(): JsonResponse
    {
        $closes = $this->dailyCloseRepository->all();

        return $this->successResponse($closes, 'Daily closes retrieved successfully');
    }

    // Melakukan EOD (End of Day)
    public function store(StoreDailyCloseRequest $request): JsonResponse
    {
        $close = $this->dailyCloseRepository->create($request->validated());

        return $this->successResponse($close, 'Daily close completed successfully (EOD Done)', 201);
    }

    public function show($id): JsonResponse
    {
        $close = $this->dailyCloseRepository->findOrFail($id);
        $close->load(['store', 'closedBy']);

        return $this->successResponse($close, 'Daily close details retrieved successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->dailyCloseRepository->delete($id);

        return $this->successResponse(null, 'Daily close report deleted successfully');
    }
}
