<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('unit');

        return [
            'name' => 'sometimes|required|string|max:100',
            'short_name' => 'sometimes|required|string|max:20|unique:units,short_name,'.$id,
            'is_active' => 'boolean',
        ];
    }
}
