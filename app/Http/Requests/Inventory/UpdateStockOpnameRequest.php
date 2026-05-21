<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opname_date' => 'sometimes|required|date',
            'notes' => 'nullable|string|max:1000',
            'status' => 'sometimes|required|in:draft,approved,cancelled',
            'items' => 'sometimes|required|array|min:1',
            'items.*.id' => 'sometimes|required|exists:stock_opname_items,id',
            'items.*.product_id' => 'required_without:items.*.id|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.physical_qty' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:255',
        ];
    }
}
