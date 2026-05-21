<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => 'required|exists:stores,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'required|string|max:100',
            'payment_method' => 'sometimes|required|string|max:50',
            'description' => 'nullable|string',
        ];
    }
}
