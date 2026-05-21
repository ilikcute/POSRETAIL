<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => 'sometimes|required|date',
            'description' => 'nullable|string',
        ];
    }
}
