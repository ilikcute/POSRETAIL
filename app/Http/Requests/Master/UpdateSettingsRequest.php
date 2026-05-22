<?php

namespace App\Http\Requests\Master;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'settings' => 'required|array',
            'settings.company_name' => 'sometimes|required|string|max:255',
            'settings.company_address' => 'sometimes|nullable|string',
            'settings.company_phone' => 'sometimes|nullable|string|max:50',
            'settings.company_email' => 'sometimes|nullable|email|max:255',
            'settings.company_tax_rate' => 'sometimes|numeric|min:0|max:100',
            'settings.default_currency' => 'sometimes|required|string|max:10',
            'settings.currency_symbol' => 'sometimes|required|string|max:10',
            'settings.thousand_separator' => 'sometimes|required|string|max:5',
            'settings.decimal_separator' => 'sometimes|required|string|max:5',
            'settings.default_language' => 'sometimes|required|string|max:10',
            'settings.timezone' => 'sometimes|required|string|max:100',
            'settings.date_format' => 'sometimes|required|string|max:50',
            'settings.theme_mode' => 'sometimes|required|string|in:light,dark',
            'settings.primary_color' => 'sometimes|required|string|max:7',
            'settings.sidebar_color' => 'sometimes|required|string|max:7',
            'settings.loyalty_spend_per_point' => 'sometimes|numeric|min:0',
            'settings.loyalty_point_value' => 'sometimes|numeric|min:0',
            'settings.drawer_safety_limit' => 'sometimes|numeric|min:0',
            'settings.password_min_length' => 'sometimes|integer|min:4|max:32',
            'settings.receipt_header' => 'sometimes|nullable|string',
            'settings.receipt_footer' => 'sometimes|nullable|string',
        ];
    }
}
