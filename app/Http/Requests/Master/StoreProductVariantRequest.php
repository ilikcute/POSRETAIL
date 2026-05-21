<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:product_variants,sku',
            'barcode' => 'nullable|string|max:100|unique:product_variants,barcode',
            'cost_price' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}
