<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => [
                'required',
                'integer',
                Rule::exists('stores', 'id')->whereNull('deleted_at'),
            ],
            'close_date' => ['required', 'date_format:Y-m-d', 'unique:daily_closes,close_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'Store wajib dipilih.',
            'store_id.exists' => 'Store tidak ditemukan atau sudah dihapus.',
            'close_date.required' => 'Tanggal EOD wajib diisi.',
            'close_date.date_format' => 'Tanggal EOD harus berformat YYYY-MM-DD.',
            'close_date.unique' => 'Tanggal EOD ini sudah pernah ditutup.',
            'notes.max' => 'Catatan tidak boleh lebih dari 1000 karakter.',
        ];
    }
}
