<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product_variant');

        return [
            'product_id' => 'sometimes|required|exists:products,id',
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:product_variants,sku,' . $id,
            'barcode' => 'nullable|string|max:100|unique:product_variants,barcode,' . $id,
            'cost_price' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}
