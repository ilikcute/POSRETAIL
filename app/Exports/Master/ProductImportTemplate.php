<?php

namespace App\Exports\Master;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductImportTemplate implements FromArray, ShouldAutoSize, WithColumnWidths, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            // Row 1: Column headers
            [
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
            ],
            // Row 2: Example data row 1
            [
                'PRD-001',
                'Indomie Goreng Original',
                'SKU-INDOMIE-001',
                '8992388123456',
                'Food & Beverage',
                'Indofood',
                'Pack',
                2500,
                3500,
                3200,
                'outright',
                0,
                10,
                50,
                20,
                3,
                'Yes',
                'No',
                'Yes',
                'Classic fried noodle product',
            ],
            // Row 3: Example data row 2 (consignment type)
            [
                'PRD-002',
                'Susu UHT Full Cream 1L',
                'SKU-SUSU-002',
                '8991234567890',
                'Dairy',
                'Frisian Flag',
                'Liter',
                14000,
                18500,
                17000,
                'consignment',
                20,
                15,
                30,
                10,
                2,
                'Yes',
                'Yes',
                'Yes',
                'Full cream UHT milk 1 liter',
            ],
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
            // Example rows: light violet tint to distinguish from actual data
            2 => [
                'font' => ['italic' => true, 'color' => ['argb' => 'FF6D28D9']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF5F3FF']],
            ],
            3 => [
                'font' => ['italic' => true, 'color' => ['argb' => 'FF6D28D9']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF5F3FF']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 30,
            'C' => 18,
            'D' => 16,
            'E' => 18,
            'F' => 18,
            'G' => 12,
            'H' => 14,
            'I' => 14,
            'J' => 14,
            'K' => 16,
            'L' => 18,
            'M' => 14,
            'N' => 14,
            'O' => 14,
            'P' => 16,
            'Q' => 12,
            'R' => 14,
            'S' => 10,
            'T' => 36,
        ];
    }

    public function title(): string
    {
        return 'Import Template';
    }
}
