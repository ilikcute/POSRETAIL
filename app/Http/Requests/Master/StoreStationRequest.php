<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'ip_address' => 'nullable|string|ip|max:45',
            'location' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
