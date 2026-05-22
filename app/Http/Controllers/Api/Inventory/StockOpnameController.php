<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Exceptions\StockOpnameException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockOpnameRequest;
use App\Http\Requests\Inventory\UpdateStockOpnameRequest;
use App\Models\Inventory\StockOpname;
use App\Repositories\Contracts\Inventory\StockOpnameRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StockOpnameController extends Controller
{
    use ApiResponseTrait;

    protected StockOpnameRepositoryInterface $stockOpnameRepository;

    public function __construct(StockOpnameRepositoryInterface $stockOpnameRepository)
    {
        $this->stockOpnameRepository = $stockOpnameRepository;
    }

    public function index(Request $request): JsonResponse
    {
        $query = StockOpname::query()
            ->with(['warehouse', 'creator', 'approver'])
            ->withCount('items')
            ->latest('id');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', (int) $request->input('warehouse_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        $opnames = $query->paginate($perPage);

        return $this->successResponse($opnames, 'Stock opnames retrieved successfully');
    }

    public function store(StoreStockOpnameRequest $request): JsonResponse
    {
        try {
            $opname = $this->stockOpnameRepository->create($request->validated());
            $opname->load(['warehouse', 'creator', 'items.product', 'items.productVariant']);

            return $this->successResponse($opname, 'Stock opname created as draft successfully', 201);
        } catch (StockOpnameException $e) {
            return $this->errorResponse($e->getMessage(), 422, [
                'stock_opname' => $e->contextData(),
                'status' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            Log::error('StockOpnameController::store failed', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Terjadi kesalahan internal saat membuat stock opname.', 500);
        }
    }

    public function show($id): JsonResponse
    {
        $opname = $this->stockOpnameRepository->findOrFail($id);
        $opname->load(['warehouse', 'creator', 'approver', 'items.product', 'items.productVariant']);

        return $this->successResponse($opname, 'Stock opname details retrieved successfully');
    }

    public function update(UpdateStockOpnameRequest $request, $id): JsonResponse
    {
        try {
            $opname = $this->stockOpnameRepository->update($id, $request->validated());
            $opname->load(['warehouse', 'creator', 'approver', 'items.product', 'items.productVariant']);

            return $this->successResponse($opname, 'Stock opname updated/processed successfully');
        } catch (StockOpnameException $e) {
            return $this->errorResponse($e->getMessage(), 422, [
                'stock_opname' => $e->contextData(),
                'status' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            Log::error('StockOpnameController::update failed', [
                'id' => $id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Terjadi kesalahan internal saat memperbarui stock opname.', 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $this->stockOpnameRepository->delete($id);

            return $this->successResponse(null, 'Stock opname draft deleted successfully');
        } catch (StockOpnameException $e) {
            return $this->errorResponse($e->getMessage(), 422, [
                'status' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            Log::error('StockOpnameController::destroy failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Terjadi kesalahan internal saat menghapus stock opname.', 500);
        }
    }
}
