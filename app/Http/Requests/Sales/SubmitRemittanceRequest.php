<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SubmitRemittanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id' => 'required|exists:shifts,id',
            'actual_cash' => 'required|numeric|min:0',
            'actual_qris' => 'required|numeric|min:0',
            'actual_card' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
