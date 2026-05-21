<?php

namespace App\Exports\Master;

use App\Models\Master\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductExport implements FromCollection, ShouldAutoSize, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function collection()
    {
        return Product::with(['category', 'brand', 'unit'])
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'SKU',
            'Barcode',
            'Category',
            'Brand',
            'Unit',
            'Cost Price',
            'Selling Price',
            'Wholesale Price',
            'Purchase Type',
            'Commission Fee (%)',
            'Min Margin (%)',
            'Safety Stock',
            'Reorder Point',
            'Lead Time (days)',
            'Is Taxable',
            'Is Consignment',
            'Is Active',
            'Description',
        ];
    }

    /**
     * @param  Product  $product
     */
    public function map($product): array
    {
        return [
            $product->code,
            $product->name,
            $product->sku ?? '',
            $product->barcode ?? '',
            $product->category?->name ?? '',
            $product->brand?->name ?? '',
            $product->unit?->name ?? '',
            (float) $product->cost_price,
            (float) $product->price,
            $product->wholesale_price !== null ? (float) $product->wholesale_price : '',
            $product->purchase_type ?? 'outright',
            (float) ($product->consignment_commission_fee ?? 0),
            (float) ($product->min_margin_percentage ?? 10),
            (float) ($product->safety_stock ?? 0),
            (float) ($product->reorder_point ?? 0),
            (int) ($product->lead_time ?? 0),
            $product->is_taxable ? 'Yes' : 'No',
            $product->is_consignment ? 'Yes' : 'No',
            $product->is_active ? 'Yes' : 'No',
            $product->description ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row: violet background, bold white text, centered
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF7C3AED']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,  // Code
            'B' => 30,  // Name
            'C' => 16,  // SKU
            'D' => 16,  // Barcode
            'E' => 18,  // Category
            'F' => 18,  // Brand
            'G' => 12,  // Unit
            'H' => 14,  // Cost Price
            'I' => 14,  // Selling Price
            'J' => 14,  // Wholesale Price
            'K' => 16,  // Purchase Type
            'L' => 18,  // Commission Fee
            'M' => 14,  // Min Margin
            'N' => 14,  // Safety Stock
            'O' => 14,  // Reorder Point
            'P' => 16,  // Lead Time
            'Q' => 12,  // Is Taxable
            'R' => 14,  // Is Consignment
            'S' => 10,  // Is Active
            'T' => 36,  // Description
        ];
    }

    public function title(): string
    {
        return 'Products';
    }
}
