<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'required|exists:units,id',
            'code' => 'required|string|max:50|unique:products,code',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'cost_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'safety_stock' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'lead_time' => 'nullable|integer|min:0',
            'is_taxable' => 'boolean',
            'is_consignment' => 'boolean',
            'is_active' => 'boolean',
            'purchase_type' => 'nullable|in:outright,consignment',
            'consignment_commission_fee' => 'nullable|numeric|min:0|max:100',
            'min_margin_percentage' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
