<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => 'required|exists:stores,id',
            'station_id' => 'required|exists:stations,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'customer_id' => 'nullable|exists:customers,id',
            'promotion_id' => 'nullable|exists:promotions,id',
            
            'status' => 'required|in:pending,completed,void',
            'payment_method' => 'nullable|string|max:50',
            
            'discount_amount' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',

            // Validasi Items Array
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.cost_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ];
    }
}
