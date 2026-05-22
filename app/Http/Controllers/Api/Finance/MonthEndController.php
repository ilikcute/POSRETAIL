<?php

namespace App\Http\Controllers\Api\Finance;

use App\Exceptions\MonthEndException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\PreviewMonthEndRequest;
use App\Http\Requests\Finance\StoreMonthEndRequest;
use App\Models\Finance\MonthEnd;
use App\Repositories\Contracts\Finance\MonthEndRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MonthEndController extends Controller
{
    use ApiResponseTrait;

    protected MonthEndRepositoryInterface $monthEndRepository;

    public function __construct(MonthEndRepositoryInterface $monthEndRepository)
    {
        $this->monthEndRepository = $monthEndRepository;
    }

    /**
     * Display a listing of month-end closures.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MonthEnd::query()
            ->with(['store:id,name', 'closedBy:id,name,email'])
            ->latest('id');

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                    ->orWhere('month', 'like', "%{$search}%")
                    ->orWhere('year', 'like', "%{$search}%");
            });
        }

        $closes = $query->paginate((int) $request->input('per_page', 15));

        return $this->successResponse($closes, 'Month ends retrieved successfully');
    }

    /**
     * Preview Month End statistics and validation flags.
     */
    public function preview(PreviewMonthEndRequest $request): JsonResponse
    {
        try {
            $summary = $this->monthEndRepository->preview($request->validated());

            return $this->successResponse($summary, 'Month end preview generated successfully');
        } catch (MonthEndException $e) {
            return $this->errorResponse($e->getMessage(), 422, [
                'month_end' => $e->contextData(),
                'month' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            Log::error('MonthEndController::preview failed', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Terjadi kesalahan internal saat memproses preview Month End.', 500);
        }
    }

    /**
     * Perform EOM (End of Month) closing and snapshot.
     */
    public function store(StoreMonthEndRequest $request): JsonResponse
    {
        try {
            $close = $this->monthEndRepository->create($request->validated());
            $close->load(['store:id,name', 'closedBy:id,name,email']);

            return $this->successResponse($close, 'Month end completed successfully (EOM Done)', 201);
        } catch (MonthEndException $e) {
            return $this->errorResponse($e->getMessage(), 422, [
                'month_end' => $e->contextData(),
                'month' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            Log::error('MonthEndController::store failed', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Terjadi kesalahan internal saat memproses Month End.', 500);
        }
    }

    /**
     * Retrieve details for a single month-end.
     */
    public function show($id): JsonResponse
    {
        $close = $this->monthEndRepository->findOrFail((int) $id);
        $close->load(['store:id,name', 'closedBy:id,name,email']);

        return $this->successResponse($close, 'Month end details retrieved successfully');
    }

    /**
     * Delete a month-end record.
     */
    public function destroy($id): JsonResponse
    {
        $this->monthEndRepository->delete((int) $id);

        return $this->successResponse(null, 'Month end report deleted successfully');
    }
}
