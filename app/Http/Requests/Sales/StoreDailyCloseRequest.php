<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => 'required|exists:stores,id',
            'close_date' => 'required|date|unique:daily_closes,close_date',
            'notes' => 'nullable|string',
        ];
    }
}
