<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => 'required|exists:warehouses,id',
            'disposal_date' => 'required|date',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.notes' => 'nullable|string|max:255',
        ];
    }
}
