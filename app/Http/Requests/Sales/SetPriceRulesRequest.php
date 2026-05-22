<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetPriceRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'min_margin_percentage' => ['required', 'numeric', 'min:0', 'max:99.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk wajib dipilih.',
            'product_id.exists' => 'Produk tidak ditemukan atau sudah dihapus.',
            'selling_price.required' => 'Harga jual wajib diisi.',
            'selling_price.numeric' => 'Harga jual harus berupa angka.',
            'selling_price.min' => 'Harga jual tidak boleh negatif.',
            'min_margin_percentage.required' => 'Batas margin minimum wajib diisi.',
            'min_margin_percentage.numeric' => 'Batas margin minimum harus berupa angka.',
            'min_margin_percentage.max' => 'Batas margin maksimum adalah 99.99%.',
        ];
    }
}
