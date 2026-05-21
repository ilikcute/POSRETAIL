<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => 'sometimes|required|exists:stores,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'type' => 'sometimes|required|in:in,out',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'category' => 'sometimes|required|string|max:100',
            'payment_method' => 'sometimes|required|string|max:50',
            'description' => 'nullable|string',
        ];
    }
}
