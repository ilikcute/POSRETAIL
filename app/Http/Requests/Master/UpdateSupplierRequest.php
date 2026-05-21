<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('supplier');

        return [
            'code' => 'nullable|string|max:50|unique:suppliers,code,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:suppliers,email,' . $id,
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
