<?php

namespace App\Imports\Master;

use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Product;
use App\Models\Master\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class ProductImport implements SkipsEmptyRows, SkipsOnError, SkipsOnFailure, ToCollection, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    use Importable, SkipsErrors, SkipsFailures;

    public int $successCount = 0;

    /** @var array<int, array<string, string>> */
    public array $failureDetails = [];

    public function collection(Collection $rows): void
    {
        // Eager-load lookups to avoid N+1 on each row
        $categories = Category::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id]);
        $brands = Brand::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id]);
        $units = Unit::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id]);

        foreach ($rows as $row) {
            $categoryId = $categories[strtolower(trim($row['category'] ?? ''))] ?? null;
            $brandId = $brands[strtolower(trim($row['brand'] ?? ''))] ?? null;
            $unitId = $units[strtolower(trim($row['unit'] ?? ''))] ?? null;

            if (! $categoryId || ! $unitId) {
                continue; // skip rows that fail relationship lookup (caught by validation)
            }

            // Normalize boolean fields: accept Yes/No/1/0/true/false
            $normalize = fn ($val): bool => in_array(strtolower((string) $val), ['yes', '1', 'true'], true);

            Product::updateOrCreate(
                ['code' => trim($row['code'])],
                [
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'unit_id' => $unitId,
                    'name' => trim($row['name']),
                    'sku' => filled($row['sku'] ?? null) ? trim($row['sku']) : null,
                    'barcode' => filled($row['barcode'] ?? null) ? trim($row['barcode']) : null,
                    'cost_price' => (float) ($row['cost_price'] ?? 0),
                    'price' => (float) ($row['selling_price'] ?? 0),
                    'wholesale_price' => filled($row['wholesale_price'] ?? null) ? (float) $row['wholesale_price'] : null,
                    'purchase_type' => in_array($row['purchase_type'] ?? 'outright', ['outright', 'consignment'])
                        ? $row['purchase_type']
                        : 'outright',
                    'consignment_commission_fee' => (float) ($row['commission_fee'] ?? 0),
                    'min_margin_percentage' => (float) ($row['min_margin'] ?? 10),
                    'safety_stock' => (float) ($row['safety_stock'] ?? 0),
                    'reorder_point' => (float) ($row['reorder_point'] ?? 0),
                    'lead_time' => (int) ($row['lead_time_days'] ?? 0),
                    'is_taxable' => $normalize($row['is_taxable'] ?? 'Yes'),
                    'is_consignment' => $normalize($row['is_consignment'] ?? 'No'),
                    'is_active' => $normalize($row['is_active'] ?? 'Yes'),
                    'description' => filled($row['description'] ?? null) ? trim($row['description']) : null,
                ]
            );

            $this->successCount++;
        }
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'category' => 'required|string',
            'unit' => 'required|string',
            'purchase_type' => 'nullable|in:outright,consignment',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'code.required' => 'Column "Code" is required.',
            'name.required' => 'Column "Name" is required.',
            'cost_price.required' => 'Column "Cost Price" is required.',
            'cost_price.numeric' => 'Column "Cost Price" must be a numeric value.',
            'selling_price.required' => 'Column "Selling Price" is required.',
            'selling_price.numeric' => 'Column "Selling Price" must be a numeric value.',
            'category.required' => 'Column "Category" is required.',
            'unit.required' => 'Column "Unit" is required.',
            'purchase_type.in' => 'Column "Purchase Type" must be either "outright" or "consignment".',
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->failureDetails[] = [
                'row' => $failure->row(),
                'column' => $failure->attribute(),
                'errors' => implode(', ', $failure->errors()),
                'values' => $failure->values(),
            ];
        }
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }
}
