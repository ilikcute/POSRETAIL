<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoyaltyTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|in:earn,redeem,adjust',
            'points' => 'required|integer',
            'amount' => 'nullable|numeric|min:0',
            'description' => 'required|string|max:255',
        ];
    }
}
