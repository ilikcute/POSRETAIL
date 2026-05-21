<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => 'required|exists:warehouses,id',
            'opname_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.physical_qty' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:255',
        ];
    }
}
