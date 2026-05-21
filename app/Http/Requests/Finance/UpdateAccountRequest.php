<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('account');

        return [
            'code' => 'sometimes|required|string|max:50|unique:accounts,code,'.$id,
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:asset,liability,equity,revenue,expense',
            'balance' => 'nullable|numeric',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
