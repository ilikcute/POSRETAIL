<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;

use App\Http\Requests\Master\StoreStationRequest;
use App\Http\Requests\Master\UpdateStationRequest;
use App\Repositories\Contracts\Master\StationRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class StationController extends Controller
{
    use ApiResponseTrait;

    protected StationRepositoryInterface $stationRepository;

    public function __construct(StationRepositoryInterface $stationRepository)
    {
        $this->stationRepository = $stationRepository;
    }

    public function index(): JsonResponse
    {
        $stations = $this->stationRepository->all();
        return $this->successResponse($stations, 'Stations retrieved successfully');
    }

    public function store(StoreStationRequest $request): JsonResponse
    {
        $station = $this->stationRepository->create($request->validated());
        return $this->successResponse($station, 'Station created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $station = $this->stationRepository->findOrFail($id); // Otomatis 404 JSON jika gagal
        return $this->successResponse($station, 'Station retrieved successfully');
    }

    public function update(UpdateStationRequest $request, $id): JsonResponse
    {
        $station = $this->stationRepository->update($id, $request->validated());
        return $this->successResponse($station, 'Station updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->stationRepository->delete($id);
        return $this->successResponse(null, 'Station deleted successfully');
    }
}
