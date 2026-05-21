<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SuspendCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'station_id' => 'required|exists:stations,id',
            'customer_id' => 'nullable|exists:customers,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ];
    }
}
