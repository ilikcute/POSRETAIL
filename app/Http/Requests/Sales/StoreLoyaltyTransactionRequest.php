<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoyaltyTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->whereNull('deleted_at'),
            ],
            'sale_id' => ['nullable', 'integer', 'exists:sales,id'],
            'type' => ['required', Rule::in(['earn', 'redeem', 'adjust'])],
            'points' => ['required', 'integer', 'not_in:0', 'min:-100000000', 'max:100000000'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'description' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer wajib dipilih.',
            'customer_id.exists' => 'Customer tidak ditemukan atau sudah dihapus.',
            'sale_id.exists' => 'Transaksi penjualan tidak ditemukan.',
            'type.required' => 'Tipe transaksi loyalty wajib dipilih.',
            'type.in' => 'Tipe transaksi loyalty tidak valid.',
            'points.required' => 'Jumlah poin wajib diisi.',
            'points.integer' => 'Jumlah poin harus berupa bilangan bulat.',
            'points.not_in' => 'Jumlah poin tidak boleh 0.',
            'description.required' => 'Deskripsi transaksi wajib diisi.',
            'description.max' => 'Deskripsi tidak boleh lebih dari 255 karakter.',
        ];
    }
}
