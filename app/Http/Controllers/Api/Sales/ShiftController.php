<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreShiftRequest;
use App\Http\Requests\Sales\UpdateShiftRequest;
use App\Repositories\Contracts\Sales\ShiftRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    use ApiResponseTrait;

    protected ShiftRepositoryInterface $shiftRepository;

    public function __construct(ShiftRepositoryInterface $shiftRepository)
    {
        $this->shiftRepository = $shiftRepository;
    }

    public function index(): JsonResponse
    {
        $shifts = $this->shiftRepository->all();

        return $this->successResponse($shifts, 'Shifts retrieved successfully');
    }

    // Membuka Shift Baru
    public function store(StoreShiftRequest $request): JsonResponse
    {
        $shift = $this->shiftRepository->create($request->validated());

        return $this->successResponse($shift, 'Shift opened successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $shift = $this->shiftRepository->findOrFail($id);
        $shift->load(['user', 'station', 'sales.customer', 'sales.promotion']);

        return $this->successResponse($shift, 'Shift details retrieved successfully');
    }

    // Menutup Shift
    public function update(UpdateShiftRequest $request, $id): JsonResponse
    {
        try {
            $shift = $this->shiftRepository->closeShift($id, $request->validated());

            return $this->successResponse($shift, 'Shift closed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function destroy($id): JsonResponse
    {
        $this->shiftRepository->delete($id);

        return $this->successResponse(null, 'Shift deleted successfully');
    }
}
