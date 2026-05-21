<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('customer');

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255|unique:customers,email,'.$id,
            'phone' => 'nullable|string|max:30|unique:customers,phone,'.$id,
            'address' => 'nullable|string',
            'member_code' => 'nullable|string|max:100|unique:customers,member_code,'.$id,
            'point_balance' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
