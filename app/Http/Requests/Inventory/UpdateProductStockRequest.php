<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'sometimes|required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'rack_id' => 'nullable|exists:racks,id',
            'qty' => 'sometimes|required|numeric',
            'min_qty' => 'nullable|numeric|min:0',
        ];
    }
}
