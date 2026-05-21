<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
            
            // Journal items must balance (Debit sum = Credit sum)
            'items' => 'required|array|min:2',
            'items.*.account_id' => 'required|exists:accounts,id',
            'items.*.debit' => 'required|numeric|min:0',
            'items.*.credit' => 'required|numeric|min:0',
        ];
    }
}
