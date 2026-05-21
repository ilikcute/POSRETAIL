<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Requests\Sales\UpdateSaleRequest;
use App\Repositories\Contracts\Sales\SaleRepositoryInterface;
use App\Services\ThermalPrintService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    use ApiResponseTrait;

    protected SaleRepositoryInterface $saleRepository;

    public function __construct(SaleRepositoryInterface $saleRepository)
    {
        $this->saleRepository = $saleRepository;
    }

    public function index(): JsonResponse
    {
        $sales = $this->saleRepository->all();

        return $this->successResponse($sales, 'Sales retrieved successfully');
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->saleRepository->create($request->validated());

        return $this->successResponse($sale, 'Sale transaction processed successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $sale = $this->saleRepository->findOrFail($id);
        $sale->load(['items.product', 'customer', 'cashier', 'store', 'station']);

        return $this->successResponse($sale, 'Sale transaction retrieved successfully');
    }

    public function update(UpdateSaleRequest $request, $id): JsonResponse
    {
        $sale = $this->saleRepository->update($id, $request->except('items'));

        return $this->successResponse($sale, 'Sale status updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->saleRepository->delete($id);

        return $this->successResponse(null, 'Sale document deleted successfully');
    }

    /**
     * Cetak Struk Ke Printer Thermal ESC/POS
     */
    public function printReceipt(Request $request, $id): JsonResponse
    {
        $connectorType = $request->input('connector_type', 'network');
        $connectorTarget = $request->input('connector_target', '192.168.1.200');
        $paperWidth = (int) $request->input('paper_width', 32);

        $printService = new ThermalPrintService;
        $result = $printService->printReceipt($id, $connectorType, $connectorTarget, $paperWidth);

        if ($result['success']) {
            return $this->successResponse(null, $result['message']);
        }

        return $this->errorResponse($result['message'], 400);
    }
}
