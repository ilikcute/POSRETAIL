<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'ip_address' => 'nullable|string|ip|max:45',
            'location' => 'nullable|string|max:255',
            'drawer_safety_limit' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}
