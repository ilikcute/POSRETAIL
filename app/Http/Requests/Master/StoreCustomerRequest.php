<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:30|unique:customers,phone',
            'address' => 'nullable|string',
            'member_code' => 'nullable|string|max:100|unique:customers,member_code',
            'point_balance' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
