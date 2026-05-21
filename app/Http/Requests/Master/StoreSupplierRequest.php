<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'nullable|string|max:50|unique:suppliers,code',
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:suppliers,email',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
