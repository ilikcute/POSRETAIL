<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|unique:accounts,code|max:50',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'balance' => 'nullable|numeric',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
