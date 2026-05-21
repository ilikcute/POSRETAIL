<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Nanti bisa diproteksi dengan Spatie: return $this->user()->can('create store');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'tax_number' => 'nullable|string|max:100',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'header_text' => 'nullable|string|max:255',
            'footer_text' => 'nullable|string|max:255',
            'print_settings' => 'nullable|array',
            'default_printer_id' => 'nullable|integer', // validasi exists:printers,id ditambahkan kalau tabel printers sudah ada
            'default_receipt_template_id' => 'nullable|integer',
            'is_active' => 'boolean',
        ];
    }
}
