<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('category');

        return [
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
