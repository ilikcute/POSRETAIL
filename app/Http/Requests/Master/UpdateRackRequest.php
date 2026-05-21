<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('rack');

        return [
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'code' => 'sometimes|required|string|max:50|unique:racks,code,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ];
    }
}
