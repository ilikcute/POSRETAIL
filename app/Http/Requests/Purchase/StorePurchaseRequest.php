<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => 'required|exists:stores,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'type' => 'required|in:order,purchase,return',
            'status' => 'required|in:pending,ordered,received,completed,cancelled',
            'payment_status' => 'required|in:unpaid,partial,paid',
            
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'parent_id' => 'nullable|exists:purchases,id',

            // Validasi Items Array
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ];
    }
}
