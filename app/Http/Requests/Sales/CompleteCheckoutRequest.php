<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class CompleteCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'queue_code' => 'required|exists:suspended_carts,queue_code',
            'target_station_id' => 'required|exists:stations,id',
            'payment_method' => 'required|in:cash,card,qris',
            'bank_account_code' => 'required|exists:accounts,code',
        ];
    }
}
