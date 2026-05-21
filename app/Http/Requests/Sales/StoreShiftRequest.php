<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'station_id' => 'required|exists:stations,id',
            'starting_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
