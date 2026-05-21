<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('warehouse'); // asumsikan resource ID route

        return [
            'code' => 'sometimes|required|string|max:50|unique:warehouses,code,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'is_main' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
