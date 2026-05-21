<?php

namespace App\Http\Controllers\Api\Master;

use App\Exports\Master\ProductExport;
use App\Exports\Master\ProductImportTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreProductRequest;
use App\Http\Requests\Master\UpdateProductRequest;
use App\Imports\Master\ProductImport;
use App\Repositories\Contracts\Master\ProductRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    use ApiResponseTrait;

    protected ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function index(): JsonResponse
    {
        $products = $this->productRepository->all();

        return $this->successResponse($products, 'Products retrieved successfully');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productRepository->create($request->validated());

        return $this->successResponse($product, 'Product created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);
        $product->load(['category', 'brand', 'unit']);

        return $this->successResponse($product, 'Product retrieved successfully');
    }

    public function update(UpdateProductRequest $request, $id): JsonResponse
    {
        $product = $this->productRepository->update($id, $request->validated());

        return $this->successResponse($product, 'Product updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->productRepository->delete($id);

        return $this->successResponse(null, 'Product deleted successfully');
    }

    /**
     * Export all products to an Excel file (.xlsx).
     */
    public function export(): BinaryFileResponse
    {
        $filename = 'products_export_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new ProductExport, $filename);
    }

    /**
     * Download a blank import template with example rows.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new ProductImportTemplate, 'products_import_template.xlsx');
    }

    /**
     * Import products from an uploaded Excel / CSV file.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $import = new ProductImport;

        Excel::import($import, $request->file('file'));

        $failures = $import->failureDetails;

        return $this->successResponse(
            [
                'success_count' => $import->successCount,
                'failure_count' => count($failures),
                'failures' => $failures,
            ],
            $import->successCount.' product(s) imported successfully.'
                .(count($failures) > 0 ? ' '.count($failures).' row(s) had errors.' : '')
        );
    }
}
