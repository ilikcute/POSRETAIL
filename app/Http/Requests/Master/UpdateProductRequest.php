<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product');
        
        return [
            'category_id' => 'sometimes|required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'sometimes|required|exists:units,id',
            'code' => 'sometimes|required|string|max:50|unique:products,code,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $id,
            'barcode' => 'nullable|string|max:100|unique:products,barcode,' . $id,
            'cost_price' => 'sometimes|required|numeric|min:0',
            'price' => 'sometimes|required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'safety_stock' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'lead_time' => 'nullable|integer|min:0',
            'is_taxable' => 'boolean',
            'is_consignment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
